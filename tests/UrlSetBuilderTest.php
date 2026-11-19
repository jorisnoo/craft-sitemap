<?php

use Noo\CraftSitemap\Config;
use Noo\CraftSitemap\UrlSetBuilder;

function row(array $overrides = []): array
{
    return array_merge([
        'siteId' => 1,
        'siteHandle' => 'de',
        'language' => 'de-CH',
        'host' => 'example.test',
        'url' => 'https://example.test/de/about',
        'uri' => 'about',
        'lastmod' => '2026-01-01T00:00:00+00:00',
    ], $overrides);
}

function translatedPage(): array
{
    return [
        row(),
        row(['siteId' => 2, 'siteHandle' => 'en', 'language' => 'en', 'url' => 'https://example.test/en/about']),
    ];
}

it('skips rows without a URL, such as translations on sites without a base URL', function () {
    $urls = (new UrlSetBuilder(new Config))->build([
        '1' => [row(), row(['siteId' => 2, 'siteHandle' => 'ka', 'language' => 'ka', 'host' => null, 'url' => null])],
    ], isMultiSite: true);

    expect($urls)->toHaveCount(1)
        ->and($urls[0]['loc'])->toBe('https://example.test/de/about');
});

it('lists a page once when several translations share its URL', function () {
    $urls = (new UrlSetBuilder(new Config))->build([
        '1' => [row(), row(['siteId' => 2, 'siteHandle' => 'ru', 'language' => 'ru'])],
    ], isMultiSite: true);

    expect($urls)->toHaveCount(1)
        ->and($urls[0]['alternates'])->toBe([]);
});

it('skips fragment URLs that point into another page', function () {
    $urls = (new UrlSetBuilder(new Config))->build([
        '1' => [row(['url' => 'https://example.test/akhalubani', 'uri' => 'akhalubani'])],
        '2' => [row(['url' => 'https://example.test/akhalubani#2021-07-25', 'uri' => 'akhalubani#2021-07-25'])],
    ], isMultiSite: false);

    expect(array_column($urls, 'loc'))->toBe(['https://example.test/akhalubani']);
});

it('omits hreflang alternates when a page has no other language version', function () {
    $urls = (new UrlSetBuilder(new Config))->build([
        '1' => [row(), row(['siteId' => 2, 'siteHandle' => 'ka', 'language' => 'ka', 'host' => null, 'url' => null])],
    ], isMultiSite: true);

    expect($urls[0]['alternates'])->toBe([]);
});

it('links every language version of a translated page', function () {
    $urls = (new UrlSetBuilder(new Config))->build(['1' => translatedPage()], isMultiSite: true);

    expect($urls)->toHaveCount(2)
        ->and($urls[0]['alternates'])->toBe([
            ['hreflang' => 'de-CH', 'href' => 'https://example.test/de/about'],
            ['hreflang' => 'en', 'href' => 'https://example.test/en/about'],
        ]);
});

it('limits a per-site sitemap to that site while keeping same-host alternates', function () {
    $urls = (new UrlSetBuilder(new Config))->build(['1' => translatedPage()], isMultiSite: true, forSiteId: 2, forSiteHost: 'example.test');

    expect(array_column($urls, 'loc'))->toBe(['https://example.test/en/about'])
        ->and($urls[0]['alternates'])->toHaveCount(2);
});

it('drops alternates on other hosts, leaving none when only the page itself remains', function () {
    $page = [
        row(),
        row(['siteId' => 2, 'siteHandle' => 'other', 'language' => 'en', 'host' => 'other.test', 'url' => 'https://other.test/about']),
    ];

    $urls = (new UrlSetBuilder(new Config))->build(['1' => $page], isMultiSite: true, forSiteId: 1, forSiteHost: 'example.test');

    expect($urls[0]['alternates'])->toBe([]);
});

it('skips URIs matching excluded patterns', function () {
    $config = new Config;
    $config->excludeUriPatterns = ['legal/*'];

    $urls = (new UrlSetBuilder($config))->build([
        '1' => [row(['uri' => 'legal/imprint', 'url' => 'https://example.test/legal/imprint'])],
        '2' => [row()],
    ], isMultiSite: false);

    expect(array_column($urls, 'loc'))->toBe(['https://example.test/de/about']);
});

it('renders escaped XML with the xhtml namespace only for multi-site installs', function () {
    $xml = UrlSetBuilder::render([[
        'loc' => 'https://example.test/?a=1&b=2',
        'lastmod' => '2026-01-01T00:00:00+00:00',
        'alternates' => [],
    ]], isMultiSite: false);

    expect($xml)->toContain('<loc>https://example.test/?a=1&amp;b=2</loc>')
        ->not->toContain('xmlns:xhtml');

    expect(simplexml_load_string($xml))->not->toBeFalse();
});
