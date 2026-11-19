<?php

use Noo\CraftSitemap\Config;
use Noo\CraftSitemap\SitemapPages;
use Noo\CraftSitemap\UrlSetBuilder;

function row(array $overrides = []): array
{
    return array_merge([
        'siteId' => 1,
        'language' => 'de-CH',
        'url' => 'https://example.test/de/about',
        'uri' => 'about',
        'lastmod' => '2026-01-01T00:00:00+00:00',
    ], $overrides);
}

function translatedPage(): array
{
    return [
        row(),
        row(['siteId' => 2, 'language' => 'en', 'url' => 'https://example.test/en/about']),
    ];
}

it('skips rows without a URL, such as translations on sites without a base URL', function () {
    $urls = (new UrlSetBuilder(new Config))->entries([row(), row(['siteId' => 2, 'language' => 'ka', 'url' => null])]);

    expect($urls)->toHaveCount(1)
        ->and($urls[0]['loc'])->toBe('https://example.test/de/about');
});

it('lists a page once when several translations share its URL', function () {
    $urls = (new UrlSetBuilder(new Config))->entries([row(), row(['siteId' => 2, 'language' => 'de-DE'])]);

    expect($urls)->toHaveCount(1)
        ->and(array_column($urls[0]['alternates'], 'hreflang'))->toBe(['de-CH', 'de-DE']);
});

it('skips fragment URLs that point into another page', function () {
    $urls = (new UrlSetBuilder(new Config))->entries([
        row(['url' => 'https://example.test/akhalubani']),
        row(['url' => 'https://example.test/akhalubani#2021-07-25']),
    ]);

    expect(array_column($urls, 'loc'))->toBe(['https://example.test/akhalubani']);
});

it('omits hreflang alternates when a page has no other language version', function () {
    $urls = (new UrlSetBuilder(new Config))->entries([row()]);

    expect($urls[0]['alternates'])->toBe([]);
});

it('links every language version of a translated page', function () {
    $urls = (new UrlSetBuilder(new Config))->entries(translatedPage());

    expect($urls)->toHaveCount(2)
        ->and($urls[0]['alternates'])->toBe([
            ['hreflang' => 'de-CH', 'href' => 'https://example.test/de/about'],
            ['hreflang' => 'en', 'href' => 'https://example.test/en/about'],
        ]);
});

function pages(array $groups, array $sites = [1], ?SitemapPages $writer = null): array
{
    $files = [];
    ($writer ?? new SitemapPages)->write($groups, $sites, new Config, count($sites) > 1,
        function ($id, $n, $xml) use (&$files) {
            $files[$id][$n] = $xml;
        });

    return $files;
}

it('writes each URL once in its own site file while preserving both language links', function () {
    $files = pages([translatedPage(), translatedPage()], [1, 2]);
    expect($files[1][1])->toContain('<loc>https://example.test/de/about</loc>', 'hreflang="en"')->not->toContain('<loc>https://example.test/en/about</loc>')
        ->and(substr_count($files[2][1], '<url>'))->toBe(1)
        ->and($files[2][1])->toContain('<loc>https://example.test/en/about</loc>', 'hreflang="de-CH"');
});

it('splits on either the URL count or the serialized bytes, including envelope and alternate links', function () {
    $groups = [[row()], [row(['url' => 'https://example.test/en/ä'])]];
    $firstBytes = strlen(pages([$groups[0]])[1][1]);
    foreach ([new SitemapPages(1), new SitemapPages(50000, $firstBytes)] as $writer) {
        $files = pages($groups, writer: $writer);
        expect($files[1])->toHaveCount(2);
        foreach ($files[1] as $xml) {
            expect(strlen($xml))->toBeLessThanOrEqual($firstBytes)
                ->and(simplexml_load_string($xml)->url)->toHaveCount(1);
        }
    }
    expect(pages([])[1][1])->toContain('</urlset>');
});

it('skips URIs matching excluded patterns', function () {
    $config = new Config;
    $config->excludeUriPatterns = ['legal/*'];

    $urls = (new UrlSetBuilder($config))->entries([
        row(['uri' => 'legal/imprint', 'url' => 'https://example.test/legal/imprint']),
        row(['uri' => 'legal/sub/page']),
    ]);

    expect(array_column($urls, 'loc'))->toBe(['https://example.test/de/about']);
});

it('renders escaped XML with the xhtml namespace only for multi-site installs', function () {
    $xml = pages([[row(['url' => 'https://example.test/?a=1&b=2'])]])[1][1];

    expect($xml)->toContain('<loc>https://example.test/?a=1&amp;b=2</loc>')
        ->not->toContain('xmlns:xhtml');

    expect(simplexml_load_string($xml))->not->toBeFalse();
});
