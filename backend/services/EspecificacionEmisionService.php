<?php

declare(strict_types=1);

namespace backend\services;

use backend\components\PresupuestoPdfGenerator;
use backend\models\ConfiguracionEspecificacion;
use backend\models\Especificacion;
use backend\models\EspecificacionEmision;
use backend\models\Proyecto;
use backend\models\ProyectoItem;
use DateTimeImmutable;
use DomainException;
use Throwable;
use Yii;

/**
 * Capa de Servicio para las EMISIONES NET: el documento con codigo NET que se
 * firma, se sella y se entrega al proveedor.
 *
 * Reglas de negocio que viven aqui (confirmadas con el Ing. de DTIC):
 *  - enlazar una especificacion a un item de la lista ES emitir: se le asigna
 *    el siguiente NET del correlativo,
 *  - el correlativo lo fija el encargado (siguienteNet de la configuracion),
 *    sube de 1 en 1, no se reinicia por año y un numero nunca se reutiliza,
 *  - una emision no cambia nunca: guarda copias de todo lo que imprime y
 *    apunta a una version que ya no se puede modificar,
 *  - un error se corrige REEMITIENDO: NET nuevo con la ultima version y la
 *    cantidad actual de la lista; la anterior queda "reemplazada por",
 *  - cambiar o quitar la especificacion de un item, o quitar el item, ANULA
 *    su emision vigente (el documento sigue en el historial),
 *  - reemitir se puede mientras el presupuesto no este aprobado ni cancelado,
 *    aunque la lista ya este en cotizacion (no cambia la lista),
 *  - avisos que no bloquean: cantidad distinta de la emitida, emision
 *    vencida, version mas nueva de la ficha.
 */
class EspecificacionEmisionService
{
    private const ARCHIVO_LOGO = 'logo-formulario-net.png';

    /**
     * Por que no se puede emitir la especificacion (null si se puede):
     * falta fijar el NET inicial, o la ficha todavia no tiene contenido.
     * ListaItemsService lo consulta ANTES de guardar, para mostrar el error
     * sin haber tocado nada.
     */
    public function motivoParaNoEmitir(?Especificacion $especificacion): ?string
    {
        if (ConfiguracionEspecificacion::obtener()->siguienteNet === null) {
            return 'todavía no se fijó el código NET inicial. Fíjelo en Especificaciones → Configuración del formulario NET.';
        }

        if ($especificacion === null) {
            return 'la especificación elegida no existe.';
        }

        if ($especificacion->getVersionActual()->one() === null) {
            return "la especificación \"{$especificacion->nombre()}\" todavía no tiene contenido técnico.";
        }

        return null;
    }

    /**
     * Emite el formulario NET de la especificacion enlazada al item, con la
     * version actual de la ficha y el siguiente NET.
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
     */
    public function emitir(ProyectoItem $item, ?string $usuario, array $condiciones = []): EspecificacionEmision
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
            'titulo' => $especificacion->nombre(),
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
            // Provisorio, para validar el resto: el numero real se toma recien
            // dentro de la transaccion, con la fila de configuracion bloqueada.
            'numeroNet' => 0,
        ]);

        if (!$emision->validate()) {
            throw new DomainException('No se pudo emitir el formulario NET: ' . implode(' ', $emision->getErrorSummary(true)));
        }

        $transaccion = Yii::$app->db->beginTransaction();

        try {
            $emision->numeroNet = $this->tomarSiguienteNet();
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
     * Reemite el formulario NET del item: NET nuevo, ultima version de la
     * ficha y cantidad ACTUAL de la lista (si la cantidad estaba mal, se
     * corrige en la lista y se reemite: asi el NET y la lista nunca dicen
     * cosas distintas). Las condiciones de entrega si se pueden cambiar.
     * La emision anterior queda "reemplazada por" la nueva.
     *
     * Si el item todavia no tenia emision vigente (fichas enlazadas antes de
     * que existieran las emisiones), simplemente emite.
     *
     * Devuelve la emision nueva o el motivo por el que no se pudo.
     *
     * @param array{motivo?: mixed, lugarEntrega?: mixed, validezOfertaDias?: mixed, tiempoEntregaDias?: mixed} $datos
     * @return array{emision: ?EspecificacionEmision, error: ?string}
     */
    public function reemitir(ProyectoItem $item, array $datos, ?string $usuario): array
    {
        if (!$this->puedeReemitir($item->proyecto)) {
            return ['emision' => null, 'error' => 'El presupuesto del proyecto ya está aprobado o cancelado: no se emiten más formularios NET.'];
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

            $nueva = $this->emitir($item, $usuario, $datos);

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
                $avisos[] = 'Todavía no tiene formulario NET emitido.';
            } else {
                // La especificacion recien cambiada en el formulario (sin
                // guardar) no corresponde a esta emision: no se compara.
                if ((int) $emision->version->idEspecificacion === (int) $item->idEspecificacion) {
                    if ((float) $emision->cantidad !== (float) $item->cantidad || (string) $emision->unidad !== (string) $item->unidadMedida) {
                        $avisos[] = 'La cantidad de la lista (' . $formato->asDecimal($item->cantidad, 2) . ' ' . $item->unidadMedida
                            . ') ya no coincide con la del ' . $emision->codigoNet() . ' (' . $formato->asDecimal($emision->cantidad, 2) . ' ' . $emision->unidad . '): reemítalo.';
                    }

                    $actual = $fichas[$item->idEspecificacion]->versionActual ?? null;
                    if ($actual !== null && (int) $actual->id !== (int) $emision->idVersion) {
                        $avisos[] = 'Hay una versión más nueva de la ficha (' . $actual->etiqueta() . '); el ' . $emision->codigoNet()
                            . ' usa la ' . $emision->version->etiqueta() . '. Reemítalo si debe llevar los cambios.';
                    }
                }

                if ($emision->estaVencida($hoy)) {
                    $avisos[] = 'El ' . $emision->codigoNet() . ' venció el ' . $emision->fechaVencimiento()->format('d/m/Y') . ': reemítalo antes de pedir cotizaciones.';
                }
            }

            $estado[$item->id] = ['emision' => $emision, 'avisos' => $avisos];
        }

        return $estado;
    }

    /**
     * Guarda la configuracion del formulario NET.
     *
     * REGLA DE NEGOCIO: el siguiente NET tiene que ser MAYOR que el ultimo
     * emitido; si no, se repetirian codigos (la base tambien lo impediria,
     * pero recien al emitir). Una vez fijado no se puede volver a dejar vacio.
     */
    public function guardarConfiguracion(ConfiguracionEspecificacion $configuracion, array $datosFormulario): bool
    {
        $anterior = $configuracion->siguienteNet;

        if (!$configuracion->load($datosFormulario) || !$configuracion->validate()) {
            return false;
        }

        if ($configuracion->siguienteNet === null || $configuracion->siguienteNet === '') {
            if ($anterior !== null) {
                $configuracion->addError('siguienteNet', 'Una vez fijado, el código NET no se puede dejar vacío.');

                return false;
            }
            $configuracion->siguienteNet = null;
        } else {
            $ultimo = $this->ultimoNetEmitido();
            if ($ultimo !== null && (int) $configuracion->siguienteNet <= $ultimo) {
                $configuracion->addError('siguienteNet', "Tiene que ser mayor que el último emitido (NET $ultimo).");

                return false;
            }
        }

        return $configuracion->save(false);
    }

    public function obtener(int $id): ?EspecificacionEmision
    {
        return EspecificacionEmision::find()
            ->with(['version.caracteristicas', 'proyecto'])
            ->andWhere(['id' => $id])
            ->one();
    }

    /**
     * Logo del formulario NET, si se coloco el archivo (no es obligatorio):
     * backend/web/img/logo-formulario-net.png. mPDF lo lee desde el disco.
     */
    public function rutaLogo(): ?string
    {
        $ruta = Yii::getAlias('@backend/web/img/' . self::ARCHIVO_LOGO);

        return is_file($ruta) ? $ruta : null;
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
        $marca = match (true) {
            $emision->estado === EspecificacionEmision::ESTADO_REEMPLAZADA => 'REEMPLAZADO',
            $emision->estado === EspecificacionEmision::ESTADO_ANULADA => 'ANULADO',
            $emision->estaVencida() => 'VENCIDO',
            default => null,
        };

        return [
            'contenido' => (new PresupuestoPdfGenerator())->generarDesdeHtml($html, $marca),
            // "NET_880_26.pdf": el codigo es unico.
            'nombreArchivo' => preg_replace('/[^A-Za-z0-9_-]/', '_', $emision->codigoNet()) . '.pdf',
        ];
    }

    public function ultimoNetEmitido(): ?int
    {
        $maximo = EspecificacionEmision::find()->max('numeroNet');

        return $maximo === null ? null : (int) $maximo;
    }

    /**
     * Toma el siguiente NET y deja el contador en el que sigue, con la fila
     * de configuracion BLOQUEADA hasta que termine la transaccion (UPDLOCK,
     * HOLDLOCK de SQL Server): si dos personas emiten a la vez, la segunda
     * espera a la primera y recibe el numero siguiente, nunca el mismo.
     * Tiene que llamarse dentro de una transaccion.
     */
    private function tomarSiguienteNet(): int
    {
        $db = Yii::$app->db;
        $numero = $db->createCommand(
            'SELECT siguienteNet FROM redes.ConfiguracionEspecificacion WITH (UPDLOCK, HOLDLOCK) WHERE id = 1'
        )->queryScalar();

        if ($numero === null || $numero === false) {
            throw new DomainException('Todavía no se fijó el código NET inicial. Fíjelo en Especificaciones → Configuración del formulario NET.');
        }

        $db->createCommand('UPDATE redes.ConfiguracionEspecificacion SET siguienteNet = siguienteNet + 1 WHERE id = 1')->execute();

        return (int) $numero;
    }

    private function textoONull(mixed $valor): ?string
    {
        $texto = is_scalar($valor) ? trim((string) $valor) : '';

        return $texto === '' ? null : $texto;
    }
}
