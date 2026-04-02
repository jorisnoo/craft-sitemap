<?php

namespace Noo\CraftSitemap;

use Craft;
use craft\elements\Category;
use craft\elements\Entry;
use craft\events\ModelEvent;
use craft\events\RegisterUrlRulesEvent;
use craft\web\UrlManager;
use yii\base\Event;
use yii\base\Module as BaseModule;

class Module extends BaseModule
{
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
                }
            );
        });
    }

    private function registerCacheInvalidation(): void
    {
        $invalidate = static function (ModelEvent $event) {
            if ($event->sender->getIsDraft() || $event->sender->getIsRevision()) {
                return;
            }

            $cache = Craft::$app->getCache();
            $cache->delete('craft-sitemap:index');

            foreach (Craft::$app->getSites()->getAllSites() as $site) {
                $cache->delete("craft-sitemap:site:{$site->handle}");
            }
        };

        Event::on(Entry::class, Entry::EVENT_AFTER_SAVE, $invalidate);
        Event::on(Entry::class, Entry::EVENT_AFTER_DELETE, $invalidate);
        Event::on(Category::class, Category::EVENT_AFTER_SAVE, $invalidate);
        Event::on(Category::class, Category::EVENT_AFTER_DELETE, $invalidate);
    }
}
