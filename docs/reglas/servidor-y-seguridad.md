# Servidor, seguridad y base de datos

La instalación paso a paso está en `docs/guia-instalacion-dtic.md`.

## Seguridad

- **Toda la aplicación exige sesión** (`'as access'` en `backend/config/main.php`); solo `site/login` y `site/error`
  son públicos. Un controlador nuevo queda protegido solo.
- **Acciones que cambian datos: por POST** (`VerbFilter` o `isPost`). El AJAX lleva el token CSRF (lo agrega `yii.js`).
- **Consultas con parámetros**, nunca texto pegado. Los comodines de `LIKE` se escapan (`[%]`, `[_]`, `[[]`).
- **Salidas codificadas** con `Html::encode()` (también dentro de `nl2br()` y de las columnas `raw` de los listados).
- **Archivos subidos** en `backend/uploads/`, FUERA de `backend/web`: los entregan `cotizacion/documento` y
  `especificacion/adjunto`, con sesión. `ArchivoSubido::rutaAbsoluta()` rechaza rutas fuera de esa carpeta. El archivo
  se escribe al final de la transacción (si la base falla, no queda un archivo huérfano).
- **Atributos que no llegan de formularios**: los que pone un servicio no tienen regla en `rules()` o llevan "!"
  (ej. `Presupuesto`, `Cotizacion.documentoRuta`). Un atributo con regla es asignable con `load()`.
- **Ingreso** (`LoginForm`): tras 5 contraseñas equivocadas del mismo usuario desde la misma IP (`MAX_INTENTOS`), o 20
  desde la misma IP con cualquier usuario (`MAX_INTENTOS_IP`), hay que esperar 15 minutos; bloqueado, ni la correcta
  entra. Por usuario + IP para que de afuera no se pueda bloquear al encargado. Conteo en el caché de Yii; un ingreso
  correcto lo borra. La IP la pone `SiteController`: detrás de un proxy habría que leer la real. "Recordarme" desmarcado.
- **Usuarios temporales** (`redes.Usuario`) hasta integrar la autenticación institucional; se administran solo por
  consola (`php yii usuario`, `usuario/crear`, `usuario/clave`, `usuario/desactivar`, `usuario/activar`). El resto del
  módulo lee el usuario solo con `backend\components\UsuarioActual::nombre()`.

## Servidor (Ubuntu + Nginx)

- **Linux distingue mayúsculas**: cada clase se llama igual que su archivo, y las rutas de CSS, JS e imágenes deben
  coincidir exactamente.
- Zona horaria `America/La_Paz` (`common/config/main.php`, también el formatter).
- Historial de migraciones en `redes.migration` (`console/controllers/MigrateController.php`).
- `assetManager.appendTimestamp`: CSS y JS salen con `?v=<fecha>`.
- **UsfxGEO** (conexión `dbGeo`) es de otra unidad: si no responde, el formulario del proyecto abre con un aviso y el área
  se escribe a mano (`AreaIntervencionService::consultarInfraestructura()`).

## Base de datos

- Todas las tablas del módulo en el esquema **`redes`**, creadas con **migraciones** (`safeUp()` y `safeDown()`). Las
  que no se pueden deshacer sin perder datos se detienen con un mensaje.
- `dbo.CatalogoSigma` es de solo lectura (ni estructura ni datos).
- Modelo lógico (diagramas y diccionario): `docs/modelo-logico-datos.md`. Actualizarlo al agregar o cambiar tablas.
- **SQL Server 2012** (compatibilidad 110) en la institución; local 2022 (160). Probado el 2026-11-11 con las 70
  migraciones en nivel 110: se aplican, se deshacen (salvo la del esquema `redes`, donde vive `redes.migration`) y los
  tests pasan. No usar funciones posteriores a 2012 (`STRING_AGG`, `CONCAT_WS`, `TRIM`, `STRING_SPLIT`,
  `DROP … IF EXISTS`, `CREATE OR ALTER`…). Cómo repetir la prueba: base nueva con
  `ALTER DATABASE … SET COMPATIBILITY_LEVEL = 110` + copia de `dbo.CatalogoSigma`, un `yii` con otro `dsn`, y
  `common/config/test-local.php` apuntando a una copia de la base para los tests.
- **Máximo 2100 parámetros por consulta** en SQL Server: los buscadores usan como mucho 10 palabras
  (`backend\components\Busqueda`).
- Con `sqlcmd` a mano, usar `-I` (QUOTED_IDENTIFIER ON); sin eso fallan los DELETE/UPDATE sobre tablas con columnas
  calculadas (error 1934).
- Yii traduce "char" a "nchar" en SQL Server: las columnas `partida` son `nchar(5)` (se dejó así).
- `SCHEMA IF NOT EXISTS` no existe en SQL Server: usar
  `IF NOT EXISTS (SELECT 1 FROM sys.schemas WHERE name = '...') BEGIN EXEC(...) END`.
- Rendimiento: la búsqueda en SIGMA (`LIKE '%palabra%'`, ~0,4 s con 51.636 filas); si se vuelve lenta, Full-Text Search
  (coordinar con la DTIC: la tabla no es del esquema `redes`).
