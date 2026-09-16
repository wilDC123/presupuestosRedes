# Proyecto: Sistema de Presupuestos de Instalaciones de Redes (USFX - DTIC)

## Qué es este proyecto
Proyecto de grado: sistema en línea para elaborar y gestionar presupuestos de
instalaciones de redes en la Universidad San Francisco Xavier de Chuquisaca (USFX),
para la Unidad de Redes y Telecomunicaciones de DTIC.

## Stack tecnológico (NO cambiar sin autorización del tutor académico)
- Backend: PHP 8.2 + Yii2 Advanced (v2.0.55)
- Frontend: jQuery, validación en frontend Y backend
- Base de datos: SQL Server (institución usa 2012; entorno local puede diferir)
- Servidor web real de la institución: Nginx (confirmado con el equipo técnico)
- SO del servidor: Ubuntu Server (Linux, case-sensitive)
- Entorno local de desarrollo: Laragon con PHP 8.2.33, Nginx en puerto 8080
- Driver SQL Server para PHP: msphpsql v5.12.0 (última compatible con PHP 8.2)

## Estructura del proyecto (Yii2 Advanced)
- `backend/` → panel administrativo, AQUÍ vive el módulo de redes que se está construyendo
- `frontend/` → sitio público, normalmente no se toca en este proyecto
- `common/` → código compartido (modelos, configuración de BD)
- `console/` → migraciones y comandos de consola
- `common/config/main-local.php` → conexión a base de datos (NUNCA se sube a git, está en .gitignore)

## Base de datos
- Base real: `POAPresupuestos` (servidor institucional en 172.16.1.250:1433)
- Copia local de desarrollo: `POAPresupuestos` en localhost:1433 (Windows Authentication)
- Tabla compartida/fija, NUNCA modificar su estructura: `dbo.CatalogoSigma` (catálogo
  nacional del gobierno, ~72,000 ítems, actualmente solo 509 accesibles en desarrollo)
- Esquema propio del proyecto: `redes` — aquí van TODAS las tablas nuevas
- Las tablas del proyecto se crean con MIGRACIONES de Yii2 (`php yii migrate/create nombre`),
  nunca con scripts SQL sueltos ejecutados a mano

## Alcance del sistema (confirmado en reuniones con el equipo técnico)
### SÍ incluye:
- Gestión de proyectos (`redes.Proyecto`)
- Subcatálogo curado de ítems de redes, con referencia lógica a `dbo.CatalogoSigma.IdSigma`
  (NUNCA duplicar el catálogo completo; selección manual/bajo demanda, no clasificación automática)
- Especificaciones técnicas por ítem (formato institucional con código `NET:xxx/26`)
- Cotizaciones de proveedores (relación N:N con ítems/especificaciones)
- Presupuestos versionados por proyecto, con estados: en_proceso, en_revision, observado,
  aprobado, cancelado
- Precio unitario "congelado" en el detalle del presupuesto al momento de crearlo
  (nunca se recalcula retroactivamente si cambia el precio de una cotización nueva)
- Generación de PDF consolidado del presupuesto
- Cómputos métricos: el sistema SOLO los almacena (con referencia de origen: plano,
  relevamiento), NUNCA los calcula

### NO incluye (explícitamente fuera de alcance):
- Control de ejecución/avance de obra
- Gestión de usuarios y roles propia (la administra el sistema institucional; este
  proyecto solo informa qué permisos necesita)
- Módulo de modificación presupuestaria en sí (solo genera una "pre-modificación")
- Adquisiciones/compras

## Convenciones de código
- Nombres de tabla en el esquema `redes`: PascalCase singular (ej. `redes.Proyecto`,
  `redes.PresupuestoDetalle`)
- Todas las tablas nuevas se crean vía migraciones, con `safeUp()` y `safeDown()`
- SQL Server no soporta `CREATE SCHEMA IF NOT EXISTS` directo — usar el patrón
  `IF NOT EXISTS (SELECT 1 FROM sys.schemas WHERE name = '...') BEGIN EXEC(...) END`

## Entorno de desarrollo — comandos útiles
- Levantar consultas de migración: `php yii migrate` (desde la raíz del proyecto)
- Crear una migración nueva: `php yii migrate/create nombre_descriptivo`
- El PATH de Windows ya está configurado para que `php` (sin ruta completa) use el
  de Laragon (8.2.33, con el driver sqlsrv) por defecto. Si alguna terminal nueva
  muestra una versión distinta, verificar con `php -v` antes de correr comandos.
- URL local del backend: http://presupuestos-redes.test:8080
- Usuario de prueba local: admin / admin123 (SOLO en tabla `dbo.user` de desarrollo,
  temporal hasta integrar autenticación institucional real)

## Riesgos y pendientes activos
- PENDIENTE INMEDIATO: existe la migración `console/migrations/m260831_014824_create_schema_redes.php`
  con el código para crear el esquema `redes` (patrón IF NOT EXISTS + EXEC), pero
  falta CONFIRMAR si ya se aplicó con `php yii migrate`. Verificar antes de crear
  las migraciones de las tablas (Categoria, Proyecto, Presupuesto, etc.), ya que
  el esquema debe existir primero.
- Falta gestionar acceso al catálogo SIGMA completo (solo 509 de ~72,000 ítems disponibles)
- Autenticación en producción no resuelta: Windows Auth funciona en entorno local, pero en
  el servidor Linux real con Nginx/PHP-FPM probablemente se necesite un usuario SQL Server
  dedicado — validar con el equipo técnico antes de dar por sentado el mecanismo
- Se usó una copia local de la base de datos institucional para desarrollo — pendiente
  informar formalmente al equipo de DTIC (política dice no sacar la BD completa de la
  institución, solo los ítems SIGMA que son públicos)
- Al integrarse al repositorio institucional real: se trabajará en una RAMA dentro del
  proyecto Yii2 existente (no en este repo aislado), con un controlador propio (ej.
  `ElaborarPresupuestoController`) dentro del módulo de "ejecución/modificaciones
  presupuestarias", terminando en un merge al proyecto principal
- PENDIENTE: `Presupuesto::crearNuevaVersion()` (`backend/models/Presupuesto.php`) ahora
  crea la nueva versión VACÍA a propósito (cada versión es solo el incremento/cambio
  solicitado, no un snapshot completo de líneas ya aprobadas). Como consecuencia, falta
  construir un reporte consolidado que sume las líneas de `PresupuestoDetalle` de TODAS
  las versiones aprobadas de un mismo proyecto, para saber el total real del proyecto.

## Diseño UML (ya completado, sirve como referencia del modelo de datos)
Se diseñaron los siguientes diagramas, con el modelo de datos ya validado:
- Casos de Uso: 1 solo actor "Encargado de Redes" (sin gestión de roles propia),
  con relaciones <<include>> (Elaborar presupuesto incluye Buscar ítem y Calcular
  precio referencial) y <<extend>> (Agregar ítem al subcatálogo extiende Buscar
  ítem; Generar PDF extiende Elaborar presupuesto; Crear nueva versión extiende
  Cambiar estado)
- Clases: 10 clases (Proyecto, Presupuesto, PresupuestoDetalle, SubcatalogoItem,
  Especificacion, Cotizacion, CotizacionDetalle, Proveedor, Categoria,
  ComputoMetrico) + enumerado EstadoPresupuesto. Composición (rombo relleno) en
  Presupuesto→PresupuestoDetalle y Cotizacion→CotizacionDetalle. Auto-relación
  0..1 en Presupuesto (versionAnterior). Atributo derivado /precioTotal
  (calculado). Restricción {congelado} en precioUnitario de PresupuestoDetalle
- Secuencia: 2 diagramas — "Crear nueva versión de presupuesto" (caso de
  versionado al modificar un presupuesto aprobado) y "Elaborar presupuesto"
  (flujo normal: buscar ítem → especificaciones → cotizaciones → confirmar
  línea con precio congelado)
- Actividades: ciclo de vida completo del presupuesto, con los ciclos
  Observado→Corregir→Enviar a revisión, y Modificación posterior→Nueva
  versión→Elaborar detalle
- Componentes: separa claramente lo desarrollado en este proyecto (Módulo
  Redes + esquema `redes`) de lo externo/institucional ya existente
  (dbo.CatalogoSigma, sistema de Presupuestos, sistema de Usuarios/Roles,
  módulo de Modificación Presupuestaria)

## Nivel del desarrollador
Persona sin experiencia previa en PHP/Yii2 aprendiendo mientras construye el proyecto.
Al explicar o generar código: preferir explicaciones claras del "por qué", no solo el "qué".
Evitar dar por sentado conocimiento de convenciones de Yii2 sin explicarlas brevemente
la primera vez que aparecen.