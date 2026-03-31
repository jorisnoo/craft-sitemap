<?php

namespace Noo\CraftSitemap;

use Craft;
use craft\elements\Category;
use craft\elements\Entry;
use craft\models\Site;

class SitemapGenerator
{
    /** @var array<string, true> */
    private array $emittedUrls = [];

    public function generate(): string
    {
        $sites = Craft::$app->getSites()->getAllSites();

        if (count($sites) <= 1) {
            return $this->generateSitemap($sites);
        }

        return $this->generateIndex($sites);
    }

    public function generateForSite(Site $site): string
    {
        return $this->generateSitemap(Craft::$app->getSites()->getAllSites(), $site);
    }

    /**
     * @param  array<Site>  $allSites
     */
    private function generateIndex(array $allSites): string
    {
        $lines = [
            '<?xml version="1.0" encoding="UTF-8"?>',
            '<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">',
        ];

        foreach ($allSites as $site) {
            $url = $site->getBaseUrl() . 'sitemap-' . $site->handle . '.xml';
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
     * @return array<string, array<\craft\base\Element>>
     */
    private function getElementGroups(): array
    {
        $entries = $this->groupByCanonicalId(
            Entry::find()
                ->uri(':notempty:')
                ->slug('not *__temp_*')
                ->site('*')
                ->orderBy('uri')
                ->all()
        );

        $categories = $this->groupByCanonicalId(
            Category::find()
                ->uri(':notempty:')
                ->site('*')
                ->all()
        );

        return array_merge($entries, $categories);
    }

    /**
     * @param  array<\craft\base\Element>  $elements
     * @return array<string, array<\craft\base\Element>>
     */
    private function groupByCanonicalId(array $elements): array
    {
        $groups = [];

        foreach ($elements as $element) {
            $key = (string) $element->canonicalId;
            $groups[$key] ??= [];
            $groups[$key][] = $element;
        }

        return $groups;
    }

    /**
     * @param  array<\craft\base\Element>  $alternates
     * @return list<array{loc: string, lastmod: string, alternates: list<array{hreflang: string, href: string}>}>
     */
    private function buildUrlEntries(array $alternates, bool $isMultiSite, ?Site $forSite = null): array
    {
        $unique = $this->deduplicateBySite($alternates);

        // Filter out entries whose URLs are already claimed by a previous canonical group
        $valid = array_filter($unique, fn ($el) => !isset($this->emittedUrls[$el->url]));

        if ($valid === []) {
            return [];
        }

        $hreflangLinks = [];
        if ($isMultiSite && count($valid) > 1) {
            foreach ($valid as $alt) {
                $hreflangLinks[] = [
                    'hreflang' => $alt->site->language,
                    'href' => $alt->url,
                ];
            }
        }

        $entries = [];

        foreach ($valid as $element) {
            $this->emittedUrls[$element->url] = true;

            if ($forSite !== null && $element->siteId !== $forSite->id) {
                continue;
            }

            $entries[] = [
                'loc' => $element->url,
                'lastmod' => $element->dateUpdated->format('c'),
                'alternates' => $hreflangLinks,
            ];
        }

        return $entries;
    }

    /**
     * Keep only the first element per site handle.
     *
     * @param  array<\craft\base\Element>  $elements
     * @return list<\craft\base\Element>
     */
    private function deduplicateBySite(array $elements): array
    {
        $seen = [];
        $result = [];

        foreach ($elements as $element) {
            $handle = $element->site->handle;

            if (!isset($seen[$handle])) {
                $seen[$handle] = true;
                $result[] = $element;
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
