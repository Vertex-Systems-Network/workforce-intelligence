<?php

namespace App\Console\Commands;

use App\Models\User;
use App\Support\LocaleCatalog;
use Illuminate\Console\Command;
use Illuminate\Contracts\Translation\HasLocalePreference;
use Illuminate\Support\Facades\Schema;

/** Provides p7 localization doctor behavior within the WorkIntel application. */ class LocalizationDoctor extends Command
{
    protected $signature = 'workintel:p7-doctor {--json}';
    protected $description = 'Validate the Phase 7 localization, language preference and RTL contracts.';

    /** Executes the command, job, or request handler. */ public function handle(): int
    {
        $checks = [
            ['name'=>'users.use_workspace_locale column','ok'=>Schema::hasTable('users') && Schema::hasColumn('users','use_workspace_locale')],
            ['name'=>'workspace default language','ok'=>Schema::hasTable('workspace_preferences') && Schema::hasColumn('workspace_preferences','default_language')],
            ['name'=>'five core locales','ok'=>LocaleCatalog::CORE === ['en','tr','ru','ur','ar']],
            ['name'=>'Arabic RTL','ok'=>LocaleCatalog::direction('ar') === 'rtl'],
            ['name'=>'Urdu RTL','ok'=>LocaleCatalog::direction('ur-PK') === 'rtl'],
            ['name'=>'user locale preference contract','ok'=>is_subclass_of(User::class, HasLocalePreference::class)],
        ];
        $englishMail=is_file(lang_path('en/messages.php')) ? array_keys(require lang_path('en/messages.php')) : [];
        foreach (LocaleCatalog::CORE as $locale) {
            $file=lang_path($locale.'/messages.php');$messages=is_file($file)?require $file:[];
            $checks[]=['name'=>"{$locale} mail translation pack",'ok'=>is_file($file) && array_diff($englishMail,array_keys($messages))===[]];
        }
        $checks[]=['name'=>'frontend locale catalog','ok'=>is_file(resource_path('js/i18n/catalog.ts'))];
        $checks[]=['name'=>'frontend localization provider','ok'=>is_file(resource_path('js/i18n/LocalizationContext.tsx'))];
        $checks[]=['name'=>'language switcher','ok'=>is_file(resource_path('js/i18n/LanguageSwitcher.tsx'))];
        $checks[]=['name'=>'document language variants','ok'=>method_exists(\App\Services\Documents\DocumentTemplateService::class,'cloneLanguageVariant')];

        $ok=collect($checks)->every('ok');
        if($this->option('json')) $this->line(json_encode(['ok'=>$ok,'checks'=>$checks],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE));
        else foreach($checks as $check) $this->line(($check['ok']?'<info>OK</info>':'<error>MISSING</error>').' '.$check['name']);
        $ok?$this->info('P7 Localization & Multi-language doctor passed.'):$this->error('P7 doctor found blocking issues.');
        return $ok?self::SUCCESS:self::FAILURE;
    }
}
