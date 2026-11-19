<?php

namespace Noo\CraftSitemap\controllers;

use Craft;
use craft\models\Site;
use craft\web\Controller;
use craft\web\Response;
use Noo\CraftSitemap\Config;
use Noo\CraftSitemap\RobotsTxt;
use Noo\CraftSitemap\SitemapGenerator;
use yii\web\NotFoundHttpException;

class SitemapController extends Controller
{
    protected array|int|bool $allowAnonymous = true;

    public function actionIndex(): Response
    {
        $host = Craft::$app->getRequest()->getHostName();
        $xml = $this->cachedXml(
            "craft-sitemap:index:{$host}",
            fn () => (new SitemapGenerator)->generate($host),
        );

        return $this->xmlResponse($xml);
    }

    public function actionSite(string $siteHandle): Response
    {
        $site = $this->findSite($siteHandle);

        if ($site === null) {
            throw new NotFoundHttpException;
        }

        $xml = $this->cachedXml(
            "craft-sitemap:site:{$site->handle}",
            fn () => (new SitemapGenerator)->generateForSite($site),
        );

        return $this->xmlResponse($xml);
    }

    public function actionRobots(): Response
    {
        $sitemapUrl = RobotsTxt::sitemapUrlForHost(
            array_map(static fn (Site $site): string => $site->getBaseUrl(), SitemapGenerator::sitesWithUrls()),
            Craft::$app->getRequest()->getHostName() ?? '',
            Craft::$app->getSites()->getCurrentSite()->getBaseUrl(),
        );

        $response = $this->response;
        $response->format = Response::FORMAT_RAW;
        $response->getHeaders()->set('Content-Type', 'text/plain; charset=UTF-8');
        $response->data = RobotsTxt::render(
            Config::load()->robots,
            $sitemapUrl,
            Craft::$app->getConfig()->getGeneral()->disallowRobots,
        );

        return $response;
    }

    /**
     * Site handles are matched case-insensitively: URLs are often lowercased
     * on the way (redirects, CDNs, crawlers), and Craft handles are unique
     * regardless of case.
     */
    private function findSite(string $handle): ?Site
    {
        foreach (SitemapGenerator::sitesWithUrls() as $site) {
            if (strcasecmp($site->handle, $handle) === 0) {
                return $site;
            }
        }

        return null;
    }

    /**
     * Serves the cached XML, taking a mutex on cache misses so concurrent
     * requests don't all regenerate the sitemap at once.
     *
     * @param  callable(): string  $generate
     */
    private function cachedXml(string $cacheKey, callable $generate): string
    {
        $cache = Craft::$app->getCache();
        $xml = $cache->get($cacheKey);

        if ($xml !== false) {
            return $xml;
        }

        $mutex = Craft::$app->getMutex();
        $lockName = "{$cacheKey}:lock";
        $acquired = $mutex->acquire($lockName, 30);

        try {
            // Another request may have generated it while we waited for the lock.
            $xml = $cache->get($cacheKey);

            if ($xml === false) {
                $xml = $generate();
                $cache->set($cacheKey, $xml);
            }
        } finally {
            if ($acquired) {
                $mutex->release($lockName);
            }
        }

        return $xml;
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
