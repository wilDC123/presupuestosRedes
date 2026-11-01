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
- CODIFICACIÓN según el lineamiento de la DTIC RD-GD-LI-001 (2026-10-22; resumen y decisiones en
  `docs/cambios-codificacion-lineamiento.md`): `RD-PI-PT-NNN` proyecto, `RD-<proceso>-PR-NNN` presupuesto,
  `RD-<proceso>-ET-NNN/AA` especificación técnica (3 cifras; después del 999 sigue con 1000). Desde la
  revisión del 2026-10-29 (paso 4, migración `m261030_024446_especificacion_con_proceso_y_anio`): PR y ET
  llevan el proceso DEL PROYECTO (el PT siempre PI), con UN solo número para todos los procesos; SOLO la
  ET lleva el año ("/26") y vuelve a 001 cada 1 de enero (`CorrelativoDocumento.anio`, `reiniciaPorAnio()`;
  año del calendario de Bolivia, `CodificacionService::anioActual()`); PT y PR no llevan año ni se
  reinician. Lo emitido conserva su código: `EspecificacionEmision.proceso` y `anio` congelados (reemitir en
  enero un 850/26 da 850/26 v02); el presupuesto guarda el código entero en `numeroPresupuesto`
  (`Presupuesto::codigoProceso()`). La línea "Proceso:" de la cabecera de los PDF sale de
  `Proceso::nombreDe()` con ese código. Las ET emitidas antes (021–024, todas de 2026) pasaron a /26. Los números los da
  el sistema desde `redes.CorrelativoDocumento` (una fila por tipo, `CodificacionService::tomar()` con
  UPDLOCK); el encargado fija desde cuál siguen en el menú "Codificación de documentos" (solo mayor que
  el último usado). El formato está en `CorrelativoDocumento::codigo()`. La solicitud de cotización NO
  lleva código del lineamiento (FR no aplica). Letra Carlito (= medidas de Calibri, OFL,
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
- FICHA TÉCNICA DEL PROYECTO (revisión del 2026-10-29, paso 7; formato PROVISIONAL hasta que el Ing. de DTIC
  pase el suyo): PDF oficio desde el botón "Imprimir ficha técnica" de la tarjeta "Datos generales" de la vista
  del proyecto (`proyecto/ficha`, `ProyectoController::actionFicha()`, vista `proyecto/ficha-pdf.php`, datos de
  `ProyectoService::datosFicha()`), se abre en el navegador para imprimir. Secciones: 1 datos (código, nombre,
  descripción, tipo, proceso, fase), 2 unidades, 3 área de intervención (agrupada), 4 equipo (persona, rol,
  responsable), 5 fechas (las de `HistorialService::fechas()`), 6 documentos de la carpeta económica (presupuesto
  con estado y total, especificaciones vigentes, solicitudes de cotización); al pie, cuándo y quién la imprimió.
  "Es un reporte de control, no de la gestión documental": cabecera SIN código del lineamiento (la de la
  solicitud: código del proyecto | unidad / dependencia | fecha de impresión | página), sin firmas ni marca de agua
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
  se convirtieron a RD-PI-PR y guardan el número viejo en `Presupuesto.numeroAnterior`). La cabecera (oficio y área) se exige recién al "Enviar a revisión" (escenario
  `Presupuesto::SCENARIO_ENVIAR`), junto con que todos los ítems tengan precio. Los estados se
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
    'ET', desde el 2026-10-22, correlativo desde 1; desde el 2026-10-29 `RD-<proceso>-ET-001/26`, ver
    CODIFICACIÓN; índice único por serie + `anio` + `numeroNet` + `versionDocumento`;
    `EspecificacionEmision::codigo()`. Las emitidas antes con `NET:880/26` se convirtieron a ET con la
    migración `m261022_144239_convertir_codigos_viejos_al_lineamiento` y guardan el NET en
    `codigoAnterior`, que se ve y se busca en el registro; la serie 'NET' queda solo para su `safeDown()`) que se firma y se
    entrega al proveedor. NUNCA cambia: guarda copias congeladas (título = nombre DTIC del ítem de la lista desde el
    2026-10-21, antes el de la ficha; cantidad, unidad,
    dependencia = unidad / dependencia del proyecto desde el 2026-10-29 (del 28 al 29 la solicitante, antes la facultad), garantía, textos,
    condiciones de entrega; el lugar de entrega por defecto es desde el 2026-10-28 el texto
    `ConfiguracionEspecificacion.lugarEntregaPorDefecto` de la configuración del formulario (vacío = no sale;
    del 2026-10-26 al 28 fue "Administración de la <facultad>"),
    `EspecificacionEmisionService::lugarEntregaPorDefecto()`, las anteriores conservan el suyo) y apunta a una
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
  y CSV. Cambiar la ficha por otra sí da código nuevo (es otro documento). Reemitir se permite mientras el presupuesto no
  esté aprobado ni cancelado. Correlativo: el ET de `redes.CorrelativoDocumento` (antes
  `ConfiguracionEspecificacion.siguienteNet`, eliminado el 2026-10-22; esa tabla sigue con los textos fijos
  del formulario), sube de 1 en 1, vuelve a 001 cada 1 de enero (2026-10-29) y nunca se reutiliza. Avisos que
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
  Especificación | Propuesta | Cumple | No cumple, secciones I, II y III); marca de agua
  REEMPLAZADO / ANULADO / VENCIDO. Al fondo de la última página, el "control de elaboración" del
  lineamiento, como el del presupuesto (2026-10-22, vista compartida `views/pdf/_control-elaboracion.php`):
  dos firmas SIN tabla (raya, nombre, cargo, "ELABORADO POR" / "APROBADO POR") y la línea "Versión: 02
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
- Cotizaciones de proveedores (relación N:N con ítems/especificaciones). Una línea que viene de
  una solicitud guarda contra qué NET cotizó (`CotizacionDetalle.idEspecificacionEmision`, no
  cambia si el NET se reemite); una cotización suelta solo elige la ficha (`idEspecificacion`)
- Textos fijos del PDF de la solicitud de cotización (cabecera, instrucciones, nota final) en
  `redes.ConfiguracionSolicitudCotizacion` (una sola fila, igual que la del NET), editables en
  "Configuración del documento" (`solicitud-cotizacion/configuracion`). La solicitud NO guarda
  copia: un cambio se ve también al reimprimir las anteriores. Título, columnas y bloque del
  proveedor siguen fijos en `backend/views/solicitud-cotizacion/pdf.php`
- SOLICITUD DE PROFORMA DE COTIZACIÓN (revisión del 2026-10-29, paso 10, decidido con el desarrollador el 2026-10-30):
  el PDF de la solicitud es un documento formal para el PROVEEDOR: título "SOLICITUD DE PROFORMA DE COTIZACIÓN",
  la misma cabecera de hoy (USFX y DTIC, sin código del lineamiento: referencia RD-PI-PT-022 / SC-01, unidad, fecha,
  página), una lista sencilla N.° | Descripción | Unidad | Cantidad (en la descripción siguen el código ET, la
  marca / modelo de referencia y la observación) y abajo el espacio para el proveedor: firma y sello, nombre /
  empresa y fecha. SIN columnas de precio ni marca ofertada, sin TOTAL, NIT, validez ni tiempo de entrega (los trae
  la proforma que devuelve el proveedor) y SIN firmas de la DTIC. Archivo `solicitud_proforma_…pdf`. Las pantallas
  siguen diciendo "Solicitud de cotización". La migración `m261030_200322_instrucciones_solicitud_de_proforma`
  cambió el texto de instrucciones por defecto ("Se solicita enviar su proforma de cotización…") solo si nadie lo
  había editado. PENDIENTE (Ing. de DTIC): ¿se adjuntan las especificaciones? (hoy, a elección al imprimir); en la
  reunión también se nombró la solicitud entre los documentos "a normar": hoy no lleva código del lineamiento; la
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
  defecto) y `HOJA_OFICIO_HORIZONTAL` (reporte de relación con SIGMA)
- Cabecera de página de los impresos (2026-10-21, `backend/views/pdf/_cabecera.php`, etiqueta
  `<htmlpageheader>` de mPDF): recuadro con escudo USFX | títulos + fila de datos | logo DTIC
  (`backend/web/img/logo-usfx.png`, `logo-dtic.png`, reducidos), repetida en cada página, a 5 mm del
  borde. Con `codigo` (presupuesto y formulario de especificación, 2026-10-22): "Proceso: …", título y
  fila Código | Versión | Fecha | Página (lineamiento 8.8.2). Sin `codigo` (solicitud de cotización):
  fila N.° / facultad / fecha / pág., y las 2 primeras líneas salen de "Configuración del documento".
  Al juntar varios documentos en un PDF cada uno sale con su cabecera
  (`PresupuestoPdfGenerator::empezarDocumento()`)
- Generación de PDF consolidado del presupuesto: marca de agua "BORRADOR" mientras no esté
  aprobado. Una tabla por categoría con su subtotal (solo la primera lleva los títulos de columnas; las demás
  usan los mismos anchos, 2026-10-21) y "TOTAL GENERAL Bs." al final; N.° corrido
  entre categorías y en cada línea el nombre DTIC con su observación (columnas como la solicitud
  de cotización, más Partida). Desde el 2026-10-21 el nombre DTIC lleva al lado, entre paréntesis, el código NET de la emisión
  VIGENTE del ítem (sin emisión vigente no sale), y en el mismo paréntesis el modelo sugerido:
  "(NET:897/26 – Dahua DH-PFS3110-8P-96)" (`ProyectoItem::modeloSugerido()`: de la versión EMITIDA, o de la actual de la ficha si
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
  "APROBADO POR" (firma 2, V°B°), SIN tabla (decidido el 2026-10-22: el 8.12 pide el contenido, no una
  tabla), y debajo "Versión: 01 · Fecha de aprobación" (`Presupuesto.fechaAprobacion`, la pone
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
    versión (01 la primera vez, +1 después de cada orden de cambio), nunca se modifica: fecha, usuario y COPIA de
    las firmas de "Configuración del PDF" del presupuesto (trait `FirmasDeControl`); se anota en el historial
    (`HistorialProyecto::ACCION_LISTA_APROBADA`). No guarda copia de la lista (mientras está aprobada no cambia).
    `ListaItemsService::documento()`: en edición imprime la versión SIGUIENTE con marca BORRADOR y las firmas de
    hoy; enviada o cerrada, la última aprobada. La "Fecha" de la cabecera es la de CREACIÓN de la versión (paso 9):
    la v01 nace con el proyecto (`Proyecto.fechaCreacion`), cada una siguiente con la orden de cambio que reabrió
    la lista (la 1.ª orden crea la v02…); la de aprobación va abajo. Las 4 listas ya enviadas recibieron su v01 sin
    fecha de aprobación. La versión sale también en la pantalla de la lista y en la ficha técnica.
    Sin "documentos relacionados" (opcional en el nivel D: cada ítem ya cita su ET)
  - RESUELTO (paso 9, transcripción del 29): la mano de obra de la DTIC se queda en el presupuesto con 0 ("cantidad
    0, precio 0, total 0… pero tiene un valor cero"); la contratada es un ítem con precio
- Carta de entrega del presupuesto (2026-10-21, `CartaController`, `CartaService`; botón "Carta de
  entrega" en el paso Presupuesto): la nota con membrete que acompaña al presupuesto. UNA por
  presupuesto (`redes.CartaPresupuesto`, índice único, FK CASCADE). Se escribe y guarda en CUALQUIER
  estado; para imprimir solo se exige la carta guardada y que el presupuesto tenga `oficioDtic` y
  `numeroPresupuesto` (NO que esté aprobado). Sin marca de agua. GUARDA su texto (copiado de
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
  Cargo sugerido "DECANO/DECANA DE LA <FACULTAD>" según el tratamiento (`web/js/carta.js`). PDF
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
- El PATH de Windows ya está configurado para que `php` (sin ruta completa) use el
  de Laragon (8.2.33, con el driver sqlsrv) por defecto. Si alguna terminal nueva
  muestra una versión distinta, verificar con `php -v` antes de correr comandos.
- Tests unitarios: `php vendor/bin/codecept run Unit -c backend`. Usan la base LOCAL de
  desarrollo (`common/config/test-local.php`, no se sube a git); cada test corre en una
  transacción que se deshace al terminar, así que no dejan datos
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
  2026-10-19; la última es `m261030_200322_instrucciones_solicitud_de_proforma`, aplicada el 2026-10-30), incluida `m260831_014824_create_schema_redes`, las dos de partida
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
  `EspecificacionEmision.proceso` {congelado} y `CorrelativoDocumento.anio` (ET por año); resumen
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
  los estados E, C, D, R? Confirmar el texto del lugar de entrega por defecto (hoy vacío: llenarlo en la
  configuración del formulario) y las preguntas abiertas de la propuesta (nota de solicitud, reportes)
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