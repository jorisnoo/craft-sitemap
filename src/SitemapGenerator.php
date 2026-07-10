<?php

namespace Noo\CraftSitemap;

use Craft;
use craft\elements\Category;
use craft\elements\db\ElementQuery;
use craft\elements\Entry;
use craft\models\Site;

/**
 * @phpstan-type SitemapRow array{siteId: int, siteHandle: string, language: string, host: string|null, url: string|null, uri: string|null, lastmod: string}
 */
class SitemapGenerator
{
    /** @var array<string, true> */
    private array $emittedUrls = [];

    private Config $config;

    public function __construct(?Config $config = null)
    {
        $this->config = $config ?? Config::load();
    }

    public function generate(?string $host = null): string
    {
        $sites = Craft::$app->getSites()->getAllSites();

        if (count($sites) <= 1) {
            return $this->generateSitemap($sites);
        }

        return $this->generateIndex($sites, $host);
    }

    public function generateForSite(Site $site): string
    {
        return $this->generateSitemap(Craft::$app->getSites()->getAllSites(), $site);
    }

    /**
     * @param  array<Site>  $allSites
     */
    private function generateIndex(array $allSites, ?string $host = null): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($allSites as $site) {
            $baseUrl = $site->getBaseUrl();

            if ($host !== null && parse_url($baseUrl, PHP_URL_HOST) !== $host) {
                continue;
            }

            $url = $baseUrl . 'sitemap-' . $site->handle . '.xml';
            $lines[] = '  <sitemap>';
            $lines[] = '    <loc>' . $this->escape($url) . '</loc>';
            $lines[] = '  </sitemap>';
        }

        $lines[] = '</sitemapindex>';

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param  array<Site>  $allSites
     */
    private function generateSitemap(array $allSites, ?Site $forSite = null): string
    {
        $isMultiSite = count($allSites) > 1;

        $groups = $this->getElementGroups();

        $urls = [];

        foreach ($groups as $alternates) {
            array_push($urls, ...$this->buildUrlEntries($alternates, $isMultiSite, $forSite));
        }

        return $this->renderXml($urls, $isMultiSite);
    }

    /**
     * @return array<string, list<SitemapRow>>
     */
    private function getElementGroups(): array
    {
        $entryQuery = Entry::find()
            ->uri(':notempty:')
            ->slug('not *__temp_*')
            ->site('*')
            ->orderBy('uri');

        if ($this->config->excludeSections !== []) {
            $entryQuery->section(array_merge(['not'], $this->config->excludeSections));
        }

        $entries = $this->collectRowsByCanonicalId($entryQuery);

        $categoryQuery = Category::find()
            ->uri(':notempty:')
            ->site('*');

        if ($this->config->excludeCategoryGroups !== []) {
            $categoryQuery->group(array_merge(['not'], $this->config->excludeCategoryGroups));
        }

        $categories = $this->collectRowsByCanonicalId($categoryQuery);

        return array_merge($entries, $categories);
    }

    /**
     * Iterates the query in batches so only one batch of hydrated elements is
     * in memory at a time, keeping just the scalar fields the sitemap needs.
     *
     * @return array<string, list<SitemapRow>>
     */
    private function collectRowsByCanonicalId(ElementQuery $query): array
    {
        $groups = [];

        foreach ($query->each() as $element) {
            $site = $element->getSite();

            $groups[(string) $element->canonicalId][] = [
                'siteId' => $element->siteId,
                'siteHandle' => $site->handle,
                'language' => $site->language,
                'host' => parse_url($site->getBaseUrl(), PHP_URL_HOST),
                'url' => $element->getUrl(),
                'uri' => $element->uri,
                'lastmod' => $element->dateUpdated->format('c'),
            ];
        }

        return $groups;
    }

    /**
     * @param  list<SitemapRow>  $alternates
     * @return list<array{loc: string, lastmod: string, alternates: list<array{hreflang: string, href: string}>}>
     */
    private function buildUrlEntries(array $alternates, bool $isMultiSite, ?Site $forSite = null): array
    {
        $unique = $this->deduplicateBySite($alternates);

        $valid = array_filter(
            $unique,
            fn ($row) => !isset($this->emittedUrls[$row['url']]) && !$this->config->isUriExcluded($row['uri']),
        );

        if ($valid === []) {
            return [];
        }

        $forSiteHost = $forSite !== null
            ? parse_url($forSite->getBaseUrl(), PHP_URL_HOST)
            : null;

        $hreflangLinks = [];
        if ($isMultiSite && count($valid) > 1) {
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

        $entries = [];

        foreach ($valid as $row) {
            $this->emittedUrls[$row['url']] = true;

            if ($forSite !== null && $row['siteId'] !== $forSite->id) {
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

            if (!isset($seen[$handle])) {
                $seen[$handle] = true;
                $result[] = $row;
            }
        }

        return $result;
    }

    /**
     * @param  list<array{loc: string, lastmod: string, alternates: list<array{hreflang: string, href: string}>}>  $urls
     */
    private function renderXml(array $urls, bool $isMultiSite): string
    {
        $lines = ['<?xml version="1.0" encoding="UTF-8"?>'];

        if ($isMultiSite) {
            $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9" xmlns:xhtml="http://www.w3.org/1999/xhtml">';
        } else {
            $lines[] = '<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">';
        }

        foreach ($urls as $url) {
            $lines[] = '  <url>';
            $lines[] = '    <loc>' . $this->escape($url['loc']) . '</loc>';
            $lines[] = '    <lastmod>' . $url['lastmod'] . '</lastmod>';

            foreach ($url['alternates'] as $alt) {
                $lines[] = '    <xhtml:link rel="alternate" hreflang="' . $this->escape($alt['hreflang']) . '" href="' . $this->escape($alt['href']) . '" />';
            }

            $lines[] = '  </url>';
        }

        $lines[] = '</urlset>';

        return implode("\n", $lines) . "\n";
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
