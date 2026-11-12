# Proyecto, fases y orden de cambio

## Proyecto, número y fases

- **Cada fila de `redes.Proyecto` es una FASE**, con su área de intervención, su equipo y su carpeta económica (lista,
  especificaciones, solicitudes, cotizaciones, presupuesto, carta).
- `Proyecto.numeroProyecto` lo **comparten todas las fases** (correlativo NP); `numeroFase` las ordena (índice único
  número + fase). Un proyecto nuevo toma el siguiente número; una fase siguiente, el de la anterior.
- Formato "001": `Proyecto::numeroTexto()` es el **único** lugar del formato.
- Textos del proyecto:
  - `identificador()`: "Proyecto 001" o "Proyecto 001 · Fase 2" (migas, títulos, avisos, historial).
  - `etiqueta()`: "001 — Nombre".
  - `lineaDocumento()`: "PROYECTO: 001 – NOMBRE EN MAYÚSCULAS (FASE 2)". Es la **única** línea del proyecto en los
    documentos (lista de materiales, presupuesto, reporte SIGMA). La solicitud de proforma no la lleva; el formulario ET
    usa "Dependencia".
- Con **fase única no se muestra "Fase"** en ningún lado (`tieneVariasFases()`).

## Agregar fase

- Botón "Agregar fase" en la **última** fase, aprobada o no (`ProyectoService::puedeAgregarFase()`): se pueden
  elaborar varias fases a la vez. En el paso Presupuesto sale como atajo cuando la fase está aprobada. Abre
  `proyecto/create?idAnterior=`.
- La fase nueva copia los **datos del proyecto** (`Proyecto::ATRIBUTOS_COMUNES`: nombre, descripción, tipo, proceso y
  las dos unidades), el equipo y el lugar de entrega por defecto. El área y la carpeta empiezan vacías.
- Los datos del proyecto **se editan solo en la fase 1**: en una fase 2+ van deshabilitados y el servidor siempre los
  toma de la fase anterior. Al guardar la fase 1 se copian a las demás fases en la misma transacción, con su historial
  ("Desde la fase 1: …") y el cambio de código LM si cambió el proceso
  (`ProyectoService::llevarDatosComunesALasFases()`).
- Quedan fijos también en la fase 1 cuando **otra** fase está aprobada (`ProyectoService::datosComunesFijos()`).
- Al editar, la fase anterior no cambia.

## Orden de cambio (formato RD-GD-FR-006)

- Es un **presupuesto adicional dentro de una fase APROBADA**, solo con lo que se agrega ("se compraron 100 m de cable,
  piden 50 m más").
- Es **una fila más de `redes.Proyecto`**, con el mismo número y fase que su fase, `idFaseOrigen` (la fase) y
  `numeroOrdenCambio` (1, 2…). Así tiene su propia lista (LM), sus ET (emisiones propias con la cantidad de la orden; la
  ET de la fase no se toca), solicitudes, presupuesto y carta, y sigue los mismos pasos.
- **No es una fase**: `getFases()` la excluye, no cuenta en `tieneVariasFases()` ni en la versión de la ficha técnica, no
  tiene código PT (usa `codigoFicha()` de la fase 1).
- Textos: `textoOrdenCambio()` "Orden de cambio N.° 1"; `identificador()` "Proyecto 001 · Fase 1 · Orden de cambio
  N.° 1"; `lineaDocumento()` "PROYECTO: 001 – NOMBRE (FASE 1) – ORDEN DE CAMBIO N.° 1"; solicitud en pantalla
  "001 · OC 1 / SC-01".
- Del FR-006 se guardan solo **tipo, descripción y motivo** (`idTipoOrdenCambio`, `descripcionCambio`, `motivoCambio`;
  escenario `Proyecto::SCENARIO_ORDEN_CAMBIO`). El FR-006 se hace en Word y se le adjunta el presupuesto.
- Reglas en `OrdenCambioFaseService` (**no confundir** con `OrdenCambioService`, que es "reabrir la lista"):
  - se registra solo sobre una fase con presupuesto aprobado (botón "Registrar orden de cambio",
    `proyecto/orden-cambio?idFase=`);
  - copia de la fase los datos del proyecto, el área, el equipo y el lugar de entrega; "Editar datos" lleva a
    `proyecto/editar-orden-cambio`;
  - se edita mientras su presupuesto no esté aprobado ni cancelado;
  - si no se aprueba se **elimina** con lista, solicitudes, cotizaciones y presupuesto; sus ET quedan anuladas y pasan a
    la fase; los códigos no se reutilizan. Una aprobada no se elimina.
- Historial en la orden y en la fase. En la ficha técnica va dentro de la carpeta de su fase y suma al costo total.

## Unidades y área de intervención

- **Dos unidades**, las dos obligatorias, de texto libre (pueden ser la misma):
  - `unidadDependencia` ("Unidad / dependencia") = la **beneficiaria**. Es la que sale en los documentos.
  - `unidadSolicitante` = **quién pide**, tal como la nota. Sale en la vista, la ficha técnica y la carta.
  - Sin catálogo: un `<datalist>` sugiere lo ya escrito y las facultades de `backend/config/parametros-proyecto.json`
    (`ProyectoService::sugerenciasUnidad()`). Nunca gestionar unidades aquí.
- **Área de intervención** (`redes.ProyectoAreaIntervencion`, 0..* por fase): edificio → bloque → nivel → espacio (en
  el código y la base se llama `ambiente`). Llega hasta donde haga falta, sin saltar un nivel.
  - Se elige de la base **UsfxGEO** de Desarrollo, **solo lectura** y solo Estado 'V'
    (`InfraestructuraProvider` → `UsfxGeoInfraestructuraProvider`, conexión `dbGeo`). Guarda los id de UsfxGEO más
    una **copia congelada** de los nombres: un área guardada se conserva aunque la den de baja.
  - Se puede **escribir a mano** (edificios en construcción o no registrados): sin ningún id (`idEdificio` NULL,
    `esManual()`), edificio obligatorio, sin repetidas. Lleva la marca "a mano" en pantallas, no en documentos.
  - Formulario: buscador (`ProyectoService::buscarAreas()`: primero los edificios, luego hasta 30 espacios; ignora tildes
    y guiones, "E303" = "E-303") y paso a paso (edificio y espacio son campos que filtran al escribir; bloque y piso,
    `<select>`; `proyecto-form.js`).
  - **Si UsfxGEO no responde**, el formulario abre igual con el aviso `ProyectoService::AVISO_INFRAESTRUCTURA` y el
    área se escribe a mano. Todas sus consultas pasan por `ProyectoService::consultarInfraestructura()`.
  - En documentos: **una línea por edificio**, con bloque, piso y espacio juntos
    (`ProyectoAreaIntervencion::lineasPorEdificio()`). En pantallas, agrupadas por "edificio · bloque" (`grupo()`,
    `detalle()`).
  - Los proyectos anteriores al 2026-10-28 conservan `facultad`, `edificio` y `redes.ProyectoBloque` ("ubicación
    anterior") hasta que se les elijan áreas.

## Equipo del proyecto

- `redes.Personal`: solo una lista de **nombres** (no son usuarios ni dan permisos), sin rol fijo.
- `redes.RolProyecto`: Proyectista, Director del proyecto, Coordinador de infraestructura, Responsable de despliegue,
  Responsable de administración, Supervisor. No da permisos.
- `redes.ProyectoParticipante`: **copia** del nombre (sin FK), rol, `esResponsable`, orden. Reglas en
  `ProyectoService::armarEquipo()`: al menos una persona y **una sola responsable** (no tiene que ser proyectista);
  una persona puede tener dos roles pero no el mismo dos veces.
- Un proyecto nuevo empieza con una fila "Proyectista" responsable. La fase siguiente hereda el equipo.

## Historial y fechas

- `redes.HistorialProyecto`: una fila por hecho, **nunca se modifica ni se borra**. `usuario` es copia del nombre; sin
  FK al proyecto (si se elimina, el historial queda) y copia de `identificador()`.
- Se escribe **desde los servicios, dentro de su transacción** (`HistorialService::registrar()`), no con eventos de
  ActiveRecord.
- Se anotan (`HistorialProyecto::ACCION_*`): proyecto creado, modificado (solo lo que cambió, "Rótulo: antes →
  después", también el lugar de entrega) o eliminado; presupuesto creado; cada cambio de estado; los **precios** elegidos
  o cambiados con el anterior (un registro por operación); la lista enviada a cotización (`ACCION_LISTA_APROBADA`, una
  versión emitida); "Cambiar ítem" de la lista; órdenes de cambio creadas o eliminadas.
- **No** se anotan todavía las filas de la lista, las especificaciones ni las cotizaciones (se agregan con un
  `registrar()` en su servicio).
- Fechas del proyecto (`HistorialService::fechas()`): creación, revisión (último paso a "en revisión"), aprobación,
  última modificación y quién.

## Vista del proyecto

- **Barra de pasos** (`ProyectoService::pasos()`): Datos, Plantilla, Lista de ítems y especificaciones, Cotización,
  Presupuesto. Sale en todas las pantallas de un paso.
- **Franja "FASES"** (`proyecto/_franja-fases.php`, `ProyectoService::franjaFases()`), con varias fases o con órdenes de
  cambio: una pastilla por fase (estado y total), sus órdenes después, la que se ve marcada y "Agregar fase N →".
  Con la franja visible, el recuadro de abajo muestra solo el **costo total** (`ProyectoService::costoTotal()`).
- **Tarjeta "Datos generales"** (clases `rd-dg-*`): descripción (cortada en 2 líneas solo si es larga), cuadrícula de
  datos del proyecto, nota "se editan en la Fase 1" (con enlace solo si la fase 1 hoy los deja cambiar,
  `ProyectoService::faseParaDatosComunes()`), y "De esta fase": área y equipo (en una orden de cambio, los de su fase).
