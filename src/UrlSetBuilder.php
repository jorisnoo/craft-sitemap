<?php

namespace Noo\CraftSitemap;

/**
 * Turns element rows grouped by canonical ID into sitemap `<url>` entries and
 * renders them. Free of Craft so the selection rules can be tested directly.
 *
 * @phpstan-type SitemapRow array{siteId: int, siteHandle: string, language: string, host: string|null, url: string|null, uri: string|null, lastmod: string}
 * @phpstan-type UrlEntry array{loc: string, lastmod: string, alternates: list<array{hreflang: string, href: string}>}
 */
class UrlSetBuilder
{
    /** @var array<string, true> */
    private array $emittedUrls = [];

    public function __construct(
        private readonly Config $config,
    ) {}

    /**
     * Build the URL entries for every group, keeping only rows that belong to
     * `$forSiteId` when given. URLs already emitted by an earlier group are
     * skipped, so one builder must be used per rendered sitemap.
     *
     * @param  array<string, list<SitemapRow>>  $groups
     * @return list<UrlEntry>
     */
    public function build(array $groups, bool $isMultiSite, ?int $forSiteId = null, ?string $forSiteHost = null): array
    {
        $urls = [];

        foreach ($groups as $alternates) {
            array_push($urls, ...$this->buildGroup($alternates, $isMultiSite, $forSiteId, $forSiteHost));
        }

        return $urls;
    }

    /**
     * @param  list<UrlEntry>  $urls
     */
    public static function render(array $urls, bool $isMultiSite): string
    {
        $lines = ['<?xml version="1.0" encoding="UTF-8"?>'];

        if ($isMultiSite) {
            $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';
        } else {
            $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        }

        foreach ($urls as $url) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>'.self::escape($url['loc']).'</loc>';
            $lines[] = '    <lastmod>'.$url['lastmod'].'</lastmod>';

            foreach ($url['alternates'] as $alt) {
                $lines[] = '    <xhtml:link rel="alternate" hreflang="'.self::escape($alt['hreflang']).'" href="'.self::escape($alt['href']).'" />';
            }

            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines)."\n";
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }

    /**
     * @param  list<SitemapRow>  $alternates
     * @return list<UrlEntry>
     */
    private function buildGroup(array $alternates, bool $isMultiSite, ?int $forSiteId, ?string $forSiteHost): array
    {
        $valid = [];
        $groupUrls = [];

        foreach ($this->deduplicateBySite($alternates) as $row) {
            // Translations can share one URL; list that page once.
            if ($this->isListable($row) && ! isset($groupUrls[$row['url']])) {
                $groupUrls[$row['url']] = true;
                $valid[] = $row;
            }
        }

        if ($valid === []) {
            return [];
        }

        $hreflangLinks = [];
        if ($isMultiSite) {
            foreach ($valid as $alt) {
                if ($forSiteHost !== null && $alt['host'] !== $forSiteHost) {
                    continue;
                }

                $hreflangLinks[] = [
                    'hreflang' => $alt['language'],
                    'href' => $alt['url'],
                ];
            }
        }

        // A page that only links to itself has no alternates worth declaring.
        if (count($hreflangLinks) < 2) {
            $hreflangLinks = [];
        }

        $entries = [];

        foreach ($valid as $row) {
            $this->emittedUrls[$row['url']] = true;

            if ($forSiteId !== null && $row['siteId'] !== $forSiteId) {
                continue;
            }

            $entries[] = [
                'loc' => $row['url'],
                'lastmod' => $row['lastmod'],
                'alternates' => $hreflangLinks,
            ];
        }

        return $entries;
    }

    /**
     * Whether a row points at a distinct, crawlable page: it needs a URL, must
     * not be a fragment of another page, and must not be excluded by config.
     *
     * @param  SitemapRow  $row
     */
    private function isListable(array $row): bool
    {
        $url = $row['url'];

        if ($url === null || $url === '' || str_contains($url, '#')) {
            return false;
        }

        if (isset($this->emittedUrls[$url])) {
            return false;
        }

        return $row['uri'] === null || ! $this->config->isUriExcluded($row['uri']);
    }

    /**
     * Keep only the first row per site handle.
     *
     * @param  list<SitemapRow>  $rows
     * @return list<SitemapRow>
     */
    private function deduplicateBySite(array $rows): array
    {
        $seen = [];
        $result = [];

        foreach ($rows as $row) {
            $handle = $row['siteHandle'];

            if (! isset($seen[$handle])) {
                $seen[$handle] = true;
                $result[] = $row;
            }
        }

        return $result;
    }
}
