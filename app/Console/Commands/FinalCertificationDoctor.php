<?php

namespace App\Console\Commands;

use App\Support\FinalCertificationCatalog;
use App\Support\ProductionCertificationCatalog;
use Illuminate\Console\Command;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Throwable;

/** Reports final M12 runtime/build readiness without mutating application data. */
class FinalCertificationDoctor extends Command
{
    protected $signature='workintel:final-certification {--json} {--require-build}';
    protected $description='Validate final WorkIntel route, scheduler, schema, runtime and production-build certification budgets.';

    /** Execute final certification checks and return a failing code when one mandatory release budget is violated. */
    public function handle(): int
    {
        $checks=[];$failed=false;
        $routeCount=count(Route::getRoutes());
        $routeOk=$routeCount>=FinalCertificationCatalog::ROUTES_MIN&&$routeCount<=FinalCertificationCatalog::ROUTES_MAX;
        $checks['route_budget']=['ok'=>$routeOk,'detail'=>"Registered routes: {$routeCount}; allowed ".FinalCertificationCatalog::ROUTES_MIN.'–'.FinalCertificationCatalog::ROUTES_MAX.'.'];if(!$routeOk)$failed=true;

        try{$events=app(Schedule::class)->events();$workIntel=count(array_filter($events,fn($event)=>str_contains((string)($event->command??''),'workintel:')));$scheduleOk=$workIntel>=FinalCertificationCatalog::SCHEDULED_WORKINTEL_MIN&&$workIntel<=FinalCertificationCatalog::SCHEDULED_WORKINTEL_MAX;$checks['scheduler_budget']=['ok'=>$scheduleOk,'detail'=>"Scheduled WorkIntel jobs: {$workIntel}; allowed ".FinalCertificationCatalog::SCHEDULED_WORKINTEL_MIN.'–'.FinalCertificationCatalog::SCHEDULED_WORKINTEL_MAX.'.'];if(!$scheduleOk)$failed=true;}catch(Throwable $e){$checks['scheduler_budget']=['ok'=>false,'detail'=>$e->getMessage()];$failed=true;}

        $missingFiles=array_values(array_filter(FinalCertificationCatalog::REQUIRED_RELEASE_FILES,fn($file)=>!is_file(base_path($file))));
        $checks['release_files']=['ok'=>$missingFiles===[],'detail'=>$missingFiles===[]?'All M12 certification landmarks are present.':'Missing: '.implode(', ',$missingFiles)];if($missingFiles)$failed=true;

        foreach(ProductionCertificationCatalog::runtimeExtensions() as $extension){$ok=extension_loaded($extension);$checks['php_ext_'.$extension]=['ok'=>$ok,'detail'=>$ok?"ext-{$extension} loaded.":"Missing ext-{$extension}."];if(!$ok)$failed=true;}
        $drivers=class_exists(\PDO::class)?\PDO::getAvailableDrivers():[];$pdoOk=count($drivers)>0;$checks['pdo_driver']=['ok'=>$pdoOk,'detail'=>$pdoOk?'PDO drivers: '.implode(', ',$drivers):'PDO has no database driver.'];if(!$pdoOk)$failed=true;

        try{$missingTables=array_values(array_filter(ProductionCertificationCatalog::REQUIRED_TABLES,fn($table)=>!Schema::hasTable($table)));$checks['schema']=['ok'=>$missingTables===[],'detail'=>$missingTables===[]?'Production certification schema landmarks exist.':'Missing tables: '.implode(', ',array_slice($missingTables,0,12))];if($missingTables)$failed=true;}catch(Throwable $e){$checks['schema']=['ok'=>false,'detail'=>$e->getMessage()];$failed=true;}

        $manifest=public_path('build/manifest.json');$build=is_file($manifest);$checks['frontend_build']=['ok'=>$build||!$this->option('require-build'),'detail'=>$build?'Vite production manifest present.':($this->option('require-build')?'Vite production manifest is required.':'Vite build not required for this doctor invocation.')];if($this->option('require-build')&&!$build)$failed=true;
        $hot=!is_file(public_path('hot'));$checks['no_vite_hot']=['ok'=>$hot,'detail'=>$hot?'public/hot absent.':'public/hot must not ship in production.'];if(!$hot)$failed=true;
        $debugSafe=!app()->environment('production')||!config('app.debug');$checks['production_debug']=['ok'=>$debugSafe,'detail'=>$debugSafe?'APP_DEBUG policy is safe.':'APP_DEBUG must be false in production.'];if(!$debugSafe)$failed=true;

        $result=['ok'=>!$failed,'release'=>ProductionCertificationCatalog::RELEASE,'m12'=>['route_count'=>$routeCount,'require_build'=>(bool)$this->option('require-build')],'checks'=>$checks];
        if($this->option('json'))$this->line((string)json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES));else foreach($checks as $name=>$check)$this->line(sprintf('[%s] %s — %s',$check['ok']?'PASS':'FAIL',$name,$check['detail']));
        return $failed?self::FAILURE:self::SUCCESS;
    }
}
