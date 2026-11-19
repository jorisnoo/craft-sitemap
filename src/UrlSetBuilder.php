<?php

namespace Noo\CraftSitemap;

/**
 * Selects distinct locations and regional alternate links for one origin.
 *
 * @phpstan-type SitemapRow array{siteId: int, language: string, url: ?string, uri: ?string, lastmod: string}
 * @phpstan-type UrlEntry array{siteId: int, loc: string, lastmod: string, alternates: list<array{hreflang: string, href: string}>}
 */
class UrlSetBuilder
{
    /** @var array<string, true> */
    private array $emittedUrls = [];

    public function __construct(
        private readonly Config $config,
    ) {}

    /**
     * One canonical group's translations, already scoped to the same origin.
     * Gather regional mappings before deduplicating locations across the file set.
     *
     * @param  list<SitemapRow>  $rows
     * @return list<UrlEntry>
     */
    public function entries(array $rows): array
    {
        $valid = [];
        $links = [];
        foreach ($rows as $row) {
            if (! $this->isListable($row)) {
                continue;
            }
            $valid[] = $row;
            $links[$row['language']] ??= ['hreflang' => $row['language'], 'href' => $row['url']];
        }

        $alternates = count($links) > 1 ? array_values($links) : [];
        $entries = [];
        foreach ($valid as $row) {
            if (isset($this->emittedUrls[$row['url']])) {
                continue;
            }
            $this->emittedUrls[$row['url']] = true;
            $entries[] = [
                'siteId' => $row['siteId'],
                'loc' => $row['url'],
                'lastmod' => $row['lastmod'],
                'alternates' => $alternates,
            ];
        }

        return $entries;
    }

    public static function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_XML1 | ENT_QUOTES, 'UTF-8');
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

        return $row['uri'] === null || ! $this->config->isUriExcluded($row['uri']);
    }
}
