# Glosario: pantalla ↔ código ↔ tabla

Algunos nombres del código no coinciden con lo que dice la pantalla (el sistema cambió y las tablas no se renombraron).
Esta tabla evita confusiones.

| En pantalla | En el código | Tabla | Nota |
|---|---|---|---|
| Proyecto / fase | `Proyecto` | `redes.Proyecto` | **Cada fila es una fase**; las fases de un proyecto comparten `numeroProyecto` |
| Orden de cambio (FR-006) | `Proyecto` con `numeroOrdenCambio`, `OrdenCambioFaseService` | `redes.Proyecto` | Un presupuesto adicional de una fase aprobada |
| Reabrir la lista / Reaperturas de la lista | `OrdenCambio`, `OrdenCambioService`, `lista-items/solicitar-cambio` | `redes.OrdenCambio` | **No** es la orden de cambio del FR-006 |
| Versión emitida de la lista de materiales | `ListaMaterialesAprobacion`, `ListaItemsService::emitirVersion()` | `redes.ListaMaterialesAprobacion` | La lista ya no se aprueba: cada fila es una versión enviada a cotización |
| Espacio (área de intervención) | `ambiente`, `idEspacio` | `redes.ProyectoAreaIntervencion` | En UsfxGEO, "Espacios" |
| Formulario de especificación / ET | `EspecificacionEmision` | `redes.EspecificacionEmision` | La emisión de una ficha para un ítem de la lista |
| Número del ET (RD-PI-ET-**033**) | `numeroNet` | `EspecificacionEmision.numeroNet` | Nombre de antes, cuando el formulario se llamaba "NET" |
| Ficha de especificación | `Especificacion` + `EspecificacionVersion` | `redes.Especificacion`, `redes.EspecificacionVersion` | La librería; no pertenece a un proyecto |
| Lista de ítems / lista de materiales | `ProyectoItem`, `ListaItemsService` | `redes.ProyectoItem` | |
| Materiales que aporta la DTIC | `ProyectoMaterialDtic` | `redes.ProyectoMaterialDtic` | |
| Trabajos complementarios | `ProyectoCondicionPrevia`, `TipoCondicionPrevia` | `redes.ProyectoCondicionPrevia` | "Condiciones previas" en el título del documento |
| Solicitud de cotización / de proforma | `SolicitudCotizacion` | `redes.SolicitudCotizacion` | "Proforma" en el PDF, "cotización" en pantallas |
| Cotización / proforma recibida | `Cotizacion`, `CotizacionDetalle` | `redes.Cotizacion`, `redes.CotizacionDetalle` | |
| Línea del presupuesto | `PresupuestoDetalle` | `redes.PresupuestoDetalle` | Una por ítem de la lista |
| Precio directo | `ElaborarPresupuestoService::DIRECTO`, `fuentePrecio` | `PresupuestoDetalle` | Un precio sin cotización |
| Ficha técnica (código PT) | `Proyecto.codigo`, `codigoFicha()` | `redes.Proyecto` | Solo la fase 1 lo tiene |
| Unidad / dependencia | `unidadDependencia` | `redes.Proyecto` | La beneficiaria |
| Unidad solicitante | `unidadSolicitante` | `redes.Proyecto` | Quién pide |
| Nombre DTIC | `SubcatalogoItem.descripcion` | `redes.SubcatalogoItem` | El nombre SIGMA no se copia |
