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

URI patterns use `fnmatch()` syntax: `*` and `?` do not cross a `/`. Use `__home__` to exclude the homepage.

### robots.txt

The generated `robots.txt` is a fallback: static files, existing Craft routes and public site templates such as `robots.txt.twig` take precedence. The fallback uses the configured rules (default: allow all). When Craft's `disallowRobots` is enabled, it instead serves `Disallow: /` without a sitemap directive.

The `Sitemap:` line uses a configured site origin (scheme, hostname and port) and the application's mount path, not a locale prefix. For example, an app mounted at `/shop` with sites under `/shop/de/` and `/shop/fr/` advertises `/shop/sitemap.xml`.

## How It Works

The module registers a `sitemap.xml` route on your site. When requested, it:

1. Selects sites sharing the requested **origin**. Use fixed absolute base URLs, directly or via configuration. Sites using a request-derived `@web`, relative URLs or no URL are omitted; sitemap requests for unknown origins return 404.
2. Reads live entries and categories in bounded database batches. Fragment URLs and null URLs are ignored. For template routes Craft must resolve the template for the element's own site, including its configured index template.
3. Builds same-origin hreflang links for the translations of each page, including regional language codes that share a single URL. Cross-origin translations are not advertised.
4. Deduplicates locations and writes the XML pages for all sites on that origin in one pass.

Small single-site installs get a URL set directly. Multi-site installs get an index listing `sitemap-<siteHandle>.xml` for sites on the requested origin. Large sets are split before either the URL count or byte-size limit, including on single-site installs. The index links directly to `sitemap-<siteHandle>-<page>.xml`. Handles are case-insensitive.

The cache is invalidated after committed content and site changes, including restores and structure moves. It expires at the next scheduled publication/expiry or within an hour, whichever comes first. During regeneration another crawler may receive `503` with `Retry-After` rather than starting a second scan.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.

## Maintainer releases

See [RELEASING.md](RELEASING.md) for versioning, changelog entries and the release command.
