<?php

namespace App\Services\Security;

use Illuminate\Validation\ValidationException;

/** Validates and pins user-configurable outbound HTTP destinations to public network addresses. */
class OutboundUrlGuard
{
    /**
     * Reject malformed, local, private, reserved, unresolved, or ambiguous outbound destinations.
     *
     * @return array{scheme:string,host:string,port:int,ips:array<int,string>,allow_private:bool}
     */
    public function assertSafe(string $url): array
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            $this->reject('Use a valid HTTP or HTTPS URL.');
        }

        $scheme = strtolower((string) ($parts['scheme'] ?? ''));
        $host = strtolower(rtrim((string) ($parts['host'] ?? ''), '.'));
        $port = (int) ($parts['port'] ?? ($scheme === 'https' ? 443 : 80));

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || $port < 1 || $port > 65535) {
            $this->reject('Use a valid HTTP or HTTPS URL.');
        }

        if (isset($parts['user']) || isset($parts['pass'])) {
            $this->reject('Outbound URLs must not contain embedded credentials.');
        }

        $allowPrivate = (bool) config('workintel.outbound.allow_private', false);
        if ($allowPrivate) {
            return ['scheme' => $scheme, 'host' => $host, 'port' => $port, 'ips' => [], 'allow_private' => true];
        }

        if (
            in_array($host, ['localhost', 'localhost.localdomain', '0.0.0.0', '127.0.0.1', '::1'], true)
            || str_ends_with($host, '.localhost')
            || str_ends_with($host, '.local')
            || str_ends_with($host, '.internal')
            || str_ends_with($host, '.lan')
        ) {
            $this->reject('Private or local outbound destinations are disabled.');
        }

        if (! filter_var($host, FILTER_VALIDATE_IP) && ! str_contains($host, '.')) {
            $this->reject('Single-label network hosts are not allowed for outbound requests.');
        }

        $ips = $this->resolveIps($host);
        if ($ips === []) {
            $this->reject('Outbound destination could not be resolved.');
        }

        foreach ($ips as $ip) {
            if (! $this->isPublicIp($ip)) {
                $this->reject('Private, loopback, link-local, or reserved outbound destinations are disabled.');
            }
        }

        sort($ips, SORT_STRING);

        return ['scheme' => $scheme, 'host' => $host, 'port' => $port, 'ips' => $ips, 'allow_private' => false];
    }

    /**
     * Return fail-closed HTTP client options for a validated user-configurable destination.
     *
     * Redirects are disabled so every new destination must be validated explicitly. For hostnames,
     * cURL DNS pinning binds the actual connection to the address that passed the safety check,
     * closing the validation/request DNS-rebinding window.
     *
     * @return array<string,mixed>
     */
    public function httpOptions(string $url): array
    {
        $destination = $this->assertSafe($url);
        $options = ['allow_redirects' => false];

        if ($destination['allow_private'] || filter_var($destination['host'], FILTER_VALIDATE_IP)) {
            return $options;
        }

        if (! defined('CURLOPT_RESOLVE')) {
            $this->reject('Secure outbound hostname pinning requires the cURL PHP extension.');
        }

        $ip = $destination['ips'][0] ?? null;
        if (! $ip) {
            $this->reject('Outbound destination could not be pinned safely.');
        }

        $address = str_contains($ip, ':') ? '['.$ip.']' : $ip;
        $options['curl'] = [
            CURLOPT_RESOLVE => [
                $destination['host'].':'.$destination['port'].':'.$address,
            ],
        ];

        return $options;
    }

    /** @return array<int,string> */
    private function resolveIps(string $host): array
    {
        if (filter_var($host, FILTER_VALIDATE_IP)) {
            return [$host];
        }

        $records = @dns_get_record($host, DNS_A | DNS_AAAA);
        if (! is_array($records)) {
            return [];
        }

        $ips = [];
        foreach ($records as $record) {
            if (! empty($record['ip']) && filter_var($record['ip'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
                $ips[] = $record['ip'];
            }
            if (! empty($record['ipv6']) && filter_var($record['ipv6'], FILTER_VALIDATE_IP, FILTER_FLAG_IPV6)) {
                $ips[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($ips));
    }

    /** Return whether the address is globally routable rather than private or reserved. */
    private function isPublicIp(string $ip): bool
    {
        return filter_var(
            $ip,
            FILTER_VALIDATE_IP,
            FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE
        ) !== false;
    }

    /** Reject an unsafe outbound destination using the validation error contract. */
    private function reject(string $message): never
    {
        throw ValidationException::withMessages(['url' => [$message]]);
    }
}
