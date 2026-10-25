# Guía de instalación en el servidor de DTIC

Módulo: Sistema de presupuestos de instalaciones de redes (Unidad de Redes y Telecomunicaciones).
Para: equipo técnico de DTIC — USFX. Fecha: 18 de octubre de 2026.

Esta guía reúne lo que el servidor necesita para instalar el módulo y los puntos que conviene
acordar antes. El módulo está hecho en PHP con Yii2 Advanced y usa la base SQL Server
`POAPresupuestos`; todo lo propio vive en el esquema `redes`.

## 1. Resumen de lo que hace falta

- Ubuntu Server con Nginx y PHP-FPM 8.2 (como mínimo 8.1).
- El driver de Microsoft para conectar PHP con SQL Server desde Linux (ODBC 18 + `pdo_sqlsrv`).
- Un usuario de SQL Server propio para la aplicación (desde Linux no hay autenticación de Windows).
- Permiso para crear el esquema `redes` y sus tablas en `POAPresupuestos`, y para leer
  `dbo.CatalogoSigma`. El módulo no crea ni modifica nada en `dbo`.
- Tres carpetas en las que PHP pueda escribir: `backend/runtime`, `backend/web/assets` y
  `backend/uploads` (más `console/runtime` para los comandos).
- Subir los límites de tamaño de archivo de Nginx y PHP a unos 12 MB.
- Integrar la autenticación institucional antes de ponerlo en uso (ver punto 8).

## 2. Requisitos del servidor

**PHP 8.2** (el código usa características de PHP 8.0 y 8.1) con estas extensiones:

- `pdo_sqlsrv` y `sqlsrv` 5.12 (conexión a SQL Server; se instalan con PECL).
- `mbstring`, `xml` / `dom`, `zip` (lee los .docx de especificaciones), `gd` (imágenes en los
  PDF), `fileinfo` (valida los archivos subidos), `intl` (recomendada).

**Driver de Microsoft para Linux:** paquete `msodbcsql18` y `unixodbc-dev`, desde el repositorio
de Microsoft para la versión de Ubuntu del servidor.

**Composer** para instalar las librerías (Yii2, mPDF para los PDF, smalot/pdfparser).

## 3. Base de datos

**Usuario de la aplicación.** Un login de SQL Server (no de Windows) con:

- `SELECT` sobre `dbo.CatalogoSigma` (solo lectura).
- En el esquema `redes`: crear, modificar y borrar tablas, y leer y escribir datos.
- Para la primera instalación, permiso para crear el esquema `redes` (o que el DBA lo cree antes:
  `CREATE SCHEMA redes`).

**Qué crea la instalación:** el esquema `redes` con unas 25 tablas, más `redes.migration`, que
registra qué cambios de estructura ya se aplicaron. En `dbo` no crea nada: solo lee
`dbo.CatalogoSigma`.

**Cómo se crean las tablas.** El módulo usa migraciones de Yii (`php yii migrate`): son scripts
versionados que se aplican en orden y se pueden revertir. Si DTIC prefiere que el DBA ejecute el
SQL a mano en vez de darle ese permiso a la aplicación, hay que acordarlo antes.

**SQL Server 2012.** Las migraciones y las pruebas automáticas se corrieron desde cero sobre una base
con nivel de compatibilidad 110 (el de 2012) sin errores, y el código no usa funciones de versiones
posteriores. Igual conviene una prueba en el servidor real, porque se probó con un motor más nuevo
en modo 2012.

**Conexión cifrada (punto a revisar con seguridad).** El driver ODBC 18 cifra la conexión por
defecto. SQL Server 2012 necesita SP4 (o la actualización de TLS 1.2) para aceptar el cifrado que
exige Ubuntu; sin eso la conexión falla. Opciones, en orden de preferencia:

1. Actualizar SQL Server 2012 a SP4 / TLS 1.2 y usar `Encrypt=yes` con un certificado válido.
2. `Encrypt=yes;TrustServerCertificate=yes` (cifra, pero sin validar el certificado).
3. `Encrypt=no` (sin cifrado; solo si la red interna lo permite).

**Intercalación (collation).** La base de desarrollo usa `Modern_Spanish_CI_AS`. Si la
institucional usa otra, conviene saberlo: cambia cómo se comparan mayúsculas y acentos en las
búsquedas.

## 4. Instalación paso a paso

1. Copiar el código (la rama del módulo) en el servidor, por ejemplo en `/var/www/presupuestos-redes`.
2. Instalar las librerías sin las de desarrollo:

   ```
   composer install --no-dev --optimize-autoloader
   ```

3. Preparar el modo producción. Esto copia la configuración de producción, desactiva el modo de
   depuración y genera la clave de las cookies:

   ```
   php init --env=Production --overwrite=n
   ```

4. Completar la conexión en `common/config/main-local.php` (servidor, usuario, contraseña y
   opciones de cifrado del punto 3). Este archivo no se sube a git.
5. Crear las tablas:

   ```
   php yii migrate
   ```

   En producción no se crea la tabla de usuarios de prueba (`dbo.user`) de la plantilla de Yii.
6. Dar permisos de escritura al usuario de PHP-FPM (normalmente `www-data`):

   ```
   chown -R www-data:www-data backend/runtime backend/web/assets backend/uploads console/runtime
   chmod -R 775 backend/runtime backend/web/assets backend/uploads console/runtime
   ```

7. Opcional: cargar la librería de especificaciones desde el banco de documentos de DTIC. Primero
   sin `--confirmar`, que solo genera un CSV para revisar (`/ruta/banco` es la carpeta con los .docx):

   ```
   php yii especificacion/importar /ruta/banco --desde=2025
   php yii especificacion/importar /ruta/banco --desde=2025 --confirmar
   ```

## 5. Configuración de Nginx

La raíz pública es `backend/web`. Ejemplo de bloque de servidor (ajustar nombre y versión de PHP):

```
server {
    listen 80;
    server_name redes.usfx.bo;
    root /var/www/presupuestos-redes/backend/web;
    index index.php;

    # Archivos de cotizaciones y especificaciones de hasta 10 MB (más margen).
    client_max_body_size 12m;

    location / {
        try_files $uri $uri/ /index.php$is_args$args;
    }

    location ~ ^/index\.php$ {
        include snippets/fastcgi-php.conf;
        fastcgi_pass unix:/run/php/php8.2-fpm.sock;
    }

    # Ningún otro .php ni archivo oculto se entrega.
    location ~ \.php$ { return 404; }
    location ~ /\. { deny all; }
}
```

El módulo funciona también dentro de una subcarpeta de otro sitio (por ejemplo `/redes/`), porque
sus enlaces usan `index.php?r=...` y no rutas fijas. Se recomienda HTTPS.

## 6. Configuración de PHP (php.ini de PHP-FPM)

```
upload_max_filesize = 12M
post_max_size = 16M
memory_limit = 256M
max_execution_time = 60
date.timezone = America/La_Paz
expose_php = Off
```

La aplicación ya fija la zona horaria de Bolivia por su cuenta; `date.timezone` es solo para que
el resto de PHP coincida.

## 7. Seguridad: lo que ya trae el módulo

- **Todo exige sesión iniciada.** Solo la pantalla de ingreso y la de error son públicas; cualquier
  pantalla nueva queda protegida automáticamente.
- **Los archivos subidos no son públicos.** Los PDF de cotizaciones y los adjuntos de
  especificaciones se guardan en `backend/uploads`, fuera de la carpeta pública, y se entregan
  solo con sesión. Una ruta escrita a mano que intente salir de esa carpeta se rechaza.
- **Modo producción** (paso 3 de la instalación): sin depuración, sin el generador de código
  (Gii) y con clave de cookies propia del servidor.
- **No crea tablas en dbo** y solo lee `dbo.CatalogoSigma`.
- **Respaldo:** la carpeta `backend/uploads` contiene documentos que no están en la base de datos;
  tiene que entrar en el respaldo del servidor.

## 8. Autenticación institucional (pendiente)

En desarrollo el ingreso usa una tabla de prueba de la plantilla de Yii (`dbo.user`, usuario
`admin`). **En producción esa tabla no se crea**, así que antes de ponerlo en uso hay que conectar el
ingreso con el sistema de usuarios de la universidad. En Yii2 eso se hace cambiando la clase de
identidad (`identityClass` en `backend/config/main.php`) por una que use el sistema institucional.
El módulo solo necesita saber quién está conectado: usa su nombre de usuario como "elaborado por" en
los formularios NET.

## 9. Verificación después de instalar

- La pantalla de ingreso carga; sin sesión, cualquier otra dirección lleva al ingreso.
- `php yii migrate/history` muestra las migraciones aplicadas.
- Se puede abrir el PDF de una lista de ítems y el de un formulario NET.
- Se puede subir un PDF de unos 5 MB en una cotización y un .docx en una especificación (el .docx
  confirma que `fileinfo` lo reconoce bien).
- Una fecha emitida después de las 20:00 sale con la fecha del día (zona horaria).
- Un archivo subido NO se abre escribiendo su dirección `/uploads/...` en el navegador.

## 10. Puntos para acordar con DTIC

1. ¿Qué versión de PHP y de Ubuntu tiene el servidor? ¿Se puede instalar el driver de Microsoft?
2. ¿SQL Server 2012 tiene SP4 o TLS 1.2? ¿Qué opción de cifrado aprueba seguridad?
3. ¿La aplicación puede aplicar sus propias migraciones o el DBA prefiere ejecutar el SQL?
4. ¿Cuál es la intercalación de `POAPresupuestos`?
5. ¿El módulo va en un subdominio propio o dentro del sistema existente (subcarpeta o rama del
   proyecto Yii2 institucional)?
6. ¿Cómo se integra con el sistema de usuarios institucional?
7. ¿`dbo.CatalogoSigma` es una tabla o una vista, y tiene los ~72.000 ítems?
8. ¿Cómo se respalda la carpeta `backend/uploads`?
