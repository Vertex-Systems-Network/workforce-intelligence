<?php
namespace App\Services\Automation\Contracts;
/** Defines the connector driver contract used by WorkIntel. */ interface ConnectorDriver
{
    /** Handles the id operation for the current WorkIntel workflow. */ public function id(): string;
    /** Handles the catalog operation for the current WorkIntel workflow. */ public function catalog(): array;
    /** Validates validate config input before it is processed. */ public function validateConfig(array $config): array;
    /** Handles the test operation for the current WorkIntel workflow. */ public function test(array $config, int $timeoutSeconds = 8): array;
    /** Handles the execute operation for the current WorkIntel workflow. */ public function execute(string $actionKey, array $config, array $input, int $timeoutSeconds = 12): array;
}
