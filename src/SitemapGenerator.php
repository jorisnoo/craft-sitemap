<?php

namespace Noo\CraftSitemap;

use Craft;
use craft\db\Query;
use craft\db\Table;
use craft\elements\Category;
use craft\elements\db\ElementQuery;
use craft\elements\Entry;
use craft\helpers\App;
use craft\models\Site;
use craft\web\View;
use DateTimeImmutable;
use DateTimeZone;

/**
 * @phpstan-import-type SitemapRow from UrlSetBuilder
 */
class SitemapGenerator
{
    /** @var array<int, View> Separate template resolution caches for each site. */
    private array $views = [];

    /** @var array<int, array<string, bool>> */
    private array $resolved = [];

    public function __construct(private readonly Config $config) {}

    /**
     * Sites that serve pages. Sites without a base URL (or with URLs turned
     * off) never appear in a sitemap, so they don't make an install multi-site.
     *
     * @return list<Site>
     */
    public static function sitesWithUrls(): array
    {
        return array_values(array_filter(
            Craft::$app->getSites()->getAllSites(),
            static fn (Site $site): bool => $site->hasUrls && self::fixedBaseUrl($site) !== null,
        ));
    }

    public static function fixedBaseUrl(Site $site): ?string
    {
        $raw = $site->getBaseUrl(false);
        if ($raw === null) {
            return null;
        }
        if (Craft::$app->getRequest()->isWebAliasSetDynamically) {
            // An environment setting may itself expand to @web. Inspect it
            // before Craft resolves aliases against this request's headers.
            $raw = preg_replace_callback('/\$\{(\w+)}|\$(\w+)/', static fn ($m) => (string) App::env($m[1] ?: $m[2]), $raw);
            if ($raw === '@web' || str_starts_with($raw, '@web/')) {
                return null;
            }
        }
        $url = $site->getBaseUrl();

        return RobotsTxt::origin($url) !== null ? $url : null;
    }

    /** The next timestamp at which live entry membership can change without a save. */
    public function expiresAt(): int
    {
        $now = time();
        $expires = $now + min(Craft::$app->getConfig()->getGeneral()->cacheDuration ?: 3600, 3600);
        foreach (['postDate', 'expiryDate'] as $column) {
            $next = (new Query)->from(Table::ENTRIES)->where(['>', $column, gmdate('Y-m-d H:i:s', $now)])->min($column);
            if ($next) {
                $expires = min($expires, (new DateTimeImmutable($next, new DateTimeZone('UTC')))->getTimestamp());
            }
        }

        return $expires;
    }

    /**
     * @param  list<int>  $siteIds  Only sites that serve pages; elsewhere an element's URL falls back to another site's
     * @return iterable<list<SitemapRow>>
     */
    public function groups(array $siteIds): iterable
    {
        $entryQuery = Entry::find()
            ->uri(':notempty:')
            ->slug('not *__temp_*')
            ->siteId($siteIds);

        // A routed nested entry can have a URI but no section. Only exclude
        // explicit section matches; a SQL NOT IN would also discard NULL.
        $excluded = [];
        foreach ($this->config->excludeSections as $handle) {
            if ($section = Craft::$app->getEntries()->getSectionByHandle($handle)) {
                $excluded[] = $section->id;
            }
        }
        if ($excluded !== []) {
            $entryQuery->andWhere(['or', ['not in', 'entries.sectionId', $excluded], ['entries.sectionId' => null]]);
        }

        if ($this->config->excludeEntryTypes !== []) {
            $entryQuery->type(array_merge(['not'], $this->config->excludeEntryTypes));
        }

        yield from $this->collectRowsByCanonicalId($entryQuery);

        $categoryQuery = Category::find()
            ->uri(':notempty:')
            ->siteId($siteIds);

        if ($this->config->excludeCategoryGroups !== []) {
            $categoryQuery->group(array_merge(['not'], $this->config->excludeCategoryGroups));
        }

        yield from $this->collectRowsByCanonicalId($categoryQuery);
    }

    /**
     * Element URL/route events require hydration. Bound the SQL result itself,
     * including on buffered connections, and carry just one group across batches.
     * Craft's default entry/category queries select canonical elements.
     *
     * @return iterable<list<SitemapRow>>
     */
    private function collectRowsByCanonicalId(ElementQuery $query): iterable
    {
        $query->orderBy(['elements.id' => SORT_ASC, 'elements_sites.siteId' => SORT_ASC])->limit(200);
        $id = $siteId = 0;
        $group = [];
        do {
            $batch = (clone $query)->andWhere(['or', ['>', 'elements.id', $id], ['and', ['elements.id' => $id], ['>', 'elements_sites.siteId', $siteId]]])->all();
            foreach ($batch as $element) {
                if ($element->id !== $id && $group !== []) {
                    yield $group;
                    $group = [];
                }
                $id = $element->id;
                $siteId = $element->siteId;
                $site = $element->getSite();
                $route = $element->getRoute();
                if (! $route || (is_array($route) && ($route[0] ?? null) === 'templates/render' && ! $this->templateExists($site, $route[1]['template'] ?? ''))) {
                    continue;
                }
                $group[] = [
                    'siteId' => $siteId, 'language' => $site->language, 'url' => $element->getUrl(),
                    'uri' => $element->uri, 'lastmod' => $element->dateUpdated->format('c'),
                ];
            }
        } while (count($batch) === 200);
        if ($group !== []) {
            yield $group;
        }
    }

    private function templateExists(Site $site, string $name): bool
    {
        if (Craft::$app->getConfig()->getGeneral()->headlessMode) {
            return false;
        }
        if (isset($this->resolved[$site->id][$name])) {
            return $this->resolved[$site->id][$name];
        }
        $sites = Craft::$app->getSites();
        $previous = $sites->getCurrentSite();
        try {
            $sites->setCurrentSite($site);
            $view = $this->views[$site->id] ??= Craft::createObject(App::viewConfig());

            return $this->resolved[$site->id][$name] = $view->doesTemplateExist($name, View::TEMPLATE_MODE_SITE);
        } finally {
            $sites->setCurrentSite($previous);
        }
    }
}
