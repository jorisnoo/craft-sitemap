<?php

use craft\elements\Entry;
use craft\models\EntryType;
use craft\models\Section;
use craft\models\Section_SiteSettings;
use craft\models\Site;
use Noo\CraftSitemap\Module;
use Noo\CraftSitemap\SitemapGenerator;
use yii\caching\TagDependency;

$vendor = dirname(__DIR__, 2).'/vendor';
require $vendor.'/autoload.php';

if ($argv[1] === 'request') {
    $_SERVER['HTTPS'] = 'on';
    $_SERVER['SERVER_PORT'] = 443;
    $_SERVER['HTTP_HOST'] = 'sitemap.example';
    $_SERVER['REQUEST_METHOD'] = 'GET';
    $_SERVER['REQUEST_URI'] = '/shop/'.$argv[2];
    $_SERVER['SCRIPT_NAME'] = $_SERVER['PHP_SELF'] = '/shop/index.php';
    $_SERVER['SCRIPT_FILENAME'] = getenv('CRAFT_BASE_PATH').'/web/index.php';
    $app = require $vendor.'/craftcms/cms/bootstrap/web.php';
    if (SitemapGenerator::fixedBaseUrl(new Site(['baseUrl' => '@web/de/'])) !== null ||
        SitemapGenerator::fixedBaseUrl(new Site(['baseUrl' => '$TEST_DYNAMIC_BASE'])) !== null) {
        throw new RuntimeException('Request-derived site origin was accepted.');
    }
    exit($app->run());
}

$app = require $vendor.'/craftcms/cms/bootstrap/console.php';
if ($argv[1] === 'rename') {
    $cache = $app->getCache();
    $dependency = new TagDependency(['tags' => Module::CACHE_TAG]);
    $dependency->evaluateDependency($cache);
    $entry = Entry::find()->slug('visible')->one();
    $tx = $app->getDb()->beginTransaction();
    $entry->slug = 'renamed';
    if (! $app->getElements()->saveElement($entry) || $dependency->isChanged($cache)) {
        throw new RuntimeException('Entry save failed or invalidated before commit.');
    }
    $tx->commit();
    if (! $dependency->isChanged($cache)) {
        throw new RuntimeException('Entry save did not invalidate on commit.');
    }
    exit;
}
if ($argv[1] !== 'seed') {
    exit($app->run());
}

$type = new EntryType(['name' => 'Page', 'handle' => 'page']);
$entries = $app->getEntries();
if (! $entries->saveEntryType($type)) {
    throw new RuntimeException(json_encode($type->getErrors()));
}
foreach (['visible' => '', 'hidden' => 'does-not-exist'] as $slug => $template) {
    $section = new Section([
        'name' => ucfirst($slug), 'handle' => $slug, 'type' => Section::TYPE_CHANNEL,
        'entryTypes' => [$type], 'siteSettings' => [new Section_SiteSettings([
            'siteId' => $app->getSites()->getPrimarySite()->id, 'hasUrls' => true,
            'uriFormat' => '{slug}', 'template' => $template,
        ])],
    ]);
    if (! $entries->saveSection($section)) {
        throw new RuntimeException(json_encode($section->getErrors()));
    }
    $entry = new Entry(['sectionId' => $section->id, 'typeId' => $type->id, 'title' => $slug, 'slug' => $slug, 'authorId' => 1]);
    if (! $app->getElements()->saveElement($entry)) {
        throw new RuntimeException(json_encode($entry->getErrors()));
    }
}
