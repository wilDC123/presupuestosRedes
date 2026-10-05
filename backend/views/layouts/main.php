<?php

declare(strict_types=1);

/**
 * Layout principal del modulo de Redes: barra lateral oscura a la izquierda
 * y el contenido de cada pantalla a la derecha (ver web/css/redes.css).
 *
 * @var yii\web\View $this
 * @var string $content
 */

use common\widgets\Alert;
use yii\bootstrap5\Breadcrumbs;
use yii\helpers\Html;

$this->render('_head');
?>
<?php $this->beginPage() ?>
<!DOCTYPE html>
<html lang="<?= Yii::$app->language ?>" data-bs-theme="light">
<head>
    <?php $this->head() ?>
    <title><?= Html::encode($this->title) ?> — DTIC Redes</title>
</head>
<body class="rd">
<?php $this->beginBody() ?>

<?= $this->render('_sidebar') ?>

<main id="main" class="rd-main" role="main">
    <?= Breadcrumbs::widget([
        'homeLink' => ['label' => 'DTIC / Redes', 'url' => Yii::$app->homeUrl],
        'links' => $this->params['breadcrumbs'] ?? [],
    ]) ?>
    <?= Alert::widget() ?>
    <?= $content ?>
</main>

<?php $this->endBody() ?>
</body>
</html>
<?php $this->endPage();
