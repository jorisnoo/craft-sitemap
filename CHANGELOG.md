# Changelog

All notable changes to this project will be documented in this file.

## [1.0.3](https://github.com/jorisnoo/craft-sitemap/releases/tag/v1.0.3) (2026-07-10)

### Performance Improvements

- batch element queries and lock sitemap cache regeneration ([42f476e](https://github.com/jorisnoo/craft-sitemap/commit/42f476e3f2968dde310d7f5802829b5dc606031f))

### Chores

- **deps:** bump actions/checkout from 6 to 7 ([c8d82bf](https://github.com/jorisnoo/craft-sitemap/commit/c8d82bfa701b01cd21d3844993004c501dc69964))
## [1.0.2](https://github.com/jorisnoo/craft-sitemap/releases/tag/v1.0.2) (2026-05-18)

### Features

- add optional configuration to exclude sections, categories, and URI patterns ([46a5eb4](https://github.com/jorisnoo/craft-sitemap/commit/46a5eb441bb00994493efd3b62aa126f6ad66c37))

### Chores

- add keywords, author homepage, and justfile ([f5ad12e](https://github.com/jorisnoo/craft-sitemap/commit/f5ad12e9c3eee6202a2aeec34379a05ce2626450))
## [1.0.1](https://github.com/jorisnoo/craft-sitemap/releases/tag/v1.0.1) (2026-05-12)

### Features

- filter hreflang links by site host ([63bab94](https://github.com/jorisnoo/craft-sitemap/commit/63bab944bc734712554ad8da0413a39f859d63a5))
## [1.0.0](https://github.com/jorisnoo/craft-sitemap/releases/tag/v1.0.0) (2026-05-12)

### Features

- add per-host sitemap caching ([995c492](https://github.com/jorisnoo/craft-sitemap/commit/995c492eb2f2bd77c143adc00b58965589462051))
- add multi-site sitemap support with per-site XML feeds and index ([33641f8](https://github.com/jorisnoo/craft-sitemap/commit/33641f8fccdf3f39dbd7f63f2271bdbd16d28924))

### Bug Fixes

- skip sitemap cache invalidation for drafts and revisions ([2009494](https://github.com/jorisnoo/craft-sitemap/commit/2009494035366f5962916c2c8b4d85e1399edc37))

### Performance Improvements

- add sitemap caching with automatic invalidation on content changes ([11d8d1f](https://github.com/jorisnoo/craft-sitemap/commit/11d8d1f0869f133148f5c19f3a0767819444a725))

### Code Refactoring

- add type guard for ModelEvent in cache invalidation ([4877ff2](https://github.com/jorisnoo/craft-sitemap/commit/4877ff22183d01cc4300ba4790d590df4228cbc5))
- implement controller-based sitemap delivery with caching ([c6b47b2](https://github.com/jorisnoo/craft-sitemap/commit/c6b47b2c18645522da364ef5fb09be056bf1dda4))
- migrate sitemap delivery from controller to Twig variable for Blitz support ([0728eb8](https://github.com/jorisnoo/craft-sitemap/commit/0728eb8bb22ff7782cb3fbc195adc645258818b5))

### Chores

- add project configuration and workflows ([aaa2cc7](https://github.com/jorisnoo/craft-sitemap/commit/aaa2cc75dcac51a5828239f40b84e6333c5588d2))
