<?php

declare(strict_types=1);

namespace backend\models;

use Yii;
use yii\base\Model;

/**
 * Formulario de ingreso del modulo, contra redes.Usuario (antes era
 * common\models\LoginForm, contra dbo.user de la plantilla).
 *
 * Los nombres username / password / rememberMe son los que ya usa la vista
 * site/login.
 */
class LoginForm extends Model
{
    public string $username = '';
    public string $password = '';
    public bool $rememberMe = true;

    private ?Usuario $usuario = null;
    private bool $buscado = false;

    public function rules(): array
    {
        return [
            [['username', 'password'], 'required'],
            ['rememberMe', 'boolean'],
            ['password', 'validarClave'],
        ];
    }

    public function attributeLabels(): array
    {
        return [
            'username' => 'Usuario',
            'password' => 'Contraseña',
            'rememberMe' => 'Recordarme',
        ];
    }

    /**
     * Un solo mensaje para usuario inexistente, inactivo o contraseña mal:
     * asi no se puede averiguar que usuarios existen.
     */
    public function validarClave(string $atributo): void
    {
        if ($this->hasErrors()) {
            return;
        }

        $usuario = $this->usuario();

        if ($usuario === null || !$usuario->activo || !$usuario->claveCorrecta($this->password)) {
            $this->addError($atributo, 'Usuario o contraseña incorrectos.');
        }
    }

    public function login(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        return Yii::$app->user->login($this->usuario(), $this->rememberMe ? 3600 * 24 * 30 : 0);
    }

    private function usuario(): ?Usuario
    {
        if (!$this->buscado) {
            $this->usuario = Usuario::porUsuario(trim($this->username));
            $this->buscado = true;
        }

        return $this->usuario;
    }
}
