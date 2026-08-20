<?php
/** Runs fast pure-PHP unit assertions that do not require a database connection. */
require dirname(__DIR__).'/vendor/autoload.php';

use App\Services\Commerce\CommerceProviderRegistry;
use App\Support\InstallationGuideCatalog;
use App\Support\LocaleCatalog;
use App\Support\ModuleCatalog;

$assertions = 0;
/** Records one unit assertion and aborts immediately when the expected condition is false. */
function unitAssert(bool $condition, string $message): void
{
    global $assertions;
    $assertions++;
    if (! $condition) throw new RuntimeException($message);
}

unitAssert(LocaleCatalog::normalize('ur-PK') === 'ur', 'Regional Urdu locale must normalize to ur.');
unitAssert(LocaleCatalog::direction('ar') === 'rtl', 'Arabic must use RTL layout direction.');
unitAssert(LocaleCatalog::direction('en') === 'ltr', 'English must use LTR layout direction.');
unitAssert(in_array('projects', ModuleCatalog::dependencies('tasks'), true), 'Tasks must depend on Projects.');
unitAssert(count(ModuleCatalog::keys()) >= 26, 'The switchable module catalog must contain the complete current module set.');
unitAssert(in_array('website', ModuleCatalog::keys(), true), 'Website Studio must be registered in the switchable module catalog.');
unitAssert(count(InstallationGuideCatalog::keys()) === 7, 'Installation Center must expose seven guides.');
$providers = (new CommerceProviderRegistry)->catalog();
unitAssert(count($providers) === 6, 'Commerce provider registry must expose six provider types.');
unitAssert((new CommerceProviderRegistry)->get('manual')->key() === 'manual', 'Manual commerce provider must resolve correctly.');

echo "Unit smoke assertions: {$assertions}\n";
echo "Unit smoke failures: 0\n";
