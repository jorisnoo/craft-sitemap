<?php

namespace Noo\CraftSitemap;

use Craft;
use craft\elements\Category;
use craft\elements\Entry;

class SitemapGenerator
{
    /** @var array<string, true> */
    private array $emittedUrls = [];

    public function generate(): string
    {
        $isMultiSite = count(Craft::$app->getSites()->getAllSites()) > 1;

        $entries = $this->groupByCanonicalId(
            Entry::find()
                ->uri(':notempty:')
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

        $urls = [];

        foreach ($entries as $alternates) {
            array_push($urls, ...$this->buildUrlEntries($alternates, $isMultiSite));
        }

        foreach ($categories as $alternates) {
            array_push($urls, ...$this->buildUrlEntries($alternates, $isMultiSite));
        }

        return $this->renderXml($urls, $isMultiSite);
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
    private function buildUrlEntries(array $alternates, bool $isMultiSite): array
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
