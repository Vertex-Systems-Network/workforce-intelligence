<?php
namespace App\Console\Commands;
use App\Support\InstallationGuideCatalog;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Schema;
/** Provides p9 installation doctor behavior within the WorkIntel application. */ class InstallationCenterDoctor extends Command
{
    protected $signature='workintel:p9-doctor {--json}';
    protected $description='Validate P9 Downloads & Installation Center contracts.';
    /** Executes the command, job, or request handler. */ public function handle():int
    {
        $manifest=storage_path('app/releases/manifest.json');$decoded=is_file($manifest)?json_decode((string)file_get_contents($manifest),true):[];$releases=$decoded['releases']??[];$checks=[
            ['name'=>'installation progress table','ok'=>Schema::hasTable('installation_guide_progress')],
            ['name'=>'seven installation guides','ok'=>count(InstallationGuideCatalog::keys())===7],
            ['name'=>'release manifest v2','ok'=>(int)($decoded['version']??0)>=2],
            ['name'=>'five release packages','ok'=>count($releases)===5],
            ['name'=>'Windows guide','ok'=>(bool)InstallationGuideCatalog::get('windows-agent')],
            ['name'=>'macOS guide','ok'=>(bool)InstallationGuideCatalog::get('macos-agent')],
            ['name'=>'Linux guide','ok'=>(bool)InstallationGuideCatalog::get('linux-agent')],
            ['name'=>'repair guide','ok'=>(bool)InstallationGuideCatalog::get('repair-uninstall')],
        ];
        foreach($releases as $release){$file=storage_path('app/releases/'.($release['file']??''));$checks[]=['name'=>'release '.$release['slug'],'ok'=>is_file($file)&&hash_file('sha256',$file)===($release['sha256']??null)];}
        $ok=collect($checks)->every('ok');if($this->option('json'))$this->line(json_encode(['ok'=>$ok,'checks'=>$checks],JSON_PRETTY_PRINT));else foreach($checks as $c)$this->line(($c['ok']?'<info>OK</info>':'<error>FAIL</error>').' '.$c['name']);return $ok?self::SUCCESS:self::FAILURE;
    }
}
