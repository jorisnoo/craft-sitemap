<?php

namespace Noo\CraftSitemap\controllers;

use Craft;
use craft\models\Site;
use craft\web\Controller;
use craft\web\Response;
use Noo\CraftSitemap\Config;
use Noo\CraftSitemap\Module;
use Noo\CraftSitemap\RobotsTxt;
use Noo\CraftSitemap\SitemapGenerator;
use Noo\CraftSitemap\SitemapPages;
use yii\caching\TagDependency;
use yii\web\NotFoundHttpException;
use yii\web\ServiceUnavailableHttpException;

class SitemapController extends Controller
{
    protected array|int|bool $allowAnonymous = true;

    public function actionIndex(): Response
    {
        return $this->sitemap(null, null);
    }

    public function actionSite(string $siteHandle, ?int $page = null): Response
    {
        return $this->sitemap($siteHandle, $page);
    }

    public function actionRobots(): Response
    {
        $request = Craft::$app->getRequest();
        $sitemapUrl = RobotsTxt::sitemapUrl(
            array_map(static fn (Site $site): string => $site->getBaseUrl(), SitemapGenerator::sitesWithUrls()),
            $request->getHostInfo(), SitemapGenerator::fixedBaseUrl(Craft::$app->getSites()->getCurrentSite()),
            $request->getBaseUrl(),
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

    private function sitemap(?string $handle, ?int $page): Response
    {
        $all = SitemapGenerator::sitesWithUrls();
        $origin = RobotsTxt::origin(Craft::$app->getRequest()->getHostInfo());
        $sites = array_values(array_filter($all, static fn (Site $s) => RobotsTxt::origin($s->getBaseUrl()) === $origin));
        if ($sites === [] || ($page !== null && $page < 1)) {
            throw new NotFoundHttpException;
        }
        $target = count($all) === 1 ? $sites[0] : null;
        if ($handle !== null) {
            $target = null;
            foreach ($sites as $site) {
                if (strcasecmp($site->handle, $handle) === 0) {
                    $target = $site;
                    break;
                }
            }
            if ($target === null) {
                throw new NotFoundHttpException;
            }
        }

        $ids = array_map(static fn (Site $s) => $s->id, $sites);
        $key = 'craft-sitemap:v2:'.implode(',', $ids).':'.(int) (count($all) > 1);
        $cache = Craft::$app->getCache();
        $read = static function () use ($cache, $key, $sites, $target, $page) {
            $manifest = $cache->get($key);
            if ($manifest === false || $manifest['dependency']->isChanged($cache)) {
                return false;
            }

            return self::document($sites, $target, $page, $manifest['counts'],
                static fn ($id, $n) => $cache->get([$manifest['generation'], $id, $n]));
        };
        if (($xml = $read()) !== false) {
            return $this->xmlResponse($xml);
        }
        $mutex = Craft::$app->getMutex();
        if (! $mutex->acquire($key, 1)) {
            $this->response->getHeaders()->set('Retry-After', '5');
            throw new ServiceUnavailableHttpException('Sitemap generation is in progress.');
        }
        try {
            if (($xml = $read()) !== false) {
                return $this->xmlResponse($xml);
            }
            // Snapshot the invalidation tag before querying, not at publication:
            // an overlapping commit cannot attach yesterday's rows to today's tag.
            $dependency = new TagDependency(['tags' => Module::CACHE_TAG]);
            $dependency->evaluateDependency($cache);
            $config = Config::load();
            $generator = new SitemapGenerator($config);
            $expires = $generator->expiresAt();
            $generation = 'craft-sitemap:'.bin2hex(random_bytes(16));
            $requestedXml = null;
            $counts = (new SitemapPages)->write($generator->groups($ids), $ids, $config, count($all) > 1,
                static function ($id, $n, $body) use ($cache, $expires, $generation, $target, $page, &$requestedXml) {
                    $cache->set([$generation, $id, $n], $body, max(1, $expires - time()));
                    if ($target?->id === $id && ($page ?? 1) === $n) {
                        $requestedXml = $body;
                    }
                });
            if ($expires > time()) {
                $cache->set($key, compact('generation', 'dependency', 'counts'), max(1, $expires - time()));
            }

            return $this->xmlResponse(self::document($sites, $target, $page, $counts, static fn () => $requestedXml));
        } finally {
            $mutex->release($key);
        }
    }

    private static function document(array $sites, ?Site $target, ?int $page, array $counts, callable $read): string|false
    {
        if ($target === null) {
            return SitemapPages::index($sites, $counts);
        }
        $count = $counts[$target->id];
        if ($page === null && $count > 1) {
            return SitemapPages::index([$target], $counts);
        }
        if (($page ?? 1) > $count) {
            throw new NotFoundHttpException;
        }

        return $read($target->id, $page ?? 1);
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
