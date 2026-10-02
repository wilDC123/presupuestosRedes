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
5. Especificacion (depende de SubcatalogoItem)
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
- descripcion (varchar 300, requerido)
- unidadDefecto (varchar 20) -- ej "Caja" "Unidad" "Metro" "Pieza"
- activo (bit, default 1)
- fechaRegistro (datetime, default now)
- REGLA DE NEGOCIO: nunca clasificar todo dbo.CatalogoSigma de antemano. Los
  registros se agregan uno por uno, bajo demanda, cuando el usuario busca en
  el catalogo institucional y decide agregarlo a su subcatalogo
- BUSQUEDA en dbo.CatalogoSigma (backend\models\CatalogoSigma::buscar()):
  LIKE simple sobre Descripcion, RamaComercial y Clase, con parametros
  bindeados (condicion 'like' de Yii). Aceptable hoy porque en desarrollo
  solo hay 509 filas accesibles. PENDIENTE: si en produccion se habilita el
  catalogo institucional completo (decenas de miles de filas), migrar a un
  indice de Full-Text Search de SQL Server sobre dbo.CatalogoSigma para
  mantener buen rendimiento -- requiere coordinacion con el equipo tecnico
  institucional, ya que esa tabla no pertenece al esquema `redes`

### redes.Especificacion
- id (PK, int, identity)
- idSubcatalogo (FK -> SubcatalogoItem, requerido)
- codigoNet (varchar 20) -- formato "NET:832/26"
- titulo (varchar 300)
- dependencia (varchar 200)
- modeloSugerido (varchar 200)
- documentoRuta (varchar 500) -- ruta al PDF/DOCX original, la fuente de verdad
  para el detalle tecnico completo (20-30 puntos), que NUNCA se estructura en
  columnas de base de datos
- fechaEmision (date)
- activo (bit, default 1)
- REGLA DE NEGOCIO: un mismo SubcatalogoItem puede tener VARIAS Especificacion
  distintas segun rango de precio o gama (ej "Enterprise" vs "Lite" para el
  mismo tipo de item "antena wifi")

### redes.Cotizacion
- id (PK, int, identity)
- idProveedor (FK -> Proveedor, requerido)
- numeroReferencia (varchar 100)
- fecha (date)
- documentoRuta (varchar 500) -- PDF original de la cotizacion, adjunto de
  respaldo
- validezDias (int)

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
2. Usuario selecciona el item -> sistema muestra las Especificacion asociadas
3. Usuario selecciona una especificacion -> sistema muestra los
   CotizacionDetalle disponibles con sus precios
4. Usuario elige una cotizacion (o ingresa precio manual) y una cantidad
5. Sistema crea un PresupuestoDetalle con el precioUnitario congelado en ese
   momento y calcula precioTotal

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

## Decisiones de captura de datos (relevante para las vistas/formularios)

- Cotizaciones de proveedores: carga MANUAL con formulario + archivo PDF
  adjunto de respaldo. No automatizar (formato variable entre proveedores)
- Especificaciones tecnicas: candidatas a extraccion SEMIAUTOMATICA desde PDF
  (formato institucional fijo, columnas predecibles: CodigoNet Titulo
  Dependencia ModeloSugerido FechaEmision) -- esto es una mejora futura, no
  bloqueante para la primera version funcional del sistema
