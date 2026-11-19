<?php

namespace Noo\CraftSitemap;

use craft\models\Site;
use LengthException;

/** Stream one origin's URL sets, retaining at most one unfinished page per site. */
class SitemapPages
{
    private const FOOTER = "</urlset>\n";

    public function __construct(private readonly int $maxUrls = 50000, private readonly int $targetBytes = 524288) {}

    /** @return array<int, int> Page counts keyed by site ID */
    public function write(iterable $groups, array $siteIds, Config $config, bool $multiSite, callable $save): array
    {
        $builder = new UrlSetBuilder($config);
        $header = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<urlset xmlns="http://www.sitemaps.org/schemas/sitemap/0.9"'.($multiSite ? ' xmlns:xhtml="http://www.w3.org/1999/xhtml"' : '').">\n";
        $pages = array_fill_keys($siteIds, 1);
        $xml = array_fill_keys($siteIds, $header);
        $counts = array_fill_keys($siteIds, 0);
        foreach ($groups as $group) {
            foreach ($builder->entries($group) as $entry) {
                $id = $entry['siteId'];
                $item = '  <url><loc>'.UrlSetBuilder::escape($entry['loc']).'</loc><lastmod>'.$entry['lastmod'].'</lastmod>';
                foreach ($entry['alternates'] as $link) {
                    $item .= '<xhtml:link rel="alternate" hreflang="'.UrlSetBuilder::escape($link['hreflang']).'" href="'.UrlSetBuilder::escape($link['href']).'" />';
                }
                $item .= "</url>\n";
                if (strlen($header) + strlen($item) + strlen(self::FOOTER) > 52428800) {
                    throw new LengthException('One sitemap entry exceeds the 50 MiB protocol limit.');
                }
                if ($counts[$id] && ($counts[$id] >= $this->maxUrls || strlen($xml[$id]) + strlen($item) + strlen(self::FOOTER) > $this->targetBytes)) {
                    $save($id, $pages[$id]++, $xml[$id].self::FOOTER);
                    $xml[$id] = $header;
                    $counts[$id] = 0;
                }
                $xml[$id] .= $item;
                $counts[$id]++;
            }
        }
        foreach ($xml as $id => $body) {
            $save($id, $pages[$id], $body.self::FOOTER);
        }

        return $pages;
    }

    /** @param list<Site> $sites */
    public static function index(array $sites, array $counts): string
    {
        $xml = '<?xml version="1.0" encoding="UTF-8"?>'."\n".'<sitemapindex xmlns="http://www.sitemaps.org/schemas/sitemap/0.9">'."\n";
        foreach ($sites as $site) {
            $count = $counts[$site->id];
            for ($n = 1; $n <= $count; $n++) {
                $url = $site->getBaseUrl().'sitemap-'.$site->handle.($count > 1 ? '-'.$n : '').'.xml';
                $xml .= '  <sitemap><loc>'.UrlSetBuilder::escape($url)."</loc></sitemap>\n";
            }
        }

        return $xml."</sitemapindex>\n";
    }
}
