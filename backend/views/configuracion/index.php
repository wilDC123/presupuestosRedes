<?php

declare(strict_types=1);

use yii\helpers\Html;

/**
 * Configuracion: tarjetas con las pantallas que se tocan poco. Cada enlace
 * lleva una linea de ayuda para saber que se cambia ahi sin entrar.
 *
 * @var yii\web\View $this
 */

$this->title = 'Configuración';
$this->params['breadcrumbs'][] = $this->title;

$grupos = [
    'Catálogos base' => [
        ['Categorías', ['/categoria/index'], 'Agrupan los ítems del subcatálogo y las tablas del presupuesto.'],
        ['Unidades de medida', ['/unidad-medida/index'], 'Nombre y abreviatura; en listados e impresos sale la abreviatura.'],
        ['Tipos de proyecto', ['/tipo-proyecto/index'], 'Clasifican proyectos y plantillas.'],
    ],
    'Codificación' => [
        ['Codificación de documentos', ['/codificacion/index'], 'Desde qué número siguen los códigos RD-PI-PT, RD-PI-PR y RD-PI-ET.'],
    ],
    'Textos de los impresos' => [
        ['PDF del presupuesto', ['/presupuesto/configuracion'], 'Nota final, firmas del control de elaboración y contactos del pie.'],
        ['Carta de entrega', ['/carta/configuracion'], 'Textos por defecto, firmas y formato del oficio.'],
        ['Solicitud de cotización', ['/solicitud-cotizacion/configuracion'], 'Cabecera, instrucciones y nota final para el proveedor.'],
        ['Formulario de especificación', ['/especificacion/configuracion'], 'Textos fijos, condiciones de entrega y firmas del formulario.'],
    ],
];
?>
<div class="configuracion-index">

    <div class="rd-pagehead">
        <div>
            <h1><?= Html::encode($this->title) ?></h1>
            <div class="rd-pagehead-sub">Catálogos base, codificación y textos fijos de los impresos.</div>
        </div>
    </div>

    <div class="rd-config-grid">
        <?php foreach ($grupos as $grupo => $enlaces): ?>
            <div class="card">
                <div class="rd-card-head"><div class="rd-card-titulo"><?= Html::encode($grupo) ?></div></div>
                <div class="rd-card-pad">
                    <?php foreach ($enlaces as [$etiqueta, $ruta, $ayuda]): ?>
                        <div class="rd-config-enlace">
                            <?= Html::a(Html::encode($etiqueta), $ruta) ?>
                            <div class="rd-nota"><?= Html::encode($ayuda) ?></div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
        <?php endforeach; ?>
    </div>

</div>
