# Phase 24 Custom Connector SDK

Custom connectors implement `App\Services\Automation\Contracts\ConnectorDriver`.

```php
<?php

namespace App\Integrations;

use App\Services\Automation\Contracts\ConnectorDriver;

final class AcmeConnector implements ConnectorDriver
{
    public function id(): string { return 'acme'; }

    public function catalog(): array
    {
        return [
            'id' => 'acme',
            'name' => 'Acme',
            'category' => 'custom',
            'description' => 'Acme organization connector.',
            'auth' => 'api_token',
            'config_fields' => [
                ['key'=>'token','label'=>'API Token','type'=>'secret','required'=>true],
            ],
            'actions' => [
                ['key'=>'record.create','name'=>'Create record','fields'=>['title','body']],
            ],
        ];
    }

    public function validateConfig(array $config): array { /* validate + normalize */ }
    public function test(array $config, int $timeoutSeconds = 8): array { /* connectivity */ }
    public function execute(string $actionKey, array $config, array $input, int $timeoutSeconds = 12): array { /* action */ }
}
```

`ConnectorRegistry` is an application singleton. Register the driver from a service provider `boot()` method:

```php
use App\Integrations\AcmeConnector;
use App\Services\Automation\ConnectorRegistry;

public function boot(ConnectorRegistry $connectors): void
{
    $connectors->register(app(AcmeConnector::class));
}
```

The same driver then powers:

1. connector catalog UI,
2. connector config validation,
3. connection tests,
4. Automation Studio action discovery,
5. runtime action execution.

## SDK rules

- never return raw secrets from `catalog()` or execution output
- validate configurable outbound URLs against `OutboundUrlGuard`
- set finite HTTP timeouts
- throw an exception on non-success responses so automation retry/dead-letter logic can work
- make remote write calls idempotent when the target API supports an idempotency key
- keep connector-specific credentials inside `IntegrationConnection.config_encrypted`
- avoid making a core WorkIntel HTTP request wait for a third-party API; connector actions are automation-run work
