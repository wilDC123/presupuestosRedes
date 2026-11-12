# Lista de ítems y especificaciones técnicas

Historia de las especificaciones: `docs/historia/cambios-especificaciones.md` y `docs/historia/plan-especificaciones.md`.

## Lista de ítems

- Paso "Lista de ítems y especificaciones" del proyecto (`ListaItemsController`, `ListaItemsService`). Cada fila
  (`redes.ProyectoItem`) es un ítem del subcatálogo con cantidad, observación y, opcionalmente, una especificación. **Sin
  precios**.
- Estados de la lista (`Proyecto.estadoLista`): en edición → **en cotización** ("Enviar a cotización") → cerrada (al
  aprobar el presupuesto). Solo se edita en edición y con el presupuesto editable (`ListaItemsService::puedeEditarse()`).
- **Reabrir la lista** (con su motivo): `redes.OrdenCambio`, `OrdenCambioService`, `lista-items/solicitar-cambio`. En
  pantalla se llama "reabrir la lista" / "Reaperturas de la lista". **No es la orden de cambio** del FR-006 (ver
  `proyecto-y-fases.md`).
- Mientras el presupuesto es **borrador**, sus líneas siguen a la lista: quitar un ítem borra su línea, y la cantidad y
  la observación se copian (`Presupuesto::copiarDeLaLista()`). El **precio no**.
- **"Cambiar ítem"**: en una fila ya guardada se elige otro ítem del subcatálogo sin quitar la fila; con la misma
  especificación, su formulario se **reemite** en vez de anularse (`ListaItemsService::cambiarItem()`). No se permite si la
  fila ya tiene cotizaciones.
- Cantidad: hasta 2 decimales y máximo 99,999,999.99 (`backend\components\Monto`).

## Lugar de entrega

- "Lugar de entrega de los materiales", **arriba de la lista** (`Proyecto.lugarEntrega`): el lugar por defecto. Si el
  proyecto no tiene, el campo sugiere `Proyecto::lugarEntregaSugerido()`. Se guarda con el mismo botón, en la misma
  transacción, y queda en el historial.
- Cada fila puede tener **otro lugar** ("Otro lugar de entrega", plegado junto a "Cambiar ítem"). Se guarda en la
  emisión de su especificación (`EspecificacionEmision.lugarEntrega`). Uno igual al de arriba se envía vacío.
- Cambiar el lugar de un formulario ya emitido lo **reemite** (motivo "Se cambió el lugar de entrega: A → B"; junto con
  "Cambiar ítem" es una sola reemisión, `ListaItemsService::reemitirPorCambio()`).
- Si no hay lugar ni en el proyecto ni en la fila, se usa `ConfiguracionEspecificacion.lugarEntregaPorDefecto` (vacío =
  no sale).

## Secciones sin precio (debajo de la lista)

Son del proyecto, se escriben en el mismo formulario y se guardan con el mismo botón. Salen en el PDF de la lista solo si
tienen filas. El presupuesto **no** las lleva ("el presupuesto es financiero").

- **Materiales que aporta la DTIC** (`redes.ProyectoMaterialDtic`, `MaterialDticService`): ítems del subcatálogo (nombre
  y unidad fijos, solo se pide la cantidad) o escritos a mano; agrupados por categoría ("Otros" para los escritos a
  mano). Título oficial en `ProyectoMaterialDtic::TITULO`.
- **Trabajos complementarios** (`redes.ProyectoCondicionPrevia`, `CondicionPreviaService`): tipo (catálogo), descripción
  y unidad encargada. Título en `ProyectoCondicionPrevia::TITULO`.

## PDF de la lista de materiales (documento normado)

- "LISTA DE MATERIALES Y CONDICIONES PREVIAS" (`lista-items/pdf.php`), con su código **LM** propio y la cabecera común.
- **No se aprueba** (se aprueba el presupuesto). "Enviar a cotización" **emite** una versión
  (`ListaItemsService::emitirVersion()`): `redes.ListaMaterialesAprobacion` = versión emitida (fecha, usuario, copia de
  la firma). La primera es 1.0; después de cada reapertura, la siguiente.
- En edición imprime la versión siguiente con marca BORRADOR y la firma de hoy; enviada o cerrada, la última emitida.
- La "Fecha" de la cabecera es la de **creación** de la versión.
- Una sola tabla: cada categoría es una banda gris sin líneas; la primera va arriba de los títulos de columnas. Al final
  "TOTAL: N ÍTEM(S) EN M CLASIFICACIÓN(ES)". Sin subtotales.
- Solo "ELABORADO POR", a todo el ancho de la hoja.

## Librería de especificaciones técnicas

Las especificaciones **viven en el sistema** (antes eran Word en un Drive). Tres niveles:

1. **Ficha** (`redes.Especificacion`): título, categoría, ítem de referencia opcional, activo. No pertenece a un ítem
   ni a un proyecto.
2. **Versión** (`redes.EspecificacionVersion`, con sus filas `EspecificacionCaracteristica`): características, modelo
   sugerido, garantía, adjunto. Una versión ya usada en una emisión **no se modifica**: editarla crea la siguiente
   (`EspecificacionService::guardarVersion()`).
3. **Emisión** (`redes.EspecificacionEmision`): el **formulario ET** con su código, que se firma y se entrega al
   proveedor. **Nunca cambia**: guarda copias congeladas (título = nombre DTIC del ítem, cantidad, unidad, dependencia,
   garantía, textos, condiciones de entrega, lugar de entrega, firmas). Estados: vigente, reemplazada, anulada; "vencida"
   se calcula (fecha + vigencia).

Reglas (`EspecificacionEmisionService`):

- **Enlazar = emitir**: elegir una ficha en una fila de la lista y guardar emite el formulario con el siguiente código.
  Como máximo una especificación por ítem. Es **opcional**: un ítem sin ella nunca bloquea nada.
- Cambiar o quitar la ficha, o quitar el ítem, **anula** la emisión vigente (no se borra).
- Un error se corrige **reemitiendo**: mismo código, versión del documento + 1, última versión de la ficha, cantidad
  actual de la lista; la anterior queda "reemplazada por". Se reemite mientras el presupuesto no esté aprobado ni
  cancelado. Cambiar la ficha por otra da un código nuevo.
- Avisos que no bloquean: cantidad distinta de la emitida, emisión vencida, versión nueva de la ficha.
- `codigo()` no lleva versión (lo citan carta, solicitud y presupuesto); `codigoConVersion()` ("RD-PI-ET-015 v1.1") en
  pantallas, registro y CSV.
- Ayudas para elegir: grupo "Sugeridas para este ítem" (fichas con ese ítem de referencia), "Ver detalle"
  (`especificacion/vista-previa`) y buscador (`EspecificacionService::buscar()`: título, modelo y características, sin
  importar tildes, como mucho 10 palabras). "Recordar como sugerida para este ítem" pone el ítem de referencia.
- Solo se enlazan fichas activas; una en uso o emitida no se elimina, se desactiva.
- **Registro** de todas las emisiones en el menú "Formularios de especificación", con exportación a CSV.

## Formulario ET en PDF

- `especificacion/formulario`, con la cabecera común y debajo "Universidad … / D.T.I.C." (copia de cada emisión,
  `EspecificacionEmision.textoCabecera`). Nada por debajo de 11 pt.
- Tabla Especificación | Propuesta | Cumple | No cumple, secciones I (características), II (garantía), III
  (condiciones).
- **Numeración calculada**, nunca guardada (`backend\components\NumeracionEspecificacion`): cada característica tiene
  su **nivel** (1 a 3, flechas ← → en la ficha), como un índice (1.4 → 1.4.1 → 1.4.1.1). Un **subtítulo** es solo la fila
  en negrita. Los mismos números en la ficha, "Ver detalle" y el formulario de edición (JS, misma regla).
- Marca de agua REEMPLAZADO / ANULADO / VENCIDO. Control de elaboración con dos firmas (copias de cada emisión).
- Todos los ET vigentes de la lista en un solo PDF: botón "Imprimir especificaciones (N)"
  (`PresupuestoPdfGenerator::generarVarios()`).

## Carga y orden de la librería (consola)

- `php yii especificacion/importar "<carpeta>"`: carga inicial desde los .docx de la DTIC (sin `--confirmar` solo
  escribe un CSV; `--desde=AAAA`).
- `php yii especificacion/ordenar console/data/orden-especificaciones-2026-11-03.json`: aplica niveles, subtítulos y
  arreglos de forma a 114 fichas, reconociéndolas por título + huella del contenido (sirve en cualquier base). Sin
  `--confirmar` solo muestra. Pendiente de correr en el servidor de la DTIC.
