<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\CorrelativoDocumento;
use backend\models\EspecificacionEmision;
use backend\models\Presupuesto;
use backend\models\Proyecto;
use backend\models\SolicitudCotizacion;
use DateTimeImmutable;
use DomainException;
use RuntimeException;
use Throwable;
use Yii;

/**
 * Capa de Servicio para la codificacion de los documentos segun el
 * lineamiento de la DTIC RD-GD-LI-001 (seccion 8.2). El formato del codigo
 * esta en CorrelativoDocumento::codigo().
 *
 * Reglas de negocio que viven aqui:
 *  - el sistema asigna el numero solo; el encargado fija desde cual sigue
 *    (pantalla "Codificación de documentos"),
 *  - sube de 1 en 1 y un numero nunca se reutiliza (el mismo numero en
 *    procesos distintos tampoco: RD-PI-PR-016 y RD-GR-PR-016 no conviven),
 *  - las especificaciones (ET) vuelven a 1 cada 1 de enero, porque llevan
 *    el año en el codigo (RD-PI-ET-001/27); proyectos y presupuestos no,
 *  - el siguiente solo puede ser MAYOR que el ultimo usado de su tipo (en
 *    ET, el ultimo del año).
 */
class CodificacionService
{
    /**
     * Dos digitos del año de hoy (26), segun el calendario de Bolivia: la
     * zona horaria de la aplicacion es America/La_Paz, no la del servidor.
     */
    public function anioActual(): int
    {
        return (int) (new DateTimeImmutable('now'))->format('y');
    }

    /**
     * Toma el siguiente numero del tipo y deja el contador en el que sigue,
     * con la fila BLOQUEADA hasta que termine la transaccion (UPDLOCK,
     * HOLDLOCK de SQL Server): si dos personas guardan a la vez, la segunda
     * espera a la primera y recibe el numero siguiente, nunca el mismo.
     * Tiene que llamarse dentro de una transaccion.
     *
     * En ET, $anio es el año del documento (por defecto el de hoy): si el
     * contador es de otro año, el numero vuelve a 1 (el primero que se
     * emite desde el 1 de enero es el 001/27).
     */
    public function tomar(string $tipo, ?int $anio = null): int
    {
        $db = Yii::$app->db;
        $fila = $db->createCommand(
            'SELECT siguiente, anio FROM redes.CorrelativoDocumento WITH (UPDLOCK, HOLDLOCK) WHERE tipo = :tipo',
            [':tipo' => $tipo]
        )->queryOne();

        if ($fila === false) {
            throw new RuntimeException("Falta el correlativo \"$tipo\" en redes.CorrelativoDocumento. Ejecute \"php yii migrate\".");
        }

        $numero = (int) $fila['siguiente'];

        if (!CorrelativoDocumento::reiniciaPorAnio($tipo)) {
            $db->createCommand('UPDATE redes.CorrelativoDocumento SET siguiente = siguiente + 1 WHERE tipo = :tipo', [':tipo' => $tipo])->execute();

            return $numero;
        }

        $anio ??= $this->anioActual();
        if ($fila['anio'] === null || (int) $fila['anio'] !== $anio) {
            $numero = 1;
        }

        $db->createCommand(
            'UPDATE redes.CorrelativoDocumento SET siguiente = :siguiente, anio = :anio WHERE tipo = :tipo',
            [':siguiente' => $numero + 1, ':anio' => $anio, ':tipo' => $tipo]
        )->execute();

        return $numero;
    }

    /**
     * Los correlativos, en el orden de CorrelativoDocumento::tipos().
     *
     * @return CorrelativoDocumento[] indexados por tipo
     */
    public function correlativos(): array
    {
        $filas = CorrelativoDocumento::find()->indexBy('tipo')->all();
        $ordenados = [];

        foreach (array_keys(CorrelativoDocumento::tipos()) as $tipo) {
            if (!isset($filas[$tipo])) {
                throw new RuntimeException("Falta el correlativo \"$tipo\" en redes.CorrelativoDocumento. Ejecute \"php yii migrate\".");
            }
            $ordenados[$tipo] = $filas[$tipo];
        }

        // ET de un año que ya paso: lo que sigue es el 001 de este año
        // (todavia no se guarda; lo hace tomar() o guardar()).
        $especificacion = $ordenados[CorrelativoDocumento::TIPO_ESPECIFICACION];
        $anio = $this->anioActual();
        if ($especificacion->anio === null || (int) $especificacion->anio !== $anio) {
            $especificacion->siguiente = 1;
            $especificacion->anio = $anio;
        }

        return $ordenados;
    }

    /**
     * El mayor numero ya usado de un tipo (null si todavia no hay ninguno),
     * con cualquier proceso. En ET, el mayor de ESTE año. Solo cuentan los
     * codigos con el formato del lineamiento: los numeros de presupuesto
     * escritos a mano antes ("690") y los NET no son de esta serie.
     */
    public function ultimoUsado(string $tipo): ?int
    {
        if ($tipo === CorrelativoDocumento::TIPO_ESPECIFICACION) {
            $maximo = EspecificacionEmision::find()
                ->where(['serie' => EspecificacionEmision::SERIE_ET, 'anio' => $this->anioActual()])
                ->max('numeroNet');

            return $maximo === null ? null : (int) $maximo;
        }

        if ($tipo === CorrelativoDocumento::TIPO_NUMERO_PROYECTO) {
            $maximo = Proyecto::find()->max('numeroProyecto');

            return $maximo === null ? null : (int) $maximo;
        }

        $codigos = match ($tipo) {
            CorrelativoDocumento::TIPO_PROYECTO => Proyecto::find()->select('codigo')->column(),
            CorrelativoDocumento::TIPO_LISTA => Proyecto::find()->select('codigoLista')->column(),
            CorrelativoDocumento::TIPO_SOLICITUD => SolicitudCotizacion::find()->select('codigoDocumento')->column(),
            CorrelativoDocumento::TIPO_PRESUPUESTO => Presupuesto::find()->select('numeroPresupuesto')->column(),
            default => [],
        };

        $numeros = array_filter(array_map(static fn ($codigo) => CorrelativoDocumento::numeroDe($tipo, $codigo), $codigos), static fn ($n) => $n !== null);

        return $numeros === [] ? null : max($numeros);
    }

    /**
     * Guarda los "siguiente numero" del formulario
     * (CorrelativoDocumento[PT][siguiente], ...).
     *
     * REGLA DE NEGOCIO: cada uno tiene que ser MAYOR que el ultimo usado de
     * su tipo; si no, se repetirian codigos (la base tambien lo impediria,
     * pero recien al crear el documento). Se guardan todos o ninguno.
     *
     * @param CorrelativoDocumento[] $correlativos los de correlativos()
     */
    public function guardar(array $correlativos, array $datosFormulario): bool
    {
        if (!CorrelativoDocumento::loadMultiple($correlativos, $datosFormulario)) {
            return false;
        }

        $valido = true;

        foreach ($correlativos as $tipo => $correlativo) {
            if (!$correlativo->validate()) {
                $valido = false;
                continue;
            }

            $ultimo = $this->ultimoUsado($tipo);
            if ($ultimo !== null && (int) $correlativo->siguiente <= $ultimo) {
                $correlativo->addError('siguiente', 'Tiene que ser mayor que el último usado (' . $this->numeroTexto($tipo, $ultimo) . ').');
                $valido = false;
            }
        }

        if (!$valido) {
            return false;
        }

        $transaccion = Yii::$app->db->beginTransaction();

        try {
            foreach ($correlativos as $correlativo) {
                $correlativo->save(false);
            }
            $transaccion->commit();
        } catch (Throwable $e) {
            $transaccion->rollBack();
            throw $e;
        }

        return true;
    }

    /**
     * Para los mensajes: "el próximo será el RD-GR-ET-001/27", con el
     * proceso del proyecto (PI si no se dice).
     */
    public function proximo(string $tipo, string $proceso = CorrelativoDocumento::PROCESO): string
    {
        $correlativo = $this->correlativos()[$tipo] ?? null;

        if ($correlativo === null) {
            throw new DomainException("Falta el correlativo \"$tipo\".");
        }

        return CorrelativoDocumento::codigo($tipo, (int) $correlativo->siguiente, $proceso, $this->anioDelCodigo($tipo));
    }

    /**
     * "016" o, en ET, "016/26" (el año de hoy): el numero sin el proceso,
     * que depende de cada proyecto.
     */
    public function numeroTexto(string $tipo, int $numero): string
    {
        return CorrelativoDocumento::numeroTexto($numero, $this->anioDelCodigo($tipo));
    }

    /**
     * El año que lleva el codigo de ese tipo hoy (null si no lleva año).
     */
    private function anioDelCodigo(string $tipo): ?int
    {
        return CorrelativoDocumento::reiniciaPorAnio($tipo) ? $this->anioActual() : null;
    }
}
