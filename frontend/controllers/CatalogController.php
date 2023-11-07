<?php

namespace frontend\controllers;

use yii\helpers\Url;
use yii\web\Controller;

class CatalogController extends Controller
{
    /**
     *
     * @return string
     */
    public function actionProduct(): string
    {

        return $this->render('product');
    }
}