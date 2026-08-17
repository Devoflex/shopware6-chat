<?php declare(strict_types=1);

namespace Chatixy\Chat\Service;

/**
 * Pure, Shopware-independent helpers (key sanitisation + URL building) so they
 * can be unit-tested with plain PHPUnit (see integrations/shopware/tests/)
 * without a Shopware install. All Shopware-aware logic lives in the Twig
 * extension + templates.
 *
 * Mirrors the WordPress/PrestaShop/Magento/OpenCart ChatixyKey so behaviour is
 * identical across integrations.
 */
class ChatixyKey
{
    /** Chatixy's default public origin, and the value every rejected host becomes. */
    public const DEFAULT_HOST = 'https://chatixy.com';

    /**
     * The only registrable domain this plugin will ever point a storefront at.
     * Both sinks that consume a host - the loader <script src> printed into every
     * storefront page and the server-side verify call - must resolve to this
     * domain or a subdomain of it, over https. See {@see isAllowedHost()}.
     */
    public const CANONICAL_DOMAIN = 'chatixy.com';

    /**
     * Environment variable that lifts the pin, for local development against a
     * dev Chatixy instance. It is a process/filesystem-level switch (Shopware's
     * .env, docker env, the shell that starts php-fpm) - nothing an HTTP request
     * can reach, so an attacker who can only POST the plugin config form cannot
     * turn it on. Never set it on a production shop.
     */
    private const INSECURE_HOST_ENV = 'CHATIXY_ALLOW_INSECURE_HOST';

    /**
     * Normalise whatever the merchant pasted into a clean 64-char widget key.
     * Accepts the bare key, "<key>.js", or the whole embed <script> tag - we
     * extract the first 64-hex run. Returns '' when none is present.
     */
    public static function sanitizeWidgetKey(mixed $raw): string
    {
        $value = strtolower(trim((string) $raw));
        if ($value === '') {
            return '';
        }
        if (preg_match('/[a-f0-9]{64}/', $value, $matches)) {
            return $matches[0];
        }
        return '';
    }

    /** True when $key is exactly a 64-char lowercase hex string. */
    public static function isValidWidgetKey(mixed $key): bool
    {
        return (bool) preg_match('/^[a-f0-9]{64}$/D', (string) $key);
    }

    /**
     * True when $origin is an origin this plugin is allowed to point at.
     *
     * Two conditions, both required: the scheme is https, and the host is either
     * CANONICAL_DOMAIN itself or a subdomain of it. The pattern is anchored at
     * both ends and only ever grows the host to the LEFT of a literal dot, which
     * is what makes the two classic near-misses fail - "evilchatixy.com" has no
     * dot before the domain, and "chatixy.com.evil.example" has trailing labels
     * past the anchor. Equivalent to the server's origin policy in
     * api/config/cable_origin_policy.rb.
     *
     * @param mixed $origin A normalised scheme://host[:port] origin.
     */
    public static function isAllowedHost(mixed $origin): bool
    {
        if (self::insecureHostsAllowed()) {
            return true;
        }
        $parts = parse_url((string) $origin);
        if (!is_array($parts) || empty($parts['host'])) {
            return false;
        }
        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : '';
        if ($scheme !== 'https') {
            return false;
        }
        $host = strtolower($parts['host']);
        return (bool) preg_match(
            '/^([a-z0-9-]+\.)*' . preg_quote(self::CANONICAL_DOMAIN, '/') . '$/',
            $host
        );
    }

    /** Whether the local-development escape hatch is switched on. */
    private static function insecureHostsAllowed(): bool
    {
        $flag = getenv(self::INSECURE_HOST_ENV);
        if ($flag === false) {
            return false;
        }
        return in_array(strtolower(trim((string) $flag)), ['1', 'true', 'yes', 'on'], true);
    }

    /**
     * Normalise the API host to a bare scheme://host[:port] origin, then pin it
     * to Chatixy's own domain over https.
     *
     * SECURITY: the returned host builds the loader <script src> that is printed
     * into every page of the storefront, plus the server-side verify call. A host
     * an attacker chose is therefore arbitrary first-party JavaScript across the
     * whole shop (stored XSS) and a redirect of the key handshake to a server
     * they control. So a host that is not an https Chatixy origin is DISCARDED
     * and replaced by {@see DEFAULT_HOST} - this method never returns an unvetted
     * host, no matter what sits in Shopware's system_config.
     *
     * Empty/malformed input falls back to DEFAULT_HOST; a scheme-less host is
     * assumed https; any path/query/fragment is dropped.
     */
    public static function sanitizeHost(mixed $raw): string
    {
        $value = trim((string) $raw);
        if ($value === '') {
            return self::DEFAULT_HOST;
        }
        if (!preg_match('#^https?://#i', $value)) {
            $value = 'https://' . $value;
        }
        $parts = parse_url($value);
        if (!is_array($parts) || empty($parts['host'])) {
            return self::DEFAULT_HOST;
        }
        $scheme = isset($parts['scheme']) ? strtolower($parts['scheme']) : 'https';
        $origin = $scheme . '://' . strtolower($parts['host']);
        if (!empty($parts['port'])) {
            $origin .= ':' . (int) $parts['port'];
        }
        return self::isAllowedHost($origin) ? $origin : self::DEFAULT_HOST;
    }

    /**
     * The public loader URL: <host>/source/<key>.js
     *
     * When $platform is non-empty it is appended as a ?platform=<id> query so
     * Chatixy's backend can detect which host system embeds the widget. A
     * 2-arg call returns the bare URL unchanged.
     */
    public static function embedSrc(string $host, string $key, string $platform = ''): string
    {
        $src = rtrim($host, '/') . '/source/' . $key . '.js';
        if ($platform !== '') {
            $src .= '?platform=' . rawurlencode($platform);
        }
        return $src;
    }

    /** The handshake URL: <host>/api/v1/widget/verify/<key> */
    public static function verifyUrl(string $host, string $key): string
    {
        return rtrim($host, '/') . '/api/v1/widget/verify/' . $key;
    }

    /**
     * The full async loader <script> tag, with the URL HTML-escaped. Returns ''
     * when the key is invalid (so callers never inject a broken tag). When
     * $platform is set it is forwarded to embedSrc() as a ?platform=<id> query.
     */
    public static function scriptTag(string $host, string $key, string $platform = ''): string
    {
        if (!self::isValidWidgetKey($key)) {
            return '';
        }
        $src = htmlspecialchars(self::embedSrc($host, $key, $platform), ENT_QUOTES, 'UTF-8');
        return '<script src="' . $src . '" async></script>';
    }
}
