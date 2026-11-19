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

it('points at the root sitemap on the host of a prefixed site base URL', function () {
    expect(RobotsTxt::sitemapUrlFor('https://www.example.test/de/'))->toBe('https://www.example.test/sitemap.xml')
        ->and(RobotsTxt::sitemapUrlFor('http://example.test:8080/'))->toBe('http://example.test:8080/sitemap.xml');
});

it('advertises no sitemap for relative or missing base URLs', function () {
    expect(RobotsTxt::sitemapUrlFor('/de/'))->toBeNull()
        ->and(RobotsTxt::sitemapUrlFor(null))->toBeNull();
});

it('advertises the sitemap of the requested host when its sites all sit under a path prefix', function () {
    $sites = ['https://report.example.test/de/', 'https://report22.example.test/de/', 'https://report22.example.test/fr/'];

    expect(RobotsTxt::sitemapUrlForHost($sites, 'REPORT22.example.test', 'https://report.example.test/de/'))
        ->toBe('https://report22.example.test/sitemap.xml');
});

it('falls back to the current site for hosts without a site of their own', function () {
    expect(RobotsTxt::sitemapUrlForHost(['https://www.example.test/'], 'alias.example.test', 'https://www.example.test/'))
        ->toBe('https://www.example.test/sitemap.xml');
});
