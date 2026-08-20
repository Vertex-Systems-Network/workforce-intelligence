<?php
namespace App\Services\Automation;

use App\Services\Automation\Contracts\ConnectorDriver;
use App\Services\Automation\Connectors\BuiltInConnectorDriver;
use App\Services\Security\OutboundUrlGuard;
use Illuminate\Validation\ValidationException;

/** Provides connector registry behavior within the WorkIntel application. */ class ConnectorRegistry
{
    /** @var array<string,ConnectorDriver> */ private array $drivers=[];
    /** Initializes the class with its required dependencies and state. */ public function __construct(OutboundUrlGuard $guard)
    {
        foreach(array_keys(BuiltInConnectorDriver::definitions()) as $provider) $this->register(new BuiltInConnectorDriver($provider,$guard));
    }
    /** Handles the register operation for the current WorkIntel workflow. */ public function register(ConnectorDriver $driver): void { $this->drivers[$driver->id()]=$driver; }
    /** Handles the driver operation for the current WorkIntel workflow. */ public function driver(string $provider): ConnectorDriver
    {
        return $this->drivers[$provider]??throw ValidationException::withMessages(['provider'=>["Unsupported connector provider: {$provider}"]]);
    }
    /** Handles the provider ids operation for the current WorkIntel workflow. */ public function providerIds(): array { return array_keys($this->drivers); }
    /** Handles the catalog operation for the current WorkIntel workflow. */ public function catalog(): array { return array_values(array_map(fn(ConnectorDriver $d)=>$d->catalog(),$this->drivers)); }
    /** Validates validate config input before it is processed. */ public function validateConfig(string $provider,array $config): array { return $this->driver($provider)->validateConfig($config); }
    /** Handles the test operation for the current WorkIntel workflow. */ public function test(string $provider,array $config,int $timeout=8): array { return $this->driver($provider)->test($config,$timeout); }
    /** Handles the execute operation for the current WorkIntel workflow. */ public function execute(string $provider,string $action,array $config,array $input,int $timeout=12): array { return $this->driver($provider)->execute($action,$config,$input,$timeout); }
}
