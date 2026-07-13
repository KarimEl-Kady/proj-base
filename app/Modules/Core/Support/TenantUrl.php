<?php

namespace App\Modules\Core\Support;

use DateTimeInterface;
use Illuminate\Support\Facades\URL;

class TenantUrl
{
    /**
     * Build a relative-signed route and attach it to the correct tenant URL.
     * Relative signatures remain valid behind proxies and tenant subdomains.
     *
     * @param  array<string, mixed>  $parameters
     */
    public static function temporarySignedRoute(
        string $name,
        DateTimeInterface $expiration,
        array $parameters = [],
    ): string {
        $identifier = static::identifier();

        if (is_multi_tenant() && $identifier !== null) {
            $parameters['tenant'] = $identifier;
        }

        $relative = URL::temporarySignedRoute(
            $name,
            $expiration,
            $parameters,
            absolute: false,
        );

        return static::baseUrl($identifier).$relative;
    }

    /**
     * Build a tenant-aware frontend URL.
     *
     * @param  array<string, scalar|null>  $query
     */
    public static function frontend(string $path, array $query = []): string
    {
        $identifier = static::identifier();

        if (is_multi_tenant() && $identifier !== null) {
            if (config('project.tenancy.tenant_identification') === 'path') {
                $path = '/'.$identifier.'/'.ltrim($path, '/');
            } else {
                $query['tenant'] = $identifier;
            }
        }

        $url = static::baseUrl($identifier).'/'.ltrim($path, '/');

        return $query === [] ? $url : $url.'?'.http_build_query($query);
    }

    protected static function identifier(): ?string
    {
        $tenantId = tenant_id();

        return $tenantId === null ? null : Tenancy::identifierForId($tenantId);
    }

    protected static function baseUrl(?string $identifier): string
    {
        $base = rtrim((string) config('app.url'), '/');

        if (! is_multi_tenant()
            || $identifier === null
            || config('project.tenancy.tenant_identification') !== 'subdomain') {
            return $base;
        }

        $parts = parse_url($base);
        $scheme = $parts['scheme'] ?? 'https';
        $host = $parts['host'] ?? 'localhost';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return "{$scheme}://{$identifier}.{$host}{$port}";
    }
}
