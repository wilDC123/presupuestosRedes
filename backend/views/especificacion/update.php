<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * @var yii\web\View $this
 * @var backend\models\Especificacion $model
 * @var backend\models\EspecificacionVersion $version
 * @var array<int, array{nombre: ?string, valor: ?string}> $filas
 * @var int $emisiones
 * @var int|null $numeroBloqueado
 * @var string[] $errores
 * @var string|null $garantiaPorDefecto
 * @var array<int, string> $categorias Ver CategoriaService::opciones()
 * @var array<int, string> $items Ver SubcatalogoItemService::opciones()
 *
 * Los datos los arma EspecificacionController::formulario(); ver _form.php.
 */

$this->title = 'Editar especificación: ' . $model->nombre();
$this->params['breadcrumbs'][] = ['label' => 'Librería de especificaciones', 'url' => ['index']];
$this->params['breadcrumbs'][] = ['label' => $model->nombre(), 'url' => ['view', 'id' => $model->id]];
$this->params['breadcrumbs'][] = 'Editar';
?>
<div class="especificacion-update">

    <h1><?= Html::encode($this->title) ?></h1>

    <?= $this->render('_form', [
        'model' => $model,
        'version' => $version,
        'filas' => $filas,
        'emisiones' => $emisiones,
        'numeroBloqueado' => $numeroBloqueado,
        'errores' => $errores,
        'garantiaPorDefecto' => $garantiaPorDefecto,
        'categorias' => $categorias,
        'items' => $items,
    ]) ?>

</div>
