<?php

declare(strict_types=1);

/**
 * Barra lateral del modulo (menu por grupos + usuario en sesion).
 *
 * Cada entrada indica en que pantallas se marca como activa:
 * - un id de controlador ('proveedor') abarca todas sus acciones, asi
 *   "Proveedores" sigue resaltado en proveedor/create, proveedor/update, etc.;
 * - una ruta completa ('carta/configuracion') abarca solo esa accion y gana
 *   sobre el controlador: la configuracion de la carta resalta su propio
 *   enlace y no "Proyectos", aunque las dos sean de CartaController.
 *
 * El menu es solo datos: al integrarse al sistema institucional se copia
 * esta lista a su menu.
 *
 * @var yii\web\View $this
 */

use yii\helpers\Html;

$controladorActual = Yii::$app->controller->id;
$rutaActual = Yii::$app->controller->route;

$menu = [
    'Trabajo' => [
        ['Inicio', ['/site/index'], ['site']],
        ['Proyectos', ['/proyecto/index'], ['proyecto', 'lista-items', 'solicitud-cotizacion', 'elaborar-presupuesto', 'carta']],
        ['Presupuestos', ['/presupuesto/index'], ['presupuesto']],
    ],
    'Catálogos' => [
        ['Subcatálogo', ['/subcatalogo-item/index'], ['subcatalogo-item']],
        ['Especificaciones', ['/especificacion/index'], ['especificacion']],
        ['Plantillas', ['/plantilla/index'], ['plantilla']],
        ['Proveedores', ['/proveedor/index'], ['proveedor']],
    ],
    'Registros' => [
        ['Formularios de especificación', ['/especificacion-emision/index'], ['especificacion-emision']],
        ['Cotizaciones', ['/cotizacion/index'], ['cotizacion']],
    ],
];

// Lo que se toca poco vive en la pagina Configuracion (tarjetas), no en la
// barra: este enlace se resalta en esa pagina y en cada pantalla a la que lleva.
$configuracion = ['Configuración', ['/configuracion/index'], [
    'configuracion', 'categoria', 'unidad-medida', 'tipo-proyecto', 'proyectista', 'codificacion',
    'presupuesto/configuracion', 'carta/configuracion',
    'solicitud-cotizacion/configuracion', 'especificacion/configuracion',
]];

// Primero se busca una entrada con la ruta exacta; si ninguna la tiene,
// se resalta la que abarca el controlador.
$todas = [...array_merge(...array_values($menu)), $configuracion];
$activa = null;
foreach ($todas as $i => [, , $pantallas]) {
    if (in_array($rutaActual, $pantallas, true)) {
        $activa = $i;
        break;
    }
}
if ($activa === null) {
    foreach ($todas as $i => [, , $pantallas]) {
        if (in_array($controladorActual, $pantallas, true)) {
            $activa = $i;
            break;
        }
    }
}

$usuario = Yii::$app->user->identity?->username;
$indice = 0;
?>
<aside class="rd-sidebar">
    <div class="rd-brand">
        <div class="rd-brand-logo">DT</div>
        <div>
            <div class="rd-brand-nombre">DTIC</div>
            <div class="rd-brand-sub">Redes y Telecom. — USFX</div>
        </div>
    </div>

    <?php // Solo esta parte se desplaza si no cabe: el logo y el usuario quedan siempre a la vista. ?>
    <div class="rd-sidebar-menu">
        <?php foreach ($menu as $grupo => $entradas): ?>
            <div class="kicker"><?= Html::encode($grupo) ?></div>
            <nav class="rd-nav">
                <?php foreach ($entradas as [$etiqueta, $ruta]): ?>
                    <?= Html::a(Html::encode($etiqueta), $ruta, [
                        'class' => 'navlink' . ($indice++ === $activa ? ' active' : ''),
                    ]) ?>
                <?php endforeach; ?>
            </nav>
        <?php endforeach; ?>
        <nav class="rd-nav rd-nav-config">
            <?= Html::a(Html::encode($configuracion[0]), $configuracion[1], [
                'class' => 'navlink' . ($indice === $activa ? ' active' : ''),
            ]) ?>
        </nav>
    </div>

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
