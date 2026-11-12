# Solicitudes de proforma y cotizaciones

Paso "Cotización" del proyecto (`SolicitudCotizacionController`, `SolicitudCotizacionService`, `CotizacionService`).

## Solicitud de proforma de cotización

- Agrupa ítems de la lista para pedirle precios a los proveedores ("Cables y conectores", "Cámaras"…). Código
  `RD-<proceso>-SC-NNN`, versión fija 1.0 (ver `codificacion.md`). En pantallas se llama "Solicitud de cotización".
- **PDF** para el proveedor: título "SOLICITUD DE PROFORMA DE COTIZACIÓN", cabecera común, una tabla N.° | Descripción |
  Unidad | Cantidad.
  - La unidad con su **nombre** ("Metro", no "m"; `UnidadMedida::nombresPorAbreviatura()`).
  - En la descripción: código ET vigente, marca / modelo de referencia y observación.
  - Sin precios, sin total, sin espacio para el proveedor (todo eso lo trae la proforma). Sin la línea del proyecto.
  - Firma "ELABORADO POR" de la DTIC al fondo de la última página (la firma 1 de la configuración del presupuesto, la de
    hoy: la solicitud no guarda copia).
  - "Imprimir" da solo la solicitud; "Imprimir con especificaciones (N)" agrega al final el formulario ET de cada ítem,
    cada uno en hoja nueva.
- **Fecha** (`SolicitudCotizacion.fechaEnvio`, opcional): la que sale en el PDF; vacía = la del día en que se imprime.
  Se cambia mientras no tenga cotizaciones. `SolicitudCotizacion.fecha` es la de creación y no sale en impresos.
- **Marca y modelo de referencia** (`SolicitudCotizacionItem.marcaModeloReferencia`): lo que la DTIC **pide**; sale entre
  paréntesis. Se edita mientras la solicitud no tenga cotizaciones.
- Textos fijos (instrucciones, nota final) en `redes.ConfiguracionSolicitudCotizacion`, "Configuración del documento".
  La solicitud no guarda copia: un cambio se ve al reimprimir las anteriores.
- La solicitud se despacha con una nota que el sistema no genera.

## Cotizaciones (proformas recibidas)

- `redes.Cotizacion` (proveedor, número de referencia, fecha, validez en días) y `CotizacionDetalle` (ítem, cantidad,
  precio, marca / modelo **ofertado**).
- Desde una solicitud ("Cotizar"): una fila por ítem; el ítem y la cantidad salen de la lista, nunca del formulario. Una
  línea guarda contra qué ET cotizó (`CotizacionDetalle.idEspecificacionEmision`; no cambia si el ET se reemite).
- Cotización **suelta** (menú Cotizaciones): solo elige la ficha (`idEspecificacion`).
- Precio y cantidad: hasta 2 decimales, máximo 99,999,999.99, y el total de la línea también
  (`backend\components\Monto`).
- **La proforma** se adjunta como **PDF subido o como enlace** (Google Drive), en `Cotizacion.documentoRuta` (el enlace
  tal cual; `tieneEnlace()`). Lo pone `CotizacionService::asignarDocumento()`: con los dos a la vez pide elegir (salvo
  que el enlace sea el ya guardado: el PDF nuevo lo reemplaza); borrar el enlace lo quita; un PDF ya subido se
  conserva (el anterior no se borra del disco: es lo que mandó el proveedor). Textos de los botones en
  `Cotizacion::textoDocumento()` y `ayudaDocumento()`.
- **Vencida** (`Cotizacion::estaVencida()`): `fecha + validezDias < hoy`. Sin fecha o sin validez no se considera
  vencida. Una cotización vencida se puede usar, pero avisa al enviar a revisión y al aprobar.
- Una cotización cuyos precios ya se usan en un presupuesto no se elimina ni se cambia.
