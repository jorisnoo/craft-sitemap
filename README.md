# Craft Sitemap

A [Craft CMS](https://craftcms.com/) module that generates a zero-config XML sitemap with multi-site hreflang support.

## Features

- Generates a `sitemap.xml` with all entries and categories that have URIs
- Adds `xhtml:link` hreflang alternates for multi-site setups
- Deduplicates URLs across canonical groups and sites
- Compatible with Blitz full-page caching
- No configuration required

## Requirements

- PHP 8.2+
- Craft CMS 5

## Installation

```bash
composer require jorisnoo/craft-sitemap
```

Register the module in your `config/app.php`:

```php
return [
    'modules' => [
        'craft-sitemap' => \Noo\CraftSitemap\Module::class,
    ],
    'bootstrap' => ['craft-sitemap'],
];
```

Add the routes in `config/routes.php`:

```php
return [
    'sitemap.xml' => ['template' => '_sitemap/index'],
    'sitemap-<siteHandle:\w+>.xml' => ['template' => '_sitemap/site'],
];
```

Create the templates:

`templates/_sitemap/index.twig`:
```twig
{% header "Content-Type: application/xml; charset=UTF-8" %}
{{ craft.sitemap.generate()|raw }}
```

`templates/_sitemap/site.twig`:
```twig
{% header "Content-Type: application/xml; charset=UTF-8" %}
{% set site = craft.app.sites.getSiteByHandle(siteHandle) %}
{% if not site %}{% exit 404 %}{% endif %}
{{ craft.sitemap.generateForSite(site)|raw }}
```

## How It Works

The module provides a `craft.sitemap` Twig variable. When a sitemap template is rendered, it:

1. Queries all entries and categories with URIs across all sites
2. Groups elements by their canonical ID to identify translations
3. Deduplicates by site handle and URL
4. Renders XML with `<loc>`, `<lastmod>`, and (on multi-site) `<xhtml:link rel="alternate">` tags

Single-site installs get a standard sitemap. Multi-site installs get a sitemap index linking to per-site sitemaps with hreflang alternate links.

Because the sitemap is rendered through Craft's template engine, it works with Blitz out of the box. Blitz tracks the element queries and automatically invalidates the cached sitemap when entries or categories change.

## License

The MIT License (MIT). Please see [LICENSE](LICENSE) for more information.
