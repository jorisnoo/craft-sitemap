<?php

namespace Noo\CraftSitemap;

use Craft;
use craft\base\ElementInterface;
use craft\elements\Category;
use craft\elements\db\ElementQuery;
use craft\elements\Entry;
use craft\models\Site;

/**
 * @phpstan-import-type SitemapRow from UrlSetBuilder
 */
class SitemapGenerator
{
    private Config $config;

    public function __construct(?Config $config = null)
    {
        $this->config = $config ?? Config::load();
    }

    /**
     * Sites that serve pages. Sites without a base URL (or with URLs turned
     * off) never appear in a sitemap, so they don't make an install multi-site.
     *
     * @return list<Site>
     */
    public static function sitesWithUrls(): array
    {
        return array_values(array_filter(
            Craft::$app->getSites()->getAllSites(),
            static fn (Site $site): bool => $site->hasUrls && $site->getBaseUrl() !== null,
        ));
    }

    public function generate(?string $host = null): string
    {
        $sites = self::sitesWithUrls();

        if (count($sites) <= 1) {
            return $this->generateSitemap($sites);
        }

        return $this->generateIndex($sites, $host);
    }

    public function generateForSite(Site $site): string
    {
        return $this->generateSitemap(self::sitesWithUrls(), $site);
    }

    /**
     * @param  list<Site>  $sites
     */
    private function generateIndex(array $sites, ?string $host = null): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($sites as $site) {
            $baseUrl = $site->getBaseUrl();

            if ($host !== null && parse_url($baseUrl, PHP_URL_HOST) !== $host) {
                continue;
            }

            $url = $baseUrl.'sitemap-'.$site->handle.'.xml';
            $lines[] = '  <sitemap>';
            $lines[] = '    <loc>'.UrlSetBuilder::escape($url).'</loc>';
            $lines[] = '  </sitemap>';
        }

        $lines[] = '</sitemapindex>';

        return implode("\n", $lines)."\n";
    }

    /**
     * @param  list<Site>  $sites
     */
    private function generateSitemap(array $sites, ?Site $forSite = null): string
    {
        $isMultiSite = count($sites) > 1;

        $forSiteHost = $forSite !== null
            ? parse_url($forSite->getBaseUrl(), PHP_URL_HOST)
            : null;

        $urls = (new UrlSetBuilder($this->config))->build(
            $this->getElementGroups(array_map(static fn (Site $site): int => $site->id, $sites)),
            $isMultiSite,
            $forSite?->id,
            $forSiteHost,
        );

        return UrlSetBuilder::render($urls, $isMultiSite);
    }

    /**
     * @param  list<int>  $siteIds  Only sites that serve pages; elsewhere an element's URL falls back to another site's
     * @return array<string, list<SitemapRow>>
     */
    private function getElementGroups(array $siteIds): array
    {
        if ($siteIds === []) {
            return [];
        }

        $entryQuery = Entry::find()
            ->uri(':notempty:')
            ->slug('not *__temp_*')
            ->siteId($siteIds)
            ->orderBy('uri');

        if ($this->config->excludeSections !== []) {
            $entryQuery->section(array_merge(['not'], $this->config->excludeSections));
        }

        if ($this->config->excludeEntryTypes !== []) {
            $entryQuery->type(array_merge(['not'], $this->config->excludeEntryTypes));
        }

        $entries = $this->collectRowsByCanonicalId($entryQuery);

        $categoryQuery = Category::find()
            ->uri(':notempty:')
            ->siteId($siteIds);

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
            if (! self::isRoutable($element)) {
                continue;
            }

            $site = $element->getSite();

            $groups[(string) $element->canonicalId][] = [
                'siteId' => $element->siteId,
                'siteHandle' => $site->handle,
                'language' => $site->language,
                'host' => parse_url((string) $site->getBaseUrl(), PHP_URL_HOST) ?: null,
                'url' => $element->getUrl(),
                'uri' => $element->uri,
                'lastmod' => $element->dateUpdated->format('c'),
            ];
        }

        return $groups;
    }

    /**
     * Whether Craft would render a page for the element. A URI alone is not
     * enough: a section with URLs but no template still answers with a 404.
     */
    private static function isRoutable(ElementInterface $element): bool
    {
        $route = $element->getRoute();

        if ($route === null) {
            return false;
        }

        if (is_array($route) && ($route[0] ?? null) === 'templates/render') {
            return ($route[1]['template'] ?? '') !== '';
        }

        return true;
    }
}
