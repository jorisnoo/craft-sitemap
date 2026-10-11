<?php

namespace Noo\CraftSitemap;

use Craft;
use craft\elements\Category;
use craft\elements\db\ElementQuery;
use craft\elements\Entry;
use craft\models\Site;
use DateTime;
use DateTimeZone;

/**
 * @phpstan-type SitemapRow array{siteId: int, language: string, url: string|null, uri: string|null, lastmod: string}
 * @phpstan-type SitemapUrl array{loc: string, lastmod: string, alternates: list<array{hreflang: string, href: string}>}
 */
class SitemapGenerator
{
    /**
     * Maximum number of URLs per sitemap file, as defined by the sitemap protocol.
     */
    public const MAX_URLS_PER_SITEMAP = 50000;

    /** @var array<string, true> */
    private array $emittedUrls = [];

    private Config $config;

    public function __construct(?Config $config = null)
    {
        $this->config = $config ?? Config::load();
    }

    public static function isMultiSite(): bool
    {
        return count(Craft::$app->getSites()->getAllSites()) > 1;
    }

    /**
     * Sites whose base URL has the given host.
     *
     * @return list<Site>
     */
    public static function sitesForHost(?string $host): array
    {
        if ($host === null) {
            return [];
        }

        return array_values(array_filter(
            Craft::$app->getSites()->getAllSites(),
            static fn (Site $site) => self::hostOf($site) === $host,
        ));
    }

    /**
     * The given site plus every other site served from the same host.
     *
     * @return list<Site>
     */
    public static function sitesSharingHost(Site $site): array
    {
        $host = self::hostOf($site);

        return $host === null ? [$site] : self::sitesForHost($host);
    }

    public static function hostOf(Site $site): ?string
    {
        $baseUrl = $site->getBaseUrl();

        return $baseUrl !== null ? parse_url($baseUrl, PHP_URL_HOST) : null;
    }

    public static function pageCount(int $urlCount): int
    {
        return max(1, (int) ceil($urlCount / self::MAX_URLS_PER_SITEMAP));
    }

    /**
     * Builds the sitemap URL entries for every site sharing the given site's
     * host, keyed by site ID. Only those sites are queried, since hreflang
     * alternates are limited to the same host.
     *
     * @return array<int, list<SitemapUrl>>
     */
    public function getUrlsForHostOf(Site $site): array
    {
        $this->emittedUrls = [];
        $isMultiSite = self::isMultiSite();
        $siteIds = array_map(static fn (Site $s) => $s->id, self::sitesSharingHost($site));

        $urls = array_fill_keys($siteIds, []);

        foreach ($this->getElementGroups($siteIds) as $alternates) {
            foreach ($this->buildUrlEntries($alternates, $isMultiSite) as $siteId => $entry) {
                $urls[$siteId][] = $entry;
            }
        }

        return $urls;
    }

    /**
     * Seconds until the next entry within the given sites goes live or expires,
     * or null if nothing is scheduled.
     *
     * @param  list<int>  $siteIds
     */
    public function secondsUntilNextScheduledChange(array $siteIds): ?int
    {
        $base = fn () => Entry::find()->siteId($siteIds)->uri(':notempty:');

        $candidates = array_filter([
            $base()->status(Entry::STATUS_PENDING)->min('entries.postDate'),
            $base()->status(Entry::STATUS_LIVE)->expiryDate('> now')->min('entries.expiryDate'),
        ]);

        if ($candidates === []) {
            return null;
        }

        $now = time();
        $seconds = array_map(
            static fn (string $date) => (new DateTime($date, new DateTimeZone('UTC')))->getTimestamp() - $now,
            $candidates,
        );

        return max(1, min($seconds));
    }

    /**
     * @param  list<string>  $locs
     */
    public function renderIndex(array $locs): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($locs as $loc) {
            $lines[] = '  <sitemap>';
            $lines[] = '    <loc>' . $this->escape($loc) . '</loc>';
            $lines[] = '  </sitemap>';
        }

        $lines[] = '</sitemapindex>';

        return implode("\n", $lines) . "\n";
    }

    /**
     * @param  list<SitemapUrl>  $urls
     */
    public function renderUrlset(array $urls): string
    {
        $lines = ['<?xml version="1.0" encoding="UTF-8"?>'];

        if (self::isMultiSite()) {
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

    /**
     * @param  list<int>  $siteIds
     * @return array<int|string, list<SitemapRow>>
     */
    private function getElementGroups(array $siteIds): array
    {
        $entryQuery = Entry::find()
            ->uri(':notempty:')
            ->slug('not *__temp_*')
            ->siteId($siteIds)
            ->orderBy('uri');

        if ($this->config->excludeSections !== []) {
            $entryQuery->section(array_merge(['not'], $this->config->excludeSections));
        }

        $entries = $this->collectRowsByCanonicalId($entryQuery);

        $categoryQuery = Category::find()
            ->uri(':notempty:')
            ->siteId($siteIds)
            ->orderBy('uri');

        if ($this->config->excludeCategoryGroups !== []) {
            $categoryQuery->group(array_merge(['not'], $this->config->excludeCategoryGroups));
        }

        $categories = $this->collectRowsByCanonicalId($categoryQuery);

        return array_merge($entries, $categories);
    }

    /**
     * Iterates the query in batches so only one batch of hydrated elements is
     * in memory at a time, keeping just the scalar fields the sitemap needs.
     * Elements are still hydrated so getUrl() honours URL events from other
     * plugins/modules.
     *
     * @return array<int|string, list<SitemapRow>>
     */
    private function collectRowsByCanonicalId(ElementQuery $query): array
    {
        $groups = [];

        foreach ($query->each() as $element) {
            $groups[$element->canonicalId][] = [
                'siteId' => $element->siteId,
                'language' => $element->getSite()->language,
                'url' => $element->getUrl(),
                'uri' => $element->uri,
                'lastmod' => $element->dateUpdated->format('c'),
            ];
        }

        return $groups;
    }

    /**
     * Element queries return each element at most once per site, so every
     * group already holds at most one row per site.
     *
     * @param  list<SitemapRow>  $alternates
     * @return array<int, SitemapUrl> keyed by site ID
     */
    private function buildUrlEntries(array $alternates, bool $isMultiSite): array
    {
        $valid = array_filter(
            $alternates,
            fn ($row) => $row['url'] !== null && !isset($this->emittedUrls[$row['url']]) && !$this->config->isUriExcluded($row['uri']),
        );

        if ($valid === []) {
            return [];
        }

        $hreflangLinks = [];
        if ($isMultiSite && count($valid) > 1) {
            foreach ($valid as $alt) {
                $hreflangLinks[] = [
                    'hreflang' => $alt['language'],
                    'href' => $alt['url'],
                ];
            }
        }

        $entries = [];

        foreach ($valid as $row) {
            $this->emittedUrls[$row['url']] = true;

            $entries[$row['siteId']] = [
                'loc' => $row['url'],
                'lastmod' => $row['lastmod'],
                'alternates' => $hreflangLinks,
            ];
        }

        return $entries;
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
    }
}
