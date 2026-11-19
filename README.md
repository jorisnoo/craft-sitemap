# Craft Sitemap

A [Craft CMS](https://craftcms.com/) module that generates a zero-config XML sitemap with multi-site hreflang support.

## Features

- Generates a `sitemap.xml` with all live entries and categories that Craft renders a page for
- Adds `xhtml:link` hreflang alternates for multi-site setups
- Deduplicates URLs across canonical groups and sites
- Serves a `robots.txt` that points crawlers at the sitemap, built from your site URLs
- Works with zero config; optional config file to exclude sections, entry types, category groups, or URI patterns

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

The sitemap will be available at `sitemap.xml` on your site, and `robots.txt` will reference it.

## Configuration

Configuration is optional. To exclude content from the sitemap or customise `robots.txt`, create `config/craft-sitemap.php`:

```php
return [
    // Skip all entries in these section handles
    'excludeSections' => ['private', 'drafts'],

    // Skip all entries of these entry type handles (e.g. entries that only redirect elsewhere)
    'excludeEntryTypes' => ['externalLink'],

    // Skip all categories in these group handles
    'excludeCategoryGroups' => ['internal'],

    // Skip URIs matching any of these glob patterns (matched against the element URI)
    'excludeUriPatterns' => ['legal/*', 'private'],

    // robots.txt rules; the Sitemap line is appended automatically
    'robots' => <<<'TXT'
        User-agent: *
        Disallow: /cache/

        User-agent: GPTBot
        Disallow: /
        TXT,
];
```

URI patterns use `fnmatch()` syntax: `*` matches any sequence of characters within a path segment, `?` matches a single character.

### robots.txt

The module answers `robots.txt` with the configured `robots` rules (default: allow everything) followed by `Sitemap: <scheme>://<host>/sitemap.xml`, where the host comes from the current site's base URL, so each environment and each domain of a multi-domain install advertises its own sitemap. When Craft's `disallowRobots` setting is on, it serves `Disallow: /` for all crawlers instead.

A static `web/robots.txt` file takes precedence over the module, so remove it to use the generated one.

## How It Works

The module registers a `sitemap.xml` route on your site. When requested, it:

1. Queries all live entries and categories with URIs across all sites
2. Keeps only elements Craft would render a page for: their section or group must have a template for that site, and fragment URLs (`page#anchor`) are left out
3. Groups elements by their canonical ID to identify translations
4. Deduplicates by site handle and URL
5. Renders an XML sitemap with `<loc>`, `<lastmod>`, and (on multi-site) `<xhtml:link rel="alternate">` tags

Single-site installs get a standard sitemap. Installs with several sites that have URLs get a sitemap index at `sitemap.xml`, listing a `sitemap-<siteHandle>.xml` per site on the requested host; hreflang alternates are added when a page exists in at least two languages. Sites without a base URL are ignored. Site handles in sitemap URLs are matched case-insensitively.

Results are cached and automatically invalidated when entries or categories are saved or deleted.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.

## Maintainer releases

See [RELEASING.md](RELEASING.md) for versioning, changelog entries and the release command.
