<?php

namespace Noo\CraftSitemap;

class RobotsTxt
{
    /**
     * @param  string  $rules  The configured robots.txt rules
     * @param  string|null  $sitemapUrl  Absolute sitemap URL to advertise, if any
     * @param  bool  $disallowAll  Block all crawlers (Craft's `disallowRobots`), ignoring the rules
     */
    public static function render(string $rules, ?string $sitemapUrl, bool $disallowAll): string
    {
        if ($disallowAll) {
            return "User-agent: *\nDisallow: /\n";
        }

        $content = rtrim($rules)."\n";

        if ($sitemapUrl !== null) {
            $content .= "\nSitemap: {$sitemapUrl}\n";
        }

        return $content;
    }

    /**
     * The root sitemap URL for the requested host: the host of the site base
     * URL served on it, or the current site's when no site lives on that host.
     * Craft falls back to the primary site for a host whose sites all sit
     * under a path prefix, which may live on another domain.
     *
     * @param  list<string>  $siteBaseUrls
     */
    public static function sitemapUrlForHost(array $siteBaseUrls, string $requestHost, ?string $currentSiteBaseUrl): ?string
    {
        foreach ($siteBaseUrls as $baseUrl) {
            if (strcasecmp((string) parse_url($baseUrl, PHP_URL_HOST), $requestHost) === 0) {
                return self::sitemapUrlFor($baseUrl);
            }
        }

        return self::sitemapUrlFor($currentSiteBaseUrl);
    }

    /**
     * The root sitemap URL on the host of a site base URL.
     */
    public static function sitemapUrlFor(?string $baseUrl): ?string
    {
        $parts = $baseUrl !== null ? parse_url($baseUrl) : false;

        if (! is_array($parts) || ! isset($parts['scheme'], $parts['host'])) {
            return null;
        }

        $port = isset($parts['port']) ? ':'.$parts['port'] : '';

        return "{$parts['scheme']}://{$parts['host']}{$port}/sitemap.xml";
    }
}
