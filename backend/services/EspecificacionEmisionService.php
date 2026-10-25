<?php

declare(strict_types=1);

namespace backend\services;

use backend\components\PresupuestoPdfGenerator;
use backend\models\ConfiguracionEspecificacion;
use backend\models\CorrelativoDocumento;
use backend\models\Especificacion;
use backend\models\EspecificacionEmision;
use backend\models\Proyecto;
use backend\models\ProyectoItem;
use DateTimeImmutable;
use DomainException;
use Throwable;
use Yii;
use yii\data\ActiveDataProvider;

/**
 * Capa de Servicio para las EMISIONES: el formulario de especificacion
 * tecnica con codigo RD-PI-ET-001 (antes del 2026-10-22, NET:880/26) que se
 * firma, se sella y se entrega al proveedor.
 *
 * Reglas de negocio que viven aqui (confirmadas con el Ing. de DTIC):
 *  - enlazar una especificacion a un item de la lista ES emitir: se le asigna
 *    el siguiente numero del correlativo ET (CodificacionService),
 *  - el correlativo lo fija el encargado (Codificación de documentos), sube
 *    de 1 en 1, no se reinicia por año y un numero nunca se reutiliza,
 *  - una emision no cambia nunca: guarda copias de todo lo que imprime y
 *    apunta a una version que ya no se puede modificar,
 *  - un error se corrige REEMITIENDO: el MISMO codigo con la version del
 *    documento siguiente (RD-PI-ET-015 v01 -> v02, lineamiento RD-GD-LI-001,
 *    4.2), la ultima version de la ficha y la cantidad actual de la lista; la
 *    anterior queda "reemplazada por",
 *  - cambiar o quitar la especificacion de un item, o quitar el item, ANULA
 *    su emision vigente (el documento sigue en el historial),
 *  - reemitir se puede mientras el presupuesto no este aprobado ni cancelado,
 *    aunque la lista ya este en cotizacion (no cambia la lista),
 *  - avisos que no bloquean: cantidad distinta de la emitida, emision
 *    vencida, version mas nueva de la ficha.
 */
class EspecificacionEmisionService
{
    /**
     * Por que no se puede emitir la especificacion (null si se puede): la
     * ficha no existe o todavia no tiene contenido.
     * ListaItemsService lo consulta ANTES de guardar, para mostrar el error
     * sin haber tocado nada.
     */
    public function motivoParaNoEmitir(?Especificacion $especificacion): ?string
    {
        if ($especificacion === null) {
            return 'la especificación elegida no existe.';
        }

        if ($especificacion->getVersionActual()->one() === null) {
            return "la especificación \"{$especificacion->nombre()}\" todavía no tiene contenido técnico.";
        }

        return null;
    }

    /**
     * Emite el formulario de la especificacion enlazada al item, con la
     * version actual de la ficha y el siguiente codigo ET.
     *
     * Con $corrige (lo usa reemitir()): es la version siguiente de ESE
     * documento, con su mismo codigo; no se toma numero del correlativo.
     *
     * $condiciones puede traer lugarEntrega, validezOfertaDias y
     * tiempoEntregaDias; lo que no venga sale de la configuracion (y el lugar,
     * de la facultad del proyecto).
     *
     * Lanza DomainException con un mensaje para el usuario si no se puede.
     * Usa su propia transaccion; dentro de otra (la de guardar la lista) se
     * anida sin problema.
     *
     * @param array{lugarEntrega?: ?string, validezOfertaDias?: mixed, tiempoEntregaDias?: mixed} $condiciones
     * @param EspecificacionEmision|null $corrige la emision que se reemite
     */
    public function emitir(ProyectoItem $item, ?string $usuario, array $condiciones = [], ?EspecificacionEmision $corrige = null): EspecificacionEmision
    {
        $especificacion = $item->idEspecificacion !== null ? Especificacion::findOne($item->idEspecificacion) : null;
        $motivo = $this->motivoParaNoEmitir($especificacion);

        if ($motivo !== null) {
            throw new DomainException(ucfirst($motivo));
        }

        $version = $especificacion->getVersionActual()->one();
        $configuracion = ConfiguracionEspecificacion::obtener();
        $proyecto = $item->proyecto;
        $hoy = new DateTimeImmutable('today');

        $emision = new EspecificacionEmision([
            'anio' => (int) $hoy->format('y'),
            'idVersion' => $version->id,
            'idProyecto' => $item->idProyecto,
            'idProyectoItem' => $item->id,
            // El bien se nombra como en la lista (nombre DTIC del item), no
            // con el titulo de la ficha: una misma ficha sirve a items con
            // nombres distintos. El titulo de la ficha solo si no hay item.
            'titulo' => $item->subcatalogoItem->descripcion ?? $especificacion->nombre(),
            'cantidad' => $item->cantidad,
            'unidad' => $item->unidadMedida,
            'dependencia' => $proyecto->facultad,
            'lugarEntrega' => $this->textoONull($condiciones['lugarEntrega'] ?? null) ?? $proyecto->facultad,
            'validezOfertaDias' => $this->textoONull($condiciones['validezOfertaDias'] ?? null) ?? $configuracion->validezOfertaDias,
            'tiempoEntregaDias' => $this->textoONull($condiciones['tiempoEntregaDias'] ?? null) ?? $configuracion->tiempoEntregaDias,
            'elaboradoPor' => $this->textoONull($usuario),
            'textoCabecera' => $configuracion->textoCabecera,
            'textoInstrucciones' => $configuracion->textoInstrucciones,
            'textoNotaFinal' => $configuracion->textoNotaFinal,
            'garantia' => $version->garantia ?? $configuracion->garantiaPorDefecto,
            'fechaEmision' => $hoy->format('Y-m-d'),
            'vigenciaDias' => $configuracion->vigenciaDias,
            'estado' => EspecificacionEmision::ESTADO_VIGENTE,
            'serie' => EspecificacionEmision::SERIE_ET,
            // Provisorio, para validar el resto: el numero real se toma recien
            // dentro de la transaccion, con la fila del correlativo bloqueada.
            'numeroNet' => 0,
            'versionDocumento' => 1,
        ]);

        if ($corrige !== null) {
            // Mismo documento: mismo codigo y la version siguiente. Una NET
            // de las de antes conserva tambien su año, que es parte del codigo.
            $emision->serie = $corrige->serie;
            $emision->numeroNet = $corrige->numeroNet;
            $emision->versionDocumento = (int) $corrige->versionDocumento + 1;
            if ($corrige->serie === EspecificacionEmision::SERIE_NET) {
                $emision->anio = $corrige->anio;
            }
            $emision->codigoAnterior = $corrige->codigoAnterior;
        }

        // Control de elaboracion: tambien una copia, como los textos.
        foreach (EspecificacionEmision::atributosFirmas() as $atributo) {
            $emision->$atributo = $configuracion->$atributo;
        }

        if (!$emision->validate()) {
            throw new DomainException('No se pudo emitir el formulario de especificación: ' . implode(' ', $emision->getErrorSummary(true)));
        }

        $transaccion = Yii::$app->db->beginTransaction();

        try {
            if ($corrige === null) {
                $emision->numeroNet = (new CodificacionService())->tomar(CorrelativoDocumento::TIPO_ESPECIFICACION);
            }
            // Si otro usuario reemitio el mismo documento al mismo tiempo, el
            // indice unico (serie, numeroNet, versionDocumento) lo rechaza.
            $emision->save(false);
            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return $emision;
    }

    /**
     * Anula la emision vigente del item (si tiene). El documento no se borra:
     * queda en el historial con su motivo.
     */
    public function anularVigente(int $idProyectoItem, string $motivo): void
    {
        EspecificacionEmision::updateAll(
            ['estado' => EspecificacionEmision::ESTADO_ANULADA, 'motivo' => $motivo],
            ['idProyectoItem' => $idProyectoItem, 'estado' => EspecificacionEmision::ESTADO_VIGENTE]
        );
    }

    /**
     * Reemite el formulario del item: el MISMO codigo con la version del
     * documento siguiente (v01 -> v02), ultima version de la ficha y
     * cantidad ACTUAL de la lista (si la cantidad estaba mal, se
     * corrige en la lista y se reemite: asi el formulario y la lista nunca dicen
     * cosas distintas). Las condiciones de entrega si se pueden cambiar.
     * La emision anterior queda "reemplazada por" la nueva.
     *
     * Si el item todavia no tenia emision vigente (fichas enlazadas antes de
     * que existieran las emisiones), simplemente emite (codigo nuevo, v01).
     *
     * Devuelve la emision nueva o el motivo por el que no se pudo.
     *
     * @param array{motivo?: mixed, lugarEntrega?: mixed, validezOfertaDias?: mixed, tiempoEntregaDias?: mixed} $datos
     * @return array{emision: ?EspecificacionEmision, error: ?string}
     */
    public function reemitir(ProyectoItem $item, array $datos, ?string $usuario): array
    {
        if (!$this->puedeReemitir($item->proyecto)) {
            return ['emision' => null, 'error' => 'El presupuesto del proyecto ya está aprobado o cancelado: no se emiten más formularios de especificación.'];
        }

        if ($item->idEspecificacion === null) {
            return ['emision' => null, 'error' => 'El ítem no tiene especificación técnica enlazada.'];
        }

        $anterior = $item->getEmisionVigente()->one();
        $motivo = $this->textoONull($datos['motivo'] ?? null);

        if ($anterior !== null && $motivo === null) {
            return ['emision' => null, 'error' => 'Escriba el motivo de la nueva emisión (ej. "facultad equivocada", "venció").'];
        }

        if ($motivo !== null && mb_strlen($motivo) > 300) {
            return ['emision' => null, 'error' => 'El motivo no puede pasar de 300 caracteres.'];
        }

        $transaccion = Yii::$app->db->beginTransaction();

        try {
            // Primero se libera la anterior: el indice unico permite UNA
            // emision vigente por item.
            if ($anterior !== null) {
                $anterior->updateAttributes(['estado' => EspecificacionEmision::ESTADO_REEMPLAZADA, 'motivo' => $motivo]);
            }

            $nueva = $this->emitir($item, $usuario, $datos, $anterior);

            if ($anterior !== null) {
                $anterior->updateAttributes(['idReemplazadaPor' => $nueva->id]);
            }

            $transaccion->commit();
        } catch (DomainException $e) {
            $transaccion->rollBack();

            return ['emision' => null, 'error' => $e->getMessage()];
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return ['emision' => $nueva, 'error' => null];
    }

    /**
     * Mientras el presupuesto no este aprobado ni cancelado (aunque la lista
     * ya este en cotizacion: reemitir no cambia la lista).
     */
    public function puedeReemitir(Proyecto $proyecto): bool
    {
        return $proyecto->presupuesto === null || $proyecto->presupuesto->puedeEditarse();
    }

    /**
     * Para la pantalla de la lista: por cada item con especificacion, su
     * emision vigente (o null) y los avisos. Todo en pocas consultas, no una
     * por item.
     *
     * @param ProyectoItem[] $items
     * @return array<int, array{emision: ?EspecificacionEmision, avisos: string[]}> indexado por id del item
     */
    public function estadoPorItem(array $items, ?DateTimeImmutable $hoy = null): array
    {
        $conEspecificacion = array_filter($items, static fn (ProyectoItem $i) => $i->id !== null && $i->idEspecificacion !== null);

        if ($conEspecificacion === []) {
            return [];
        }

        $ids = array_map(static fn (ProyectoItem $i) => $i->id, $conEspecificacion);
        $vigentes = EspecificacionEmision::find()
            ->with('version')
            ->andWhere(['idProyectoItem' => $ids, 'estado' => EspecificacionEmision::ESTADO_VIGENTE])
            ->indexBy('idProyectoItem')
            ->all();

        $fichas = Especificacion::find()
            ->with('versionActual')
            ->andWhere(['id' => array_unique(array_map(static fn (ProyectoItem $i) => $i->idEspecificacion, $conEspecificacion))])
            ->indexBy('id')
            ->all();

        $formato = Yii::$app->formatter;
        $estado = [];

        foreach ($conEspecificacion as $item) {
            $emision = $vigentes[$item->id] ?? null;
            $avisos = [];

            if ($emision === null) {
                $avisos[] = 'Todavía no tiene formulario de especificación emitido.';
            } else {
                // La especificacion recien cambiada en el formulario (sin
                // guardar) no corresponde a esta emision: no se compara.
                if ((int) $emision->version->idEspecificacion === (int) $item->idEspecificacion) {
                    if ((float) $emision->cantidad !== (float) $item->cantidad || (string) $emision->unidad !== (string) $item->unidadMedida) {
                        $avisos[] = 'La cantidad de la lista (' . $formato->asDecimal($item->cantidad, 2) . ' ' . $item->unidadMedida
                            . ') ya no coincide con la del ' . $emision->codigo() . ' (' . $formato->asDecimal($emision->cantidad, 2) . ' ' . $emision->unidad . '): reemítalo.';
                    }

                    $actual = $fichas[$item->idEspecificacion]->versionActual ?? null;
                    if ($actual !== null && (int) $actual->id !== (int) $emision->idVersion) {
                        $avisos[] = 'Hay una versión más nueva de la ficha (' . $actual->etiqueta() . '); el ' . $emision->codigo()
                            . ' usa la ' . $emision->version->etiqueta() . '. Reemítalo si debe llevar los cambios.';
                    }
                }

                if ($emision->estaVencida($hoy)) {
                    $avisos[] = 'El ' . $emision->codigo() . ' venció el ' . $emision->fechaVencimiento()->format('d/m/Y') . ': reemítalo antes de pedir cotizaciones.';
                }
            }

            $estado[$item->id] = ['emision' => $emision, 'avisos' => $avisos];
        }

        return $estado;
    }

    /**
     * Guarda la configuracion del formulario (textos y valores por defecto).
     * El correlativo se fija aparte, en CodificacionService.
     */
    public function guardarConfiguracion(ConfiguracionEspecificacion $configuracion, array $datosFormulario): bool
    {
        return $configuracion->load($datosFormulario) && $configuracion->save();
    }

    /**
     * Registro de todas las emisiones (reemplaza el Excel "1000
     * Especificaciones" de DTIC), de la mas nueva a la mas antigua.
     *
     * Filtros (todos opcionales):
     *  - anio: dos digitos (26),
     *  - estado: vigente | vencida | reemplazada | anulada. "Vencida" no se
     *    guarda: es una vigente cuya fecha + vigencia ya paso, y "vigente"
     *    aqui son solo las que todavia no vencieron,
     *  - dependencia: texto exacto,
     *  - buscar: "RD-PI-ET-007" o "NET:880/26" busca ese codigo, todas sus
     *    versiones ("RD-PI-ET-007 v02": solo esa version) (el NET
     *    tambien en el codigo anterior de las que se pasaron a ET); un numero
     *    solo ("7", "880") lo busca en los dos; cualquier otro texto busca en
     *    el titulo y en el codigo del proyecto.
     *
     * @param array{anio?: string, estado?: string, dependencia?: string, buscar?: string} $filtros
     * @param int|false $porPagina false = todas (para exportar)
     */
    public function registro(array $filtros = [], int|false $porPagina = 25): ActiveDataProvider
    {
        $query = EspecificacionEmision::find()
            ->alias('e')
            ->joinWith('proyecto p', false)
            ->with(['version', 'proyecto', 'proyectoItem.subcatalogoItem', 'reemplazadaPor'])
            // Por orden de emision: el numero no sirve, porque la serie ET
            // empezo en 1 despues de los NET.
            ->orderBy(['e.id' => SORT_DESC]);

        $anio = trim((string) ($filtros['anio'] ?? ''));
        if (ctype_digit($anio)) {
            $query->andWhere(['e.anio' => (int) $anio]);
        }

        // Vencida = fecha de emision + vigencia ya paso (calculado en SQL Server).
        $vencida = 'DATEADD(day, e.vigenciaDias, e.fechaEmision) < CAST(GETDATE() AS date)';
        switch ((string) ($filtros['estado'] ?? '')) {
            case 'vigente':
                $query->andWhere(['e.estado' => EspecificacionEmision::ESTADO_VIGENTE])->andWhere("NOT ($vencida)");
                break;
            case 'vencida':
                $query->andWhere(['e.estado' => EspecificacionEmision::ESTADO_VIGENTE])->andWhere($vencida);
                break;
            case EspecificacionEmision::ESTADO_REEMPLAZADA:
            case EspecificacionEmision::ESTADO_ANULADA:
                $query->andWhere(['e.estado' => $filtros['estado']]);
                break;
        }

        $dependencia = trim((string) ($filtros['dependencia'] ?? ''));
        if ($dependencia !== '') {
            $query->andWhere(['e.dependencia' => $dependencia]);
        }

        $buscar = trim((string) ($filtros['buscar'] ?? ''));
        // "RD-PI-ET-007 v02", como lo muestran las pantallas.
        if (preg_match('/^(.+?)\s+v(\d+)$/i', $buscar, $conVersion) === 1
            && CorrelativoDocumento::numeroDe(CorrelativoDocumento::TIPO_ESPECIFICACION, mb_strtoupper($conVersion[1])) !== null) {
            $buscar = $conVersion[1];
            $query->andWhere(['e.versionDocumento' => (int) $conVersion[2]]);
        }
        $numeroEt = CorrelativoDocumento::numeroDe(CorrelativoDocumento::TIPO_ESPECIFICACION, mb_strtoupper($buscar));
        if ($numeroEt !== null) {
            $query->andWhere(['e.serie' => EspecificacionEmision::SERIE_ET, 'e.numeroNet' => $numeroEt]);
        } elseif (preg_match('/^(NET:\s*)?(\d+)(?:\/(\d{2}))?$/i', $buscar, $m) === 1) {
            // Un NET que siga como NET, o una ya pasada a ET con ese codigo anterior.
            $numero = (int) $m[2];
            $anio = isset($m[3]) ? (int) $m[3] : null;
            $net = ['and', ['e.serie' => EspecificacionEmision::SERIE_NET, 'e.numeroNet' => $numero]];
            if ($anio !== null) {
                $net[] = ['e.anio' => $anio];
            }
            $anterior = ['like', 'e.codigoAnterior', sprintf('NET:%03d/', $numero) . ($anio !== null ? sprintf('%02d', $anio) : '%'), false];
            $condiciones = ['or', $net, $anterior];
            // Un numero solo tambien puede ser el de una ET.
            if ($m[1] === '' && $anio === null) {
                $condiciones[] = ['e.serie' => EspecificacionEmision::SERIE_ET, 'e.numeroNet' => $numero];
            }
            $query->andWhere($condiciones);
        } elseif ($buscar !== '') {
            $query->andWhere(['or', ['like', 'e.titulo', $buscar], ['like', 'p.codigo', $buscar]]);
        }

        return new ActiveDataProvider([
            'query' => $query,
            'pagination' => $porPagina === false ? false : ['pageSize' => $porPagina],
            // El orden lo fija la consulta (de la mas nueva a la mas antigua).
            'sort' => false,
        ]);
    }

    /**
     * Opciones de los filtros del registro: los años y las dependencias que
     * tienen emisiones.
     *
     * @return array{anios: array<int, string>, dependencias: array<string, string>}
     */
    public function opcionesRegistro(): array
    {
        $anios = EspecificacionEmision::find()->select('anio')->distinct()->orderBy(['anio' => SORT_DESC])->column();
        $dependencias = EspecificacionEmision::find()
            ->select('dependencia')
            ->distinct()
            ->andWhere(['not', ['dependencia' => null]])
            ->orderBy(['dependencia' => SORT_ASC])
            ->column();

        return [
            'anios' => array_combine(array_map('intval', $anios), array_map(static fn ($a) => '20' . sprintf('%02d', $a), $anios)) ?: [],
            'dependencias' => array_combine($dependencias, $dependencias) ?: [],
        ];
    }

    /**
     * El registro filtrado como CSV para abrir en Excel: separado por ";" y
     * con BOM UTF-8, que es lo que Excel en español espera para mostrar bien
     * los acentos.
     *
     * @param array{anio?: string, estado?: string, dependencia?: string, buscar?: string} $filtros
     */
    public function exportarCsv(array $filtros): string
    {
        $formato = Yii::$app->formatter;
        $salida = fopen('php://temp', 'r+');
        fwrite($salida, "\xEF\xBB\xBF");
        fputcsv($salida, ['Código', 'Versión', 'Código anterior', 'Fecha de emisión', 'Dependencia', 'Especificación', 'Versión de la ficha', 'Ítem de la lista', 'Cantidad', 'Unidad', 'Modelo sugerido', 'Proyecto', 'Estado', 'Motivo', 'Reemplazada por', 'Elaborado por'], ';');

        foreach ($this->registro($filtros, false)->getModels() as $emision) {
            /** @var EspecificacionEmision $emision */
            fputcsv($salida, [
                $emision->codigo(),
                $emision->versionTexto(),
                $emision->codigoAnterior,
                $formato->asDate($emision->fechaEmision, 'php:d/m/Y'),
                $emision->dependencia,
                $emision->titulo,
                $emision->version->etiqueta(),
                $emision->proyectoItem->subcatalogoItem->descripcion ?? '',
                $formato->asDecimal($emision->cantidad, 2),
                $emision->unidad,
                $emision->version->modeloSugerido,
                $emision->proyecto->codigo ?? '',
                $emision->estadoLabel(),
                $emision->motivo,
                $emision->reemplazadaPor?->codigoConVersion() ?? '',
                $emision->elaboradoPor,
            ], ';');
        }

        rewind($salida);
        $csv = stream_get_contents($salida);
        fclose($salida);

        return (string) $csv;
    }

    public function obtener(int $id): ?EspecificacionEmision
    {
        return EspecificacionEmision::find()
            ->with(['version.caracteristicas', 'proyecto'])
            ->andWhere(['id' => $id])
            ->one();
    }

    /**
     * Convierte el HTML del formulario (vista especificacion/formulario-pdf)
     * en el PDF. Una emision que ya no sirve lleva una marca de agua para que
     * una copia impresa no se confunda con la vigente: REEMPLAZADO, ANULADO o
     * VENCIDO.
     *
     * @return array{contenido: string, nombreArchivo: string}
     */
    public function generarPdf(EspecificacionEmision $emision, string $html): array
    {
        return [
            'contenido' => (new PresupuestoPdfGenerator())->generarDesdeHtml($html, $this->marcaDeAgua($emision)),
            // "RD-PI-ET-007_v02.pdf" (o "NET_880_26_v01.pdf"): codigo + version es unico.
            'nombreArchivo' => preg_replace('/[^A-Za-z0-9_-]/', '_', $emision->codigoConVersion()) . '.pdf',
        ];
    }

    /**
     * Formularios de especificación VIGENTES de la lista del proyecto, en el orden de la
     * lista (por clasificacion). Los anulados y reemplazados no entran: no se
     * entregan. Un vigente ya vencido si entra, con su marca VENCIDO.
     *
     * @return EspecificacionEmision[]
     */
    public function vigentesDelProyecto(Proyecto $proyecto): array
    {
        $emisiones = [];

        foreach ((new ListaItemsService())->agrupar($proyecto) as $grupo) {
            foreach ($grupo['items'] as $item) {
                if ($item->emisionVigente !== null) {
                    $emisiones[] = $item->emisionVigente;
                }
            }
        }

        return $emisiones;
    }

    /**
     * Todos los formularios del proyecto en UN PDF, cada uno desde una hoja
     * nueva y con su marca de agua. $formularios: el HTML de cada emision (la
     * vista especificacion/formulario-pdf la arma el controlador).
     *
     * @param array<int, array{emision: EspecificacionEmision, html: string}> $formularios
     * @return array{contenido: string, nombreArchivo: string}
     */
    public function generarPdfDelProyecto(Proyecto $proyecto, array $formularios): array
    {
        return [
            'contenido' => (new PresupuestoPdfGenerator())->generarVarios($this->documentosPdf($formularios)),
            'nombreArchivo' => 'especificaciones_' . preg_replace('/[^A-Za-z0-9_-]/', '_', $proyecto->codigo ?: (string) $proyecto->id) . '.pdf',
        ];
    }

    /**
     * Los formularios como documentos de PresupuestoPdfGenerator::generarVarios(),
     * cada uno con su marca de agua. Lo usan el PDF de todos los formularios
     * del proyecto y el de la solicitud de cotizacion (que los lleva al final).
     *
     * @param array<int, array{emision: EspecificacionEmision, html: string}> $formularios
     * @return array<int, array{html: string, marcaDeAgua: ?string}>
     */
    public function documentosPdf(array $formularios): array
    {
        return array_map(fn (array $f) => [
            'html' => $f['html'],
            'marcaDeAgua' => $this->marcaDeAgua($f['emision']),
        ], $formularios);
    }

    /**
     * Marca de agua de una emision que ya no sirve, para que una copia
     * impresa no se confunda con la vigente. null = vigente.
     */
    private function marcaDeAgua(EspecificacionEmision $emision): ?string
    {
        return match (true) {
            $emision->estado === EspecificacionEmision::ESTADO_REEMPLAZADA => 'REEMPLAZADO',
            $emision->estado === EspecificacionEmision::ESTADO_ANULADA => 'ANULADO',
            $emision->estaVencida() => 'VENCIDO',
            default => null,
        };
    }

    private function textoONull(mixed $valor): ?string
    {
        $texto = is_scalar($valor) ? trim((string) $valor) : '';

        return $texto === '' ? null : $texto;
    }
}
