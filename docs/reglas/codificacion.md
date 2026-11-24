# Codificación de documentos

Según el lineamiento de la DTIC **RD-GD-LI-001**. La historia y las decisiones están en
`docs/historia/cambios-codificacion-lineamiento.md`.

## Códigos

| Documento | Código | Dónde se guarda | Quién lo pone |
|---|---|---|---|
| Ficha técnica del proyecto | `RD-PI-PT-NNN` (siempre PI) | `Proyecto.codigo`, solo en la fase 1 | `ProyectoService` al crear el proyecto |
| Lista de materiales | `RD-<proceso>-LM-NNN` | `Proyecto.codigoLista` (cada fase y cada orden de cambio tiene la suya) | `ProyectoService` al crear la fase |
| Especificación técnica (formulario ET) | `RD-<proceso>-ET-NNN` | `EspecificacionEmision.numeroNet` + `proceso` | `EspecificacionEmisionService` al emitir |
| Solicitud de proforma | `RD-<proceso>-SC-NNN`, versión fija 1.0 | `SolicitudCotizacion.codigoDocumento` | `SolicitudCotizacionService` al crearla |
| Presupuesto | `RD-<proceso>-PR-NNN` | `Presupuesto.numeroPresupuesto` (el código entero) | `ElaborarPresupuestoService::delProyecto()` |
| Número de proyecto | `001` (lo comparten todas las fases) | `Proyecto.numeroProyecto` | `ProyectoService::asignarNumero()` |

- `<proceso>` es el código del proceso **del proyecto** (catálogo `redes.Proceso`: GD, DC, PI, DS, GR). Un solo
  número para todos los procesos: RD-PI-PR-016 y RD-GR-PR-017 son correlativos.
- **Ningún código lleva el año ni se reinicia.** Tres cifras; después del 999 sigue con 1000.
- **Lo emitido conserva su código.** `EspecificacionEmision.proceso` queda congelado: reemitir da el mismo código con la
  versión + 1. El presupuesto guarda su código entero, y `Presupuesto::codigoProceso()` lo lee de ahí.
- **La lista de materiales:** si se cambia el proceso antes del primer envío a cotización, el código LM cambia de
  proceso con el mismo número; después queda fijo.
- `EspecificacionEmision.anio` queda como dato (filtro "Año" del registro); el registro también encuentra
  "RD-PI-ET-033/26", tal como se imprimió.
- En **pantallas** la solicitud se muestra corta: "SC-01" (`SolicitudCotizacion::codigoCorto()`) o
  "001 · Fase 2 / SC-01" (`codigo()`). En el PDF y en la ficha técnica va su código RD-…-SC-NNN.

## Versión de una fase aprobada

- No es un código del lineamiento: `Proyecto.numeroVersionMayor`/`numeroVersionMenor` se muestran "1.0", "1.1",
  "2.0"… (`Proyecto::numeroVersionTexto()`), por fuera de los correlativos PT/LM/ET/SC/PR/NP.
- Al abrir una versión **no se repite** el código PT (es del proyecto, no se gasta un número nuevo) ni la carta de
  entrega de la versión vieja. **Sí** saca código de lista (LM) y de presupuesto (PR) propios, con
  `CodificacionService::tomar()` como cualquier fila nueva de `Proyecto`. Detalle en
  `docs/reglas/proyecto-y-fases.md`.

## Correlativos

- `redes.CorrelativoDocumento`: una fila por tipo (PT, PR, ET, LM, SC, NP). El formato está en
  `CorrelativoDocumento::codigo()`.
- `CodificacionService::tomar()` toma el siguiente número con `WITH (UPDLOCK, HOLDLOCK)`. **Siempre dentro de una
  transacción**: así dos usuarios a la vez no reciben el mismo número.
- Un número tomado no se reutiliza nunca, aunque se elimine el documento.
- El encargado fija desde qué número siguen en el menú **"Codificación de documentos"** (solo un número mayor que el
  último usado).

## Versión de los documentos

- `backend\components\VersionDocumento`: la versión 1 se escribe "1.0", la 2 "1.1"… En la base se guarda el número
  corrido. El buscador del registro entiende "v1.1" y también el formato viejo "v02".
- La línea "Proceso:" de la cabecera de los PDF sale de `Proceso::nombreDe()` con el código del documento.
