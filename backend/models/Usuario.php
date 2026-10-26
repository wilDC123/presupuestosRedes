<?php

declare(strict_types=1);

namespace backend\models;

use Yii;
use yii\db\ActiveRecord;
use yii\web\IdentityInterface;

/**
 * Modelo para la tabla "redes.Usuario": usuarios TEMPORALES para entrar al
 * modulo mientras no se integre la autenticacion institucional. Es la
 * "identidad" de Yii (identityClass en backend/config/main.php): lo que
 * devuelve Yii::$app->user->identity.
 *
 * Se crean y administran por consola (php yii usuario/...), no hay pantalla.
 * Al integrarse con el sistema institucional, identityClass pasa a la clase
 * de ese sistema y esta tabla deja de usarse.
 *
 * @property int $id
 * @property string $usuario nombre con el que se ingresa
 * @property string $claveHash contraseña cifrada (nunca la contraseña)
 * @property string $authKey clave de la cookie "recordarme"
 * @property bool $activo uno inactivo no puede ingresar
 * @property string $fechaCreacion
 * @property-read string $username el mismo $usuario (ver getUsername())
 */
class Usuario extends ActiveRecord implements IdentityInterface
{
    public const CLAVE_MINIMA = 8;

    public static function tableName(): string
    {
        return 'redes.Usuario';
    }

    public function rules(): array
    {
        return [
            [['usuario'], 'trim'],
            [['usuario'], 'required'],
            [['usuario'], 'string', 'max' => 64],
            [['usuario'], 'match', 'pattern' => '/^[A-Za-z0-9._-]+$/',
                'message' => 'El usuario solo admite letras, números, punto, guion y guion bajo (sin espacios).'],
            [['activo'], 'default', 'value' => true],
            [['activo'], 'boolean'],
            [['usuario'], 'unique', 'message' => 'Ya existe un usuario con ese nombre.'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'id' => 'ID',
            'usuario' => 'Usuario',
            'activo' => 'Activo',
            'fechaCreacion' => 'Fecha de creación',
        ];
    }

    /**
     * El resto del modulo lee identity->username (la barra lateral, el
     * "elaborado por" de las emisiones); asi sigue igual con cualquier
     * clase de identidad.
     */
    public function getUsername(): string
    {
        return $this->usuario;
    }

    public static function porUsuario(string $usuario): ?self
    {
        return static::findOne(['usuario' => $usuario]);
    }

    public function ponerClave(string $clave): void
    {
        $this->claveHash = Yii::$app->security->generatePasswordHash($clave);
    }

    public function claveCorrecta(string $clave): bool
    {
        return Yii::$app->security->validatePassword($clave, $this->claveHash);
    }

    public function nuevaAuthKey(): void
    {
        $this->authKey = Yii::$app->security->generateRandomString();
    }

    // ---- IdentityInterface ----

    public static function findIdentity($id): ?self
    {
        return static::findOne(['id' => $id, 'activo' => true]);
    }

    public static function findIdentityByAccessToken($token, $type = null): ?self
    {
        return null;
    }

    public function getId(): int
    {
        return (int) $this->id;
    }

    public function getAuthKey(): string
    {
        return $this->authKey;
    }

    public function validateAuthKey($authKey): bool
    {
        return $this->authKey === $authKey;
    }
}
