<?php

namespace Noo\CraftSitemap;

use Craft;

class Config
{
    public const DEFAULT_ROBOTS = "User-agent: *\nDisallow:";

    /** @var list<string> */
    public array $excludeSections = [];

    /** @var list<string> */
    public array $excludeEntryTypes = [];

    /** @var list<string> */
    public array $excludeCategoryGroups = [];

    /** @var list<string> */
    public array $excludeUriPatterns = [];

    public string $robots = self::DEFAULT_ROBOTS;

    public static function load(): self
    {
        $data = Craft::$app->getConfig()->getConfigFromFile('craft-sitemap');

        $config = new self;
        $config->excludeSections = array_values((array) ($data['excludeSections'] ?? []));
        $config->excludeEntryTypes = array_values((array) ($data['excludeEntryTypes'] ?? []));
        $config->excludeCategoryGroups = array_values((array) ($data['excludeCategoryGroups'] ?? []));
        $config->excludeUriPatterns = array_values((array) ($data['excludeUriPatterns'] ?? []));
        $config->robots = (string) ($data['robots'] ?? self::DEFAULT_ROBOTS);

        return $config;
    }

    public function isUriExcluded(string $uri): bool
    {
        foreach ($this->excludeUriPatterns as $pattern) {
            if (fnmatch($pattern, $uri, FNM_PATHNAME)) {
                return true;
            }
        }

        return false;
    }
}
