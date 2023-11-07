<?php

/** @var yii\web\View $this */

$this->title = 'Каталог товаров';

?>
<div class="site-index">

    <!--<div class="p-5 mb-4 bg-transparent rounded-3">
        <div class="container-fluid py-5 text-center">
            <h1 class="display-4">Congratulations!</h1>
            <p class="fs-5 fw-light">You have successfully created your Yii-powered application.</p>
            <p><a class="btn btn-lg btn-success" href="https://www.yiiframework.com">Get started with Yii</a></p>
        </div>
    </div>-->

    <div class="body-content">

        <div class="row">
            <?php
            foreach ($products as $k => $product) {
                ?>
                <div class="col-lg-4">
                    <h2><?=$product['name']?></h2>

                    <img src="<?=$product['image']?>" />

                    <p><?=$product['price']?></p>

                    <p><a class="btn btn-outline-secondary" href="<?=$product['url']?>">Подробнее</a></p>
                </div>
                <?php
            }
            ?>
        </div>

    </div>
</div>
