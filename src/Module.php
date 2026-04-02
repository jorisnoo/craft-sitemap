<?php

namespace Noo\CraftSitemap;

use Craft;
use craft\web\twig\variables\CraftVariable;
use yii\base\Event;
use yii\base\Module as BaseModule;

class Module extends BaseModule
{
    public function init(): void
    {
        Craft::setAlias('@Noo/CraftSitemap', __DIR__);

        parent::init();

        Craft::$app->onInit(function () {
            $this->registerTwigVariable();
        });
    }

    private function registerTwigVariable(): void
    {
        Event::on(
            CraftVariable::class,
            CraftVariable::EVENT_INIT,
            static function (Event $event) {
                $event->sender->set('sitemap', SitemapVariable::class);
            }
        );
    }
}
