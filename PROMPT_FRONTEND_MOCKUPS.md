# Frontend del módulo de redes: mockups, cambios del ingeniero y estado actual
(lee CLAUDE.md y spec_tecnica_sistema.md primero)

**Actualizado al 2026-10-16.** Este documento nació como el prompt para construir las pantallas de
los mockups (`C:\Users\ASUS\Desktop\mockups_finales`, versión del 30-sep-2026 mostrada al ingeniero)
con los cambios que pidió el ingeniero. Ahora registra **cómo quedó implementado cada punto** y qué
difiere de los mockups. Los `.dc.html`/`.png` se conservan sin cambios como registro de lo mostrado;
donde no coinciden con el sistema, **manda este documento**.

Leyenda: ✅ hecho · 🔄 hecho con cambios respecto al mockup · ⏳ pendiente · ❌ retirado

## Decisión confirmada: una fase NO es una versión ✅
Una ampliación (Fase 2, 3…) es un **proyecto nuevo con su propio código**, enlazado al de la fase
anterior (`Proyecto.idProyectoAnterior`, `Proyecto.numeroFase`). Un proyecto aprobado nunca se
modifica. Cada proyecto tiene su propia lista y su propio presupuesto; una fase nueva no copia líneas
de la anterior. El versionado de `Presupuesto` se retiró. Pendiente (anotado en CLAUDE.md): un
reporte consolidado que sume las fases aprobadas.

## Reglas de siempre
- Arquitectura de 4 capas: las vistas no consultan la BD ni contienen reglas de negocio; los
  controladores son delgados y delegan en `backend/services/`.
- Migraciones con `safeUp`/`safeDown`, esquema `redes`, PascalCase singular. NUNCA modificar
  `dbo.CatalogoSigma`.
- Validación en frontend (jQuery) Y en backend. Textos en español.
- Explicar el porqué de cada decisión (el desarrollador está aprendiendo Yii2).

## Cambios del ingeniero: cómo quedaron
1. ✅ **Gestionar Proyecto** termina en los datos básicos (código RD-PI-PT-NN automático, nombre,
   descripción, tipo, proyectistas, facultad, edificio, bloques). Lo demás son pasos aparte que usan el
   id del proyecto.
2. ✅ **Facultad/Edificio/Bloque parametrizados**: `ParametrosProyectoService` con la interfaz de
   `backend/services/contracts/` y la implementación `JsonParametrosProyectoProvider` (JSON hoy, web
   service mañana).
3. ✅ **Plantillas con clasificación configurable** (`Plantilla`, `PlantillaCategoria`,
   `PlantillaItem`): crear, editar, desactivar y tipo de proyecto sugerido. Un proyecto puede empezar
   en blanco. La plantilla se COPIA a la lista.
4. ✅ **Lista de ítems ≠ Presupuesto**: lista sin precios en su propia pantalla, con "Imprimir lista"
   (PDF). Flujo: lista → especificaciones → solicitud de cotización → presupuesto.
5. 🔄 **Especificaciones técnicas**: en vez de un "paso" con enlace a Drive, son una **librería**
   (`redes.Especificacion`, código `NET:xxx/26`) clasificada por categoría. El documento (PDF/DOCX) se
   **sube al servidor** (`uploads/especificaciones`), con "Extraer datos automáticamente". Se enlaza
   como máximo una por ítem de la lista, desde la pantalla de la lista, y siempre es OPCIONAL.
6. 🔄 **Solicitud de cotización**: los ítems de la lista se reparten en solicitudes por **rubro**
   (nombre libre, ej. "Ferretería y ducteo"), a mano o con "por clasificación", en lugar de un catálogo
   configurable de tipos de proveedor. Cada solicitud se imprime en PDF con sus especificaciones.
7. ✅ **Carga de precios manual**: la cotización se registra DESDE su solicitud; cada línea apunta a
   su ítem de la lista (`CotizacionDetalle.idProyectoItem`). Cuadro comparativo por solicitud con el
   menor precio resaltado. Las cotizaciones "sueltas" (sin solicitud) siguen siendo válidas.
8. ⏳ **Trazabilidad**: el PDF del presupuesto indica el proveedor de cada línea y trae el cuadro
   comparativo en anexo, pero la pantalla del presupuesto todavía **no enlaza** el archivo de la
   cotización de origen (`Cotizacion.documentoRuta`).
9. ✅ **Estados y bloqueo**: lista edición → en cotización → cerrada; cambiar una lista enviada exige
   una **orden de cambio** (`redes.OrdenCambio`: motivo, fecha, quién, estado) que la reabre. Estados
   del presupuesto: en_proceso, en_revision, observado, aprobado, cancelado.
10. ✅ **Tres totales** (ítem, clasificación, general) + resumen por partida, en pantalla y en PDF.
11. ⏳ **Segundo presupuesto con ítems SIGMA** (ID Sigma, partida, clase, descripción SIGMA, rama
    comercial; "sin ítem SIGMA" para mano de obra): no implementado.
12. ❌ **Carpeta económica / "Documentos del proyecto"**: retirada el 2026-10-16. El paso
    "Documentos" se quitó de la barra del proyecto: el sistema ya genera o guarda cada documento en su
    propio paso (lista impresa, solicitudes, archivos de cotización y de especificación, PDF del
    presupuesto). El mockup `Documentos` queda solo como referencia.
13. Fuera de alcance (sin cambios): evaluación de proveedores, control posterior a la carpeta
    económica, lectura automática de cotizaciones.

## Agregado después de los mockups (no estaba en la revisión del ingeniero)
- **Unidades de medida** (`redes.UnidadMedida`, menú "Unidades de medida"): el subcatálogo elige la
  unidad de un catálogo; la lista, el presupuesto y los cómputos guardan la abreviatura congelada.
- **Presupuesto como "documento vivo"** (ver Fase G): reemplaza la pantalla "Presupuesto" del mockup.
- **PDF del presupuesto**: marca de agua "BORRADOR" mientras no esté aprobado, proveedor por línea y
  anexo con el cuadro comparativo y los ítems con un precio distinto del menor.

## Fases: estado
### Fase A: base visual ✅
Barra lateral oscura, migas de pan, IBM Plex Sans/Mono, tarjetas y tablas de los mockups
(`backend/web/css/redes.css`, compatible con Bootstrap 5). Menú actual: Dashboard, Categorías,
Unidades de medida, Proveedores, Subcatálogo, Plantillas, Proyectos, Especificaciones, Cotizaciones,
Presupuestos, Cómputos métricos (**sin "Documentos"**). Dashboard con datos reales
(`DashboardService`): proyectos activos, en revisión, monto aprobado, ítems del subcatálogo vs.
SIGMA, cotizaciones por vincular. Proveedores con panel "Proveedor seleccionado". Subcatálogo con
nombre DTIC, categoría, nombre SIGMA, partida, unidad y estado.

### Fase B: Proyecto ✅
"Nuevo proyecto" con código automático y continuidad de fases (solo proyectos aprobados como fase
anterior). La vista del proyecto es el centro de navegación con **6 pasos**:
**Datos → Plantilla → Lista de ítems → Especificaciones → Cotización → Presupuesto**
(el 7.º paso del mockup, "Documentos", se retiró).

### Fase C: Plantillas ✅
Administración de plantillas y pantalla "Cargar plantilla" con vista previa y "Omitir, empezar en blanco".

### Fase D: Lista de ítems ✅
Buscador del subcatálogo a la izquierda, ítems agrupados por clasificación a la derecha, cantidad,
observación y especificación por ítem, imprimir lista, estados y orden de cambio
(`redes.ProyectoItem`).

### Fase E: Especificaciones 🔄 (Documentos ❌)
Librería de especificaciones con subida de documento y extracción de metadatos; se enlazan desde la
lista. La pantalla "Documentos del proyecto" se retiró (cambio 12).

### Fase F: Solicitud de cotización y cotizaciones 🔄
Solicitudes por rubro con PDF; cotización registrada desde la solicitud con ítems ya cargados (solo
marca y precio, totales con jQuery) y archivo de la proforma subido al servidor
(`uploads/cotizaciones`), fecha y validez; cuadro comparativo por solicitud.

### Fase G: Presupuesto 🔄 (rediseñado como "documento vivo")
La pantalla "Presupuesto" del mockup (detalle con líneas agregadas una por una) se reemplazó por una
sola pantalla en el paso Presupuesto del proyecto (`ElaborarPresupuestoController`):
- cada ítem de la lista ES una línea; al entrar se propone el **menor precio vigente**;
- tabla con una columna por proveedor; elegir otro precio se guarda solo (AJAX) y REEMPLAZA la
  línea con precio y partida congelados en ese momento (🔒 precio congelado se mantiene);
- justificación **opcional** si no es el menor; atajos "menor precio por ítem" y "todo a un proveedor";
- cabecera (número, oficio, área, fecha) obligatoria recién al "Enviar a revisión", que además exige
  que todos los ítems tengan precio; aprobar/observar/cancelar en la misma pantalla;
- el menú "Presupuestos" quedó como listado de consulta.
Detalle para el tutor: `docs/cambios-presupuesto-vivo.md`. Pendientes de esta fase: cambios 8 y 11.
