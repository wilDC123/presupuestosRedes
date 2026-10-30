<?php

declare(strict_types=1);

namespace backend\models;

use yii\db\ActiveRecord;

/**
 * Modelo para la tabla "redes.ProyectoAreaIntervencion": un lugar fisico
 * donde se hace el trabajo del proyecto (edificio -> bloque -> nivel ->
 * ambiente). Un proyecto puede tener varias (ej. tres laboratorios).
 *
 * Llega hasta el nivel que haga falta: puede quedarse en el edificio o bajar
 * hasta el ambiente. Los id* son referencias LOGICAS a la base UsfxGEO (otra
 * base, sin llave foranea, como idSigma); los nombres son COPIAS tomadas al
 * guardar el proyecto, para que un documento ya firmado siga diciendo lo
 * mismo aunque despues renombren el ambiente.
 *
 * No tiene reglas: sus filas las arma ProyectoService con lo que devuelve la
 * fuente de infraestructura, nunca con nombres del formulario. La excepcion
 * es el area ESCRITA A MANO (2026-10-29: un edificio en construccion o que
 * aun no esta en UsfxGEO): sin ningun id, solo los nombres que se
 * escribieron. Ver esManual().
 *
 * @property int $id
 * @property int $idProyecto
 * @property int $orden
 * @property string|null $idEdificio NULL = area escrita a mano.
 * @property string $edificio
 * @property int|null $idBloqueArea
 * @property string|null $bloque
 * @property string|null $numeroNivel
 * @property string|null $nivel
 * @property string|null $idEspacio
 * @property string|null $codigoAmbiente
 * @property string|null $ambiente
 */
class ProyectoAreaIntervencion extends ActiveRecord
{
    public static function tableName(): string
    {
        return 'redes.ProyectoAreaIntervencion';
    }

    /**
     * "Edificio Carrera de Pedagogia · Bloque A · Piso 1: B101 Laboratorio",
     * con las partes que tenga.
     */
    public function texto(): string
    {
        $lugar = self::lugar($this->nivel, $this->ambiente);

        return $lugar === '' ? $this->grupo() : $this->grupo() . ' · ' . $lugar;
    }

    /**
     * Encabezado del grupo en el formulario, la vista y los documentos
     * ("Edificio Carrera de Pedagogia · Bloque A de Aulas y Oficinas"), con
     * el nombre tal como esta en UsfxGEO (sin rotulo "Edificio:": en los
     * documentos ya va despues de "Area de intervencion:") ...
     */
    public function grupo(): string
    {
        return implode(' · ', array_filter([$this->edificio, $this->bloque], static fn ($parte) => $parte !== null && $parte !== ''));
    }

    /**
     * ... y la linea de cada area dentro del grupo ("Piso 3: Aula E-303").
     * Un area que se queda en el piso, el bloque o el edificio lo dice. La
     * misma regla esta en web/js/proyecto-form.js (detalleDe()).
     */
    public function detalle(): string
    {
        if ($this->ambiente !== null && $this->ambiente !== '') {
            return self::lugar($this->nivel, $this->ambiente);
        }

        if ($this->nivel !== null && $this->nivel !== '') {
            return $this->nivel . ' (todo el piso)';
        }

        return $this->bloque !== null && $this->bloque !== '' ? '(todo el bloque)' : '(todo el edificio)';
    }

    /**
     * "Piso 1: B101 Laboratorio", "Piso 1" o "B101 Laboratorio".
     */
    private static function lugar(?string $nivel, ?string $ambiente): string
    {
        return implode(': ', array_filter([$nivel, $ambiente], static fn ($parte) => $parte !== null && $parte !== ''));
    }

    /**
     * true si se escribio a mano: no viene de UsfxGEO (no tiene id).
     */
    public function esManual(): bool
    {
        return $this->idEdificio === null || $this->idEdificio === '';
    }

    /**
     * Lo que envia el formulario por cada area (para volver a mostrar las
     * guardadas al editar el proyecto): los identificadores de UsfxGEO, o
     * los nombres si se escribio a mano.
     *
     * @return array{idEdificio: string, idBloqueArea: ?int, numeroNivel: ?string, idEspacio: ?string}|array{manual: true, edificio: string, bloque: ?string, nivel: ?string, ambiente: ?string}
     */
    public function claves(): array
    {
        if ($this->esManual()) {
            return [
                'manual' => true,
                'edificio' => (string) $this->edificio,
                'bloque' => $this->bloque,
                'nivel' => $this->nivel,
                'ambiente' => $this->ambiente,
            ];
        }

        return [
            'idEdificio' => (string) $this->idEdificio,
            'idBloqueArea' => $this->idBloqueArea === null ? null : (int) $this->idBloqueArea,
            'numeroNivel' => $this->numeroNivel,
            'idEspacio' => $this->idEspacio,
        ];
    }

    /**
     * Texto de varias areas para documentos: agrupa por edificio y bloque y
     * nombra los ambientes una sola vez ("Edificio X · Bloque B: B101, B102;
     * Edificio Y"). Asi tres laboratorios del mismo bloque ocupan una linea.
     *
     * @param self[] $areas
     */
    public static function resumen(array $areas): string
    {
        $grupos = [];

        foreach ($areas as $area) {
            $cabeza = $area->grupo();
            $detalle = $area->ambiente ?: $area->nivel;
            $grupos[$cabeza] ??= [];

            if ($detalle !== null && $detalle !== '' && !in_array($detalle, $grupos[$cabeza], true)) {
                $grupos[$cabeza][] = $detalle;
            }
        }

        $partes = [];

        foreach ($grupos as $cabeza => $detalles) {
            $partes[] = $detalles === [] ? $cabeza : $cabeza . ': ' . implode(', ', $detalles);
        }

        return implode('; ', $partes);
    }
}
