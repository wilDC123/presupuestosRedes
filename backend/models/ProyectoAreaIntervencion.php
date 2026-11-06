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
     * Texto de varias areas para documentos y el historial: las lineas de
     * lineasPorEdificio() separadas por " | " (una por edificio).
     *
     * @param self[] $areas
     */
    public static function resumen(array $areas): string
    {
        return implode(' | ', self::lineasPorEdificio($areas));
    }

    /**
     * El area de intervencion como se escribe en los documentos (revision del
     * Ing. de DTIC del 2026-11-05, punto 5): UNA linea por edificio, con su
     * edificio, bloque, piso y espacio juntos, sin rotulos aparte. Antes el
     * edificio y el bloque iban en negrita como encabezado de un grupo.
     *
     *   "Edificio Facultad de Medicina, Bloque A, Piso 1: Anfiteatro y A-104 Sala"
     *   "Edificio Trabajo Social, Bloque A y Bloque B"     (los dos bloques enteros)
     *   "Edificio X, Bloque A, Piso 1: A101; Piso 2: A201 y A202; Bloque B"
     *   "Edificio Central"                                  (todo el edificio)
     *
     * El edificio no se repite: varias areas del mismo edificio van en su
     * linea; dos edificios son dos lineas. Los nombres van tal como estan en
     * UsfxGEO (o como se escribieron a mano), en el orden en que se eligieron.
     *
     * @param self[] $areas
     * @return string[]
     */
    public static function lineasPorEdificio(array $areas): array
    {
        $valor = static fn (?string $parte): string => trim((string) $parte);
        // edificio => bloque => piso => espacios ('' = no llega a ese nivel)
        $arbol = [];

        foreach ($areas as $area) {
            $edificio = $valor($area->edificio);
            $bloque = $valor($area->bloque);
            $piso = $valor($area->nivel);
            $espacio = $valor($area->ambiente);
            $arbol[$edificio][$bloque][$piso] ??= [];

            if ($espacio !== '' && !in_array($espacio, $arbol[$edificio][$bloque][$piso], true)) {
                $arbol[$edificio][$bloque][$piso][] = $espacio;
            }
        }

        $lineas = [];

        foreach ($arbol as $edificio => $bloques) {
            $partes = [];
            // Los bloques enteros (sin piso ni espacio) se nombran juntos, donde aparece el primero.
            $enteros = [];
            $posicionEnteros = null;

            foreach ($bloques as $bloque => $pisos) {
                $detalle = [];

                foreach ($pisos as $piso => $espacios) {
                    $piso = (string) $piso;
                    if ($piso === '' && $espacios === []) {
                        continue;
                    }
                    $nombres = self::enumerar($espacios);
                    $detalle[] = $piso === '' ? $nombres : ($nombres === '' ? $piso : $piso . ': ' . $nombres);
                }

                $bloque = (string) $bloque;
                if ($bloque === '') {
                    if ($detalle !== []) {
                        $partes[] = implode('; ', $detalle);
                    }
                } elseif ($detalle === []) {
                    $enteros[] = $bloque;
                    $posicionEnteros ??= count($partes);
                } else {
                    $partes[] = $bloque . ', ' . implode('; ', $detalle);
                }
            }

            if ($enteros !== []) {
                array_splice($partes, (int) $posicionEnteros, 0, [self::enumerar($enteros)]);
            }

            $edificio = (string) $edificio;
            $lineas[] = $partes === [] ? $edificio : $edificio . ', ' . implode('; ', $partes);
        }

        return $lineas;
    }

    /** "A", "A y B", "A, B y C" ('' si no hay ninguno). */
    private static function enumerar(array $nombres): string
    {
        if (count($nombres) < 2) {
            return (string) ($nombres[0] ?? '');
        }

        $ultimo = array_pop($nombres);

        return implode(', ', $nombres) . ' y ' . $ultimo;
    }
}
