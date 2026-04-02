<?php

namespace Noo\CraftSitemap;

use craft\models\Site;

class SitemapVariable
{
    public function generate(): string
    {
        return (new SitemapGenerator)->generate();
    }

    public function generateForSite(Site $site): string
    {
        return (new SitemapGenerator)->generateForSite($site);
    }
}
