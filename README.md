# Craft Sitemap

A [Craft CMS](https://craftcms.com/) module that generates a zero-config XML sitemap with multi-site hreflang support.

## Features

- Generates a `sitemap.xml` with all entries and categories that have URIs
- Adds `xhtml:link` hreflang alternates for multi-site setups
- Deduplicates URLs across canonical groups and sites
- Works with zero config; optional config file to exclude sections, category groups, or URI patterns

## Requirements

- PHP 8.2+
- Craft CMS 5

## Installation

```bash
composer require jorisnoo/craft-sitemap
```

Then register the module in your `config/app.php`:

```php
return [
    'modules' => [
        'craft-sitemap' => \Noo\CraftSitemap\Module::class,
    ],
    'bootstrap' => ['craft-sitemap'],
];
```

The sitemap will be available at `sitemap.xml` on your site.

## Configuration

Configuration is optional. To exclude content from the sitemap, create `config/craft-sitemap.php`:

```php
return [
    // Skip all entries in these section handles
    'excludeSections' => ['private', 'drafts'],

    // Skip all categories in these group handles
    'excludeCategoryGroups' => ['internal'],

    // Skip URIs matching any of these glob patterns (matched against the element URI)
    'excludeUriPatterns' => ['legal/*', 'private'],

    // Seconds to cache the sitemap (default: Craft's `cacheDuration` setting; 0 = until content changes)
    'cacheDuration' => 3600,
];
```

URI patterns use `fnmatch()` syntax: `*` matches any sequence of characters within a path segment, `?` matches a single character.

## How It Works

The module registers a `sitemap.xml` route on your site. When requested, it:

1. Queries all entries and categories with URIs on the sites that share the requested site's host
2. Groups elements by their canonical ID to identify translations
3. Deduplicates by URL
4. Renders an XML sitemap with `<loc>`, `<lastmod>`, and (on multi-site) `<xhtml:link rel="alternate">` tags

Single-site installs get a standard sitemap at `sitemap.xml`. Multi-site installs serve a sitemap index at `sitemap.xml` listing a `sitemap-<siteHandle>.xml` for each site on the requested host (hosts that don't match any site's base URL fall back to the site Craft resolves for the request); each per-site sitemap includes hreflang alternate links to translations on the same host.

### Large sites

A sitemap file may contain at most 50,000 URLs. When a site exceeds that, its URLs are split into pages served at `sitemap-<siteHandle>-<page>.xml` (starting at 1):

- the sitemap index (`sitemap.xml`) lists the pages instead of `sitemap-<siteHandle>.xml`
- on single-site installs, `sitemap.xml` becomes a sitemap index of the pages
- `sitemap-<siteHandle>.xml` itself returns a sitemap index of the pages

Sites below the limit are served exactly as before.

### Caching

Results are cached and automatically invalidated when entries or categories are saved or deleted. The cache also expires when the next scheduled entry goes live or expires, and after `cacheDuration` seconds at the latest.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.

## Maintainer releases

See [RELEASING.md](RELEASING.md) for versioning, changelog entries and the release command.
