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
- Tipos de proyecto: catálogo `redes.TipoProyecto` (nombre, activo), administrado por el
  encargado en el menú "Tipos de proyecto" (decidido el 2026-10-19; antes estaban en
  `backend/config/parametros-proyecto.json`). `Proyecto.idTipoProyecto` y
  `Plantilla.idTipoProyecto` son FK. Solo se eligen tipos activos, pero quien ya tenía uno
  desactivado lo conserva (`TipoProyecto::reglaElegible()`); uno en uso no se elimina, se
  desactiva. Facultades, edificios, bloques y proyectistas SIGUEN en el JSON
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
- Librería de especificaciones técnicas: las especificaciones VIVEN EN EL SISTEMA (antes eran
  Word en el Drive del Ing.). Tres niveles (decidido con el Ing. de DTIC el 2026-10-17; resumen
  para el tutor en `docs/cambios-especificaciones.md`, plan en `docs/plan-especificaciones.md`):
  - FICHA `redes.Especificacion`: solo título, categoría (`idCategoria`), ítem de referencia
    opcional (`idSubcatalogo`) y activo. NO pertenece a un ítem ni a un proyecto.
  - VERSIÓN `redes.EspecificacionVersion` (v1, v2…) con sus filas
    `redes.EspecificacionCaracteristica` (nombre, valor), modelo sugerido, garantía y adjunto. Una
    versión ya usada en una emisión NO se modifica: editarla crea la siguiente
    (`EspecificacionService::guardarVersion()`); cambiar solo título/categoría/activo no crea versión.
  - EMISIÓN `redes.EspecificacionEmision`: el formulario con código `NET:880/26` que se firma y se
    entrega al proveedor. NUNCA cambia: guarda copias congeladas (título, cantidad, unidad,
    dependencia = facultad del proyecto, garantía, textos, condiciones de entrega) y apunta a una
    versión que ya no cambia. Estados: vigente, reemplazada, anulada; "vencida" se calcula
    (fecha + vigencia). Reglas en `EspecificacionEmisionService`.
  Se ENLAZA a los ítems de la lista con `redes.ProyectoItem.idEspecificacion` (como máximo una por
  ítem, desde la pantalla de la lista y con su mismo bloqueo) y ENLAZAR = EMITIR: al guardar la
  lista se asigna el siguiente NET. Cambiar o quitar la ficha, o quitar el ítem, ANULA la emisión
  vigente (no se borra). Un error se corrige REEMITIENDO (NET nuevo, última versión, cantidad
  actual de la lista; la anterior queda "reemplazada por"), permitido mientras el presupuesto no
  esté aprobado ni cancelado. Correlativo NET: lo fija el encargado en
  `redes.ConfiguracionEspecificacion.siguienteNet` (una sola fila, con los textos fijos del
  formulario, editables), sube de 1 en 1, no se reinicia por año y nunca se reutiliza. Avisos que
  no bloquean: cantidad distinta de la emitida, emisión vencida, versión nueva de la ficha. La
  especificación es OPCIONAL por ítem: un ítem sin ella nunca bloquea lista, solicitud,
  cotización, presupuesto ni PDF. Los impresos citan el NET de la emisión vigente
  (`ProyectoItem::referenciaEspecificacion()`), salvo el PDF de la solicitud de cotización, que
  desde el 2026-10-20 no lo cita (tampoco rubro, nombre del proyecto, edificio ni bloques: solo
  "Unidad" = facultad). Solo se enlazan fichas activas; una en uso o
  emitida no se elimina, se desactiva
- Formulario NET en PDF (`especificacion/formulario`) con el formato institucional (tabla
  Especificación | Propuesta | Cumple | No cumple, secciones I, II y III); marca de agua
  REEMPLAZADO / ANULADO / VENCIDO. Registro de todas las emisiones en el menú "Formularios NET"
  (reemplaza el Excel "1000 Especificaciones"), con exportación a CSV
- Carga inicial desde el banco de .docx de DTIC: `php yii especificacion/importar "<carpeta>"`
  (sin `--confirmar` solo escribe un CSV; `--desde=AAAA`). Agrupa por título + modelo sugerido y
  toma la emisión más reciente de cada familia. No importa emisiones antiguas
- Cotizaciones de proveedores (relación N:N con ítems/especificaciones). Una línea que viene de
  una solicitud guarda contra qué NET cotizó (`CotizacionDetalle.idEspecificacionEmision`, no
  cambia si el NET se reemite); una cotización suelta solo elige la ficha (`idEspecificacion`)
- Textos fijos del PDF de la solicitud de cotización (cabecera, instrucciones, nota final) en
  `redes.ConfiguracionSolicitudCotizacion` (una sola fila, igual que la del NET), editables en
  "Configuración del documento" (`solicitud-cotizacion/configuracion`). La solicitud NO guarda
  copia: un cambio se ve también al reimprimir las anteriores. Título, columnas y bloque del
  proveedor siguen fijos en `backend/views/solicitud-cotizacion/pdf.php`
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
- Tests unitarios: `php vendor/bin/codecept run Unit -c backend`. Usan la base LOCAL de
  desarrollo (`common/config/test-local.php`, no se sube a git); cada test corre en una
  transacción que se deshace al terminar, así que no dejan datos
- URL local del backend: http://presupuestos-redes.test:8080
- Usuario de prueba local: admin / admin123 (SOLO en tabla `dbo.user` de desarrollo,
  temporal hasta integrar autenticación institucional real)

## Riesgos y pendientes activos
- RESUELTO: las migraciones están aplicadas (verificado con `php yii migrate/history` el
  2026-10-19; la última es `m261020_030140_crear_tipo_proyecto`), incluida `m260831_014824_create_schema_redes`, las dos de partida
  (`m261005_033949_add_partida_to_subcatalogo_item`,
  `m261005_034753_add_partida_observacion_to_presupuesto_detalle`) y las de especificaciones
  (`m261015_035448_libreria_especificaciones`, `m261017_231816_versiones_y_emisiones_especificacion`).
- PENDIENTE (encargado + Ing. de DTIC): fijar el NET inicial en Especificaciones →
  "Configuración del formulario NET" (el Excel del Ing. va por ~890; confirmar el número). Hasta
  entonces la lista no deja enlazar especificaciones. Revisar la nota final del formulario
  ("Señor proveedor ser evaluado…": parece faltarle "para"; está tal cual el formulario oficial)
- PENDIENTE: las 165 fichas importadas del banco (2026-10-18, `--desde=2025`) están SIN categoría
  (filtro "— Sin categoría —" de la librería); al editar una, el formulario exige categoría.
  RESUELTO (2026-10-18): la ficha id 378 se llamaba "CÁMARA PTZ" por un error del .docx
  `840 NVR NVR1108HS-8P-S3_H` del banco; ahora es "NVR de 8 Canales PoE" (Dispositivos de
  comunicacion). Conviene revisar el CSV de la importación con el Ing.
- PENDIENTE (opcional): logo del formulario NET en `backend/web/img/logo-formulario-net.png`
- Preparado para el servidor de DTIC (2026-10-18; guía en `docs/guia-instalacion-dtic.md`):
  - Toda la aplicación exige sesión (`'as access'` en `backend/config/main.php`); solo
    `site/login` y `site/error` son públicos. Un controlador nuevo queda protegido solo.
  - Archivos subidos en `backend/uploads/` (FUERA de `backend/web`), entregados por
    `cotizacion/documento` y `especificacion/adjunto`; `ArchivoSubido::rutaAbsoluta()` rechaza
    rutas fuera de esa carpeta (el adjunto se puede escribir a mano).
  - Zona horaria `America/La_Paz` (`common/config/main.php`, también `formatter.defaultTimeZone`).
  - Historial de migraciones en `redes.migration` (`console/controllers/MigrateController.php`;
    copió el de `dbo.migration`, que en la base local se puede borrar). Las migraciones de la
    plantilla (`dbo.user`) solo crean la tabla en desarrollo (`YII_ENV_DEV`).
  - mPDF escribe sus temporales en `@runtime/mpdf`, no en `vendor/`.
  - Probado: las 29 migraciones desde cero y los tests, sobre una base temporal con
    compatibilidad 110 (SQL Server 2012). La base local es SQL Server 2022 (compatibilidad 160).
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
- PENDIENTE (tutor): especificaciones con versiones y formularios NET cambian el diagrama de
  clases (Especificacion se divide en ficha, versión, característica y emisión, más la
  configuración), agregan secuencias "Emitir" y "Reemitir formulario NET", casos de uso y un
  diagrama de actividades del ciclo de vida de una emisión; resumen en
  `docs/cambios-especificaciones.md`
- PENDIENTE (tutor): agregar `ConfiguracionSolicitudCotizacion` (2026-10-19) al diagrama de clases
- PENDIENTE (tutor): agregar `TipoProyecto` (2026-10-19) al diagrama de clases, asociado a
  Proyecto y a Plantilla (0..1 tipo por proyecto/plantilla)
- PENDIENTE (Ing. de DTIC): confirmar los tipos de proyecto reales; los 4 cargados
  (Ampliación de red, Cableado estructurado, Videovigilancia, Equipamiento activo) venían de
  los mockups
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
  (calculado). Restricción {congelado} en precioUnitario de PresupuestoDetalle.
  ACTUALIZACIÓN 2026-10-18 (pendiente de dibujar, ver `docs/cambios-especificaciones.md`):
  Especificacion pasa a ser Especificacion (ficha) ◆— EspecificacionVersion ◆—
  EspecificacionCaracteristica, más EspecificacionEmision (auto-relación reemplazadaPor,
  copias {congelado}, enumerado EstadoEmision) y ConfiguracionEspecificacion
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