<?php

declare(strict_types=1);

/**
 * Barra lateral del modulo (menu "Gestion" + usuario en sesion).
 *
 * Cada entrada indica con que controladores se marca como activa: asi el
 * menu "Proveedores" sigue resaltado en proveedor/create, proveedor/update, etc.
 *
 * @var yii\web\View $this
 */

use yii\helpers\Html;

$controladorActual = Yii::$app->controller->id;

$menu = [
    ['Dashboard', ['/site/index'], ['site']],
    ['Categorías', ['/categoria/index'], ['categoria']],
    ['Proveedores', ['/proveedor/index'], ['proveedor']],
    ['Subcatálogo', ['/subcatalogo-item/index'], ['subcatalogo-item']],
    ['Plantillas', ['/plantilla/index'], ['plantilla']],
    ['Proyectos', ['/proyecto/index'], ['proyecto']],
    ['Especificaciones', ['/especificacion/index'], ['especificacion']],
    ['Cotizaciones', ['/cotizacion/index'], ['cotizacion']],
    ['Presupuestos', ['/presupuesto/index'], ['presupuesto']],
    ['Cómputos métricos', ['/computo-metrico/index'], ['computo-metrico']],
];

$usuario = Yii::$app->user->identity?->username;
?>
<aside class="rd-sidebar">
    <div class="rd-brand">
        <div class="rd-brand-logo">DT</div>
        <div>
            <div class="rd-brand-nombre">DTIC</div>
            <div class="rd-brand-sub">Redes y Telecom. — USFX</div>
        </div>
    </div>

    <div class="kicker">Gestión</div>
    <nav class="rd-nav">
        <?php foreach ($menu as [$etiqueta, $ruta, $controladores]): ?>
            <?= Html::a(Html::encode($etiqueta), $ruta, [
                'class' => 'navlink' . (in_array($controladorActual, $controladores, true) ? ' active' : ''),
            ]) ?>
        <?php endforeach; ?>
    </nav>

    <div class="rd-usuario">
        <?php if ($usuario === null): ?>
            <?= Html::a('Iniciar sesión', ['/site/login']) ?>
        <?php else: ?>
            <div class="rd-usuario-avatar"><?= Html::encode(mb_strtoupper(mb_substr($usuario, 0, 2))) ?></div>
            <div>
                <div class="rd-usuario-nombre"><?= Html::encode($usuario) ?></div>
                <?php // Cerrar sesion va por POST (VerbFilter de SiteController), por eso es un formulario y no un enlace. ?>
                <?= Html::beginForm(['/site/logout'], 'post') ?>
                    <?= Html::submitButton('Cerrar sesión', ['class' => 'rd-salir']) ?>
                <?= Html::endForm() ?>
            </div>
        <?php endif; ?>
    </div>
</aside>
