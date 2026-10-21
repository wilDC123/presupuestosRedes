<?php

declare(strict_types=1);

use backend\models\CartaPresupuesto;
use yii\helpers\Html;

/**
 * HTML fuente del PDF de la carta de entrega. Se renderiza con
 * renderPartial() desde CartaController::actionImprimir() y se le pasa a
 * mPDF (PresupuestoPdfGenerator), en hoja oficio y SIN marca de agua.
 *
 * El membrete (escudo, titulos, logo DTIC, linea roja/azul, "4 siglos" y
 * contactos) es UNA imagen (web/img/fondo-carta.png) que va de fondo en
 * todas las paginas (@page background), estirada a la hoja completa. Como en
 * la carta Word de la DTIC, el membrete no llega a los bordes: deja 6 mm a
 * la izquierda, 8 arriba, 17 a la derecha y 20 abajo (mide 193 x 302 mm).
 * mPDF no puede dar a un fondo un tamaño en mm, asi que esos espacios ya
 * vienen dentro de la imagen: es un lienzo transparente del tamaño de la
 * hoja oficio con el membrete original pegado en esa posicion. Si cambia el
 * membrete, hay que volver a armarla igual.
 *
 * Los margenes del texto (4,75 arriba, 4,5 abajo, 3 a cada lado) lo dejan
 * dentro de la parte blanca del membrete: debajo del encabezado (termina a
 * unos 40 mm), sobre los contactos (empiezan a unos 292 mm) y a la derecha
 * de la linea roja/azul (a unos 20 mm).
 *
 * Fuente: "pagelladtic" (TeX Gyre Pagella, ver backend/fonts/pagella-dtic),
 * en lugar de Book Antiqua, que no se puede llevar al servidor. Todo en
 * cursiva, como la carta modelo.
 *
 * @var yii\web\View $this
 * @var backend\models\CartaPresupuesto $carta
 * @var backend\models\Presupuesto $presupuesto
 * @var backend\services\CartaService $service
 * @var backend\models\ConfiguracionCarta $configuracion
 * @var string $fondo ruta absoluta de la imagen del membrete
 */

$texto = static fn (?string $valor): string => Html::encode($service->textoParaImprimir((string) $valor, $presupuesto));
$firmas = $carta->firmas();
$firmasArriba = array_filter([$firmas[1] ?? null, $firmas[2] ?? null]);
?>
<!DOCTYPE html>
<html>
<head>
<meta charset="utf-8">
<style>
    @page {
        margin-top: 47.5mm;
        margin-bottom: 45mm;
        margin-left: 30mm;
        margin-right: 30mm;
        background: url("<?= Html::encode($fondo) ?>") no-repeat;
        /* 6 = la imagen se estira a toda la hoja. */
        background-image-resize: 6;
    }
    body {
        font-family: pagelladtic;
        font-style: italic;
        font-size: 11pt;
        color: #000;
    }
    .derecha {
        text-align: right;
        line-height: 1.2;
    }
    .bloque {
        line-height: 1.2;
        margin-top: 12pt;
    }
    /* Sin justificar: en un bloque angosto y en mayusculas, mPDF separa
       las letras para llenar la linea y se ve mal. */
    .referencia {
        margin-top: 12pt;
        margin-left: 37.5mm;
        text-align: left;
        font-weight: bold;
        line-height: 1.2;
    }
    .saludo {
        margin-top: 12pt;
    }
    p {
        text-align: justify;
        line-height: 1.5;
        margin: 6pt 0 0 0;
    }
    .despedida {
        margin-top: 12pt;
    }
    table.firmas {
        width: 100%;
        border-collapse: collapse;
        margin-top: 4mm;
    }
    /* El padding de arriba es el espacio para firmar y sellar (mPDF no
       respeta el alto de un div dentro de una celda). */
    table.firmas td {
        width: 50%;
        text-align: center;
        vertical-align: bottom;
        font-size: 10pt;
        padding: 20mm 2mm 0 2mm;
    }
    .espacio-vobo {
        height: 24mm;
    }
    .cargo {
        font-weight: bold;
    }
    .vobo {
        text-align: center;
        font-size: 10pt;
        font-weight: bold;
    }
    .vobo .cargo {
        font-size: 8pt;
    }
    .pie {
        margin-top: 8mm;
        font-size: 8pt;
        line-height: 1.3;
    }
</style>
</head>
<body>

<div class="derecha">
    <?= Html::encode($service->lugarYFecha($carta, $configuracion)) ?><br>
    <strong><?= Html::encode($service->numeroOficio($carta, $presupuesto, $configuracion)) ?></strong>
</div>

<div class="bloque">
    <?= Html::encode($carta->tratamiento) ?>:<br>
    <?= Html::encode($carta->destinatarioNombre) ?><br>
    <strong><?= Html::encode($carta->destinatarioCargo) ?></strong><br>
    Presente:
</div>

<div class="referencia">Ref. <?= $texto($carta->referencia) ?></div>

<?php if (!empty($carta->saludo)): ?>
    <div class="saludo"><?= $texto($carta->saludo) ?></div>
<?php endif; ?>

<?php foreach (CartaPresupuesto::parrafos($carta->cuerpo) as $parrafo): ?>
    <p><?= nl2br($texto($parrafo)) ?></p>
<?php endforeach; ?>

<?php if (!empty($carta->despedida)): ?>
    <div class="despedida"><?= $texto($carta->despedida) ?></div>
<?php endif; ?>

<?php if ($firmasArriba !== []): ?>
    <table class="firmas">
        <tr>
            <?php foreach ($firmasArriba as $firma): ?>
                <td>
                    <?= Html::encode(trim($firma['prefijo'] . ' ' . $firma['nombre'])) ?><br>
                    <span class="cargo"><?= Html::encode($firma['cargo']) ?></span>
                </td>
            <?php endforeach; ?>
        </tr>
    </table>
<?php endif; ?>

<?php if (isset($firmas[3])): ?>
    <div class="espacio-vobo"></div>
    <div class="vobo">
        <?= Html::encode(trim($firmas[3]['prefijo'] . '   ' . $firmas[3]['nombre'])) ?><br>
        <span class="cargo"><?= Html::encode($firmas[3]['cargo']) ?></span>
    </div>
<?php endif; ?>

<?php if (!empty($carta->copia) || !empty($carta->adjunto)): ?>
    <div class="pie">
        <?php if (!empty($carta->copia)): ?>
            Copia: <?= $texto($carta->copia) ?><br>
        <?php endif; ?>
        <?php if (!empty($carta->adjunto)): ?>
            Adjunto: <?= $texto($carta->adjunto) ?>
        <?php endif; ?>
    </div>
<?php endif; ?>

</body>
</html>
