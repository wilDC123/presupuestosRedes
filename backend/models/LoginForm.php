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
 *
 * LIMITE DE INTENTOS: tras MAX_INTENTOS fallos del mismo usuario desde la
 * misma direccion IP, o MAX_INTENTOS_IP desde la misma IP con cualquier
 * usuario, se espera BLOQUEO_SEGUNDOS. Se cuenta por usuario + IP (y
 * no solo por usuario) para que alguien de afuera no pueda dejar bloqueado al
 * encargado escribiendo mal su contrasena a proposito. El conteo va en el
 * cache de Yii (FileCache en runtime/): sin tabla nueva. Un ingreso correcto
 * borra el conteo de ese usuario.
 */
class LoginForm extends Model
{
    public const MAX_INTENTOS = 5;
    public const MAX_INTENTOS_IP = 20;
    public const BLOQUEO_SEGUNDOS = 15 * 60;

    public string $username = '';
    public string $password = '';
    /** Desmarcado por defecto: en una PC compartida la sesion quedaria abierta 30 dias. */
    public bool $rememberMe = false;
    /**
     * Direccion IP de quien intenta entrar. La pone el controlador
     * (Yii::$app->request->userIP); no tiene regla, asi que no llega del formulario.
     */
    public string $ip = '';

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
     * asi no se puede averiguar que usuarios existen. Con demasiados intentos
     * fallidos ni siquiera se revisa la contraseña.
     */
    public function validarClave(string $atributo): void
    {
        if ($this->hasErrors()) {
            return;
        }

        if ($this->bloqueado()) {
            $this->addError($atributo, 'Demasiados intentos fallidos. Espere ' . (int) (self::BLOQUEO_SEGUNDOS / 60) . ' minutos e intente de nuevo.');

            return;
        }

        $usuario = $this->usuario();

        if ($usuario === null || !$usuario->activo || !$usuario->claveCorrecta($this->password)) {
            $this->contarFallo();
            $this->addError($atributo, 'Usuario o contraseña incorrectos.');

            return;
        }

        Yii::$app->cache->delete($this->claveUsuario());
    }

    public function login(): bool
    {
        if (!$this->validate()) {
            return false;
        }

        return Yii::$app->user->login($this->usuario(), $this->rememberMe ? 3600 * 24 * 30 : 0);
    }

    /**
     * true si este usuario (desde esta IP) o esta IP ya llegaron al limite.
     */
    public function bloqueado(): bool
    {
        $cache = Yii::$app->cache;

        return (int) $cache->get($this->claveUsuario()) >= self::MAX_INTENTOS
            || (int) $cache->get($this->claveIp()) >= self::MAX_INTENTOS_IP;
    }

    private function contarFallo(): void
    {
        $cache = Yii::$app->cache;

        foreach ([$this->claveUsuario(), $this->claveIp()] as $clave) {
            // Cada fallo vuelve a contar los 15 minutos desde ahora.
            $cache->set($clave, (int) $cache->get($clave) + 1, self::BLOQUEO_SEGUNDOS);
        }
    }

    private function claveUsuario(): string
    {
        return 'ingreso-fallido-usuario:' . sha1(mb_strtolower(trim($this->username), 'UTF-8') . '|' . $this->ip);
    }

    private function claveIp(): string
    {
        return 'ingreso-fallido-ip:' . sha1($this->ip);
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
