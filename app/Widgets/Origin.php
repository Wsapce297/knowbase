<?php

namespace App\Widgets;

/**
 * Turns what a team types ("HTTPS://Acme.com/") into a clean origin ("https://acme.com"),
 * or null when it isn't one we can safely use.
 *
 * An origin is scheme + host + port: exactly what browsers compare when deciding
 * whether a page may embed the widget. Being strict matters, because these values
 * are written into a security header (Content-Security-Policy) in part 3.
 */
final class Origin
{
    private const DEFAULT_PORTS = ['http' => 80, 'https' => 443];

    /** Letters, digits and hyphens, separated by dots (no wildcards, spaces or symbols). */
    private const HOSTNAME_PATTERN = '/^(?=.{1,253}$)[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?(?:\.[a-z0-9](?:[a-z0-9-]{0,61}[a-z0-9])?)*$/';

    public static function normalize(string $value): ?string
    {
        $value = trim($value);

        // Whitespace inside an address is never valid, and blocks attempts like "https://a.com; script-src *".
        if ($value === '' || preg_match('/\s/', $value) === 1) {
            return null;
        }

        $parts = parse_url($value);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        // An origin has no login, page path, query string or #fragment.
        if (isset($parts['user']) || isset($parts['pass']) || isset($parts['query']) || isset($parts['fragment'])) {
            return null;
        }

        if (! in_array($parts['path'] ?? '', ['', '/'], true)) {
            return null;
        }

        $scheme = strtolower($parts['scheme']);
        $host = strtolower($parts['host']);

        if (! isset(self::DEFAULT_PORTS[$scheme]) || ! self::isValidHost($host)) {
            return null;
        }

        // Real websites must use https; plain http is only for local test sites.
        if ($scheme === 'http' && ! self::isLocalHost($host)) {
            return null;
        }

        $origin = "{$scheme}://{$host}";
        $port = $parts['port'] ?? null;

        if ($port !== null && $port !== self::DEFAULT_PORTS[$scheme]) {
            $origin .= ":{$port}";
        }

        return $origin;
    }

    /**
     * Hosts that only exist on a developer's own machine (Laragon uses *.test).
     */
    public static function isLocalHost(string $host): bool
    {
        return in_array($host, ['localhost', '127.0.0.1'], true)
            || str_ends_with($host, '.test')
            || str_ends_with($host, '.localhost');
    }

    private static function isValidHost(string $host): bool
    {
        if (filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4) !== false) {
            return true;
        }

        return preg_match(self::HOSTNAME_PATTERN, $host) === 1
            && ($host === 'localhost' || str_contains($host, '.'));
    }
}
