<?php

namespace Noo\CraftSitemap;

use Craft;
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
            if (Craft::$app->getRequest()->getIsConsoleRequest()) {
                return;
            }

            Event::on(
                UrlManager::class,
                UrlManager::EVENT_REGISTER_SITE_URL_RULES,
                static function (RegisterUrlRulesEvent $event) {
                    $event->rules['sitemap.xml'] = 'craft-sitemap/sitemap';
                }
            );
        });
    }
}
