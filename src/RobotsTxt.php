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

    public static function sitemapUrl(array $siteBaseUrls, string $requestOrigin, ?string $currentBaseUrl, string $mount): ?string
    {
        $origin = self::origin($requestOrigin);
        $origins = array_map(self::origin(...), $siteBaseUrls);
        if ($origin === null || ! in_array($origin, $origins, true)) {
            $origin = self::origin($currentBaseUrl);
        }

        return $origin === null ? null : $origin.rtrim($mount, '/').'/sitemap.xml';
    }

    public static function origin(?string $url): ?string
    {
        $parts = $url === null ? false : parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        if (! in_array($scheme, ['http', 'https'], true) || empty($parts['host']) || isset($parts['user']) || isset($parts['pass'])) {
            return null;
        }
        $port = $parts['port'] ?? null;
        $suffix = $port === null || $port === ($scheme === 'https' ? 443 : 80) ? '' : ':'.$port;

        return $scheme.'://'.strtolower($parts['host']).$suffix;
    }
}
