<?php

declare(strict_types=1);

namespace backend\services;

use backend\components\PresupuestoPdfGenerator;
use backend\models\CartaPresupuesto;
use backend\models\ConfiguracionCarta;
use backend\models\Presupuesto;
use DateTime;

/**
 * Capa de Servicio de la carta de entrega del presupuesto.
 *
 * Reglas de negocio que viven aqui:
 *  - una sola carta por presupuesto, y se puede escribir y guardar en
 *    CUALQUIER estado del presupuesto (no hace falta que este aprobado),
 *  - una carta nueva se llena con los textos de ConfiguracionCarta y los
 *    datos del proyecto; despues guarda su propia copia,
 *  - para IMPRIMIR solo se exige que la carta este guardada y que el
 *    presupuesto ya tenga numero de oficio y numero de presupuesto (los
 *    recibe al "Enviar a revision"),
 *  - el PDF no lleva marca de agua: se imprime cuando ya esta todo.
 *
 * Marcadores:
 *  - {proyecto} y {facultad}: se reemplazan al llenar la carta nueva (asi el
 *    encargado puede corregir la frase),
 *  - {oficio}, {numeroPresupuesto} y {especificaciones} (los codigos NET
 *    vigentes del proyecto): quedan en el texto y se reemplazan al imprimir,
 *    porque al escribir la carta pueden no existir todavia.
 */
class CartaService
{
    /** Marcadores que se reemplazan al imprimir (ver textoParaImprimir()). */
    public const MARCADORES_AL_IMPRIMIR = ['{oficio}', '{numeroPresupuesto}', '{especificaciones}'];

    private const MESES = [1 => 'enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre'];

    /**
     * La carta guardada del presupuesto o, si aun no tiene, una nueva SIN
     * guardar, ya llena (ver nueva()).
     */
    public function delPresupuesto(Presupuesto $presupuesto): CartaPresupuesto
    {
        return $presupuesto->carta ?? $this->nueva($presupuesto);
    }

    /**
     * Carta nueva (sin guardar) llena con la configuracion y el proyecto:
     * fecha de hoy, cargo "DECANO DE LA <FACULTAD>", referencia y cuerpo con
     * el nombre del proyecto y la facultad, y las tres firmas.
     */
    public function nueva(Presupuesto $presupuesto): CartaPresupuesto
    {
        $configuracion = ConfiguracionCarta::obtener();
        $proyecto = $presupuesto->proyecto;
        $nombreProyecto = trim((string) $proyecto->nombre);
        $facultad = trim((string) $proyecto->facultad);

        $carta = new CartaPresupuesto();
        $carta->idPresupuesto = $presupuesto->id;
        $carta->fecha = date('Y-m-d');
        $carta->tratamiento = 'Señor';
        $carta->destinatarioCargo = $this->cargoSugerido('Señor', $facultad);
        // Los marcadores se normalizan antes de reemplazar: una plantilla
        // guardada con {PROYECTO} tambien funciona.
        $carta->referencia = CartaPresupuesto::mayusculas(strtr(CartaPresupuesto::normalizarMarcadores((string) $configuracion->plantillaReferencia), [
            '{proyecto}' => $nombreProyecto,
            '{facultad}' => $facultad,
        ]));
        // En medio de una frase el nombre del proyecto va con minuscula
        // ("requerimiento de instalación de…"), salvo que empiece con una
        // sigla ("UPS…"), que se deja como esta.
        $carta->cuerpo = strtr(CartaPresupuesto::normalizarMarcadores((string) $configuracion->plantillaCuerpo), [
            '{proyecto}' => $this->minusculaInicial($nombreProyecto),
            '{facultad}' => $facultad,
        ]);
        $carta->saludo = $configuracion->saludo;
        $carta->despedida = $configuracion->despedida;
        $carta->copia = $configuracion->copia;
        $carta->adjunto = $configuracion->plantillaAdjunto;

        foreach (CartaPresupuesto::atributosFirmas() as $atributo) {
            $carta->$atributo = $configuracion->$atributo;
        }

        return $carta;
    }

    /**
     * Cargo sugerido para el destinatario: "DECANA DE LA FACULTAD DE…" con
     * Señora/Señoras, "DECANO DE LA FACULTAD DE…" con los demas. Vacio si
     * el proyecto no tiene facultad. La pantalla lo cambia sola al elegir
     * otro tratamiento (web/js/carta.js), mientras no se haya escrito otro.
     */
    public function cargoSugerido(string $tratamiento, string $facultad): string
    {
        if ($facultad === '') {
            return '';
        }

        $cargo = in_array($tratamiento, ['Señora', 'Señoras'], true) ? 'DECANA' : 'DECANO';

        return mb_strtoupper($cargo . ' DE LA ' . $facultad, 'UTF-8');
    }

    /**
     * Guarda la carta (nueva o ya existente) con lo que llega del formulario.
     */
    public function guardar(CartaPresupuesto $carta, array $datosFormulario): bool
    {
        return $carta->load($datosFormulario) && $carta->save();
    }

    /**
     * Lo que falta para imprimir la carta; vacio = se puede imprimir. No
     * exige que el presupuesto este aprobado.
     *
     * @return string[]
     */
    public function faltaParaImprimir(Presupuesto $presupuesto): array
    {
        $falta = [];

        if ($presupuesto->carta === null) {
            $falta[] = 'Guarde la carta.';
        }
        if (trim((string) $presupuesto->oficioDtic) === '') {
            $falta[] = 'El presupuesto todavía no tiene número de oficio DTIC (se pone al enviarlo a revisión).';
        }
        if (trim((string) $presupuesto->numeroPresupuesto) === '') {
            $falta[] = 'El presupuesto todavía no tiene número de presupuesto (se pone al enviarlo a revisión).';
        }

        return $falta;
    }

    /**
     * Reemplaza {oficio} y {numeroPresupuesto} con los del presupuesto, y
     * {especificaciones} con los numeros de sus formularios NET.
     */
    public function textoParaImprimir(string $texto, Presupuesto $presupuesto): string
    {
        $texto = CartaPresupuesto::normalizarMarcadores($texto);
        $especificaciones = str_contains($texto, '{especificaciones}') ? $this->codigosNet($presupuesto) : '';

        // Sin formularios emitidos el marcador desaparece con su espacio:
        // "especificaciones técnicas y cotización", no "técnicas  y".
        if ($especificaciones === '') {
            $texto = str_replace(' {especificaciones}', '', $texto);
        }

        return strtr($texto, [
            '{oficio}' => trim((string) $presupuesto->oficioDtic),
            '{numeroPresupuesto}' => trim((string) $presupuesto->numeroPresupuesto),
            '{especificaciones}' => $especificaciones,
        ]);
    }

    /**
     * "896, 897, 900": el numero (sin "NET:" ni el año) de los formularios
     * NET VIGENTES de la lista del proyecto (los que se entregan), de menor a
     * mayor. Vacio si no hay ninguno.
     */
    public function codigosNet(Presupuesto $presupuesto): string
    {
        $emisiones = (new EspecificacionEmisionService())->vigentesDelProyecto($presupuesto->proyecto);

        usort($emisiones, static fn ($a, $b) => [$a->anio, $a->numeroNet] <=> [$b->anio, $b->numeroNet]);

        return implode(', ', array_map(static fn ($emision) => (string) $emision->numeroNet, $emisiones));
    }

    /**
     * "DTIC Of. Nº 598/2026": el formato de la configuracion con el oficio
     * del presupuesto y el año de la carta. Si el oficio ya trae su año
     * ("598/2026"), no se repite.
     */
    public function numeroOficio(CartaPresupuesto $carta, Presupuesto $presupuesto, ConfiguracionCarta $configuracion): string
    {
        return $this->formatearOficio((string) $presupuesto->oficioDtic, (string) $carta->fecha, $configuracion);
    }

    /**
     * El oficio con el formato de la configuracion de la carta ("DTIC Of. Nº
     * {oficio}/{anio}"): lo usan la carta (con su fecha) y el PDF del
     * presupuesto (con la del presupuesto). Sin fecha, el año actual. Si el
     * oficio ya trae su año ("598/2026"), no se repite. Vacio si no hay oficio.
     *
     * @param string $fecha aaaa-mm-dd
     */
    public function formatearOficio(string $oficio, string $fecha, ?ConfiguracionCarta $configuracion = null): string
    {
        $oficio = trim($oficio);

        if ($oficio === '') {
            return '';
        }

        $configuracion ??= ConfiguracionCarta::obtener();
        $formato = CartaPresupuesto::normalizarMarcadores((string) ($configuracion->formatoOficio ?: '{oficio}'));

        if (str_contains($oficio, '/')) {
            $formato = str_replace('/{anio}', '', $formato);
        }

        return strtr($formato, [
            '{oficio}' => $oficio,
            '{anio}' => preg_match('/^\d{4}/', $fecha) === 1 ? substr($fecha, 0, 4) : date('Y'),
        ]);
    }

    /**
     * "Sucre, 16 de octubre de 2026".
     */
    public function lugarYFecha(CartaPresupuesto $carta, ConfiguracionCarta $configuracion): string
    {
        $fecha = DateTime::createFromFormat('Y-m-d', (string) $carta->fecha);
        $texto = $fecha === false
            ? (string) $carta->fecha
            : (int) $fecha->format('j') . ' de ' . self::MESES[(int) $fecha->format('n')] . ' de ' . $fecha->format('Y');

        return trim((string) $configuracion->ciudad) !== '' ? $configuracion->ciudad . ', ' . $texto : $texto;
    }

    /**
     * Textos fijos de la carta (redes.ConfiguracionCarta).
     */
    public function guardarConfiguracion(ConfiguracionCarta $configuracion, array $datosFormulario): bool
    {
        return $configuracion->load($datosFormulario) && $configuracion->save();
    }

    /**
     * Convierte el HTML de la vista carta/pdf (la arma el controlador) en el
     * PDF, en hoja oficio y sin marca de agua.
     *
     * @return array{contenido: string, nombreArchivo: string}
     */
    public function generarPdf(Presupuesto $presupuesto, string $html): array
    {
        $generador = new PresupuestoPdfGenerator();

        return [
            'contenido' => $generador->generarDesdeHtml($html),
            'nombreArchivo' => $generador->nombreArchivo($presupuesto, 'carta_'),
        ];
    }

    private function minusculaInicial(string $texto): string
    {
        $segunda = mb_substr($texto, 1, 1, 'UTF-8');

        if ($texto === '' || $segunda === '' || mb_strtolower($segunda, 'UTF-8') !== $segunda) {
            return $texto;
        }

        return mb_strtolower(mb_substr($texto, 0, 1, 'UTF-8'), 'UTF-8') . mb_substr($texto, 1, null, 'UTF-8');
    }
}
