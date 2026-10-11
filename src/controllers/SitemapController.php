<?php

namespace Noo\CraftSitemap\controllers;

use Craft;
use craft\models\Site;
use craft\web\Controller;
use craft\web\Response;
use Noo\CraftSitemap\Config;
use Noo\CraftSitemap\Module;
use Noo\CraftSitemap\SitemapGenerator;
use yii\caching\TagDependency;
use yii\web\NotFoundHttpException;

class SitemapController extends Controller
{
    protected array|int|bool $allowAnonymous = true;

    private ?Config $config = null;

    private ?SitemapGenerator $generator = null;

    public function actionIndex(): Response
    {
        $sitesService = Craft::$app->getSites();

        if (!SitemapGenerator::isMultiSite()) {
            // Single-site installs serve the URL set directly (or an index of
            // pages once it exceeds the per-file URL limit); the host is irrelevant.
            return $this->siteResponse($sitesService->getPrimarySite(), null);
        }

        // Key the index by the matched group of sites rather than the raw Host
        // header, so arbitrary hosts can't create cache entries. Hosts that
        // don't match any site fall back to the site Craft resolved for the request.
        $sites = SitemapGenerator::sitesForHost(Craft::$app->getRequest()->getHostName())
            ?: SitemapGenerator::sitesSharingHost($sitesService->getCurrentSite());

        $locs = [];
        foreach ($sites as $site) {
            array_push($locs, ...$this->sitemapLocs($site));
        }

        return $this->xmlResponse($this->generator()->renderIndex($locs));
    }

    public function actionSite(string $siteHandle, ?int $page = null): Response
    {
        $site = Craft::$app->getSites()->getSiteByHandle($siteHandle);

        if ($site === null) {
            throw new NotFoundHttpException();
        }

        return $this->siteResponse($site, $page);
    }

    /**
     * Renders a site's URL set, the requested page of it, or — when the site
     * exceeds the per-file URL limit and no page is given — an index of its pages.
     */
    private function siteResponse(Site $site, ?int $page): Response
    {
        $urls = $this->siteUrls($site);
        $pageCount = SitemapGenerator::pageCount(count($urls));

        if ($page === null && $pageCount > 1) {
            return $this->xmlResponse($this->generator()->renderIndex($this->sitemapLocs($site)));
        }

        $page ??= 1;

        if ($page < 1 || $page > $pageCount) {
            throw new NotFoundHttpException();
        }

        $slice = array_slice($urls, ($page - 1) * SitemapGenerator::MAX_URLS_PER_SITEMAP, SitemapGenerator::MAX_URLS_PER_SITEMAP);

        return $this->xmlResponse($this->generator()->renderUrlset($slice));
    }

    /**
     * The sitemap URLs to list in an index for the given site: its own
     * sitemap, or one URL per page when it exceeds the per-file limit.
     *
     * @return list<string>
     */
    private function sitemapLocs(Site $site): array
    {
        $baseUrl = $site->getBaseUrl() ?? '/';
        $pageCount = SitemapGenerator::pageCount(count($this->siteUrls($site)));

        if ($pageCount === 1) {
            return [$baseUrl . 'sitemap-' . $site->handle . '.xml'];
        }

        $locs = [];
        for ($page = 1; $page <= $pageCount; $page++) {
            $locs[] = $baseUrl . 'sitemap-' . $site->handle . '-' . $page . '.xml';
        }

        return $locs;
    }

    /**
     * @return list<array{loc: string, lastmod: string, alternates: list<array{hreflang: string, href: string}>}>
     */
    private function siteUrls(Site $site): array
    {
        $handles = array_map(static fn (Site $s) => $s->handle, SitemapGenerator::sitesSharingHost($site));
        $urlsBySite = $this->cached(
            'craft-sitemap:urls:' . implode(',', $handles),
            fn () => $this->generator()->getUrlsForHostOf($site),
            $site,
        );

        return $urlsBySite[$site->id] ?? [];
    }

    /**
     * Serves the cached value, taking a mutex on cache misses so concurrent
     * requests don't all regenerate the sitemap at once.
     *
     * @template T
     *
     * @param  callable(): T  $generate
     * @return T
     */
    private function cached(string $cacheKey, callable $generate, Site $site): mixed
    {
        $cache = Craft::$app->getCache();
        $value = $cache->get($cacheKey);

        if ($value !== false) {
            return $value;
        }

        $mutex = Craft::$app->getMutex();
        $lockName = "{$cacheKey}:lock";
        $acquired = $mutex->acquire($lockName, 30);

        try {
            // Another request may have generated it while we waited for the lock.
            $value = $cache->get($cacheKey);

            if ($value === false) {
                $value = $generate();
                $cache->set(
                    $cacheKey,
                    $value,
                    $this->cacheDuration($site),
                    new TagDependency(['tags' => Module::CACHE_TAG]),
                );
            }
        } finally {
            if ($acquired) {
                $mutex->release($lockName);
            }
        }

        return $value;
    }

    /**
     * The configured cache duration, shortened so the cache expires when the
     * next entry on the site's host goes live or expires.
     */
    private function cacheDuration(Site $site): int
    {
        $duration = $this->config()->cacheDuration
            ?? Craft::$app->getConfig()->getGeneral()->cacheDuration;

        $siteIds = array_map(static fn (Site $s) => $s->id, SitemapGenerator::sitesSharingHost($site));
        $untilChange = $this->generator()->secondsUntilNextScheduledChange($siteIds);

        if ($untilChange === null) {
            return $duration;
        }

        return $duration > 0 ? min($duration, $untilChange) : $untilChange;
    }

    private function config(): Config
    {
        return $this->config ??= Config::load();
    }

    private function generator(): SitemapGenerator
    {
        return $this->generator ??= new SitemapGenerator($this->config());
    }

    private function xmlResponse(string $xml): Response
    {
        $response = $this->response;
        $response->format = Response::FORMAT_RAW;
        $response->getHeaders()->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->data = $xml;

        return $response;
    }
}
