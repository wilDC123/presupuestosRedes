# Presupuesto y carta de entrega

Historia del "documento vivo": `docs/historia/cambios-presupuesto-vivo.md`.

## Un presupuesto por fase, como documento vivo

- **Un solo presupuesto por fase u orden de cambio** (índice único `Presupuesto.idProyecto`). Lo que se agrega después
  de aprobar va como **fase siguiente** u **orden de cambio**, cada una con su propio presupuesto. No hay "versiones" del
  presupuesto.
- Se elabora en **una pantalla**, el paso "Presupuesto" (`ElaborarPresupuestoController`, `ElaborarPresupuestoService`).
  No hay "crear presupuesto" ni "agregar líneas": **cada ítem de la lista es una línea** (`PresupuestoDetalle`, como
  máximo una por ítem, índice único filtrado).
- **Al entrar** se crea solo, en "en proceso", con su código PR, y cada ítem sin línea recibe el **menor precio
  VIGENTE** (si todos están vencidos, el más barato igual; `completarSinLinea()`). Un ítem que ya tiene precio no se
  toca.
- El menú "Presupuestos" es solo un listado de consulta (abrir, PDF, reporte SIGMA, eliminar uno en proceso,
  configuración).

## Precios

- La tabla tiene una columna por proveedor; marcar otro precio se guarda por AJAX (`elaborar-presupuesto.js`).
- Una línea solo usa una cotización **de su mismo ítem**; si la cotización es de una fila de la lista, solo esa fila
  puede usarla; las sueltas, solo si la fila no tiene cotizaciones propias (el servidor lo verifica: el navegador podría
  mandar cualquier id).
- **Precio directo**: un precio escrito sin cotización, con su fuente opcional (`PresupuestoDetalle.fuentePrecio`).
  El botón "SIGMA: x.xx" usa el `PrecioReferencial` del ítem SIGMA (`FUENTE_SIGMA`), y el atajo "Precio SIGMA en los
  ítems sin precio" lo pone en todos los que no tienen (`usarPrecioSigma()`).
- **Atajos** (`adjudicar()`): "menor precio por ítem" y "todo a un proveedor" (los ítems que ese proveedor no cotizó
  quedan como estaban).
- Elegir un precio que **no es el menor** admite una justificación **opcional** (`PresupuestoDetalle.justificacion`):
  el presupuesto es un precio referencial, no la adjudicación de una compra. Se ve en la pantalla, no en el PDF.
- **Precio congelado**: elegir otro precio **reemplaza** la línea (borra y crea otra, con precio y partida congelados en
  ese momento). El precio de una línea guardada **nunca se recalcula**, aunque cambie la cotización o SIGMA.
- **Montos**: precio y cantidad con hasta 2 decimales, máximo 99,999,999.99, y el total de la línea (cantidad × precio)
  también (`backend\components\Monto`). El total de cada línea lo calcula SQL Server (`precioTotal`, redondeado a 2
  decimales); los totales suman esas líneas.
- Solo se eligen precios con el presupuesto **en proceso** (`Presupuesto::esBorrador()`).
- `PresupuestoDetalle.observacion`: texto de la línea (medidas, "según especificación"); viene de la lista.

## Estados

```
en proceso → en revisión → aprobado
     ↑            ↓  ↘
     └── observado    cancelado
```

- Mapa único en `Presupuesto::transicionesPermitidas()`; aprobado y cancelado son finales. Se cambian en la misma
  pantalla (`PresupuestoService::cambiarEstado()`).
- **Enviar a revisión** exige el código PR (escenario `SCENARIO_ENVIAR`) y que **todos los ítems tengan precio**.
- **Aprobar**: guarda `fechaAprobacion`, **copia las firmas** de la configuración al presupuesto (`firma1…`,
  `firma2…`; trait `FirmasDeControl`) y **cierra la lista**, todo en una transacción.
- Fecha del documento (cabecera del PDF, pantalla y listado): `Presupuesto.fechaCreacion`. La de aprobación va abajo.
- Todos los atributos de `Presupuesto` llevan "!" en `rules()`: se validan, pero `load()` no los asigna desde un
  formulario. Los pone siempre un servicio.

## Mano de obra de la DTIC

- **No es un ítem** (no se compra: sin partida, sin cotización, no suma). Es el texto `Presupuesto.manoDeObra` (NULL =
  no lleva), que se pone o quita con una casilla en borrador. Un presupuesto nuevo nace con ella.
- En el PDF va en la categoría "Mano de obra" (o una propia al final): "MANO DE OBRA", unidad "Servicio", cantidad 0.00,
  precios 0.00.
- La mano de obra **contratada** a una empresa sí es un ítem normal, con partida y cotización.

## PDF del presupuesto

- `presupuesto/pdf.php`: cabecera común, la línea del proyecto (`lineaDocumento()`), y **una sola tabla**: cada
  categoría es una banda gris (la primera, arriba de los títulos de columnas), con su subtotal, y al final la fila
  "TOTAL (Bs.)". N.° corrido entre categorías.
- Cada línea: nombre DTIC con su observación y, entre paréntesis, el **modelo sugerido** de la especificación
  (`ProyectoItem::modeloSugerido()`), columnas como la solicitud más Partida.
- **No** lleva: nombre SIGMA, proveedor por línea, resumen por partida, justificaciones, área de intervención, estado
  ni oficio DTIC.
- Marca de agua BORRADOR mientras no esté aprobado. Después del total, la nota opcional y, **al fondo de la última
  página** (`PresupuestoPdfGenerator::MARCA_AL_PIE`), el control de elaboración: "ELABORADO POR" y "APROBADO POR"
  (V°B°) y la fecha de aprobación.
- Nota y firmas en `redes.ConfiguracionPresupuesto` ("Configuración del PDF"). Un aprobado usa **su copia** de las
  firmas; los demás estados, las de hoy (`Presupuesto::firmaDeControl()`).
- **Resumen por partida** (`PresupuestoService::resumenPorPartida()`): en la pantalla, no en el PDF. Su total coincide
  siempre con el total por categorías.
- **Reporte "Relación con el catálogo SIGMA"** (`presupuesto/pdf-sigma`, horizontal): cada línea con su ítem SIGMA,
  partida y precio congelados frente a los de hoy del catálogo, y si está vigente. Es el único impreso con el nombre
  SIGMA. Sin firmas.

## Carta de entrega

- La nota con membrete que acompaña al presupuesto (`CartaController`, `CartaService`, botón "Carta de entrega"). **Una
  por presupuesto** (`redes.CartaPresupuesto`). Se escribe en cualquier estado.
- El **oficio DTIC** se escribe en la pantalla de la carta (`Presupuesto.oficioDtic`). Para imprimir se exigen la carta
  guardada, el oficio y el código del presupuesto (no que esté aprobado). Formato "DTIC Of. Nº 602/2026"
  (`CartaService::numeroOficio()`).
- **Guarda su texto**: al crearla copia los textos y las 3 firmas de `redes.ConfiguracionCarta` ("Configuración de la
  carta"); cambiar la configuración no altera las ya escritas, salvo ciudad y formato del oficio.
- Marcadores: {proyecto}, {unidad} (= unidad **solicitante**: la carta responde a quien pidió; {facultad} es el nombre
  viejo y vale lo mismo) y {dependencia} (= beneficiaria) se reemplazan al crearla; {oficio}, {numeroPresupuesto} y
  {especificaciones} (los números de los ET vigentes, de menor a mayor: "896, 897, 900") al imprimir.
- Tratamientos en `redes.TratamientoCarta`; el cargo "DECANO/DECANA DE LA …" se sugiere solo si la unidad empieza con
  "Facultad" (`web/js/carta.js`).
- PDF oficio con el membrete `backend/web/img/fondo-carta.png` de fondo. Letra "pagelladtic" (TeX Gyre Pagella, en
  `backend/fonts/pagella-dtic`), porque Book Antiqua es de Windows y no se puede llevar al servidor.
