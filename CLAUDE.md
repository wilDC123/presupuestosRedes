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
  nacional del gobierno, ~72,000 ítems; la copia local de desarrollo tiene 51,636,
  verificado el 2026-10-04 -- la cifra anterior de "509 accesibles" ya no es vigente)
- Esquema propio del proyecto: `redes` — aquí van TODAS las tablas nuevas
- Las tablas del proyecto se crean con MIGRACIONES de Yii2 (`php yii migrate/create nombre`),
  nunca con scripts SQL sueltos ejecutados a mano

## Alcance del sistema (confirmado en reuniones con el equipo técnico)
### SÍ incluye:
- Gestión de proyectos (`redes.Proyecto`)
- Subcatálogo curado de ítems de redes, con referencia lógica a `dbo.CatalogoSigma.IdSigma`
  (NUNCA duplicar el catálogo completo; selección manual/bajo demanda, no clasificación automática)
- Nombre DTIC vs. nombre SIGMA: `SubcatalogoItem.descripcion` es el nombre DTIC (corto y
  genérico, el que sale en listados, presupuesto y PDF). El nombre oficial SIGMA no se copia:
  se lee con la relación `getCatalogoSigma()`. Varios ítems DTIC pueden apuntar al mismo
  `idSigma` (SIGMA es genérico); se avisa "ya usado por: ..." pero NO se bloquea
- Partida presupuestaria (5 dígitos, `dbo.CatalogoSigma.IdGasto`): columna `partida` en
  `redes.SubcatalogoItem` (copiada desde SIGMA al guardar, o a mano si el ítem no viene de
  SIGMA) y en `redes.PresupuestoDetalle` (CONGELADA al crear la línea, igual que el precio).
  Categoría y partida son datos distintos: una categoría DTIC mezcla varias partidas
- Unidades de medida: catálogo `redes.UnidadMedida` (nombre, abreviatura, activo).
  `SubcatalogoItem.idUnidadMedida` es FK; `ProyectoItem.unidadMedida`,
  `PresupuestoDetalle.unidadMedida` y `ComputoMetrico.unidad` guardan la ABREVIATURA como texto
  (congelada al crear la fila, como el precio y la partida). Se muestra la abreviatura en
  listados y PDF. Solo se eligen unidades activas; una en uso no se elimina, se desactiva
- `PresupuestoDetalle.observacion`: texto libre de la línea (medidas, "según especificación");
  los detalles de una compra concreta van ahí, no en el subcatálogo
- Resumen por partida presupuestaria (`PresupuestoService::resumenPorPartida()`), en la vista
  del presupuesto y en el PDF; su total coincide siempre con el total por categorías
- Presupuesto como DOCUMENTO VIVO: se elabora en UNA pantalla, el paso "Presupuesto" del
  proyecto (`ElaborarPresupuestoController`, `ElaborarPresupuestoService`). No hay "crear
  presupuesto" ni "agregar líneas": cada ítem de la lista ES una línea (`PresupuestoDetalle`, como
  máximo una por `idProyectoItem`, índice único filtrado). Al entrar, el presupuesto se crea solo
  en "en proceso" y cada ítem sin línea recibe el MENOR precio vigente. La tabla tiene una columna
  por proveedor; marcar otro precio se guarda por AJAX. Atajos: "menor precio por ítem" y "todo a
  un proveedor"
- Elegir otro precio REEMPLAZA la línea (borra y crea otra con precio y partida congelados en ese
  momento); el precio de una línea guardada nunca se modifica. Elegir un precio que NO es el menor
  admite una `PresupuestoDetalle.justificacion` OPCIONAL (decidido el 2026-10-16: el presupuesto es
  un precio referencial, no la adjudicación de una compra). El anexo del PDF lista los ítems con un
  precio distinto del menor cotizado, con su justificación si la tienen
- Solo se eligen precios con el presupuesto "en proceso" (`Presupuesto::esBorrador()`); en
  revisión, aprobado o cancelado la tabla es de solo lectura. Mientras es borrador, sus líneas siguen
  a la lista: quitar un ítem borra su línea y la cantidad se vuelve a copiar (el precio no)
- La cabecera (número, oficio, área, fecha) se exige recién al "Enviar a revisión" (escenario
  `Presupuesto::SCENARIO_ENVIAR`), junto con que todos los ítems tengan precio. Los estados se
  cambian en la misma pantalla. El menú "Presupuestos" es solo un listado de consulta; las rutas
  viejas (`presupuesto/view`, `detalle`, `armar`, `create`, `update`) redirigen al paso Presupuesto
- Una línea solo puede usar una cotización de su mismo ítem, y si la cotización es de una fila de
  la lista (`CotizacionDetalle.idProyectoItem`), solo esa fila puede usarla (las sueltas, sin
  `idProyectoItem`, se ofrecen solo si la fila no tiene cotizaciones propias); cotización vencida
  (`fecha + validezDias < hoy`) se permite pero genera aviso al enviar y al aprobar
- Librería de especificaciones técnicas (formato institucional con código `NET:xxx/26`):
  `redes.Especificacion` NO pertenece a un ítem ni a un proyecto; se clasifica por categoría
  (`idCategoria`) y `idSubcatalogo` es solo un "ítem de referencia" opcional. Se ENLAZA a los
  ítems de la lista de cada proyecto con `redes.ProyectoItem.idEspecificacion` (como máximo una
  por ítem, desde la pantalla de la lista y con su mismo bloqueo). La especificación es
  OPCIONAL por ítem: un ítem sin ella nunca bloquea lista, solicitud, cotización, presupuesto ni
  PDF. El código del proyecto no se guarda en la especificación; los impresos lo muestran junto
  a ella. Solo se enlazan especificaciones activas; una en uso no se elimina, se desactiva
- Cotizaciones de proveedores (relación N:N con ítems/especificaciones)
- Marca y modelo: `SolicitudCotizacionItem.marcaModeloReferencia` es lo que la DTIC PIDE como
  referencia (texto libre, opcional; sale entre paréntesis junto a la descripción en el PDF de la
  solicitud; se edita mientras la solicitud no tenga cotizaciones y viaja con el ítem si se mueve
  de solicitud). `CotizacionDetalle.marcaModelo` es lo que el proveedor OFERTA. No va al presupuesto
- Presupuestos versionados por proyecto, con estados: en_proceso, en_revision, observado,
  aprobado, cancelado
- Precio unitario "congelado" en el detalle del presupuesto al momento de crearlo
  (nunca se recalcula retroactivamente si cambia el precio de una cotización nueva)
- Generación de PDF consolidado del presupuesto: marca de agua "BORRADOR" mientras no esté
  aprobado, proveedor por línea y anexo con el cuadro comparativo y las justificaciones
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
- RESUELTO: las migraciones están aplicadas (verificado con `php yii migrate/history` el
  2026-10-16; la última es `m261016_012942_justificacion_y_linea_unica_presupuesto`), incluida `m260831_014824_create_schema_redes`, las dos de partida
  (`m261005_033949_add_partida_to_subcatalogo_item`,
  `m261005_034753_add_partida_observacion_to_presupuesto_detalle`) y la de la librería de
  especificaciones (`m261015_035448_libreria_especificaciones`).
- OJO con `sqlcmd` a mano: usar `-I` (QUOTED_IDENTIFIER ON). Sin eso, los DELETE/UPDATE sobre
  tablas con columnas calculadas (ej. `CotizacionDetalle.precioTotal`) fallan con el error 1934.
- OJO con los tipos en migraciones: Yii2 traduce "char" a "nchar" en SQL Server aunque se
  escriba como texto (`'char(5) NULL'`), así que las columnas `partida` son `nchar(5)`. Se
  decidió dejarlas así (coherente con el resto del esquema, que es `nvarchar`).
- Falta gestionar acceso al catálogo SIGMA completo (la copia local tiene 51,636 de ~72,000 ítems)
- La búsqueda en SIGMA usa `LIKE '%palabra%'` (~0,4 s por búsqueda con 51,636 filas). Si se
  vuelve lenta, migrar a Full-Text Search de SQL Server; requiere coordinación con DTIC porque
  `dbo.CatalogoSigma` no es del esquema `redes`
- PENDIENTE (fuera de alcance por ahora): código UNSPSC / "Catálogo de Bienes y Servicios de
  cuarto nivel" (SIGEP). Se evaluará cuando se consigan las guías operativas del SIGEP y se
  sepa si la universidad ya lo relaciona con SIGMA en alguna tabla
- PENDIENTE (fuera de alcance por ahora): índice único sobre `SubcatalogoItem.descripcion`. Se
  decide después de ver cómo queda el subcatálogo recargado
- PENDIENTE (mejora posible): enlazar especificaciones desde las plantillas (`PlantillaItem`)
  para que se copien a la lista al cargar una plantilla
- Autenticación en producción no resuelta: Windows Auth funciona en entorno local, pero en
  el servidor Linux real con Nginx/PHP-FPM probablemente se necesite un usuario SQL Server
  dedicado — validar con el equipo técnico antes de dar por sentado el mecanismo
- Se usó una copia local de la base de datos institucional para desarrollo — pendiente
  informar formalmente al equipo de DTIC (política dice no sacar la BD completa de la
  institución, solo los ítems SIGMA que son públicos)
- Al integrarse al repositorio institucional real: se trabajará en una RAMA dentro del
  proyecto Yii2 existente (no en este repo aislado), con un controlador propio
  (`ElaborarPresupuestoController`, que ya existe en este repo) dentro del módulo de
  "ejecución/modificaciones presupuestarias", terminando en un merge al proyecto principal
- PENDIENTE (tutor): el flujo "documento vivo" cambia los diagramas de secuencia "Elaborar
  presupuesto" y de actividades; resumen de los cambios en `docs/cambios-presupuesto-vivo.md`
- PENDIENTE: `PresupuestoService::crearNuevaVersion()` (`backend/services/PresupuestoService.php`) ahora
  crea la nueva versión VACÍA a propósito (cada versión es solo el incremento/cambio
  solicitado, no un snapshot completo de líneas ya aprobadas). Como consecuencia, falta
  construir un reporte consolidado que sume las líneas de `PresupuestoDetalle` de TODAS
  las versiones aprobadas de un mismo proyecto, para saber el total real del proyecto.
  El resumen por partida (`resumenPorPartida()`) ya existe pero es por presupuesto (una
  versión); el consolidado debería reutilizarlo sumando todas las versiones aprobadas.

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