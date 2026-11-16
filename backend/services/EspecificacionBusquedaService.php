<?php

declare(strict_types=1);

namespace backend\services;

use backend\components\Busqueda;
use backend\models\Especificacion;
use backend\models\ProyectoItem;
use backend\models\SubcatalogoItem;
use yii\db\Expression;

/**
 * Capa de Servicio para ELEGIR especificaciones de la libreria: los
 * selectores de la lista de items y de las lineas de cotizacion, el buscador
 * de la ventana "Ver detalle" y "Recordar como sugerida para este ítem".
 *
 * Se separo de EspecificacionService (que queda con la ficha en si: crear,
 * editar, versiones, adjunto, eliminar) porque son dos trabajos distintos:
 * aqui solo se LEE la libreria para ofrecerla, alla se modifica.
 *
 * Reglas de negocio que viven aqui:
 *  - los selectores solo ofrecen especificaciones ACTIVAS (una desactivada
 *    sigue mostrandose donde ya estaba enlazada),
 *  - las "sugeridas" para un item son las fichas que lo tienen como item de
 *    referencia, y "Recordar como sugerida" nunca pisa uno ya puesto.
 */
class EspecificacionBusquedaService
{
    /**
     * Maximo de fichas que devuelve buscar(): si hay mas, conviene afinar.
     */
    public const LIMITE_BUSQUEDA = 30;

    /**
     * Nombre del grupo de fichas sugeridas en el selector de la lista.
     */
    public const GRUPO_SUGERIDAS = 'Sugeridas para este ítem';

    /**
     * selector() de una pantalla que no ofrece elegir especificaciones (ej.
     * la lista ya enviada a cotizacion): las mismas claves, sin datos.
     */
    public const SELECTOR_VACIO = ['grupos' => [], 'urls' => [], 'subcatalogos' => [], 'sugeridasPorItem' => []];

    /**
     * Opciones para los selectores de especificacion (lista del proyecto,
     * lineas de cotizacion), en UNA consulta para toda la pantalla:
     *
     *  - grupos: [idCategoria => ['nombre' => ..., 'opciones' => [id => titulo]]]
     *    en el orden de las categorias ("Sin categoría" al final). La vista
     *    lo convierte en <optgroup> y pone primero el de la categoria del item.
     *  - urls: [id => enlace al documento] para el enlace "ver documento".
     *  - subcatalogos: [id => idSubcatalogo] de las fichas con item de
     *    referencia (para saber cuales ya lo tienen).
     *  - sugeridasPorItem: [idSubcatalogo => [id, ...]]: las fichas que se
     *    sugieren para cada item del subcatalogo (REGLA: las que lo tienen
     *    como item de referencia). La usan opcionesPorItem() y el JS de la
     *    lista para las filas nuevas.
     *
     * Cada opcion dice el titulo, el modelo sugerido de la version actual (si
     * el titulo no lo trae ya) y la version: "Switch de 16 puertos — Mod. SG2218 (v2)".
     *
     * Solo especificaciones ACTIVAS, mas las de $incluirIds aunque esten
     * desactivadas: son las que ya estan enlazadas en la pantalla y no deben
     * perderse al volver a guardar.
     *
     * @param int[] $incluirIds
     * @return array{grupos: array<int, array{nombre: string, opciones: array<int, string>}>, urls: array<int, string>, subcatalogos: array<int, int>, sugeridasPorItem: array<int, int[]>}
     */
    public function selector(array $incluirIds = []): array
    {
        $query = Especificacion::find()->with(['categoria', 'versionActual'])->andWhere(['activo' => true]);
        $incluirIds = array_values(array_filter(array_map('intval', $incluirIds)));

        if ($incluirIds !== []) {
            $query->orWhere(['id' => $incluirIds]);
        }

        $especificaciones = $query->all();

        usort($especificaciones, static fn (Especificacion $a, Especificacion $b) =>
            [$a->categoria->orden ?? PHP_INT_MAX, $a->nombre()] <=> [$b->categoria->orden ?? PHP_INT_MAX, $b->nombre()]);

        $grupos = [];
        $urls = [];
        $subcatalogos = [];
        $sugeridasPorItem = [];

        foreach ($especificaciones as $especificacion) {
            $idCategoria = (int) $especificacion->idCategoria;
            $grupos[$idCategoria]['nombre'] ??= $especificacion->categoria->nombre ?? 'Sin categoría';
            $grupos[$idCategoria]['opciones'][$especificacion->id] = $this->textoOpcion($especificacion);

            $url = $especificacion->urlDocumento();
            if ($url !== null) {
                $urls[$especificacion->id] = $url;
            }

            if ($especificacion->idSubcatalogo !== null) {
                $subcatalogos[$especificacion->id] = (int) $especificacion->idSubcatalogo;
                $sugeridasPorItem[(int) $especificacion->idSubcatalogo][] = (int) $especificacion->id;
            }
        }

        return ['grupos' => $grupos, 'urls' => $urls, 'subcatalogos' => $subcatalogos, 'sugeridasPorItem' => $sugeridasPorItem];
    }

    /**
     * Opciones del selector de CADA fila de la lista de items, ya ordenadas:
     *  1. "Sugeridas para este ítem" (ver sugeridasPorItem en selector()),
     *     que se MUEVEN a ese grupo: cada ficha aparece una sola vez,
     *  2. el grupo de la categoria del item,
     *  3. las demas categorias.
     *
     * Devuelve un mapa con clave "idCategoria|idSubcatalogo" (una entrada
     * por combinacion, no por fila) mas la clave '' para la fila modelo de
     * las filas nuevas (sin sugeridas; ahi las ordena el JS). Cada entrada:
     *  - opciones: [nombre del grupo => [id => texto]] para Html::dropDownList(),
     *  - grupos: [nombre del grupo => atributos del <optgroup>].
     *
     * @param array $selector resultado de selector()
     * @param ProyectoItem[] $items
     * @return array<string, array{opciones: array<string, array<int, string>>, grupos: array<string, array<string, int|string>>}>
     */
    public function opcionesPorItem(array $selector, array $items): array
    {
        $mapa = ['' => $this->opcionesDeFila($selector, null, null)];

        foreach ($items as $item) {
            $clave = $item->idCategoria . '|' . $item->idSubcatalogo;
            $mapa[$clave] ??= $this->opcionesDeFila(
                $selector,
                $item->idCategoria !== null ? (int) $item->idCategoria : null,
                $item->idSubcatalogo !== null ? (int) $item->idSubcatalogo : null
            );
        }

        return $mapa;
    }

    /**
     * Ver opcionesPorItem().
     *
     * @return array{opciones: array<string, array<int, string>>, grupos: array<string, array<string, int|string>>}
     */
    private function opcionesDeFila(array $selector, ?int $idCategoria, ?int $idSubcatalogo): array
    {
        $idsSugeridas = array_flip($idSubcatalogo !== null ? ($selector['sugeridasPorItem'][$idSubcatalogo] ?? []) : []);
        $sugeridas = [];
        $primero = [];
        $resto = [];
        $grupos = [];

        foreach ($selector['grupos'] as $idGrupo => $grupo) {
            $opciones = array_diff_key($grupo['opciones'], $idsSugeridas);
            $sugeridas += array_intersect_key($grupo['opciones'], $idsSugeridas);

            if ($opciones === []) {
                continue;
            }

            $grupos[$grupo['nombre']] = ['data-categoria' => $idGrupo];

            if ($idCategoria !== null && (int) $idGrupo === $idCategoria) {
                $primero[$grupo['nombre']] = $opciones;
            } else {
                $resto[$grupo['nombre']] = $opciones;
            }
        }

        if ($sugeridas !== []) {
            $primero = [self::GRUPO_SUGERIDAS => $sugeridas] + $primero;
            $grupos[self::GRUPO_SUGERIDAS] = ['data-sugeridas' => 1];
        }

        return ['opciones' => $primero + $resto, 'grupos' => $grupos];
    }

    /**
     * Texto de una opcion del selector. Muchas fichas tienen titulos casi
     * iguales ("SWITCH 24 PUERTOS"): el modelo sugerido es lo que las
     * distingue. Se omite si el titulo ya lo contiene (pasa con varias
     * fichas importadas del banco de .docx).
     */
    private function textoOpcion(Especificacion $especificacion): string
    {
        $texto = $especificacion->nombre();
        $version = $especificacion->versionActual;
        $modelo = trim((string) ($version->modeloSugerido ?? ''));

        if ($modelo !== '' && mb_stripos($texto, $modelo, 0, 'UTF-8') === false) {
            $texto .= ' — Mod. ' . $modelo;
        }

        return $texto
            . ($version !== null ? ' (' . $version->etiqueta() . ')' : '')
            . ($especificacion->activo ? '' : ' — inactiva');
    }

    /**
     * Busqueda de la ventana "Ver detalle" de la lista de items: fichas
     * ACTIVAS que contienen TODAS las palabras, en cualquier orden (misma
     * regla que el buscador de SIGMA del subcatalogo), en el titulo, el
     * modelo sugerido o las caracteristicas (nombre o valor) de su version
     * actual. Asi "24 poe" encuentra un switch PoE aunque el titulo no lo diga.
     *
     * COLLATE Modern_Spanish_CI_AI: la base es Modern_Spanish_CI_AS, que
     * distingue tildes ("camara" no encontraria "Cámara"). AI = accent
     * insensitive, solo para esta comparacion.
     *
     * Devuelve como maximo LIMITE_BUSQUEDA fichas:
     * [['id' => 14, 'texto' => 'SWITCH ... (v1)', 'conReferencia' => true], ...]
     * (conReferencia: si ya tiene item de referencia; la ventana ofrece
     * "Recordar como sugerida" solo en las que no)
     *
     * @return array<int, array{id: int, texto: string, conReferencia: bool}>
     */
    public function buscar(string $texto): array
    {
        // A lo sumo Busqueda::MAX_PALABRAS (SQL Server acepta 2100 parametros; aqui van 6 por palabra).
        $palabras = Busqueda::palabras($texto);

        if ($palabras === []) {
            return [];
        }

        $query = Especificacion::find()
            ->alias('e')
            ->with(['versionActual'])
            ->andWhere(['e.activo' => true]);

        // Relevancia: cada palabra suma 2 si esta en el titulo y 1 si esta en
        // el modelo sugerido. Las fichas que solo coinciden por sus
        // caracteristicas (ej. una antena que menciona PoE) quedan al final.
        $puntaje = [];
        $paramsPuntaje = [];

        foreach ($palabras as $i => $palabra) {
            // Los comodines de LIKE escritos por el usuario se buscan tal cual.
            $patron = '%' . strtr($palabra, ['[' => '[[]', '%' => '[%]', '_' => '[_]']) . '%';
            // Un parametro por cada uso: el driver de SQL Server no deja
            // repetir el mismo nombre de parametro en una consulta.
            $p = array_map(static fn (int $n) => ":p{$i}_{$n}", range(1, 6));

            $puntaje[] = "CASE WHEN e.titulo COLLATE Modern_Spanish_CI_AI LIKE {$p[4]} THEN 2 ELSE 0 END"
                . " + CASE WHEN EXISTS (SELECT 1 FROM redes.EspecificacionVersion vm WHERE vm.idEspecificacion = e.id"
                . " AND vm.numero = (SELECT MAX(vm2.numero) FROM redes.EspecificacionVersion vm2 WHERE vm2.idEspecificacion = e.id)"
                . " AND vm.modeloSugerido COLLATE Modern_Spanish_CI_AI LIKE {$p[5]}) THEN 1 ELSE 0 END";
            $paramsPuntaje[$p[4]] = $patron;
            $paramsPuntaje[$p[5]] = $patron;
            $p = array_slice($p, 0, 4);

            $query->andWhere(
                "(e.titulo COLLATE Modern_Spanish_CI_AI LIKE {$p[0]}
                  OR EXISTS (
                      SELECT 1 FROM redes.EspecificacionVersion v
                      WHERE v.idEspecificacion = e.id
                        AND v.numero = (SELECT MAX(v2.numero) FROM redes.EspecificacionVersion v2 WHERE v2.idEspecificacion = e.id)
                        AND (v.modeloSugerido COLLATE Modern_Spanish_CI_AI LIKE {$p[1]}
                             OR EXISTS (
                                 SELECT 1 FROM redes.EspecificacionCaracteristica c
                                 WHERE c.idVersion = v.id
                                   AND (c.nombre COLLATE Modern_Spanish_CI_AI LIKE {$p[2]}
                                        OR c.valor COLLATE Modern_Spanish_CI_AI LIKE {$p[3]})))))",
                array_fill_keys($p, $patron)
            );
        }

        $fichas = $query
            ->orderBy([
                // Expression: SQL escrito a mano dentro del ORDER BY, con sus parametros.
                'puntaje' => new Expression('(' . implode(' + ', $puntaje) . ') DESC', $paramsPuntaje),
                'e.titulo' => SORT_ASC,
            ])
            ->limit(self::LIMITE_BUSQUEDA)
            ->all();

        return array_map(fn (Especificacion $especificacion) => [
            'id' => (int) $especificacion->id,
            'texto' => $this->textoOpcion($especificacion),
            'conReferencia' => $especificacion->idSubcatalogo !== null,
        ], $fichas);
    }

    /**
     * "Recordar como sugerida para este ítem" (ventana "Ver detalle" de la
     * lista): pone $idSubcatalogo como item de referencia de la ficha, SOLO
     * si todavia no tiene uno (nunca pisa uno ya puesto) y si el item esta
     * ACTIVO (la misma regla que el formulario de la ficha). No crea version:
     * el item de referencia es dato de la ficha, no del contenido tecnico.
     */
    public function recordarItemDeReferencia(int $idEspecificacion, int $idSubcatalogo): void
    {
        if (!SubcatalogoItem::find()->andWhere(['id' => $idSubcatalogo, 'activo' => true])->exists()) {
            return;
        }

        Especificacion::updateAll(
            ['idSubcatalogo' => $idSubcatalogo],
            ['id' => $idEspecificacion, 'idSubcatalogo' => null]
        );
    }

    /**
     * Ids de las especificaciones ACTIVAS: las unicas que se pueden enlazar
     * de nuevo (una desactivada solo se conserva donde ya estaba).
     *
     * @return array<int, true>
     */
    public function idsActivas(): array
    {
        $ids = Especificacion::find()->select('id')->andWhere(['activo' => true])->column();

        return array_fill_keys(array_map('intval', $ids), true);
    }
}
