<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\CorrelativoDocumento;
use backend\models\EspecificacionEmision;
use backend\models\Presupuesto;
use backend\models\Proyecto;
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
 *  - sube de 1 en 1, no se reinicia por año y un numero nunca se reutiliza,
 *  - el siguiente solo puede ser MAYOR que el ultimo usado de su tipo.
 */
class CodificacionService
{
    /**
     * Toma el siguiente numero del tipo y deja el contador en el que sigue,
     * con la fila BLOQUEADA hasta que termine la transaccion (UPDLOCK,
     * HOLDLOCK de SQL Server): si dos personas guardan a la vez, la segunda
     * espera a la primera y recibe el numero siguiente, nunca el mismo.
     * Tiene que llamarse dentro de una transaccion.
     */
    public function tomar(string $tipo): int
    {
        $db = Yii::$app->db;
        $numero = $db->createCommand(
            'SELECT siguiente FROM redes.CorrelativoDocumento WITH (UPDLOCK, HOLDLOCK) WHERE tipo = :tipo',
            [':tipo' => $tipo]
        )->queryScalar();

        if ($numero === null || $numero === false) {
            throw new RuntimeException("Falta el correlativo \"$tipo\" en redes.CorrelativoDocumento. Ejecute \"php yii migrate\".");
        }

        $db->createCommand('UPDATE redes.CorrelativoDocumento SET siguiente = siguiente + 1 WHERE tipo = :tipo', [':tipo' => $tipo])->execute();

        return (int) $numero;
    }

    /**
     * Los tres correlativos, en el orden de CorrelativoDocumento::tipos().
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

        return $ordenados;
    }

    /**
     * El mayor numero ya usado de un tipo (null si todavia no hay ninguno).
     * Solo cuentan los codigos con el formato del lineamiento: los numeros
     * de presupuesto escritos a mano antes ("690") y los NET no son de esta
     * serie.
     */
    public function ultimoUsado(string $tipo): ?int
    {
        if ($tipo === CorrelativoDocumento::TIPO_ESPECIFICACION) {
            $maximo = EspecificacionEmision::find()->where(['serie' => EspecificacionEmision::SERIE_ET])->max('numeroNet');

            return $maximo === null ? null : (int) $maximo;
        }

        $codigos = match ($tipo) {
            CorrelativoDocumento::TIPO_PROYECTO => Proyecto::find()->select('codigo')->column(),
            CorrelativoDocumento::TIPO_PRESUPUESTO => Presupuesto::find()->select('numeroPresupuesto')->column(),
            default => [],
        };

        $numeros = array_filter(array_map(static fn ($codigo) => CorrelativoDocumento::numeroDe($tipo, $codigo), $codigos), static fn ($n) => $n !== null);

        return $numeros === [] ? null : max($numeros);
    }

    /**
     * Guarda los tres "siguiente numero" del formulario
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
                $correlativo->addError('siguiente', 'Tiene que ser mayor que el último usado (' . CorrelativoDocumento::codigo($tipo, $ultimo) . ').');
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
     * Para los mensajes: "el próximo será el RD-PI-ET-001".
     */
    public function proximo(string $tipo): string
    {
        $correlativo = CorrelativoDocumento::findOne($tipo);

        if ($correlativo === null) {
            throw new DomainException("Falta el correlativo \"$tipo\".");
        }

        return CorrelativoDocumento::codigo($tipo, (int) $correlativo->siguiente);
    }
}
