# Proyecto: Sistema de Presupuestos de Instalaciones de Redes (USFX - DTIC)

## Qué es este proyecto
Proyecto de grado: sistema en línea para elaborar y gestionar presupuestos de
instalaciones de redes en la Universidad San Francisco Xavier de Chuquisaca (USFX),
para la Unidad de Redes y Telecomunicaciones de DTIC.

## Stack tecnológico (NO cambiar sin autorización del tutor académico)
- Backend: PHP 8.3 + Yii2 Advanced (v2.0.55). Desde el 2026-11-02 (la Ing. de DTIC pidió PHP 8.3 para el
  servidor de pruebas): `composer.json` exige `>=8.3` y PHPStan analiza como 8.3 (`phpVersion: 80300`)
- Frontend: jQuery, validación en frontend Y backend
- Base de datos: SQL Server (institución usa 2012; entorno local puede diferir)
- Servidor web real de la institución: Nginx (confirmado con el equipo técnico)
- SO del servidor: Ubuntu Server (Linux, case-sensitive)
- Entorno local de desarrollo: Laragon con PHP 8.3.33 (`C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64`, mismo
  php.ini que el 8.2.33 de antes), Nginx en puerto 8080
- Driver SQL Server para PHP: msphpsql v5.12.0 (soporta PHP 8.1 a 8.3)
- Modelo lógico de datos (diagramas ER en Mermaid + diccionario generado de la base): `docs/modelo-logico-datos.md`.
  Al agregar o cambiar tablas, actualizarlo

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
- CODIFICACIÓN según el lineamiento de la DTIC RD-GD-LI-001 (2026-10-22; resumen y decisiones en
  `docs/cambios-codificacion-lineamiento.md`): `RD-PI-PT-NNN` proyecto, `RD-<proceso>-PR-NNN` presupuesto,
  `RD-<proceso>-ET-NNN` especificación técnica (3 cifras; después del 999 sigue con 1000). Desde la
  revisión del 2026-10-29 (paso 4, migración `m261030_024446_especificacion_con_proceso_y_anio`): PR y ET
  llevan el proceso DEL PROYECTO (el PT siempre PI), con UN solo número para todos los procesos. NINGÚN código
  lleva el AÑO ni se reinicia: desde el 2026-11-10 (migración `m261110_060000_especificacion_sin_anio`) la ET
  tampoco ("RD-PI-ET-033", no "/26"; del 29-10 al 10-11 llevaba "/26" y volvía a 001 cada 1 de enero), sigue
  subiendo (999, 1000…); también las ya emitidas salen sin "/26". Se quitaron `CorrelativoDocumento.anio`,
  `reiniciaPorAnio()` y `anioActual()`; `EspecificacionEmision.anio` queda solo como dato (filtro "Año" del
  registro, código de los NET viejos) y el registro todavía encuentra "RD-PI-ET-033/26" (lo impreso). Lo emitido
  conserva su código: `EspecificacionEmision.proceso` congelado (reemitir da el mismo código, versión + 1); el
  presupuesto guarda el código entero en `numeroPresupuesto`
  (`Presupuesto::codigoProceso()`). La línea "Proceso:" de la cabecera de los PDF sale de
  `Proceso::nombreDe()` con ese código. Los números los da
  el sistema desde `redes.CorrelativoDocumento` (una fila por tipo, `CodificacionService::tomar()` con
  UPDLOCK); el encargado fija desde cuál siguen en el menú "Codificación de documentos" (solo mayor que
  el último usado). El formato está en `CorrelativoDocumento::codigo()`. Desde el 2026-11-09 (formatos
  corregidos v3) la SOLICITUD DE PROFORMA lleva código `RD-<proceso>-SC-NNN` (`SolicitudCotizacion.codigoDocumento`,
  correlativo `CorrelativoDocumento::TIPO_SOLICITUD`, sin año, un número para todos los procesos; lo pone
  `SolicitudCotizacionService` al crearla y no cambia; versión fija 1.0; migración
  `m261110_010000_codigo_solicitud_cotizacion`, que numeró las existentes en orden de creación). "SC-01" (`codigoCorto()`)
  y "001 · Fase 2 / SC-01" (`codigo()`) siguen solo en las pantallas. Letra Carlito (= medidas de Calibri, OFL,
  `backend/fonts/carlito`) en todos los impresos menos la carta
- Gestión de proyectos (`redes.Proyecto`; código RD-PI-PT-NNN del correlativo PT al crearlo, los
  existentes se renumeraron a 3 cifras)
- Unidad solicitante y área de intervención (2026-10-28, revisión con el Ing. del 2026-10-27; resumen
  en `docs/cambios-unidad-area-intervencion.md`). Reemplazan a "Facultad / Edificio / Bloques":
  - DOS unidades (revisión del 2026-10-29, migración `m261029_232347_agregar_unidad_dependencia_a_proyecto`),
    ambas obligatorias, pueden ser la misma o distintas:
    `Proyecto.unidadDependencia` ("Unidad / dependencia") = la BENEFICIARIA, a dónde va el proyecto; es la que
    sale en los documentos (formulario de especificación "Dependencia", solicitud "Unidad", lista, presupuesto).
    `Proyecto.unidadSolicitante` = QUIÉN pide, tal como la nota (ej. el Rector pide cámaras para el Canal
    Universitario); sale en la vista del proyecto, la carta de entrega y (pendiente) la ficha técnica. Los
    proyectos de antes copiaron la solicitante en la dependencia. Las dos son texto libre (facultad, carrera,
    oficina o persona). SIN catálogo: un mismo `<datalist>` sugiere lo ya escrito en cualquiera de las dos y
    las facultades del JSON (`ProyectoService::sugerenciasUnidad()`). NUNCA gestionar unidades aquí
  - `redes.ProyectoAreaIntervencion` (0..* por proyecto): DÓNDE se trabaja, edificio → bloque →
    nivel → ambiente (en pantalla se llama "Espacio" desde el 2026-10-29, como en redes; en el código y la
    base sigue siendo `ambiente`) (llega hasta donde haga falta, no se salta un nivel), elegido de la base
    `UsfxGEO` de Desarrollo, SOLO LECTURA y solo Estado 'V' (`InfraestructuraProvider`,
    `UsfxGeoInfraestructuraProvider`, conexión `dbGeo` en `main-local.php`). Guarda los id de
    UsfxGEO (referencia lógica, como idSigma) + COPIA congelada de los nombres; una ya guardada se
    conserva aunque la den de baja. Sin "agregar" edificios ni ambientes a UsfxGEO, PERO desde el 2026-10-29
    (pedido del Ing.: edificios en construcción o aún no registrados) un área se puede ESCRIBIR A MANO
    ("¿No está en la lista? Escríbala a mano", copia lo elegido en el paso a paso): sin ningún id
    (`idEdificio` NULL = a mano, `ProyectoAreaIntervencion::esManual()`; migración
    `m261030_001726_area_intervencion_escrita_a_mano`), solo los nombres; edificio obligatorio, sin repetidas
    (por nombre, sin mayúsculas); marca "a mano" en formulario y vista, en los documentos sale igual. Formulario con buscador
    (`proyecto/buscar-ambiente`, `ProyectoService::buscarAreas()`: primero los EDIFICIOS que coinciden,
    que se ponen en el paso a paso, y luego hasta 30 ambientes con aviso "hay más"; ignora tildes y
    guiones, "E303" = "E-303") y paso a paso: el EDIFICIO no es un `<select>` (con 134 la lista del
    navegador se abría muy alta) sino un campo que filtra al escribir, con lista propia debajo y
    los edificios ya usados en proyectos primero (`ProyectoService::edificiosParaFormulario()`, 2026-10-28),
    a todo el ancho; debajo bloque y piso (selects) y AMBIENTE (otro campo que filtra, `crearCombo()` en
    `web/js/proyecto-form.js`), en cascada (`proyecto/bloques|niveles|ambientes`). Resultados del buscador en
    dos renglones (ambiente arriba, edificio · bloque · piso abajo). Las áreas elegidas se ven AGRUPADAS por
    edificio · bloque, una línea por área (`ProyectoAreaIntervencion::grupo()` y `detalle()`). Desde el 2026-10-29 el
    grupo NO lleva rótulo "Edificio:" (en los documentos ya va después de "Área de intervención:") y usa el
    nombre de UsfxGEO tal cual (quitarle el "Edificio " inicial dejaba nombres como "II (ExCasona)" o "Carrera
    de Pedagogia", que parece la unidad); piso y ambiente van con dos puntos: "Edificio Carrera de Pedagogia ·
    Bloque A" / "Piso 1: A100 Gradas"; en los documentos "Edificio X · Bloque B: B101, B102; Edificio Y"
  - Texto para documentos: `Proyecto::areaIntervencionTexto()` (agrupa por edificio y bloque). Los
    proyectos de antes conservan `facultad`, `edificio` y `redes.ProyectoBloque` (ya no se escriben,
    no se borraron) y muestran esa "ubicación anterior" hasta que se les elijan áreas
- Subcatálogo curado de ítems de redes, con referencia lógica a `dbo.CatalogoSigma.IdSigma`
  (NUNCA duplicar el catálogo completo; selección manual/bajo demanda, no clasificación automática).
  Un ítem INACTIVO no se elige (2026-10-21): `SubcatalogoItemService::opciones($conservar)` trae solo
  activos más los que el registro ya tenía; la ficha de especificación lo valida (ítem de referencia)
  y "Recordar como sugerida" lo ignora. Las líneas de cotización solo filtran el desplegable, sin
  validar: una solicitud puede traer un ítem de la lista que se desactivó después
- Nombre DTIC vs. nombre SIGMA: `SubcatalogoItem.descripcion` es el nombre DTIC (corto y
  genérico, el que sale en listados, solicitud de cotización y pantallas), guardado SIEMPRE en
  MAYÚSCULAS (2026-10-20: regla "filter" del modelo + migración `m261020_063033_nombre_dtic_en_mayusculas`
  para los existentes). Al elegir un ítem desde SIGMA el campo se prellena con la `Descripcion`
de SIGMA en mayúsculas (antes con la Clase; pedido del Ing. el 2026-10-20) y el usuario la
edita para darle el estilo DTIC. También el PDF del presupuesto muestra SOLO el nombre DTIC (2026-10-20:
  el nombre SIGMA no sale en ningún impreso). El nombre oficial SIGMA no se copia:
  se lee con la relación `getCatalogoSigma()`. Varios ítems DTIC pueden apuntar al mismo
  `idSigma` (SIGMA es genérico); se avisa "ya usado por: ..." pero NO se bloquea
- Partida presupuestaria (5 dígitos, `dbo.CatalogoSigma.IdGasto`): columna `partida` en
  `redes.SubcatalogoItem` (copiada desde SIGMA al guardar, o a mano si el ítem no viene de
  SIGMA) y en `redes.PresupuestoDetalle` (CONGELADA al crear la línea, igual que el precio).
  Categoría y partida son datos distintos: una categoría DTIC mezcla varias partidas
- Unidades de medida: catálogo `redes.UnidadMedida` (nombre, abreviatura, activo).
  `SubcatalogoItem.idUnidadMedida` es FK; `ProyectoItem.unidadMedida`,
  y `PresupuestoDetalle.unidadMedida` guardan la ABREVIATURA como texto
  (congelada al crear la fila, como el precio y la partida). Se muestra la abreviatura en
  listados y PDF. Solo se eligen unidades activas; una en uso no se elimina, se desactiva
- Tipos de proyecto: catálogo `redes.TipoProyecto` (nombre, activo), administrado por el
  encargado en el menú "Tipos de proyecto" (decidido el 2026-10-19; antes estaban en
  `backend/config/parametros-proyecto.json`). `Proyecto.idTipoProyecto` y
  `Plantilla.idTipoProyecto` son FK. Solo se eligen tipos activos, pero quien ya tenía uno
  desactivado lo conserva (`TipoProyecto::reglaElegible()`); uno en uso no se elimina, se
  desactiva. En el JSON quedan SOLO las facultades (codigo + nombre), como sugerencia de unidad
  solicitante; sus edificios y bloques se quitaron el 2026-10-28. Desde la revisión del 2026-10-29 un
  tipo dice QUÉ se hace, no el sistema que se instala: Ampliación, Mejoramiento, Refuncionalización y
  Equipamiento (migración `m261029_230226_tipos_de_proyecto_segun_revision`; Cableado estructurado y
  Videovigilancia salen: se borran si nadie los usa y se desactivan si algún proyecto los tiene)
- Procesos (revisión del 2026-10-29): catálogo `redes.Proceso` (código de 2 letras mayúsculas, único;
  nombre único; activo), cargado con la tabla 4 (8.5) del lineamiento: GD, DC, PI, DS, GR; en
  Configuración → "Procesos" (`ProcesoService`, migración `m261030_002958_crear_proceso`). Todo lo que se
  presupuesta va como proyecto, pero no siempre lo lleva PI: `Proyecto.idProceso` (FK, obligatorio en el
  formulario; NULL permitido en la base, como idTipoProyecto) dice qué proceso. Un proyecto nuevo trae PI
  (`ProcesoService::idPorDefecto()`); la fase siguiente hereda el de la anterior; los existentes quedaron en
  PI. En uso (por proyectos, especificaciones emitidas o presupuestos con su código): no se elimina (se
  desactiva) y su CÓDIGO no se cambia (va en el código de los documentos, ver CODIFICACIÓN); quien ya lo
  tenía lo conserva (`Proceso::reglaElegible()`)
- EQUIPO DEL PROYECTO (revisión del 2026-10-29, paso 5; migración `m261030_030403_equipo_del_proyecto`;
  antes, desde el 2026-10-28, solo "Proyectistas" con `redes.Proyectista` / `ProyectoProyectista`, que se
  borraron): quiénes trabajan en el proyecto y con qué rol, para la ficha técnica.
  - `redes.Personal` (nombre único, activo; Configuración → "Personal", `PersonalService`): solo una lista de
    NOMBRES, no usuarios ni permisos, y SIN rol fijo (el rol es de cada participación). Cargó los 2 proyectistas.
    El Ing. quiere que después venga del sistema institucional (web service); hasta entonces se administra aquí.
  - `redes.RolProyecto` (nombre único, activo; Configuración → "Roles del proyecto", `RolProyectoService`):
    Proyectista, Director del proyecto, Coordinador de infraestructura, Responsable de despliegue, Responsable de
    administración, Supervisor (los que nombró el Ing.). No da permisos en el sistema.
  - `redes.ProyectoParticipante` (0..* por proyecto, CASCADE): COPIA del nombre (sin FK, como antes: corregir o
    desactivar a la persona no cambia los proyectos; "en uso" se cuenta por nombre) + `idRol` (FK) +
    `esResponsable` + `orden`. Reglas en `ProyectoService::armarEquipo()`: al menos una persona y UNA responsable
    (no tiene que ser proyectista: un mantenimiento del data center lo lleva quien lo administra; índice único
    filtrado), persona y rol activos salvo los que el proyecto ya tenía o hereda, una persona puede tener dos
    roles pero no el mismo dos veces, fila en blanco se ignora. `Proyecto::responsable()`.
  - Formulario: tabla "Equipo del proyecto" (persona | rol | responsable | quitar; `Proyecto::$equipo` y
    `$equipoResponsable`, fila modelo `<template>` en `_form.php`, JS en `proyecto-form.js`). Proyecto nuevo (o
    uno de antes sin equipo, al editarlo) empieza con una fila de rol Proyectista marcada como responsable. La
    FASE SIGUIENTE hereda el equipo con su responsable y se puede cambiar
  - Quien crea el proyecto debería quedar como proyectista (pedido del Ing.): PENDIENTE hasta la autenticación
    institucional (los usuarios temporales no tienen nombre completo)
- HISTORIAL Y FECHAS DEL PROYECTO (revisión del 2026-10-29, paso 6; migración
  `m261030_032255_crear_historial_proyecto`): "cualquier cambio se tiene que poder consultar".
  `redes.HistorialProyecto`, una fila por hecho, nunca se modifica ni se borra: fecha y hora (Bolivia), `usuario`
  (COPIA del nombre de usuario; NULL = "(antes del historial)", también en consola y tests), `idProyecto` SIN FK +
  `codigoProyecto` (si se elimina el proyecto, el historial queda), `accion` (`HistorialProyecto::ACCION_*`),
  `estado` (nuevo estado del presupuesto, solo en sus cambios de estado) y `detalle` (texto, una línea por cambio).
  Se escribe desde los SERVICIOS, dentro de su transacción (`HistorialService::registrar()`), no con eventos de
  ActiveRecord (el sistema usa mucho updateAll/deleteAll). Se anotan: proyecto creado / eliminado; datos del
  proyecto cambiados, SOLO lo que cambió, "Rótulo: antes → después" (`HistorialService::foto()` y `cambios()`:
  nombre, descripción, tipo, proceso, unidades, área de intervención, equipo, fase anterior); presupuesto creado;
  cada cambio de estado ("RD-PI-PR-016: En revisión → Aprobado", `PresupuestoService::anotarEstado()`); PRECIOS
  elegidos o cambiados con el anterior ("Switch: 1,250.00 (Proveedor A) → 1,180.00 (Proveedor B)"), un registro
  por operación (elegir, atajos, menor precio al entrar; `ElaborarPresupuestoService::anotarPrecios()`); cambiar
  solo la fuente o la justificación no se anota. La migración cargó lo conocido sin usuario: creación de cada
  proyecto y presupuesto y la aprobación de los aprobados. Lista de ítems, especificaciones y cotizaciones TODAVÍA
  no se anotan (se pueden agregar igual: un `registrar()` en su servicio). Vista del proyecto: recuadro "Fechas"
  (`HistorialService::fechas()`: creación = `Proyecto.fechaCreacion`, revisión = último paso a "en revisión",
  aprobación = `Presupuesto.fechaAprobacion`, última modificación = último hecho y quién) y sección "Historial"
- FICHA TÉCNICA DEL PROYECTO (revisión del 2026-10-29, paso 7; desde el 2026-11-05 documento normado, ver PARTE 4 arriba;
  lo que sigue es lo de antes): PDF oficio desde el botón "Imprimir ficha técnica" de la tarjeta "Datos generales" de la vista
  del proyecto (`proyecto/ficha`, `ProyectoController::actionFicha()`, vista `proyecto/ficha-pdf.php`, datos de
  `ProyectoService::datosFicha()`), se abre en el navegador para imprimir. Secciones: 1 datos (código, nombre,
  descripción, tipo, proceso, fase), 2 unidades, 3 área de intervención (agrupada), 4 equipo (persona, rol,
  responsable), 5 fechas (las de `HistorialService::fechas()`), 6 documentos de la carpeta económica (presupuesto
  con estado y total, especificaciones vigentes, solicitudes de cotización); al pie, cuándo y quién la imprimió.
  "Es un reporte de control, no de la gestión documental": cabecera SIN código del lineamiento (la de la
  solicitud: código del proyecto | unidad / dependencia | fecha de impresión | página), sin firmas ni marca de agua
- REVISIÓN DEL 2026-11-05, PARTE 1 (acta `Desktoprchivos guia\A1cuerdos_revision_ficha_proyecto.md`): VERSIÓN
  "1.0" en todos los documentos (`backend\components\VersionDocumento`: la versión 1 se escribe "1.0", la 2 "1.1"…;
  en la base sigue el número corrido; el buscador del registro entiende "v1.1" y "v02"; antes "01", "02").
  ÁREA DE INTERVENCIÓN en documentos = UNA línea por edificio, sin repetirlo, con bloque, piso y espacio juntos
  (`ProyectoAreaIntervencion::lineasPorEdificio()`; bloques enteros juntos: "Bloque A y Bloque B"; `resumen()` las une
  con " | " para el historial). El presupuesto y la lista ya NO llevan unidad ni área (van en la ficha técnica); el
  presupuesto ya no pide área al enviarlo a revisión (`Presupuesto.areaIntervencion` queda solo con lo de antes).
  Formulario ET: nada por debajo de 11 pt
- REVISIÓN DEL 2026-11-05, PARTE 2 (punto 7; migración `m261106_030000_codigo_lista_materiales`): la LISTA DE MATERIALES
  NO SE APRUEBA (la que se aprueba es el presupuesto) y tiene CÓDIGO PROPIO `RD-<proceso>-LM-NNN`
  (`Proyecto.codigoLista`, correlativo `CorrelativoDocumento::TIPO_LISTA`, sin año; antes llevaba el del proyecto). Lo pone
  `ProyectoService` al crear el proyecto (cada fase es su propio proyecto, su propia lista); si se cambia el proceso antes del
  primer envío a cotización, el código cambia de proceso con el mismo número; después queda fijo. Los proyectos existentes
  recibieron LM-001… en orden de creación. "Enviar a cotización" EMITE la versión (`ListaItemsService::emitirVersion()`;
  `ListaMaterialesAprobacion` conserva su nombre: una fila = versión enviada, `fechaAprobacion` = fecha del envío). El PDF
  lleva solo "ELABORADO POR" y "Versión: x" (`_control-elaboracion` con `soloElaborado`). Lo que se dice abajo de la
  aprobación de la lista (paso 8b) quedó así reemplazado
- ORDEN DE CAMBIO (revisión del 2026-11-05 punto 4, PARTE 6b, 2026-11-09; formato RD-GD-FR-006; migración
  `m261110_030000_orden_de_cambio`; decidido con el desarrollador el 2026-11-09): un PRESUPUESTO ADICIONAL dentro de una
  fase APROBADA, solo con lo que se agrega ("100 m de cable comprados, piden 50 m más"). OPCIÓN B: es una FILA MÁS de
  `redes.Proyecto`, con el mismo `numeroProyecto` y `numeroFase` que su fase, `idFaseOrigen` (la fase) y
  `numeroOrdenCambio` (1, 2…; índice único número + fase + orden). Así tiene sola su lista (código LM propio), sus ET
  (EMISIONES PROPIAS con la cantidad de la orden; la ET de la fase no se toca), solicitudes (SC), presupuesto (PR) y carta,
  y sigue los mismos pasos. `Proyecto::esOrdenCambio()`, `textoOrdenCambio()` ("Orden de cambio N.° 1"), relaciones
  `faseOrigen` y `ordenesCambio`. NO es una fase: `getFases()` la excluye, `esUltimaFase()` = false, no cuenta en
  `tieneVariasFases()` ni en la versión de la ficha técnica (aprobarla no la cambia), no tiene código PT (usa
  `codigoFicha()` de la fase 1). `identificador()` = "Proyecto 001 · Fase 1 · Orden de cambio N.° 1"; `lineaDocumento()` =
  "PROYECTO: 001 – NOMBRE (FASE 1) – ORDEN DE CAMBIO N.° 1" (propuesta, a confirmar con el Ing.); SC en pantalla "001 · OC 1 /
  SC-01". Del FR-006 se guardan SOLO tipo, descripción y motivo (`idTipoOrdenCambio`, `descripcionCambio`,
  `motivoCambio`; escenario `Proyecto::SCENARIO_ORDEN_CAMBIO`); el FR-006 se hace en Word y se le adjunta el presupuesto. El
  TIPO es un catálogo (pedido del desarrollador el 2026-11-09; migración `m261110_040000_crear_tipo_orden_cambio`):
  `redes.TipoOrdenCambio` (nombre único, activo), cargado con la lista 7.1.1 del FR-006 tal cual (Nuevas solicitudes de los
  usuarios; Errores u omisiones en la planificación y propuestas técnicas; Presupuestos imprecisos; Cambios en el alcance de
  los proyectos; Costos de recursos; Eventos no planificados), administrado en Configuración → "Tipos de orden de cambio"
  (`TipoOrdenCambioService`); obligatorio en la orden; uno en uso no se elimina (se desactiva) y quien lo tenía lo conserva
  (`TipoOrdenCambio::reglaElegible()`). Reglas en `OrdenCambioFaseService` (no confundir con `OrdenCambioService` = reabrir la
  lista): se registra solo sobre una FASE con presupuesto aprobado (botón "Registrar orden de cambio" en la vista,
  `proyecto/orden-cambio?idFase=`); copia de la fase los datos del proyecto, el área, el equipo y el lugar de entrega (fijos:
  `datosComunesFijos()`; el formulario general del proyecto no la edita, "Editar datos" lleva a
  `proyecto/editar-orden-cambio`); se edita mientras su presupuesto no esté aprobado ni cancelado; si NO se aprueba se
  ELIMINA con lista, solicitudes, cotizaciones y presupuesto, sus ET quedan ANULADAS y pasan a la fase (una emisión siempre
  es de un proyecto), los códigos LM/SC/PR/ET no se reutilizan; una aprobada no se elimina. Historial en la orden y en la fase
  (`ACCION_ORDEN_CAMBIO_CREADA` / `_ELIMINADA`). Vista: "Fases del proyecto" con las órdenes debajo de su fase; listado con
  "OC n". En la ficha técnica va dentro de la carpeta de su fase y suma al COSTO TOTAL (parte 6c, ver FICHA TÉCNICA V3).
  PENDIENTE: confirmar con el Ing. la línea del proyecto y el código LM propio de la orden
- PIES DE FIRMA EN TABLA (2026-11-09, decidido por el desarrollador, como los Word de FORMATOS CORREGIDOS v3):
  `views/pdf/_control-elaboracion.php` (presupuesto, formulario ET y lista de materiales) es una tabla: fila gris
  "| ELABORADO POR | APROBADO POR", filas "Firma:" (2 cm para firmar), "Nombre:" (prefijo + nombre, ej. "V°B° Ing. …") y
  "Cargo:" (negrita), y abajo, de lado a lado en gris claro, "Fecha de aprobación: 23-10-2026" (fecha dd-mm-aaaa). SIN
  VERSIÓN desde el 2026-11-10 (ya está en la cabecera de la página); la lista y la solicitud (una firma) no llevan esa fila. Anchos del Word 12% | 38% | 50%. La lista de materiales (no se aprueba, `soloElaborado`): solo "ELABORADO
  POR" a TODO EL ANCHO de la hoja (indicación del Ing., 2026-11-09: con una sola firma ocupa todo el ancho), y abajo solo la versión. El formulario ET la pide en 11 pt (`letraGrande`); los demás en 9/10 pt. Las
  firmas siguen saliendo de las mismas configuraciones y copias congeladas que antes
- FICHA TÉCNICA V3 (PARTE 6c, 2026-11-09; el Word "00 PT RD-PI-PT-001" de FORMATOS CORREGIDOS v3; reemplaza el orden de
  las partes 4 y 5d): `proyecto/ficha-pdf.php` + `ProyectoService::datosFicha()`. 1. INFORMACIÓN GENERAL (n.° de proyecto,
  nombre EN MAYÚSCULAS, descripción, tipo, proceso, "Fases" si hay varias, COSTO TOTAL DEL PROYECTO y las FECHAS en el mismo
  cuadro: con fase única filas "Fecha de creación / revisión / aprobación / última modificación", con varias un subcuadro
  "Fechas | Fase 1 | Fase 2"), 2. UNIDADES / DEPENDENCIA (título desde el 2026-11-10), y después con fase única 3 área, 4 equipo, 5 CARPETA ECONÓMICA; con varias,
  desde el 2026-11-11, "3. FASES" y dentro "3.1 FASE 1" (3.1.1 área, 3.1.2 equipo, 3.1.3 carpeta), "3.2 FASE 2" (3.2.1…)… (del 09 al
  11-11 "3. FASE 1" (3.1, 3.2, 3.3), "4. FASE 2"…). Desde el 2026-11-11 los títulos de PRIMER nivel ("1. INFORMACIÓN GENERAL",
  "2. UNIDADES / DEPENDENCIA", "3. FASES" o, con fase única, "3. ÁREA DE INTERVENCIÓN"…) y los de cada fase ("3.1 FASE 1") van
  en MAYÚSCULAS; los de tercer nivel ("3.1.1 Área de intervención") no CARPETA = tabla Documento | Código | Detalle | Versión
  (`ProyectoService::carpeta()`): lista de materiales (rótulo "Lista de materiales y condiciones previas (BoM)" desde el 2026-11-10; "Elaborada el …"), presupuesto ("Aprobado el … · Total Bs." o su estado),
  especificaciones vigentes (con "Reemitida el …: motivo" si se reemitieron; el motivo está en la emisión reemplazada), solicitudes
  de proforma (rubro · fecha) y PROFORMAS RECIBIDAS (una fila por cotización: proveedor · fecha; solo si hay). Las ÓRDENES DE
  CAMBIO de la fase van dentro de su carpeta: barra gris "Orden de cambio N.° 1 · solicitada el … · aprobada el …" + "Motivo: …"
  y sus documentos. COSTO TOTAL = `ProyectoService::costoTotal()`: suma de los presupuestos APROBADOS de fases y órdenes, con el
  detalle "Fase 1: 5,732.50 + orden de cambio N.° 1: 1,870.00 + fase 2: 3,026.00 (presupuestos aprobados)" (fase única:
  "Presupuesto aprobado"); también en la vista del proyecto (recuadro de fases). En la FICHA, desde el 2026-11-11
  (sugerencia del Ing.), con varias fases (o con órdenes de cambio aprobadas) ese detalle va en FILAS dentro de la misma
  columna: "Fase 1 | Bs. 1,000.00", "Fase 2 | Bs. 2,500.00" (cada orden después de su fase) y al final "Total (presupuestos
  aprobados) | Bs. 3,500.00"; el rótulo "Costo total del proyecto" ocupa todas esas filas (rowspan). SIN FIRMAS (sale el control de elaboración que
  tenía desde la parte 4); sigue la marca BORRADOR mientras alguna fase esté sin aprobar. Fechas dd-mm-aaaa; carpeta en 10 pt;
  al pie "Impresa el … por …"
- REABRIR LA LISTA (PARTE 6a, 2026-11-09): lo que el sistema llamaba "orden de cambio" (reabrir una lista enviada a
  cotización, con su motivo: `redes.OrdenCambio`, `OrdenCambioService`, `lista-items/solicitar-cambio`) en pantalla se
  llama ahora "reabrir la lista" / "Reaperturas de la lista". La tabla y las clases conservan su nombre. La ORDEN DE
  CAMBIO del formato RD-GD-FR-006 es otra cosa: un presupuesto adicional de una fase aprobada (parte 6)
- REVISIÓN DEL 2026-11-05, PARTE 3: el LUGAR DE ENTREGA es de CADA especificación (pueden ser distintos). Se escribe en la
  fila de la lista, debajo del selector de especificación (`items[n][lugarEntrega]`, `ProyectoItem::$lugarEntregaPedido`, no
  es columna: se guarda en `EspecificacionEmision.lugarEntrega`). Formulario nuevo: toma el de la fila (vacío = el del
  proyecto). Uno ya emitido con otro lugar se REEMITE al guardar (mismo código, versión + 1, motivo "Se cambió el lugar de
  entrega: A → B"; junto con "Cambiar ítem" es una sola reemisión, `ListaItemsService::reemitirPorCambio()`); vacío no
  cambia nada. `Proyecto.lugarEntrega` queda como "Lugar de entrega por defecto" y SALE de la ficha técnica. DESDE EL 2026-11-10 ("no quieren que esté en crear un proyecto") ese lugar por defecto ya NO
  se pide en el formulario del proyecto ni sale en su vista: se escribe ARRIBA DE LA LISTA DE ÍTEMS ("Lugar de entrega de los
  materiales", `lugarEntregaProyecto`), se guarda con el mismo botón, ANTES de emitir y en la
  misma transacción (`ListaItemsService::guardar(..., $lugarEntrega)`, null = no se toca, '' = se borra; queda en el
  historial); si el proyecto no tiene, el campo trae la sugerencia `Proyecto::lugarEntregaSugerido()` y se guarda al guardar la
  lista. Cada fila sigue pudiendo poner otro; al escribir arriba se actualiza el texto de ejemplo de las filas (`lista-items.js`).
  La fila va PLEGADA (ocupaba mucho), en la MISMA LÍNEA que "Cambiar ítem": si su lugar es el de arriba (o no tiene), solo
  el enlace "Otro lugar de entrega"; si es otro, "· Entrega: … Cambiar"; el campo aparece debajo de esa línea al hacer clic. Uno igual al de arriba se envía vacío (en un
  formulario ya emitido, vacío no reemite; para volver al de arriba hay que escribirlo).
  `Proyecto` ya no tiene regla para `lugarEntrega` (el formulario del proyecto no lo cambia); la fase siguiente y la orden de
  cambio lo siguen copiando
- NÚMERO DE PROYECTO (revisión del 2026-11-05 puntos 1 y 3; PARTE 5a, migración `m261106_120000_numero_de_proyecto`;
  decidido con el desarrollador el 2026-11-06: OPCIÓN 1, sin tabla nueva). Cada fila de `redes.Proyecto` es una FASE (con
  su área, equipo y carpeta económica); `Proyecto.numeroProyecto` (correlativo `CorrelativoDocumento::TIPO_NUMERO_PROYECTO`
  = 'NP') lo COMPARTEN todas las fases y `numeroFase` las ordena (índice único numeroProyecto + numeroFase). Un proyecto
  nuevo toma el siguiente NP; una fase siguiente, el de la anterior (`ProyectoService::asignarNumero()`), y no se repite una
  fase. Se escribe "001" (`Proyecto::numeroTexto()`, ÚNICO lugar del formato, 3 cifras hasta que el Ing. confirme dígitos o
  prefijo). `Proyecto::identificador()` = "Proyecto 001" o "Proyecto 001 · Fase 2" (migas, títulos, avisos);
  `etiqueta()` = "001 — Nombre"; `tieneVariasFases()` (con fase única no se muestra "Fase"). Los existentes: cada cadena de
  fases recibió un número en orden de creación (local: 26 y 27 = proyecto 001 fases 1 y 2, 28 = 002). VARIAS FASES A LA VEZ
  (dicho por el Ing. de DTIC, 2026-11-09, PARTE 5f): una fase siguiente se crea aunque la anterior no esté aprobada (del 06
  al 09-11 se exigía la anterior APROBADA).
  PARTE 5b ("Agregar fase"): botón en la vista del proyecto y en el paso Presupuesto, en la vista del proyecto en la
  ÚLTIMA fase, aprobada o no; en el paso Presupuesto, como atajo, con ella aprobada (`ProyectoService::puedeAgregarFase()`,
  `Proyecto::esUltimaFase()`); abre `proyecto/create?idAnterior=`
  ("Agregar fase N al proyecto 001"). FRANJA "FASES" (2026-11-10, `proyecto/_franja-fases.php`, `ProyectoService::franjaFases()`):
  encima de la barra de pasos de la vista del proyecto, una pastilla por fase (estado y total de su presupuesto), sus órdenes
  de cambio después ("›"), la que se ve marcada ("· está viendo") y al final "Agregar fase N →"; con varias fases (o con
  órdenes de cambio) el botón "Agregar fase" de la cabecera se quita y queda solo esa pastilla. Con fase única y sin órdenes
  no sale y todo queda como antes. El recuadro "Fases del proyecto" de abajo: con la franja visible, desde el 2026-11-11 ("es doble información") ya no lista las fases y queda solo "Costo del proyecto 001" con el costo total y su detalle; con fase única, igual que antes. TARJETA "DATOS GENERALES" (rediseño del 2026-11-11, clases `rd-dg-*` de `redes.css`, sin JS): descripción entera si es corta; si es LARGA (más de 240 caracteres o con saltos de línea) cortada en 2 líneas con "Ver descripción completa" / "Ver menos" (un <details> vacío que despliega el mismo párrafo con :has(), sin repetir el texto), cuadrícula Tipo · Proceso · unidades (una sola celda si las dos unidades son iguales), nota en las fases 2+: "se editan en la Fase 1" con enlace solo si la fase 1 hoy los deja cambiar (`ProyectoService::faseParaDatosComunes()`), si no "ya no se pueden cambiar (hay una fase aprobada)"; sin :has() en el navegador, la descripción sale entera y sin botón (`@supports not selector(:has(*))`), y debajo de una línea lo DE ESTA FASE ("De esta fase" + Fase N; en una orden de cambio "De la fase" con enlace, área y equipo de `faseOrigen`; con fase única sin subtítulo): área por grupo (3 grupos a la vista + "Ver N áreas más") y equipo con la responsable primero (5 a la vista + "Ver N personas más"; N = lo que aparece al abrir); bajo 900 px, una columna. La fase nueva copia los DATOS DEL PROYECTO (`Proyecto::ATRIBUTOS_COMUNES`: nombre,
  descripción, tipo, proceso y las dos unidades), que en una fase 2+ van DESHABILITADOS y el servidor siempre toma de la fase
  anterior. Se escriben SOLO en la fase 1: al guardarla se COPIAN a las demás fases en la misma transacción, con su historial
  ("Desde la fase 1: …") y el cambio de código LM si cambió el proceso (`ProyectoService::llevarDatosComunesALasFases()`);
  quedan fijos también en la fase 1 cuando OTRA fase está aprobada (`ProyectoService::datosComunesFijos()`); trae el
  equipo y el lugar de entrega por defecto (editables); área y carpeta empiezan VACÍAS. Ya no hay selector "fase anterior"
  en el formulario (se quitó `proyectosAprobados()`); al editar, la fase anterior no cambia. Vista: "Fases del proyecto 001"
  (`Proyecto::$fases`) con el estado de cada presupuesto; listado ordenado por número y fase, "Fase" solo si hay varias.
  PARTE 5c: la ÚNICA línea del proyecto en los documentos es `Proyecto::lineaDocumento()` = "PROYECTO: 001 – NOMBRE EN
  MAYÚSCULAS (FASE 2)" (guion largo; "(FASE n)" solo si hay varias fases; SIN negrilla, a confirmar con el Ing.). La usan la
  lista de materiales, el presupuesto y el reporte de relación con SIGMA. La solicitud de cotización NO la lleva (decidido
  con el desarrollador); el formulario de especificación sigue con su formato (Dependencia, sin línea de proyecto).
  Pendiente: 5d una sola ficha PT por proyecto
- EL CÓDIGO PT ES DE LA FICHA TÉCNICA, no del proyecto (aclarado por el desarrollador el 2026-11-05: el lineamiento cambiará
  "Proyecto técnico" por "Ficha técnica del proyecto"; en "Codificación de documentos" el tipo PT ya se llama así). Hoy se
  guarda en `Proyecto.codigo` porque cada proyecto tiene una sola ficha; el proyecto tendrá su propio número en la parte 5
- UNA SOLA FICHA TÉCNICA POR PROYECTO (PARTE 5d, 2026-11-06; migración `m261106_150000_una_ficha_por_proyecto`): solo la fase
  1 tiene código PT (`Proyecto.codigo`); una fase siguiente no toma uno (NULL) y usa el de su proyecto
  (`Proyecto::codigoFicha()`). La migración quitó el PT de las fases 2+ (local: RD-PI-PT-027) y lo anotó en el historial; el
  número no se reutiliza. La ficha se imprime IGUAL desde cualquier fase (`ProyectoService::datosFicha()`: `fases`, una por
  fase con sus fechas, lista, presupuesto, ET y SC): 1 datos (con "Fases: N"), 2 unidades, 3 fechas (una columna por fase),
  y un bloque "4. FASE 1" (4.1 área, 4.2 equipo, 4.3 documentos), "5. FASE 2"…; con fase única, 4 área, 5 equipo, 6
  documentos. VERSIÓN calculada, sin tabla: cada fase aprobada es una versión (1.0, 1.1…), contadas en el ORDEN EN QUE
  SE APROBARON (5f: la fase 2 puede aprobarse antes que la 1); mientras ALGUNA fase tenga el presupuesto sin aprobar, la
  siguiente en BORRADOR (una fase con presupuesto cancelado no cuenta); aprobada = fecha y firmas del presupuesto aprobado
  más reciente; fecha de cabecera = creación de la fase más nueva. En pantallas la solicitud de cotización se identifica con
  el número: "001 · Fase 2 / SC-01" (`SolicitudCotizacion::codigo()`; en el PDF y la ficha, su código RD-PI-SC-NNN);
  el historial copia `identificador()` en `codigoProyecto`
- REVISIÓN DEL 2026-11-05, PARTE 4 (punto 2): la FICHA TÉCNICA es un DOCUMENTO normado (antes "reporte de control"):
  cabecera del lineamiento (código = el del proyecto RD-PI-PT-NNN hasta la parte 5, versión 1.0, fecha = creación del
  proyecto), orden 1 datos, 2 unidades, 3 fechas, 4 área, 5 equipo, 6 documentos (especificaciones en subtabla Código | Ítem
  | Versión), "Fase" solo si hay más de una (`datosFicha()['variasFases']`), sin lugar de entrega, letra 11, control de
  elaboración (elaborado y aprobado) al pie. Hasta las fases (parte 5) queda APROBADA con el presupuesto (punto de control
  "Revisar y aprobar diseño"): fecha y firmas del presupuesto aprobado; antes, marca BORRADOR. Proformas: pendiente de decidir.
  DESDE EL 2026-11-09 el orden, las proformas y la falta de firmas son los de FICHA TÉCNICA V3 (parte 6c)
- `PresupuestoDetalle.observacion`: texto libre de la línea (medidas, "según especificación");
  los detalles de una compra concreta van ahí, no en el subcatálogo
- Resumen por partida presupuestaria (`PresupuestoService::resumenPorPartida()`), en la vista
  del presupuesto (NO en el PDF desde el 2026-10-20); su total coincide siempre con el total por
  categorías
- Presupuesto como DOCUMENTO VIVO: se elabora en UNA pantalla, el paso "Presupuesto" del
  proyecto (`ElaborarPresupuestoController`, `ElaborarPresupuestoService`). No hay "crear
  presupuesto" ni "agregar líneas": cada ítem de la lista ES una línea (`PresupuestoDetalle`, como
  máximo una por `idProyectoItem`, índice único filtrado). Al entrar, el presupuesto se crea solo
  en "en proceso" y cada ítem sin línea recibe el MENOR precio vigente. La tabla tiene una columna
  por proveedor; marcar otro precio se guarda por AJAX. Atajos: "menor precio por ítem" y "todo a
  un proveedor"
- PRECIO DIRECTO (2026-10-21, pedido para no tener que registrar proveedor y cotización de todo):
  en el paso Presupuesto cada ítem puede llevar un precio escrito sin cotización (columna "Precio
  directo"; antes se llamaba "Manual") con su fuente opcional (`PresupuestoDetalle.fuentePrecio`,
  migración `m261021_052012_agregar_fuente_precio_a_presupuesto_detalle`; NULL en las líneas de
  cotización). El botón "SIGMA: x.xx" de la fila usa el `PrecioReferencial` del ítem SIGMA (fuente
  `PresupuestoDetalle::FUENTE_SIGMA`), y el atajo "Precio SIGMA en los ítems sin precio"
  (`ElaborarPresupuestoService::usarPrecioSigma()`) lo pone en todos los que no tienen precio,
  sin tocar los ya elegidos. Se congela igual que cualquier precio. Las cotizaciones siguen siendo
  opcionales para quien las tenga
- Elegir otro precio REEMPLAZA la línea (borra y crea otra con precio y partida congelados en ese
  momento); el precio de una línea guardada nunca se modifica. Elegir un precio que NO es el menor
  admite una `PresupuestoDetalle.justificacion` OPCIONAL (decidido el 2026-10-16: el presupuesto es
  un precio referencial, no la adjudicación de una compra). La justificación se ve en la pantalla
  del paso Presupuesto; el PDF ya no tiene anexo (2026-10-20)
- Solo se eligen precios con el presupuesto "en proceso" (`Presupuesto::esBorrador()`); en
  revisión, aprobado o cancelado la tabla es de solo lectura. Mientras es borrador, sus líneas siguen
  a la lista: quitar un ítem borra su línea y la cantidad se vuelve a copiar (el precio no)
- El número (`numeroPresupuesto`) es el código RD-PI-PR-NNN que el sistema le da al crear el
  presupuesto (`ElaborarPresupuestoService::delProyecto()`; ya no se escribe; los de antes, como "690",
  se convirtieron a RD-PI-PR y guardan el número viejo en `Presupuesto.numeroAnterior`). "Enviar a revisión" exige el código (escenario
  `Presupuesto::SCENARIO_ENVIAR`) y que todos los ítems tengan precio; desde el 2026-11-11 NO pide nada (antes el oficio y
  el área): el oficio DTIC se escribe en la carta de entrega. Los estados se
  cambian en la misma pantalla. FECHAS (revisión del 2026-10-29, paso 9; "arriba va la fecha de creación del
  documento", abajo la de aprobación): la "Fecha" de la cabecera del PDF, de la pantalla y del listado es
  `Presupuesto.fechaCreacion` (cuando se empezó a elaborar, `Presupuesto::fechaFormateada()`); desde el 2026-10-30 ya
  no se escribe una fecha al enviar a revisión (`Presupuesto.fecha` queda sin usar, con la de los de antes). El
  mismo criterio en la lista de materiales. El menú "Presupuestos" es solo un listado de consulta; las rutas
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
  - EMISIÓN `redes.EspecificacionEmision`: el formulario con código `RD-PI-ET-001` (columna `serie` =
    'ET', desde el 2026-10-22, correlativo desde 1; desde el 2026-10-29 `RD-<proceso>-ET-001`, sin año desde el
    2026-11-10, ver CODIFICACIÓN; índice único por serie + `numeroNet` + `versionDocumento`;
    `EspecificacionEmision::codigo()`. Las emitidas antes con `NET:880/26` se convirtieron a ET con la
    migración `m261022_144239_convertir_codigos_viejos_al_lineamiento` y guardan el NET en
    `codigoAnterior`, que se ve y se busca en el registro; la serie 'NET' queda solo para su `safeDown()`) que se firma y se
    entrega al proveedor. NUNCA cambia: guarda copias congeladas (título = nombre DTIC del ítem de la lista desde el
    2026-10-21, antes el de la ficha; cantidad, unidad,
    dependencia = unidad / dependencia del proyecto desde el 2026-10-29 (del 28 al 29 la solicitante, antes la facultad), garantía, textos,
    condiciones de entrega; el LUGAR DE ENTREGA es desde el 2026-11-03 el del PROYECTO (`Proyecto.lugarEntrega`, texto
    libre, desde el 2026-11-10 arriba de la lista de ítems y antes en el formulario del proyecto; migración `m261104_020312_agregar_lugar_entrega_a_proyecto`, que puso
    "Administración de la <unidad>" a los proyectos cuya unidad / dependencia empieza con "Facultad"; el formulario lo
    SUGIERE igual, `Proyecto::lugarEntregaSugerido()` y `proyecto-form.js`, mientras el usuario no escriba otro; la fase
    siguiente lo hereda; va al historial y a la ficha técnica) y, si el proyecto no tiene, el texto
    `ConfiguracionEspecificacion.lugarEntregaPorDefecto` (vacío = no sale) (`EspecificacionEmisionService::lugarEntregaDe()`;
    pedido del Ing. el 2026-10-26: "Administración de la … facultad", pero no todo se entrega en una facultad; del
    2026-10-26 al 28 se armaba solo con la facultad, del 28 al 3-11 era solo el de la configuración). Al reemitir, lo
    escrito en la ventana manda (vacío = el del proyecto); las anteriores conservan el suyo) y apunta a una
    versión que ya no cambia. Estados: vigente, reemplazada, anulada; "vencida" se calcula
    (fecha + vigencia). Reglas en `EspecificacionEmisionService`.
  Se ENLAZA a los ítems de la lista con `redes.ProyectoItem.idEspecificacion` (como máximo una por
  ítem, desde la pantalla de la lista y con su mismo bloqueo; un mismo ítem del subcatálogo puede
  tener varias fichas posibles). Ayudas para elegir (2026-10-20): cada opción muestra el modelo
  sugerido de la versión actual, arriba va el grupo "Sugeridas para este ítem" (fichas cuyo ítem de
  referencia es el de la fila) y "Ver detalle" abre una ventana con la ficha
  (`especificacion/vista-previa`, `web/js/especificacion-vista-previa.js`). Sin texto, la ventana
  ofrece la ficha elegida en la fila y las sugeridas; su buscador (`especificacion/buscar`,
  `EspecificacionService::buscar()`) busca en toda la librería activa: todas las palabras, en título,
  modelo y características de la versión actual, sin importar tildes (`COLLATE Modern_Spanish_CI_AI`),
  título primero, máx. 30. La casilla "Recordar como sugerida para este ítem" (solo en fichas sin
  ítem de referencia) se aplica al guardar la lista (`items[n][recordarReferencia]`) y nunca pisa
  un ítem de referencia ya puesto. ENLAZAR = EMITIR: al guardar la
  lista se asigna el siguiente código ET. Cambiar o quitar la ficha, o quitar el ítem, ANULA la emisión
  vigente (no se borra). Un error se corrige REEMITIENDO: MISMO código y versión del documento + 1
  (`EspecificacionEmision.versionDocumento`, desde el 2026-10-24 por el lineamiento 4.2: RD-PI-ET-015
  v01 → v02; antes cada reemisión tomaba un código nuevo, y esas quedaron como estaban), última versión
  de la ficha, cantidad actual de la lista; la anterior queda "reemplazada por". Cada versión es su
  propia fila (índice único serie + `numeroNet` + `versionDocumento`; migración
  `m261025_000938_version_del_documento_en_emision`). La casilla "Versión" del formulario es la del
  DOCUMENTO, no la de la ficha (`versionTexto()`); `codigo()` no lleva versión (lo citan carta,
  solicitud y presupuesto) y `codigoConVersion()` ("RD-PI-ET-015 v02") se usa en pantallas, registro
  y CSV. Cambiar la ficha por otra sí da código nuevo (es otro documento). "CAMBIAR ÍTEM" (2026-11-04): en una fila
  ya guardada de la lista se elige otro ítem del subcatálogo SIN quitar la fila (ej. el cable en metros por el de
  cajas); con la misma ficha su formulario se REEMITE (mismo código, versión + 1, motivo "Se cambió el ítem: A → B",
  conserva las condiciones de entrega) en vez de anularse y gastar otro número (`ListaItemsService::cambiarItem()`,
  `reemitirPorCambio()`; JS en `lista-items.js`). No se permite si la fila ya tiene cotizaciones
  (`SolicitudCotizacionService::itemTieneCotizaciones()`: precios de otro producto, se quita y se agrega); con el
  presupuesto en borrador borra su línea (precio directo o SIGMA del ítem anterior); se anota en el historial
  (`HistorialProyecto::ACCION_LISTA_ITEM_CAMBIADO`). Reemitir se permite mientras el presupuesto no
  esté aprobado ni cancelado. Correlativo: el ET de `redes.CorrelativoDocumento` (antes
  `ConfiguracionEspecificacion.siguienteNet`, eliminado el 2026-10-22; esa tabla sigue con los textos fijos
  del formulario), sube de 1 en 1, no se reinicia (del 2026-10-29 al 11-10 volvía a 001 cada 1 de enero) y nunca se reutiliza. Avisos que
  no bloquean: cantidad distinta de la emitida, emisión vencida, versión nueva de la ficha. La
  especificación es OPCIONAL por ítem: un ítem sin ella nunca bloquea lista, solicitud,
  cotización, presupuesto ni PDF. Los impresos citan el NET de la emisión vigente
  (`ProyectoItem::referenciaEspecificacion()`). El PDF de la solicitud de cotización no lleva
  rubro, nombre del proyecto, edificio ni bloques (solo "Unidad" = unidad / dependencia desde el 2026-10-29; antes la solicitante y la facultad), pero desde el
  2026-10-21 SÍ cita el NET vigente junto al nombre del ítem y lleva AL FINAL del mismo PDF el
  formulario NET de cada ítem, cada uno en hoja nueva y con su marca de agua
  (`SolicitudCotizacionService::emisionesVigentes()` y `generarPdf()`; el 2026-10-20 se había
  quitado; se volvió a pedir para que el proveedor reciba todo junto). Desde el 2026-10-30 (paso 10; la
  transcripción es ambigua) los formularios van SOLO con el botón "Imprimir con especificaciones (N)" de la lista
  de solicitudes (`solicitud-cotizacion/imprimir?especificaciones=1`); "Imprimir" da solo la solicitud. En `generarVarios()` cada documento
  después del primero empieza sin la cabecera `@page` del anterior y con la numeración reiniciada.
  Solo se enlazan fichas activas; una en uso o
  emitida no se elimina, se desactiva
- Formulario de especificación en PDF (`especificacion/formulario`; en pantallas ya no se llama
  "formulario NET") con la cabecera del lineamiento (código, versión = la de la ficha emitida, fecha,
  página) y el formato institucional (tabla
  Especificación | Propuesta | Cumple | No cumple, secciones I, II y III). NUMERACIÓN (2026-11-03): cada fila
  lleva su número, CALCULADO y nunca guardado (`backend\components\NumeracionEspecificacion`, el único lugar que
  lo decide; `EspecificacionVersion::caracteristicasNumeradas()`): I = 1.1 marca y modelo, 1.2 URL, 1.3… las
  características. Cada fila tiene su NIVEL (`EspecificacionCaracteristica.nivel`, 1 a 3, flechas ← → en la ficha;
  migración `m261103_190000_nivel_en_caracteristicas_de_especificacion`), como un índice: nivel 2 va debajo de la
  última de nivel 1 (1.4 → 1.4.1), nivel 3 debajo de la de nivel 2 (1.4.1.1) y una de nivel 1 cierra el grupo (1.5);
  un nivel que se salta uno se corrige al numerar y al guardar (`NumeracionEspecificacion::niveles()`). Un SUBTÍTULO
  (`esSubtitulo`, casilla "Subtítulo"; un solo texto en `valor`, sin nombre) solo es la fila en negrita: el nivel no
  depende de él (antes del nivel, todo lo que seguía a un subtítulo quedaba dentro hasta el siguiente; la migración
  dejó así los niveles, sin cambiar ningún número, y lo atrapado se corrige a mano); II = garantía, una fila por línea (2.1…); la última = condiciones, una fila por dato
  (3.1 validez, 3.2 tiempo, 3.3 lugar; 2.x si no hay garantía). Se quita el número escrito a mano del Word
  ("II. MEDIO AMBIENTE", "1.Garantías"; `sinNumeral()`). Los mismos números se ven en la ficha, en "Ver detalle"
  (`views/especificacion/_caracteristicas.php`) y en el formulario de edición (JS, misma regla). La migración
  `m261103_141729_subtitulos_en_caracteristicas_de_especificacion` marcó 259 subtítulos seguros (sin nombre, una
  línea, ≤ 40 caracteres, sin punto final, con filas después; `EspecificacionCaracteristica::pareceSubtitulo()`
  para el importador), también en versiones emitidas: al reimprimirlas salen numeradas (decidido el 2026-11-03);
  marca de agua
  REEMPLAZADO / ANULADO / VENCIDO. Al fondo de la última página, el "control de elaboración" del
  lineamiento, como el del presupuesto (2026-10-22, vista compartida `views/pdf/_control-elaboracion.php`):
  dos firmas EN TABLA desde el 2026-11-09 (ver PIES DE FIRMA EN TABLA; antes sin tabla) y la línea "Versión: 02
  · Fecha de aprobación: …" (versión de la ficha; fecha = la de emisión). Las firmas se editan en la configuración del formulario y cada emisión
  guarda su COPIA (trait `FirmasDeControl`, migración
  `m261022_233202_control_elaboracion_en_formulario_especificacion`); las emitidas antes salen en blanco.
  El usuario que emitió solo se ve en el registro. Todos los NET VIGENTES de la lista en un solo PDF, cada uno desde una hoja nueva y en el orden de la
  lista, sin índice (2026-10-21; botón "Imprimir especificaciones (N)" en la lista y en el paso
  Presupuesto, `lista-items/imprimir-especificaciones`, `PresupuestoPdfGenerator::generarVarios()`).
  Registro de todas las emisiones en el menú "Formularios NET"
  (reemplaza el Excel "1000 Especificaciones"), con exportación a CSV
- Carga inicial desde el banco de .docx de DTIC: `php yii especificacion/importar "<carpeta>"`
  (sin `--confirmar` solo escribe un CSV; `--desde=AAAA`). Agrupa por título + modelo sugerido y
  toma la emisión más reciente de cada familia. No importa emisiones antiguas
- ORDEN DE LA LIBRERÍA (2026-11-03): `php yii especificacion/ordenar console/data/orden-especificaciones-2026-11-03.json`
  (sin `--confirmar` solo muestra; `EspecificacionOrdenService`). El plan, revisado y aprobado por el desarrollador, deja
  114 fichas con niveles, subtítulos y arreglos de forma (sin cambiar el orden de las filas ni los valores técnicos).
  Cada ficha se reconoce por TÍTULO + HUELLA del contenido (nombre y valor de sus filas), no por id: sirve también en el
  servidor de la DTIC; una ficha editada después del plan, ya ordenada o con la versión actual emitida se salta. Todo en
  una transacción; se puede volver a correr. Aplicado en la base local el 2026-11-03 (las 172 fichas: ese día se borraron
  los 9 proyectos de PRUEBA que tenían formularios ET, para liberar las 8 fichas emitidas; copia previa de la base en
  `POAPresupuestos-antes-de-borrar-proyectos-2026-11-03.bak`, carpeta Backup de SQL Server). Las siglas en subtítulos
  (VLAN, MIMO) quedan en mayúsculas. PENDIENTE: correrlo en el servidor de la DTIC y llevar al Ing. las 44 dudas (título "UN disco" con DOS discos,
  FACE PLATE 4 TOMAS con "2 posiciones", fichas vacías, datos repetidos, restos del Word)
- Cotizaciones de proveedores (relación N:N con ítems/especificaciones). Una línea que viene de
  una solicitud guarda contra qué NET cotizó (`CotizacionDetalle.idEspecificacionEmision`, no
  cambia si el NET se reemite); una cotización suelta solo elige la ficha (`idEspecificacion`)
- PROFORMA RECIBIDA (2026-11-11, pedido del Ing. de DTIC: las proformas se guardan en un Drive): cada cotización
  adjunta su proforma como PDF subido O como ENLACE (campo "Enlace a la proforma (Google Drive)", atributo virtual
  `Cotizacion::$documentoEnlace`, http(s) válido, máx. 500). Los dos van en `Cotizacion.documentoRuta` (el enlace tal cual;
  `tieneEnlace()`), sin migración; lo pone `CotizacionService::asignarDocumento()`: con los dos a la vez pide elegir (salvo que
  el enlace sea el ya guardado: el PDF nuevo lo reemplaza), borrar el enlace lo quita, un PDF ya subido se conserva.
  `documentoRuta` ya no tiene regla (no llega desde formularios). En la ficha técnica, "Proformas recibidas" lleva "ver
  proforma" con el enlace (el PDF subido no: su enlace pide sesión)
- Textos fijos del PDF de la solicitud de cotización (instrucciones, nota final; la "cabecera" ya no sale desde el
  2026-11-09 y se quitó de la pantalla) en
  `redes.ConfiguracionSolicitudCotizacion` (una sola fila, igual que la del NET), editables en
  "Configuración del documento" (`solicitud-cotizacion/configuracion`). La solicitud NO guarda
  copia: un cambio se ve también al reimprimir las anteriores. Título, columnas y bloque del
  proveedor siguen fijos en `backend/views/solicitud-cotizacion/pdf.php`
- SOLICITUD DE PROFORMA DE COTIZACIÓN (revisión del 2026-10-29, paso 10, decidido con el desarrollador el 2026-10-30):
  el PDF de la solicitud es un documento formal para el PROVEEDOR: título "SOLICITUD DE PROFORMA DE COTIZACIÓN",
  la cabecera de todos los documentos (desde el 2026-11-09, con su código RD-PI-SC-NNN; antes una propia con la
  referencia, la unidad, la fecha y la página), una lista sencilla N.° | Descripción | Unidad | Cantidad (la UNIDAD con su NOMBRE, "Metro" y no "m", desde el 2026-11-10:
  `UnidadMedida::nombresPorAbreviatura()`, la abreviatura si no está en el catálogo; igual la "Cantidad:" del formulario ET, "24
  Unidad"; en la descripción siguen el código ET, la
  marca / modelo de referencia y la observación). Desde el 2026-11-10 SIN espacio para el proveedor (antes firma y
  sello, nombre / empresa y fecha). SIN columnas de precio ni marca ofertada, sin TOTAL, NIT, validez ni tiempo de entrega (los trae
  la proforma que devuelve el proveedor). Desde el 2026-11-09 (como el Word v3) lleva también la firma "ELABORADO POR" de la DTIC, en la tabla de una sola firma a todo el ancho, desde el 2026-11-10 AL FONDO de la última página (`MARCA_AL_PIE`): la firma 1 de "Configuración del PDF" del presupuesto, la de HOY (la solicitud no guarda copia); antes, sin firmas de la DTIC. Archivo `solicitud_proforma_…pdf`. Las pantallas
  siguen diciendo "Solicitud de cotización". La migración `m261030_200322_instrucciones_solicitud_de_proforma`
  cambió el texto de instrucciones por defecto ("Se solicita enviar su proforma de cotización…") solo si nadie lo
  había editado. PENDIENTE (Ing. de DTIC): ¿se adjuntan las especificaciones? (hoy, a elección al imprimir); en la
  reunión también se nombró la solicitud entre los documentos "a normar" (desde el 2026-11-09 lleva código SC); la
  solicitud se despacha con una NOTA que el sistema no genera
- Fecha de la solicitud de cotización (2026-10-24): `SolicitudCotizacion.fechaEnvio` (date, opcional,
  con calendario en "Cambiar nombre, nota o fecha de envío"; migración
  `m261025_014520_agregar_fecha_envio_a_solicitud_cotizacion`) es la que sale en el PDF
  (`fechaDelDocumento()`); vacía = la del día en que se imprime, como antes. Se cambia mientras la
  solicitud no tenga cotizaciones. `SolicitudCotizacion.fecha` es la de CREACIÓN (GETDATE()) y no sale
  en ningún impreso
- Marca y modelo: `SolicitudCotizacionItem.marcaModeloReferencia` es lo que la DTIC PIDE como
  referencia (texto libre, opcional; sale entre paréntesis junto a la descripción en el PDF de la
  solicitud; se edita mientras la solicitud no tenga cotizaciones y viaja con el ítem si se mueve
  de solicitud). `CotizacionDetalle.marcaModelo` es lo que el proveedor OFERTA. No va al presupuesto
- Presupuestos versionados por proyecto, con estados: en_proceso, en_revision, observado,
  aprobado, cancelado
- Precio unitario "congelado" en el detalle del presupuesto al momento de crearlo
  (nunca se recalcula retroactivamente si cambia el precio de una cotización nueva)
- Tamaño de hoja de TODOS los impresos: OFICIO, 8,5 x 13 pulgadas = 216 x 330 mm (2026-10-21; antes
  A4), la que usa la DTIC. En mPDF se llama "Folio": `PresupuestoPdfGenerator::HOJA_OFICIO` (por
  defecto) y `HOJA_OFICIO_HORIZONTAL` (reporte de relación con SIGMA). Desde el 2026-11-03 todos los
  impresos van en ESCALA DE GRISES (fondos #e0e0e0 títulos, #d0d0d0 categorías, #f2f2f2 subtotales), como el
  formulario de especificación y la lista de materiales; el presupuesto tenía azules. Solo los logos y el
  membrete de la carta conservan su color
- Cabecera de página de los impresos (`backend/views/pdf/_cabecera.php`, etiqueta `<htmlpageheader>` de mPDF),
  repetida en cada página, a 5 mm del borde (contenido desde 37 mm). Desde el 2026-11-09 es UNA SOLA para todos los
  documentos (ficha técnica, lista, ET, solicitud, presupuesto), copiada de los Word de "FORMATOS CORREGIDOS v3":
  tabla de 5 columnas con los anchos del Word; izquierda (2 filas) logo DTIC USFX + "DIVISION DE TECNOLOGIAS DE
  INFORMACION Y COMUNICACION U.M.R.P.S.F.X.CH." en 6 pt; centro "PROCESO: <NOMBRE EN MAYÚSCULAS>" (10 pt) y el título
  (12 pt), en negrita; derecha logo RedUSFX + "Redes y Telecomunicaciones"; abajo Código | Versión | Fecha | Página en
  10 pt sin negrita, fecha dd-mm-aaaa (lineamiento 8.8.2). Logos `backend/web/img/logo-dtic-usfx.png` y
  `logo-redusfx.png`, sacados de los Word (se borraron `logo-usfx.png` y `logo-dtic.png`). El texto "Cabecera" de la
  configuración de la solicitud ya no se usa (columna queda). El del FORMULARIO ET sí (2026-11-10, como el Word v3): sale
  DEBAJO de la cabecera, centrado y en negrita ("Universidad Mayor, Real y Pontificia de “San Francisco Xavier” de Chuquisaca"
  en 13 pt y "D.T.I.C." en 11), de la copia de cada emisión (`EspecificacionEmision.textoCabecera`); se edita en la configuración
  del formulario ("Universidad y unidad"); la migración `m261110_050000_universidad_con_comillas_en_especificacion` le puso las
  comillas si nadie lo había cambiado. Al juntar varios documentos en un PDF cada uno sale con su cabecera
  (`PresupuestoPdfGenerator::empezarDocumento()`)
- Generación de PDF consolidado del presupuesto: marca de agua "BORRADOR" mientras no esté
  aprobado. Datos generales: la línea del proyecto (`Proyecto::lineaDocumento()`, ver PARTE 5c; del 2026-11-05 al 06
  "Proyecto: RD-PI-PT-028 — Nombre"), a todo el ancho: SIN el oficio de la DTIC desde el 2026-11-11 (antes a la derecha). Una tabla por categoría con su subtotal (solo la primera lleva los títulos de columnas; las demás
  usan los mismos anchos, 2026-10-21) y "TOTAL GENERAL Bs." al final; N.° corrido
  entre categorías y en cada línea el nombre DTIC con su observación (columnas como la solicitud
  de cotización, más Partida). Del 2026-10-21 al 11-11 el nombre DTIC llevaba al lado, entre paréntesis, el código de la emisión
  VIGENTE del ítem y el modelo sugerido; desde el 2026-11-11 SOLO el modelo sugerido (sin el código ET):
  "(Dahua DH-PFS3110-8P-96)" (`ProyectoItem::modeloSugerido()`: de la versión EMITIDA, o de la actual de la ficha si
  aún no se emitió; también en el paso Presupuesto, junto a "Especificación técnica: NET…"). Desde el 2026-10-20 NO lleva nombre SIGMA,
  proveedor por línea, resumen por partida, anexo (cuadro comparativo y justificaciones), área
  de intervención ni estado
- Reporte de apoyo "Relación con el catálogo SIGMA" (`presupuesto/pdf-sigma`, 2026-10-20; vista
  `presupuesto/pdf-sigma.php`, A4 horizontal, botones en el paso Presupuesto y en el listado):
  cada línea con su ítem DTIC y el ítem SIGMA al que apunta (clase, descripción, Id SIGMA,
  especificación), partida y precio de la línea (congelados) frente a la partida (`IdGasto`) y el
  `PrecioReferencial` que tiene HOY el catálogo, y su estado (vigente o no). Marca con * la partida
  distinta y resume cuántas líneas tienen o no ítem SIGMA. Sin firmas ni mano de obra; lleva la
  misma marca de agua que el presupuesto. Es el único impreso que muestra el nombre SIGMA
- Después del total, el PDF del presupuesto lleva una nota opcional y, AL FONDO de la última página
  (2026-10-21: marca `PresupuestoPdfGenerator::MARCA_AL_PIE`; si no caben, van al fondo de una
  página nueva), dos firmas lado a lado
  (prefijo opcional como "V°B°", nombre con tratamiento, cargo guardado en MAYÚSCULAS); desde el
  2026-10-22 son el "control de elaboración" del lineamiento: rotuladas "ELABORADO POR" (firma 1) y
  "APROBADO POR" (firma 2, V°B°), EN TABLA desde el 2026-11-09 (del 22-10 al 09-11 sin tabla: "el 8.12 pide el
  contenido, no una tabla"; ver PIES DE FIRMA EN TABLA), y debajo "Versión: 01 · Fecha de aprobación" (`Presupuesto.fechaAprobacion`, la pone
  `PresupuestoService::cambiarEstado()` al aprobar; sin fecha, una raya). SIN pie de página desde el
  2026-10-30 (paso 9: "si es sistema de gestión documental no tiene pie de página"; los contactos están en el
  membrete de la carta; migración `m261030_162507_quitar_pie_de_configuracion_presupuesto` quitó sus columnas;
  del 20 al 30 hubo una banda verde con 2 columnas de contactos). Todo sale de
  `redes.ConfiguracionPresupuesto` (una sola fila, CHECK id = 1; 2026-10-20), editable en
  "Configuración del PDF" (`presupuesto/configuracion`); NADA de eso queda escrito en la vista. Un
  campo vacío no sale; una firma sin nombre queda en blanco para llenarla a mano. Un cambio se ve
  también al reimprimir presupuestos anteriores, SALVO las firmas de los APROBADOS (2026-10-24,
  documento controlado del lineamiento): `PresupuestoService::cambiarEstado()` las copia al aprobar a
  `Presupuesto.firma1…/firma2…` (trait `FirmasDeControl`, sin reglas: no llegan de formularios;
  migración `m261025_021822_copiar_firmas_al_aprobar_presupuesto`, que llenó los ya aprobados con la
  configuración de ese día) y el PDF las toma con `Presupuesto::firmaDeControl()`: aprobado = la
  copia, cualquier otro estado = la configuración de hoy
- Mano de obra a cargo del personal de la DTIC (2026-10-20): NO es un ítem del subcatálogo (no se
  compra: sin partida, sin cotización, no suma al total). Es el texto `Presupuesto.manoDeObra`
  (NULL = no lleva), que se pone o quita con una casilla en el paso Presupuesto, solo en borrador
  (`ElaborarPresupuestoService::guardarManoDeObra()`; texto por defecto
  `Presupuesto::MANO_DE_OBRA_DTIC`). El PDF la muestra en la categoría "Mano de obra" (una
  tabla propia al final si el presupuesto no tiene esa categoría): "MANO DE OBRA", unidad
  "Servicio", cantidad 0.00 (antes 1; `Presupuesto::CANTIDAD_MANO_DE_OBRA`, 2026-10-23), precios 0.00.
  Desde el 2026-10-23 un presupuesto NUEVO nace con la mano de obra puesta
  (`ElaborarPresupuestoService::delProyecto()`) y el encargado la desmarca si no corresponde; los
  ya creados quedan como estaban. La mano de obra CONTRATADA
  a una empresa sí es un ítem normal (categoría "Mano de obra", con partida y cotización)
- SECCIONES SIN PRECIO EN LA LISTA (revisión del Ing. de DTIC del 2026-10-29, paso 8a; migración
  `m261030_035251_secciones_sin_precio_a_la_lista`). "El presupuesto es financiero: si no tiene cantidades y montos,
  no puede ir". Del 2026-10-23 al 29 el presupuesto llevaba al final (paso Presupuesto y su PDF) "Otros materiales y
  equipamiento que proveerá la D.T.I.C." y "Trabajos complementarios…" (`redes.PresupuestoComplemento`, ELIMINADA;
  la migración pasó sus filas al proyecto de su presupuesto). Ahora son del PROYECTO y se escriben DEBAJO DE LA LISTA
  DE ÍTEMS, en el mismo formulario y con el mismo botón (`ListaItemsService::guardar(..., $condiciones, $materiales)`,
  misma transacción; null = no se tocan, '' = se borran todas), mientras la lista está en edición. La lista de
  ítems NO cambió (decidido con el desarrollador el 2026-10-30: nada de "provisión" por ítem; todo lo de la lista se
  cotiza y va al presupuesto, como antes). Se llenan "solo a veces" y salen en el impreso SOLO si tienen filas
  (hoy en el PDF de la lista, `lista-items/pdf.php`, después de los ítems):
  - `redes.ProyectoMaterialDtic` (`MaterialDticService`, `views/lista-items/_materiales-dtic.php`): mismas columnas
    que antes (subtítulo, ítem del subcatálogo opcional, descripción, cantidad > 0, unidad; SIN observaciones desde el
    2026-10-30; fila en blanco o solo con subtítulo se ignora). El Ing. dijo que deberían ser "necesariamente ítems del
    catálogo"; hoy se admite también escribir a mano. Pantalla rehecha el 2026-10-30: título corto "Materiales que aporta
    la DTIC" (el oficial en un ⓘ y en el PDF), filas AGRUPADAS por subtítulo; un buscador que filtra al escribir (los
    ítems del panel del subcatálogo) agrega la fila con nombre y unidad FIJOS y solo pide la cantidad (si ya está, va a
    su cantidad); "¿No está? Escribirlo a mano" agrega una fila editable. El SUBTÍTULO es la categoría del ítem (COPIA
    del nombre al guardar, siempre la actual); a mano se elige la categoría, "Otros" por defecto
    (`ProyectoMaterialDtic::GRUPO_OTROS`)
  - Las dos secciones: agrupadas en pantalla (trabajos por tipo; cambiar el tipo o la categoría pasa la fila a su
    grupo), mensaje "Todavía no hay" si están vacías, y un segundo "Guardar lista" debajo con el aviso de cambios sin
    guardar (`.lista-sin-guardar`)
  - `redes.ProyectoCondicionPrevia` = los trabajos complementarios (condiciones y requerimientos previos;
    `CondicionPreviaService`, `views/lista-items/_condiciones.php`): tipo, descripción, unidad encargada (texto libre
    con sugerencias); SIN observaciones (2026-10-30). El tipo ya no es uno de los 2 subtítulos fijos: sale del catálogo
    `redes.TipoCondicionPrevia` (Configuración → "Tipos de trabajo complementario"; Instalaciones eléctricas, Obras
    civiles, Apertura de ductos, Despeje del ambiente; "que haya algo para crear"; en uso no se elimina)
  - Títulos en `ProyectoMaterialDtic::TITULO` y `ProyectoCondicionPrevia::TITULO` (los de antes). JS común en
    `lista-items.js` (`.lista-seccion`)
  - DOCUMENTO NORMADO (paso 8b, 2026-10-30; migración `m261030_160530_crear_aprobacion_lista_materiales`): el PDF
    de la lista ("Imprimir lista de materiales") es la "LISTA DE MATERIALES Y CONDICIONES PREVIAS" con la cabecera
    del lineamiento y el control de elaboración al fondo. SIN código propio (el 8.3 no tiene uno; decidido con el
    desarrollador el 2026-10-30): lleva el CÓDIGO DEL PROYECTO (RD-PI-PT-NNN, "Proceso:" = PI), porque cada proyecto
    tiene una sola lista y ese código no cambia. "Enviar a cotización" APRUEBA la versión siguiente
    (`ListaItemsService::enviarACotizacion()` → `aprobarVersion()`): `redes.ListaMaterialesAprobacion`, una fila por
    versión (01 la primera vez, +1 después de cada reapertura de la lista), nunca se modifica: fecha, usuario y COPIA de
    las firmas de "Configuración del PDF" del presupuesto (trait `FirmasDeControl`); se anota en el historial
    (`HistorialProyecto::ACCION_LISTA_APROBADA`). No guarda copia de la lista (mientras está aprobada no cambia).
    `ListaItemsService::documento()`: en edición imprime la versión SIGUIENTE con marca BORRADOR y las firmas de
    hoy; enviada o cerrada, la última aprobada. La "Fecha" de la cabecera es la de CREACIÓN de la versión (paso 9):
    la v01 nace con el proyecto (`Proyecto.fechaCreacion`), cada una siguiente con la reapertura que reabrió
    la lista (la 1.ª orden crea la v02…); la de aprobación va abajo. Las 4 listas ya enviadas recibieron su v01 sin
    fecha de aprobación. La versión sale también en la pantalla de la lista y en la ficha técnica.
    Sin "documentos relacionados" (opcional en el nivel D: cada ítem ya cita su ET)
  - RESUELTO (paso 9, transcripción del 29): la mano de obra de la DTIC se queda en el presupuesto con 0 ("cantidad
    0, precio 0, total 0… pero tiene un valor cero"); la contratada es un ítem con precio
- Carta de entrega del presupuesto (2026-10-21, `CartaController`, `CartaService`; botón "Carta de
  entrega" en el paso Presupuesto): la nota con membrete que acompaña al presupuesto. UNA por
  presupuesto (`redes.CartaPresupuesto`, índice único, FK CASCADE). Se escribe y guarda en CUALQUIER
  estado; para imprimir solo se exige la carta guardada y que el presupuesto tenga `oficioDtic` y
  `numeroPresupuesto` (NO que esté aprobado). Desde el 2026-11-11 el OFICIO DTIC se escribe en la pantalla de la carta (campo
  "Oficio DTIC" arriba, se guarda en `Presupuesto.oficioDtic` con el mismo botón, en cualquier estado, `CartaService::guardar()`);
  antes se pedía al enviar a revisión. El PDF del presupuesto ya no lo muestra. Sin marca de agua. GUARDA su texto (copiado de
  `redes.ConfiguracionCarta` al crearla: textos por defecto y 3 firmas propias, la 3.ª el V°B° del
  jefe, editables en "Configuración de la carta"); cambiar la configuración no altera las ya
  escritas, salvo ciudad y formato del oficio. Marcadores: {proyecto}, {unidad} (= unidad solicitante: la carta responde a quien
  pidió, decidido el 2026-10-29; {facultad} es el nombre de antes y vale lo mismo) y {dependencia} (= la
  beneficiaria, 2026-10-29); el cargo "DECANO/DECANA DE LA…" se sugiere solo si la unidad
  empieza con "Facultad") se reemplazan al
  crear la carta; {oficio}, {numeroPresupuesto} y {especificaciones} quedan en el texto y se
  reemplazan AL IMPRIMIR ({especificaciones} = solo el NÚMERO de los NET vigentes del proyecto, sin
  "NET:" ni año, de menor a mayor, separados por comas: "896, 897, 900", `CartaService::codigosNet()`;
  sin ninguno, el marcador desaparece). El
  adjunto por defecto es "…Nro: {numeroPresupuesto}, especificaciones técnicas {especificaciones} y
  cotización.".
  Cargo sugerido "DECANO/DECANA DE LA <FACULTAD>" según el tratamiento (`web/js/carta.js`). TRATAMIENTOS
  (2026-11-04; antes 4 fijos en `CartaPresupuesto::TRATAMIENTOS`): lista `redes.TratamientoCarta` (nombre único,
  "cargo en femenino" = DECANA, orden; migración `m261104_233000_crear_tratamiento_carta`) editada en la tabla
  "Tratamientos" de "Configuración de la carta" (`ConfiguracionCarta::$tratamientos`, se reemplaza entera en
  `CartaService::guardarConfiguracion()`, al menos uno, sin repetidos). Una carta nueva toma el primero; la carta
  guarda COPIA del texto y conserva uno ya quitado de la lista (`CartaPresupuesto::tratamientosElegibles()`). PDF
  oficio con el membrete `backend/web/img/fondo-carta.png` de fondo (@page background) y márgenes
  4,75 / 4,5 / 3 / 3 cm. El PDF del presupuesto muestra el oficio con el MISMO formato que la carta
  ("DTIC Of. Nº 602/2026"; el año es el de la fecha de la carta si ya existe, si no el de creación del presupuesto,
  `Presupuesto::fechaParaOficio()`, 2026-10-30; `CartaService::formatearOficio()`),
  o "Oficio DTIC: —" si aún no tiene. Fuente "pagelladtic" (TeX Gyre Pagella convertida a TTF, en
  `backend/fonts/pagella-dtic`, ver su MANIFEST) en vez de Book Antiqua/Andalus, que son de Windows
  y no se pueden llevar al servidor
- Barra de pasos del proyecto (`ProyectoService::pasos()`, vista `proyecto/_pasos.php`): Datos,
  Plantilla, "Lista de ítems y especificaciones" (`ProyectoService::PASO_LISTA`; un solo paso desde
  el 2026-10-21, porque las especificaciones se enlazan en la misma pantalla de la lista),
  Cotización y Presupuesto. Sale en todas las pantallas de un paso (vista del proyecto, plantilla,
  lista, solicitudes/cotizar/comparativo, paso Presupuesto, carta), no en los formularios de
  edición; cada controlador le pasa `pasos` a su vista. En la lista, ir a otro paso con cambios sin
  guardar pide confirmación (`lista-items.js`)

### NO incluye (explícitamente fuera de alcance):
- Cómputos métricos (QUITADOS el 2026-10-21 por decisión del desarrollador: el módulo funcionaba
  pero nunca se usó y estaba fuera del flujo del proyecto). La migración
  `m261021_215240_eliminar_computo_metrico` borra `redes.ComputoMetrico` (se detiene si la tabla
  tiene datos; `safeDown()` la vuelve a crear vacía). Estaban en el alcance confirmado y en el
  diagrama de clases: ver el PENDIENTE (tutor) de abajo
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
- El PATH de Windows (del SISTEMA, no del usuario) debe apuntar al PHP 8.3.33 de Laragon (con el driver
  sqlsrv). Con el 8.2 todo comando se detiene: "Composer detected issues in your platform ... >= 8.3.0".
  Verificar con `php -v` antes de correr comandos.
- Tests: `php vendor/bin/codecept run -c backend` (Unit y Functional; la Functional `LoginCest` prueba el
  ingreso con `redes.Usuario` desde el 2026-11-02). Solo los unitarios: `php vendor/bin/codecept run Unit -c backend`. Usan la base LOCAL de
  desarrollo (`common/config/test-local.php`, no se sube a git); cada test corre en una
  transacción que se deshace al terminar, así que no dejan datos
- Análisis estático: `composer static` (PHPStan, nivel 5; correrlo antes de cada PR, como los tests;
  debe terminar en "No errors"). Desde el 2026-10-31 analiza también `backend/services` y
  `backend/components`, no analiza `frontend/`, y usa una LÍNEA BASE (`phpstan-baseline.neon`) con los
  avisos que ya existían (casi todos falsas alarmas de tipos, como `->all()` sin `@var Modelo[]`): solo
  muestra los NUEVOS. Al anotar un tipo y corregir un aviso viejo, regenerar la línea base con
  `php vendor/bin/phpstan analyse --generate-baseline phpstan-baseline.neon`
- URL local del backend: http://presupuestos-redes.test:8080
- Usuario de prueba local: admin / admin123. Desde el 2026-10-25 el ingreso usa
  `redes.Usuario` (usuarios TEMPORALES hasta integrar la autenticación institucional;
  modelo `backend\models\Usuario` = `identityClass`, formulario `backend\models\LoginForm`,
  migración `m261026_003449_crear_usuario_temporal`, que copió los de `dbo.user`). Se
  administran solo por consola: `php yii usuario` (lista), `usuario/crear <usuario>`,
  `usuario/clave`, `usuario/desactivar`, `usuario/activar` (`UsuarioService`; contraseña de 8+
  caracteres pedida por teclado, oculta en Linux). Un usuario no se borra, se desactiva. El
  `frontend` sigue con `common\models\User` / `dbo.user` de la plantilla

## Riesgos y pendientes activos
- RESUELTO: las migraciones están aplicadas (verificado con `php yii migrate/history` el
  2026-10-19; la última es `m261110_060000_especificacion_sin_anio`, aplicada el 2026-11-10), incluida `m260831_014824_create_schema_redes`, las dos de partida
  (`m261005_033949_add_partida_to_subcatalogo_item`,
  `m261005_034753_add_partida_observacion_to_presupuesto_detalle`) y las de especificaciones
  (`m261015_035448_libreria_especificaciones`, `m261017_231816_versiones_y_emisiones_especificacion`).
- PENDIENTE (encargado + Ing. de DTIC): confirmar desde qué número siguen PT, PR y ET en
  "Codificación de documentos" si el registro de Google Drive ya tiene números usados (hoy ET y PR
  empiezan en 1, decidido el 2026-10-22). Confirmar con el Ing. si el control de elaboración (presupuesto
  y formulario) lleva también "Revisado por" (8.12 lo menciona; hoy 2 firmas). Revisar la nota final del formulario
  ("Señor proveedor ser evaluado…": parece faltarle "para"; está tal cual el formulario oficial)
- PENDIENTE: las 165 fichas importadas del banco (2026-10-18, `--desde=2025`) están SIN categoría
  (filtro "— Sin categoría —" de la librería); al editar una, el formulario exige categoría.
  RESUELTO (2026-10-18): la ficha id 378 se llamaba "CÁMARA PTZ" por un error del .docx
  `840 NVR NVR1108HS-8P-S3_H` del banco; ahora es "NVR de 8 Canales PoE" (Dispositivos de
  comunicacion). Conviene revisar el CSV de la importación con el Ing.
  2026-10-20: en la base LOCAL se asignó el ítem de referencia (`idSubcatalogo`) a 78 fichas, solo
  donde la coincidencia con un ítem activo del subcatálogo era segura (79 en total con la ficha 14).
  Fue un UPDATE a mano, no una migración (los id de las fichas importadas cambian entre bases); en
  el servidor de DTIC habrá que repetirlo desde la pantalla o con un script propio
- Preparado para el servidor de DTIC (2026-10-18; guía en `docs/guia-instalacion-dtic.md`):
  - Toda la aplicación exige sesión (`'as access'` en `backend/config/main.php`); solo
    `site/login` y `site/error` son públicos. Un controlador nuevo queda protegido solo.
  - Archivos subidos en `backend/uploads/` (FUERA de `backend/web`), entregados por
    `cotizacion/documento` y `especificacion/adjunto`; `ArchivoSubido::rutaAbsoluta()` rechaza
    rutas fuera de esa carpeta (el adjunto se puede escribir a mano).
  - Zona horaria `America/La_Paz` (`common/config/main.php`, también `formatter.defaultTimeZone`).
  - Historial de migraciones en `redes.migration` (`console/controllers/MigrateController.php`;
    copió el de `dbo.migration`, que en la base local se puede borrar). Las migraciones de la
    plantilla (`dbo.user`) solo crean la tabla en desarrollo (`YII_ENV_DEV`); el backend ya no
    la usa para ingresar (ver `redes.Usuario`).
  - mPDF escribe sus temporales en `@runtime/mpdf`, no en `vendor/`.
  - `assetManager.appendTimestamp` (2026-10-28): CSS y JS salen con `?v=<fecha>`, así el navegador
    no se queda con una versión vieja después de un cambio.
  - Probado en compatibilidad 110 (SQL Server 2012; la base local es SQL Server 2022, compatibilidad 160). Última
    vez el 2026-11-11 (hallazgo 1 de la auditoría), con las 70 migraciones: en una base vacía con `YII_ENV=prod` se
    aplican todas, se deshacen 67 (la del esquema `redes` no: ahí vive `redes.migration`, es esperable) y se vuelven a
    aplicar sin errores; y con una copia de la base local en nivel 110, los 246 tests pasan. Cómo repetirlo: base nueva
    con `ALTER DATABASE … SET COMPATIBILITY_LEVEL = 110` + copia de `dbo.CatalogoSigma` (`SELECT … INTO`), un `yii`
    con otro `dsn` para las migraciones, y `common/config/test-local.php` apuntando a la copia para los tests. Ojo: el
    nivel 110 en un 2022 no es un 2012 real (algunas funciones nuevas funcionan igual); la prueba final es en el
    servidor de la DTIC
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
- PENDIENTE (DTIC): integrar el ingreso con el sistema de usuarios institucional; mientras
  tanto, usuarios temporales en `redes.Usuario`. Al integrarlo se cambia `identityClass` en
  `backend/config/main.php`; el resto del módulo lee el usuario SOLO con
  `backend\components\UsuarioActual::nombre()` (2026-10-31), que es el único lugar que usa `identity->username`
  `redes.Usuario` NO es la "gestión de usuarios y roles propia" que está fuera de alcance: sin
  roles, sin pantallas, solo para poder ingresar al servidor de prueba. Informarlo al tutor
- Conexión a la BD en producción no resuelta: Windows Auth funciona en entorno local, pero en
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
- PENDIENTE (tutor): el precio directo (2026-10-21) agrega `PresupuestoDetalle.fuentePrecio` al
  diagrama de clases y hace opcional la cotización en la secuencia "Elaborar presupuesto"
- PENDIENTE (tutor): agregar `ConfiguracionSolicitudCotizacion` (2026-10-19) al diagrama de clases
- PENDIENTE (tutor): agregar `ConfiguracionPresupuesto` y el atributo `Presupuesto.manoDeObra`
  (2026-10-20) al diagrama de clases
- PENDIENTE (tutor): se QUITARON los cómputos métricos (2026-10-21). Estaban en el alcance
  confirmado ("SÍ incluye") y son una de las 10 clases del diagrama de clases: informar al tutor y
  al Ing. de DTIC, y sacar `ComputoMetrico` del diagrama de clases, del de componentes y de los
  casos de uso donde aparezca
- PENDIENTE (tutor): agregar `CartaPresupuesto` (0..1 por Presupuesto, composición) y
  `ConfiguracionCarta` (2026-10-21) al diagrama de clases, y el caso de uso "Elaborar carta de
  entrega" (extiende Elaborar presupuesto)
- PENDIENTE (tutor): codificación del lineamiento (2026-10-22): clase `CorrelativoDocumento`,
  `Presupuesto.fechaAprobacion` y `numeroAnterior`, `EspecificacionEmision.serie` (ET / NET) y
  `codigoAnterior`, `EspecificacionEmision.versionDocumento` (2026-10-24: reemitir = mismo código, versión + 1),
  `Presupuesto.firma1Prefijo…firma2Cargo` {congelado al aprobar} (2026-10-24), se quita
  `ConfiguracionEspecificacion.siguienteNet`, caso de uso "Configurar codificación de documentos"; 2026-10-29:
  `EspecificacionEmision.proceso` {congelado} (`CorrelativoDocumento.anio` se agregó el 2026-10-29 y se quitó el
  2026-11-10: la ET ya no lleva año); resumen
  en `docs/cambios-codificacion-lineamiento.md`
- PENDIENTE (tutor): secciones sin precio (2026-10-29): `PresupuestoComplemento` (2026-10-23) ya NO existe; nuevas
  clases `ProyectoMaterialDtic` (composición 0..* desde Proyecto, asociación 0..1 a SubcatalogoItem),
  `ProyectoCondicionPrevia` (composición 0..* desde Proyecto) y `TipoCondicionPrevia` (catálogo); caso de uso
  "Gestionar tipos de trabajo complementario"; "Elaborar lista de ítems" incluye registrar materiales de la DTIC y
  trabajos complementarios (antes en "Elaborar presupuesto")
- PENDIENTE (tutor): lista de materiales normada (2026-10-30, paso 8b): clase `ListaMaterialesAprobacion`
  (composición 0..* desde Proyecto; version, fechaAprobacion, usuario {copia}, firmas {congelado}); la secuencia
  "Enviar a cotización" termina aprobando una versión. Informar al Ing. que la lista lleva el código del proyecto
- PENDIENTE (tutor): unidad solicitante y área de intervención (2026-10-28): `Proyecto.unidadSolicitante`
  y `Proyecto.unidadDependencia` (2026-10-29: beneficiaria, distinta de la solicitante),
  clase `ProyectoAreaIntervencion` (composición 0..*), `ConfiguracionEspecificacion.lugarEntregaPorDefecto`,
  componente externo "Infraestructura física (UsfxGEO)"; salen facultad/edificio/bloques. Ver
  `docs/cambios-unidad-area-intervencion.md`
- PENDIENTE (tutor): equipo del proyecto (2026-10-29): clases `Personal` (catálogo, sin asociación: el proyecto
  copia el nombre), `RolProyecto` (catálogo) y `ProyectoParticipante` (composición 0..* desde Proyecto; nombre
  {copia}, esResponsable, orden; asociación 1 RolProyecto → 0..*); sale `Proyectista`. Casos de uso "Gestionar
  personal" y "Gestionar roles del proyecto"; "Registrar proyecto" incluye el equipo
- PENDIENTE (tutor): historial (2026-10-29): clase `HistorialProyecto` (sin asociación navegable: guarda idProyecto
  y una copia del código para sobrevivir a la eliminación; usuario {copia}); caso de uso "Consultar historial del
  proyecto"; las secuencias que cambian datos, precios o estados terminan registrando el hecho
- PENDIENTE (Ing. de DTIC / Desarrollo): ¿seguir leyendo las tablas de UsfxGEO o usar vistas? ¿qué son
  los estados E, C, D, R? Confirmar el lugar de entrega habitual de los proyectos que no son de una facultad
  (Rectorado, Canal, DTIC) para el texto por defecto de la configuración del formulario (hoy vacío) y las preguntas abiertas de la propuesta (nota de solicitud, reportes)
- PENDIENTE (tutor): agregar `Proyecto.lugarEntrega` (2026-11-03) al diagrama de clases
- PENDIENTE (tutor): revisión del 2026-11-05 (partes 1 a 5d): `Proyecto.numeroProyecto` (común a las fases; cada fila es
  una fase, asociación "fases" por el número), `Proyecto.codigoLista`, `Proyecto.codigo` = código de la ficha (solo fase 1),
  `ListaMaterialesAprobacion` = versión emitida (no aprobada), tipos NP y LM del correlativo; caso de uso "Agregar fase"
  (reemplaza "Crear fase siguiente"); secuencias y actividades. Resumen en `docs/cambios-revision-2026-11-05.md`
- PENDIENTE (tutor): `SolicitudCotizacion.codigoDocumento` y el tipo SC del correlativo (2026-11-09) en el diagrama de
  clases; informar al Ing. que la solicitud tomó código propio RD-PI-SC-NNN como en los formatos corregidos v3
- PENDIENTE (tutor): orden de cambio (2026-11-09, parte 6b): en el diagrama de clases, auto-asociación de Proyecto
  "ordenesCambio" 0..* (idFaseOrigen) y los atributos numeroOrdenCambio, descripcionCambio, motivoCambio, tipoCambio; caso de
  uso "Registrar orden de cambio" (extiende Consultar proyecto, sobre una fase aprobada) y "Eliminar orden de cambio";
  clase `TipoOrdenCambio` (catálogo, asociación 1 tipo → 0..* órdenes) y caso de uso "Gestionar tipos de orden de cambio"
- PENDIENTE (tutor): agregar `TratamientoCarta` (2026-11-04; catálogo sin asociación: la carta copia el nombre) al
  diagrama de clases; "Configurar carta de entrega" incluye la lista de tratamientos
- PENDIENTE (tutor): agregar la clase `Proceso` (2026-10-29; catálogo, asociación 1 proceso → 0..* proyectos)
  al diagrama de clases y el caso de uso "Gestionar procesos"
- PENDIENTE (tutor): agregar `TipoProyecto` (2026-10-19) al diagrama de clases, asociado a
  Proyecto y a Plantilla (0..1 tipo por proyecto/plantilla)
- RESUELTO (2026-10-29): tipos de proyecto confirmados por el Ing. de DTIC (Ampliación, Mejoramiento,
  Refuncionalización, Equipamiento). En la reunión nombró también "Despliegue" una vez: si hace falta,
  el encargado lo agrega en "Tipos de proyecto"
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
  ComputoMetrico) + enumerado EstadoPresupuesto. ACTUALIZACIÓN 2026-10-21: ComputoMetrico se quitó
  del sistema (ver "NO incluye"); falta sacarla del diagrama. Composición (rombo relleno) en
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