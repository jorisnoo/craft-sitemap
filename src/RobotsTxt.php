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
