<?php

namespace Noo\CraftSitemap\controllers;

use Craft;
use craft\web\Controller;
use craft\web\Response;
use Noo\CraftSitemap\SitemapGenerator;
use yii\web\NotFoundHttpException;

class SitemapController extends Controller
{
    protected array|int|bool $allowAnonymous = true;

    public function actionIndex(): Response
    {
        $xml = (new SitemapGenerator)->generate();

        return $this->xmlResponse($xml);
    }

    public function actionSite(string $siteHandle): Response
    {
        $site = Craft::$app->getSites()->getSiteByHandle($siteHandle);

        if ($site === null) {
            throw new NotFoundHttpException();
        }

        $xml = (new SitemapGenerator)->generateForSite($site);

        return $this->xmlResponse($xml);
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
