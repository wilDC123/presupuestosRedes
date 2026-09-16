<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var backend\models\ComputoMetrico $model
 */

$this->title = 'Nuevo cómputo métrico';
$this->params['breadcrumbs'][] = ['label' => 'Cómputos métricos', 'url' => ['index']];
$this->params['breadcrumbs'][] = $this->title;
?>
<div class="computo-metrico-create">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
    ]) ?>

</div>
