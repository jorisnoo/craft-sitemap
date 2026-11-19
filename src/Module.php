<?php

namespace Noo\CraftSitemap;

use Craft;
use craft\elements\Category;
use craft\elements\Entry;
use craft\events\InvalidateElementCachesEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\services\Elements;
use craft\services\Sites;
use craft\web\UrlManager;
use craft\web\View;
use yii\base\Event;
use yii\base\Module as BaseModule;
use yii\caching\TagDependency;

class Module extends BaseModule
{
    public const CACHE_TAG = 'craft-sitemap:content';

    public function init(): void
    {
        Craft::setAlias('@Noo/CraftSitemap', __DIR__);

        parent::init();

        $this->controllerNamespace = 'Noo\\CraftSitemap\\controllers';

        Craft::$app->onInit(function () {
            $this->registerCacheInvalidation();

            if (Craft::$app->getRequest()->getIsConsoleRequest()) {
                return;
            }

            Event::on(
                UrlManager::class,
                UrlManager::EVENT_REGISTER_SITE_URL_RULES,
                static function (RegisterUrlRulesEvent $event) {
                    $event->rules['sitemap.xml'] = 'craft-sitemap/sitemap';
                    $event->rules['sitemap-<siteHandle:\w+>.xml'] = 'craft-sitemap/sitemap/site';
                    $event->rules['sitemap-<siteHandle:\w+>-<page:[1-9]\d*>.xml'] = 'craft-sitemap/sitemap/site';
                    $general = Craft::$app->getConfig()->getGeneral();
                    if (! isset($event->rules['robots.txt']) && ($general->headlessMode || ! $general->privateTemplateTrigger ||
                        ! Craft::$app->getView()->doesTemplateExist('robots.txt', View::TEMPLATE_MODE_SITE, true))) {
                        $event->rules['robots.txt'] = 'craft-sitemap/sitemap/robots';
                    }
                }
            );
        });
    }

    private function registerCacheInvalidation(): void
    {
        $queued = false;
        $invalidate = static function () use (&$queued) {
            if ($queued) {
                return;
            }
            $queued = true;
            Craft::$app->getDb()->onAfterTransaction(static function () use (&$queued) {
                $queued = false;
                TagDependency::invalidate(Craft::$app->getCache(), self::CACHE_TAG);
            });
        };

        Event::on(Elements::class, Elements::EVENT_INVALIDATE_CACHES, static function (InvalidateElementCachesEvent $event) use ($invalidate) {
            $element = $event->element ?? null;
            if ($element && ($element->getIsDraft() || $element->getIsRevision())) {
                return;
            }
            foreach (['element', 'element::'.Entry::class, 'element::'.Category::class] as $tag) {
                if (in_array($tag, $event->tags, true) || in_array($tag.'::*', $event->tags, true)) {
                    $invalidate();

                    return;
                }
            }
        });
        Event::on(Sites::class, Sites::EVENT_AFTER_SAVE_SITE, $invalidate);
        Event::on(Sites::class, Sites::EVENT_AFTER_DELETE_SITE, $invalidate);
    }
}
