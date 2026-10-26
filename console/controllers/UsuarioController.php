<?php

declare(strict_types=1);

namespace console\controllers;

use backend\services\UsuarioService;
use yii\console\Controller;
use yii\console\ExitCode;
use yii\helpers\Console;

/**
 * Usuarios TEMPORALES del modulo (redes.Usuario), mientras no se integre la
 * autenticacion institucional.
 *
 * Uso:
 *   php yii usuario                  lista los usuarios
 *   php yii usuario/crear <usuario>  crea uno (pide la contraseña dos veces)
 *   php yii usuario/clave <usuario>  le cambia la contraseña
 *   php yii usuario/desactivar <usuario>   ya no puede ingresar
 *   php yii usuario/activar <usuario>
 *
 * La contraseña se pide por teclado y no como argumento, para que no quede
 * en el historial de comandos del servidor. En Linux no se ve al escribirla.
 */
class UsuarioController extends Controller
{
    private UsuarioService $service;

    public function init(): void
    {
        parent::init();
        $this->service = new UsuarioService();
    }

    public function actionIndex(): int
    {
        $usuarios = $this->service->listar();

        if ($usuarios === []) {
            $this->stdout("No hay usuarios. Cree uno con: php yii usuario/crear <usuario>\n");

            return ExitCode::OK;
        }

        foreach ($usuarios as $u) {
            $this->stdout(sprintf("  %-30s %-9s creado %s\n", $u->usuario, $u->activo ? 'activo' : 'INACTIVO', $u->fechaCreacion));
        }

        return ExitCode::OK;
    }

    public function actionCrear(string $usuario): int
    {
        $clave = $this->pedirClave();
        if ($clave === null) {
            return ExitCode::DATAERR;
        }

        return $this->resultado($this->service->crear($usuario, $clave), "Usuario \"$usuario\" creado.");
    }

    public function actionClave(string $usuario): int
    {
        $clave = $this->pedirClave();
        if ($clave === null) {
            return ExitCode::DATAERR;
        }

        return $this->resultado($this->service->cambiarClave($usuario, $clave), "Contraseña de \"$usuario\" cambiada.");
    }

    public function actionDesactivar(string $usuario): int
    {
        return $this->resultado($this->service->cambiarActivo($usuario, false), "Usuario \"$usuario\" desactivado.");
    }

    public function actionActivar(string $usuario): int
    {
        return $this->resultado($this->service->cambiarActivo($usuario, true), "Usuario \"$usuario\" activado.");
    }

    private function resultado(?string $error, string $exito): int
    {
        if ($error !== null) {
            $this->stderr($error . "\n", Console::FG_RED);

            return ExitCode::DATAERR;
        }

        $this->stdout($exito . "\n", Console::FG_GREEN);

        return ExitCode::OK;
    }

    /**
     * Pide la contraseña dos veces; null si no coinciden.
     */
    private function pedirClave(): ?string
    {
        $clave = $this->leerOculto('Contraseña: ');
        $repetida = $this->leerOculto('Repita la contraseña: ');

        if ($clave !== $repetida) {
            $this->stderr("Las contraseñas no coinciden.\n", Console::FG_RED);

            return null;
        }

        return $clave;
    }

    /**
     * En Linux apaga el eco de la terminal mientras se escribe (stty). En
     * Windows no hay forma simple desde PHP, asi que se ve.
     */
    private function leerOculto(string $mensaje): string
    {
        $this->stdout($mensaje);
        $ocultar = DIRECTORY_SEPARATOR === '/' && function_exists('posix_isatty') && posix_isatty(STDIN);

        if ($ocultar) {
            shell_exec('stty -echo');
        }

        $linea = fgets(STDIN);

        if ($ocultar) {
            shell_exec('stty echo');
            $this->stdout("\n");
        }

        return rtrim((string) $linea, "\r\n");
    }
}
