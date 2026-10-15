# Especificación Técnica — Sistema de Presupuestos de Redes (para construcción con Claude Code)

Este documento consolida el modelo de datos y las reglas de negocio ya validadas
(diagramas de Casos de Uso, Clases, Secuencia, Actividades y Componentes) en un
formato directamente utilizable para generar migraciones, modelos, controladores
y vistas en Yii2 Advanced. Úsalo junto con CLAUDE.md.

## Orden de construcción (respetar esta secuencia de dependencias)

1. Categoria (sin dependencias)
2. Proveedor (sin dependencias)
3. Proyecto (sin dependencias)
4. SubcatalogoItem (depende de Categoria, referencia externa a dbo.CatalogoSigma)
5. Especificacion (depende de Categoria; SubcatalogoItem solo como referencia opcional)
6. Cotizacion (depende de Proveedor)
7. CotizacionDetalle (depende de Cotizacion, SubcatalogoItem, Especificacion)
8. Presupuesto (depende de Proyecto, auto-referencia a si misma)
9. PresupuestoDetalle (depende de Presupuesto, SubcatalogoItem, CotizacionDetalle)
10. ComputoMetrico (depende de Proyecto)

## Modelo de datos completo (columnas, tipos, reglas)

### redes.Categoria
- id (PK, int, identity)
- nombre (varchar 100, requerido)
- orden (int, requerido)
- Datos semilla obligatorios (7 filas, orden 1-7): "Cable redes", "Materiales y
  accesorios para redes", "Materiales ducteado de redes", "Cableado de fibra
  optica", "Armario de telecomunicaciones", "Dispositivos de comunicacion",
  "Mano de obra"

### redes.Proveedor
- id (PK, int, identity)
- razonSocial (varchar 200, requerido)
- nit (varchar 50)
- contacto (varchar 200) -- nombre de persona, ej "Ing. Juan Diaz"
- telefono (varchar 100)
- direccion (varchar 300)

### redes.Proyecto
- id (PK, int, identity)
- codigo (varchar 50, opcional)
- nombre (varchar 300, requerido)
- facultad (varchar 200)
- edificio (varchar 200)
- bloque (varchar 100)
- fechaCreacion (datetime, default now)

### redes.SubcatalogoItem
- id (PK, int, identity)
- idSigma (uniqueidentifier, NULL permitido) -- FK logica a dbo.CatalogoSigma,
  SOLO LECTURA de esa tabla, nunca escribir ahi
- idCategoria (FK -> Categoria, requerido)
- descripcion (varchar 300, requerido) -- es el NOMBRE DTIC: corto y generico
  ("Switch PoE", "Regleta tapacable", "Jack RJ45 hembra categoria 6"). Es el
  que usan listados, presupuesto y PDF. El nombre oficial SIGMA NO se copia:
  se lee por la relacion SubcatalogoItem::getCatalogoSigma()
- unidadDefecto (varchar 20) -- ej "Caja" "Unidad" "Metro" "Pieza"
- activo (bit, default 1)
- fechaRegistro (datetime, default now)
- partida (nchar 5, NULL en la base pero obligatoria al guardar) -- partida
  presupuestaria de 5 digitos (ej "39700"), que es dbo.CatalogoSigma.IdGasto
- REGLA DE NEGOCIO (partida): categoria y partida son datos DISTINTOS y se
  guardan los dos -- una misma categoria DTIC mezcla varias partidas (ej
  "Dispositivos de comunicacion": switch 43120, camara 43500, disco duro
  39800). Si el item tiene idSigma y SIGMA trae IdGasto, el servicio copia
  esa partida al guardar (despues de load() y antes de save(), porque la
  validacion corre dentro de save()) y gana sobre lo que venga del
  formulario. Si no tiene idSigma (ej mano de obra, partida 25xxx) o SIGMA no
  trae partida, se ingresa a mano
- REGLA DE NEGOCIO (reutilizacion de idSigma): SIGMA es generico, asi que
  VARIOS items DTIC pueden apuntar al mismo idSigma (ej "Switch PoE 24p" y
  "Switch 48p"). No hay restriccion de unicidad sobre idSigma: al elegir una
  fila de SIGMA ya usada se muestra el aviso "ya usado por: <nombres DTIC>",
  que NO bloquea. Las comparaciones de idSigma en PHP se hacen en minusculas
  (strtolower), porque un uniqueidentifier puede llegar en mayusculas o
  minusculas segun el origen
- REGLA DE NEGOCIO (nombre DTIC): al agregar desde SIGMA, descripcion se
  prellena con la Clase de SIGMA ("JACK" -> "Jack") y el usuario la edita. Los
  detalles de una compra concreta ("2 metros", "12mm x 4 metros", "segun
  especificacion") NO van en el subcatalogo: van en la observacion de la
  linea del presupuesto
- LISTADO: nombre DTIC, categoria, nombre SIGMA (puede ser vacio), partida,
  unidad, activo; con filtro por partida
- REGLA DE NEGOCIO: nunca clasificar todo dbo.CatalogoSigma de antemano. Los
  registros se agregan uno por uno, bajo demanda, cuando el usuario busca en
  el catalogo institucional y decide agregarlo a su subcatalogo
- BUSQUEDA en dbo.CatalogoSigma (backend\models\CatalogoSigma::buscar()):
  solo items vigentes (CodigoEstado = 'V'). El texto se separa en palabras y
  deben aparecer TODAS (AND), cada una en Clase, Descripcion o RamaComercial
  (OR), con parametros bindeados (condicion 'like' de Yii). "jack cat 6"
  encuentra "JACK ... RJ45 CAT 6". Maximo 100 filas, con aviso en pantalla al
  alcanzarlo; la tabla de resultados muestra la partida (IdGasto).
  Limitacion conocida: "RJ45" no encuentra "RJ-45". La copia local de
  desarrollo tiene 51.636 filas y cada busqueda tarda ~0,4 s (LIKE
  '%palabra%' recorre toda la tabla). PENDIENTE: si se vuelve lenta, migrar a
  un indice de Full-Text Search de SQL Server sobre dbo.CatalogoSigma --
  requiere coordinacion con el equipo tecnico institucional, ya que esa tabla
  no pertenece al esquema `redes`
- DATOS REALES de dbo.CatalogoSigma (verificado 2026-10-04): IdSigma
  uniqueidentifier; IdGasto varchar(20), siempre 5 digitos, sin nulos;
  CodigoEstado char(1), unico valor 'V'; collation Modern_Spanish_CI_AS

### redes.Especificacion (LIBRERIA de especificaciones tecnicas)
- id (PK, int, identity)
- idCategoria (FK -> Categoria, NULL en la base pero obligatoria al guardar
  desde el formulario) -- mismas categorias del subcatalogo
- idSubcatalogo (FK -> SubcatalogoItem, NULL permitido) -- "item de
  referencia", solo informativo: NO limita donde se puede usar la ficha
- codigoNet (varchar 20) -- formato "NET:832/26"
- titulo (varchar 300)
- dependencia (varchar 200)
- modeloSugerido (varchar 200)
- documentoRuta (varchar 500) -- ruta al PDF/DOCX original, la fuente de verdad
  para el detalle tecnico completo (20-30 puntos), que NUNCA se estructura en
  columnas de base de datos
- fechaEmision (date)
- activo (bit, default 1)
- REGLA DE NEGOCIO (libreria, decidida en la demo con el ingeniero): la
  especificacion NO pertenece a un item ni a un proyecto. Se carga una vez, se
  clasifica por categoria y se ENLAZA a los items de la lista de cada proyecto
  (redes.ProyectoItem.idEspecificacion). Asi un mismo tipo de item puede usar
  fichas distintas segun el proyecto (ej "Camara IP basica" vs "Camara IP alta
  gama") y una ficha sirve a muchos items de muchos proyectos
- REGLA DE NEGOCIO (opcional): no todos los items tienen especificacion. Un
  item sin especificacion es un caso normal: ni la lista, ni la solicitud de
  cotizacion, ni la cotizacion, ni el presupuesto, ni los PDF fallan por eso
- REGLA DE NEGOCIO: el codigo del proyecto NO se guarda en la especificacion;
  los impresos lo muestran junto a ella ("Segun especificacion tecnica
  NET:012/26 — proyecto RD-...")
- REGLA DE NEGOCIO: los selectores solo ofrecen especificaciones ACTIVAS; una
  desactivada se conserva donde ya estaba enlazada. Una especificacion en uso
  (ProyectoItem o CotizacionDetalle) no se elimina: se desactiva
- No se redactan especificaciones en el sistema: se suben (PDF/DOCX, con
  "Extraer datos automaticamente") o se enlazan (documentoRuta acepta un
  enlace https://, ej Google Drive)

### redes.ProyectoItem (solo la parte de especificaciones)
- idEspecificacion (FK -> Especificacion, NULL permitido, sin ON DELETE
  CASCADE) -- COMO MAXIMO una especificacion por item de la lista. Se elige en
  la pantalla de la lista del proyecto y sigue el mismo bloqueo que el resto de
  la lista (con la lista enviada a cotizacion, hace falta una orden de cambio)
- La solicitud de cotizacion, el PDF de la lista y el presupuesto (vista y
  PDF) citan la especificacion enlazada del item

### redes.Cotizacion
- id (PK, int, identity)
- idProveedor (FK -> Proveedor, requerido)
- numeroReferencia (varchar 100)
- fecha (date)
- documentoRuta (varchar 500) -- PDF original de la cotizacion, adjunto de
  respaldo
- validezDias (int)
- REGLA DE NEGOCIO (vencimiento, Cotizacion::estaVencida()): vencida cuando
  fecha + validezDias < hoy. Si falta fecha o validezDias (ambas permiten
  NULL) se considera NO vencida. Una cotizacion vencida nunca bloquea: solo
  genera avisos (ver PresupuestoDetalle)

### redes.CotizacionDetalle
- id (PK, int, identity)
- idCotizacion (FK -> Cotizacion, requerido, ON DELETE CASCADE -- composicion)
- idSubcatalogo (FK -> SubcatalogoItem, requerido)
- idEspecificacion (FK -> Especificacion, NULL permitido)
- marcaModelo (varchar 300)
- cantidad (decimal 10-2)
- precioUnitario (decimal 10-2)
- precioTotal (calculado = cantidad * precioUnitario, columna persisted o
  calculada en la capa de aplicacion)
- REGLA DE NEGOCIO: una Cotizacion puede tener varias lineas (items distintos);
  un mismo item/especificacion puede aparecer en varias cotizaciones de
  distintos proveedores

### redes.Presupuesto
- id (PK, int, identity)
- idProyecto (FK -> Proyecto, requerido)
- idVersionAnterior (FK -> Presupuesto misma tabla, NULL permitido) --
  auto-referencia para el versionado
- numeroVersion (int, requerido, default 1)
- numeroPresupuesto (varchar 20) -- ej "677"
- oficioDtic (varchar 100)
- areaIntervencion (varchar 300)
- estado (varchar 20, requerido) -- valores permitidos EXACTOS: 'en_proceso'
  'en_revision' 'observado' 'aprobado' 'cancelado' -- default 'en_proceso'
- fecha (date)
- fechaCreacion (datetime, default now)
- REGLA DE NEGOCIO CRITICA: un Presupuesto con estado 'aprobado' NUNCA se edita
  directamente. Cualquier modificacion requiere crear un registro NUEVO con
  numeroVersion incrementado e idVersionAnterior apuntando al id original,
  copiando los PresupuestoDetalle existentes antes de aplicar el cambio nuevo
- REGLA DE NEGOCIO: transiciones de estado validas -- en_proceso -> en_revision
  -> (observado -> vuelve a en_proceso) -> aprobado / cancelado

### redes.PresupuestoDetalle
- id (PK, int, identity)
- idPresupuesto (FK -> Presupuesto, requerido, ON DELETE CASCADE -- composicion)
- idSubcatalogo (FK -> SubcatalogoItem, requerido)
- idCotizacionDetalle (FK -> CotizacionDetalle, NULL permitido) -- origen del
  precio, opcional porque a veces se ingresa manualmente sin cotizacion
  registrada todavia
- cantidad (decimal 10-2, requerido)
- unidadMedida (varchar 20)
- precioUnitario (decimal 10-2, requerido) -- CONGELADO: se copia del
  CotizacionDetalle (o se ingresa manual) EN EL MOMENTO de crear esta fila y
  JAMAS se recalcula automaticamente despues, aunque cambie el precio de la
  cotizacion origen
- precioTotal (calculado = cantidad * precioUnitario)
- partida (nchar 5, NULL permitido) -- CONGELADA: se copia de
  SubcatalogoItem.partida EN EL MOMENTO de crear la fila
  (PresupuestoService::congelarPartida()) y JAMAS se recalcula, aunque cambie
  la partida del item. No tiene regla en rules() a proposito, para que no se
  pueda enviar desde el formulario
- observacion (nvarchar 300, NULL permitido) -- texto libre de la linea:
  medidas ("12mm x 4 metros"), "segun especificacion tecnica", etc. Se
  muestra junto al nombre DTIC en la pantalla de lineas y en el PDF
- REGLA DE NEGOCIO: si la linea usa una cotizacion (idCotizacionDetalle), esa
  cotizacion debe ser del MISMO item (CotizacionDetalle.idSubcatalogo ==
  PresupuestoDetalle.idSubcatalogo); si no coincide, error de validacion en
  el campo de cotizacion y la linea no se guarda
- REGLA DE NEGOCIO: cotizacion vencida -> se permite, con aviso (flash
  warning) al agregar la linea y al pasar el presupuesto a 'aprobado'

### Resumen por partida presupuestaria (no es tabla, es un calculo)
- PresupuestoService::resumenPorPartida(Presupuesto): agrupa las lineas por
  la partida GUARDADA en PresupuestoDetalle -> partida, n. de lineas,
  subtotal; ordenado por partida; lineas sin partida al final como "Sin
  partida"
- Se muestra en presupuesto/view.php, en presupuesto/detalle.php (debajo del
  total general) y como seccion final del PDF
- El total por partidas SIEMPRE coincide con el total por categorias (son las
  mismas lineas sumadas de otra forma). Verificado con un caso de 10 lineas y
  8 partidas: 13.536,33 por ambos caminos y por SUM() en SQL Server
- PENDIENTE: es por presupuesto (una version). El consolidado de todas las
  versiones aprobadas de un proyecto sigue sin construirse

### redes.ComputoMetrico
- id (PK, int, identity)
- idProyecto (FK -> Proyecto, requerido)
- descripcion (varchar 300) -- ej "Metros de cable horizontal Bloque B"
- valor (decimal 10-2)
- unidad (varchar 20)
- referenciaOrigen (varchar 500) -- texto libre citando el documento fuente,
  ej "Plano de instalacion Bloque B rev 2"
- REGLA DE NEGOCIO: el sistema JAMAS calcula estos valores (no analiza planos),
  solo los ALMACENA junto con su referencia de origen

## Enumerado (no es tabla, es un tipo/constante)

EstadoPresupuesto: EN_PROCESO, EN_REVISION, OBSERVADO, APROBADO, CANCELADO
(en la base de datos se guarda como texto en minusculas segun el campo
`estado` de Presupuesto arriba)

## Flujo funcional principal (del diagrama de Secuencia "Elaborar presupuesto")

1. Usuario busca un item -> primero en SubcatalogoItem, si no esta ahi, en
   dbo.CatalogoSigma (con boton para agregarlo al subcatalogo)
2. En la lista de items del proyecto, el usuario enlaza (opcionalmente) una
   especificacion de la libreria a cada item que la necesite
3. Al presupuestar un item, el sistema muestra los CotizacionDetalle
   disponibles con sus precios (y la especificacion que cita cada uno)
4. Usuario elige una cotizacion DEL MISMO ITEM (o ingresa precio manual), una
   cantidad y, si hace falta, una observacion
5. Sistema crea un PresupuestoDetalle con el precioUnitario y la partida
   congelados en ese momento y calcula precioTotal
6. El presupuesto muestra el detalle por categoria y el resumen por partida
   presupuestaria

## Flujo de versionado (del diagrama de Secuencia "Crear nueva version")

1. Usuario intenta agregar/modificar algo en un Presupuesto con estado
   'aprobado'
2. Sistema detecta que no es editable
3. Sistema crea un nuevo Presupuesto con numeroVersion+1 e idVersionAnterior
   apuntando al original, en estado 'en_proceso', SIN copiar los
   PresupuestoDetalle de la version anterior (arranca vacio)
4. El usuario arma desde cero las lineas de esta nueva fase

## Fuera de alcance (NO construir nada de esto)

- Control de ejecucion/avance de obra
- Gestion de usuarios y roles propia (usar autenticacion de prueba temporal
  hasta integrar con el sistema institucional real)
- Modulo de modificacion presupuestaria en si (este sistema solo GENERA el
  listado final que alimentaria a ese modulo externo)
- Adquisiciones/compras
- Calculo automatico de computos metricos
- Clasificacion automatica del catalogo SIGMA completo
- Codigo UNSPSC / "Catalogo de Bienes y Servicios de cuarto nivel" (SIGEP):
  se evaluara cuando se consigan las guias operativas del SIGEP y se sepa si
  la universidad ya lo relaciona con SIGMA en alguna tabla
- Indice unico sobre SubcatalogoItem.descripcion: se decide despues de ver
  como queda el subcatalogo recargado

## Decisiones de captura de datos (relevante para las vistas/formularios)

- Cotizaciones de proveedores: carga MANUAL con formulario + archivo PDF
  adjunto de respaldo. No automatizar (formato variable entre proveedores)
- Especificaciones tecnicas: candidatas a extraccion SEMIAUTOMATICA desde PDF
  (formato institucional fijo, columnas predecibles: CodigoNet Titulo
  Dependencia ModeloSugerido FechaEmision). Ya implementado: boton "Extraer
  datos automaticamente" del formulario de la libreria
- PENDIENTE (mejora posible): enlazar especificaciones desde las plantillas
  (PlantillaItem), para que se copien a la lista al cargar una plantilla
