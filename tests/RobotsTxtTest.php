<?php

use Noo\CraftSitemap\RobotsTxt;

it('appends the sitemap URL to the configured rules', function () {
    expect(RobotsTxt::render("User-agent: *\nDisallow: /cache/\n", 'https://example.test/sitemap.xml', false))
        ->toBe("User-agent: *\nDisallow: /cache/\n\nSitemap: https://example.test/sitemap.xml\n");
});

it('blocks all crawlers without advertising the sitemap when robots are disallowed', function () {
    expect(RobotsTxt::render("User-agent: *\nDisallow:", 'https://example.test/sitemap.xml', true))
        ->toBe("User-agent: *\nDisallow: /\n");
});

it('keeps the application mount but not the site locale, matching the configured origin', function () {
    $sites = ['https://example.test/shop/de/', 'http://example.test/shop/fr/', 'https://example.test:8443/shop/fr/'];
    expect(RobotsTxt::sitemapUrl($sites, 'https://EXAMPLE.test:8443', $sites[0], '/shop'))->toBe('https://example.test:8443/shop/sitemap.xml')
        ->and(RobotsTxt::sitemapUrl($sites, 'http://example.test', $sites[0], '/shop'))->toBe('http://example.test/shop/sitemap.xml')
        ->and(RobotsTxt::sitemapUrl($sites, 'https://example.test:443', null, ''))->toBe('https://example.test/sitemap.xml');
});

it('ignores missing and relative bases and falls back to a configured site for aliases', function () {
    expect(RobotsTxt::sitemapUrl(['/de/'], 'https://example.test', null, ''))->toBeNull()
        ->and(RobotsTxt::sitemapUrl([], 'https://alias.test', 'https://site.test/en/', ''))->toBe('https://site.test/sitemap.xml');
});
