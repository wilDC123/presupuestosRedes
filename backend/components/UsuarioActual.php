<?php

declare(strict_types=1);

namespace backend\components;

use Throwable;
use Yii;

/**
 * El usuario con sesion, en UN solo lugar. Lo usan el historial, quien emite
 * las especificaciones, quien aprueba la lista, la orden de cambio, la ficha
 * tecnica y el menu lateral.
 *
 * Hoy la identidad es backend\models\Usuario (usuarios temporales). Al
 * integrar la autenticacion institucional se cambia identityClass en
 * backend/config/main.php y, si el nombre ya no esta en "username", solo se
 * toca este archivo. Por eso no vive en UsuarioService, que administra los
 * usuarios temporales y se ira con ellos.
 */
class UsuarioActual
{
    /**
     * Nombre de usuario de quien tiene la sesion, o null si no hay (consola,
     * tests). En la web siempre hay: toda la aplicacion exige sesion.
     */
    public static function nombre(): ?string
    {
        try {
            if (!Yii::$app->has('user')) {
                return null;
            }
            $identidad = Yii::$app->user->identity;
        } catch (Throwable) {
            // Sin sesion posible (consola, tests).
            return null;
        }

        return $identidad !== null ? (string) $identidad->username : null;
    }
}
