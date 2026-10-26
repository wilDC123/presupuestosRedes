<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\Usuario;

/**
 * Capa de Servicio para los usuarios temporales (redes.Usuario). La usa el
 * comando de consola php yii usuario/...; no hay pantalla.
 *
 * Cada metodo devuelve null si salio bien, o el mensaje de error.
 */
class UsuarioService
{
    /**
     * @return Usuario[] por nombre
     */
    public function listar(): array
    {
        return Usuario::find()->orderBy(['usuario' => SORT_ASC])->all();
    }

    public function crear(string $usuario, string $clave): ?string
    {
        $error = $this->errorDeClave($clave);
        if ($error !== null) {
            return $error;
        }

        $modelo = new Usuario(['usuario' => $usuario, 'activo' => true]);
        $modelo->ponerClave($clave);
        $modelo->nuevaAuthKey();

        return $modelo->save() ? null : implode(' ', $modelo->getFirstErrors());
    }

    /**
     * Cambia la contraseña y la clave de "recordarme": las sesiones
     * recordadas en otros equipos dejan de valer.
     */
    public function cambiarClave(string $usuario, string $clave): ?string
    {
        $modelo = Usuario::porUsuario($usuario);
        if ($modelo === null) {
            return "No existe el usuario \"$usuario\".";
        }

        $error = $this->errorDeClave($clave);
        if ($error !== null) {
            return $error;
        }

        $modelo->ponerClave($clave);
        $modelo->nuevaAuthKey();

        return $modelo->save(false) ? null : 'No se pudo guardar la contraseña.';
    }

    /**
     * Un usuario no se borra, se desactiva: su nombre queda en lo que
     * emitio (el "elaborado por" de los formularios).
     */
    public function cambiarActivo(string $usuario, bool $activo): ?string
    {
        $modelo = Usuario::porUsuario($usuario);
        if ($modelo === null) {
            return "No existe el usuario \"$usuario\".";
        }

        $modelo->activo = $activo;
        if (!$activo) {
            // Corta tambien las sesiones recordadas.
            $modelo->nuevaAuthKey();
        }

        return $modelo->save(false) ? null : 'No se pudo guardar el usuario.';
    }

    private function errorDeClave(string $clave): ?string
    {
        if (mb_strlen($clave) < Usuario::CLAVE_MINIMA) {
            return 'La contraseña debe tener al menos ' . Usuario::CLAVE_MINIMA . ' caracteres.';
        }

        return null;
    }
}
