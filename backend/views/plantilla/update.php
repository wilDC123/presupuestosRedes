<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * Recibe las variables de PlantillaController::guardar() y las pasa tal cual
 * a _form.php, donde estan documentadas.
 *
 * @var yii\web\View $this
 * @var backend\models\Plantilla $model
 */

$this->title = 'Editar plantilla';
$this->params['breadcrumbs'][] = ['label' => 'Plantillas', 'url' => ['index']];
$this->params['breadcrumbs'][] = $model->nombre;
?>
<div class="plantilla-update">

    <div class="rd-pagehead">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="rd-pagehead-sub">
                Los cambios valen para los proyectos que carguen esta plantilla desde ahora; los que ya la usaron no cambian.
            </div>
        </div>
        <div class="rd-acciones">
            <?= Html::a('Cancelar', ['index'], ['class' => 'btn btn-outline']) ?>
            <?= Html::submitButton('Guardar plantilla', ['class' => 'btn btn-primary', 'form' => 'plantilla-form']) ?>
        </div>
    </div>

    <?= $this->render('_form', [
        'model' => $model,
        'items' => $items,
        'idsCategoria' => $idsCategoria,
        'categorias' => $categorias,
        'tipos' => $tipos,
        'subcatalogo' => $subcatalogo,
    ]) ?>

</div>
