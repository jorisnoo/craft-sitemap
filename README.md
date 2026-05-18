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
];
```

URI patterns use `fnmatch()` syntax: `*` matches any sequence of characters within a path segment, `?` matches a single character.

## How It Works

The module registers a `sitemap.xml` route on your site. When requested, it:

1. Queries all entries and categories with URIs across all sites
2. Groups elements by their canonical ID to identify translations
3. Deduplicates by site handle and URL
4. Renders an XML sitemap with `<loc>`, `<lastmod>`, and (on multi-site) `<xhtml:link rel="alternate">` tags

Single-site installs get a standard sitemap. Multi-site installs automatically include hreflang alternate links for each translation.

Results are cached and automatically invalidated when entries or categories are saved or deleted.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
