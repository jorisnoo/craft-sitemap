<?php

namespace Noo\CraftSitemap\controllers;

use craft\web\Controller;
use craft\web\Response;
use Noo\CraftSitemap\SitemapGenerator;

class SitemapController extends Controller
{
    protected array|int|bool $allowAnonymous = true;

    public function actionIndex(): Response
    {
        $xml = (new SitemapGenerator)->generate();

        $response = $this->response;
        $response->format = Response::FORMAT_RAW;
        $response->getHeaders()->set('Content-Type', 'application/xml; charset=UTF-8');
        $response->data = $xml;

        return $response;
    }
}
