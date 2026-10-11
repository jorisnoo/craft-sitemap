<?php

namespace Noo\CraftSitemap;

use Craft;

class Config
{
    /** @var list<string> */
    public array $excludeSections = [];

    /** @var list<string> */
    public array $excludeCategoryGroups = [];

    /** @var list<string> */
    public array $excludeUriPatterns = [];

    /**
     * Seconds to cache the generated sitemap. Null uses Craft's `cacheDuration`
     * general config setting; 0 caches until content changes. The duration is
     * always shortened so the cache expires when the next entry goes live or expires.
     */
    public ?int $cacheDuration = null;

    public static function load(): self
    {
        $data = Craft::$app->getConfig()->getConfigFromFile('craft-sitemap');

        $config = new self();
        $config->excludeSections = array_values((array) ($data['excludeSections'] ?? []));
        $config->excludeCategoryGroups = array_values((array) ($data['excludeCategoryGroups'] ?? []));
        $config->excludeUriPatterns = array_values((array) ($data['excludeUriPatterns'] ?? []));
        $config->cacheDuration = isset($data['cacheDuration']) ? (int) $data['cacheDuration'] : null;

        return $config;
    }

    public function isUriExcluded(string $uri): bool
    {
        foreach ($this->excludeUriPatterns as $pattern) {
            if (fnmatch($pattern, $uri)) {
                return true;
            }
        }

        return false;
    }
}
