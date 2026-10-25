<?php

declare(strict_types=1);

namespace console\controllers;

use yii\console\controllers\MigrateController as MigrateControllerBase;

/**
 * `php yii migrate` con el historial de migraciones en el esquema del
 * proyecto (redes.migration) en vez de dbo.migration: en la base
 * institucional, este modulo no debe crear tablas en dbo.
 *
 * Se configura en console/config/main.php (controllerMap 'migrate').
 */
class MigrateController extends MigrateControllerBase
{
    public $migrationTable = 'redes.migration';

    /**
     * Yii crea la tabla de historial ANTES de correr la primera migracion,
     * asi que el esquema redes (que crea esa migracion) todavia podria no
     * existir: se crea aqui primero. SQL Server no tiene CREATE SCHEMA IF NOT
     * EXISTS, de ahi el IF NOT EXISTS ... EXEC.
     *
     * Si la base ya tenia el historial viejo en dbo.migration (las bases de
     * desarrollo creadas antes de este cambio), se copia, para que no se
     * intente correr de nuevo lo que ya esta aplicado. dbo.migration no se
     * borra: se puede eliminar a mano despues.
     */
    protected function createMigrationHistoryTable()
    {
        $this->db->createCommand(
            "IF NOT EXISTS (SELECT 1 FROM sys.schemas WHERE name = 'redes') BEGIN EXEC('CREATE SCHEMA redes') END"
        )->execute();

        parent::createMigrationHistoryTable();

        if ($this->db->createCommand("SELECT OBJECT_ID('dbo.migration', 'U')")->queryScalar() !== null) {
            $copiadas = $this->db->createCommand(
                'INSERT INTO redes.migration (version, apply_time)
                 SELECT version, apply_time FROM dbo.migration
                 WHERE version NOT IN (SELECT version FROM redes.migration)'
            )->execute();

            $this->stdout("Se copiaron $copiadas registro(s) del historial anterior (dbo.migration) a redes.migration.\n");
        }
    }
}
