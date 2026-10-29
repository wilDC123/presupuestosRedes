# Unidad solicitante y área de intervención (2026-10-28)

Cambio pedido en la revisión del sistema con el Ing. de DTIC (2026-10-27). Propuesta completa,
con las preguntas que siguen abiertas: `Escritorio\todo\para casos de facultades o sedes, etc\propuesta-unidad-solicitante-texto-libre.md`.

## Qué cambió

"Facultad / Edificio / Bloques" mezclaba dos datos distintos. Ahora son dos:

| | Unidad solicitante | Área de intervención |
|---|---|---|
| Qué es | Quién pide (facultad, carrera, laboratorio, oficina o persona) | Dónde se hace el trabajo |
| Cómo se llena | Texto libre, tal como dice la nota; se sugiere lo ya escrito y las facultades | Se elige de la infraestructura de la universidad (base `UsfxGEO`, solo lectura): edificio → bloque → nivel → ambiente, con buscador |
| Dónde se guarda | `Proyecto.unidadSolicitante` | `redes.ProyectoAreaIntervencion` (0..* por proyecto) |
| Catálogo propio | No (nadie lo mantendría) | No (lo mantiene Desarrollo; "no duplicar información") |

- Cada área guarda los identificadores de `UsfxGEO` (referencia lógica, sin llave foránea, como
  `idSigma`) y una **copia congelada** de los nombres. Llega hasta el nivel que haga falta.
- Solo se ofrece lo vigente (`Estado = 'V'`); un área ya guardada se conserva aunque después la den
  de baja.
- Los proyectos de antes conservan su facultad (copiada a la unidad solicitante por la migración
  `m261028_194501_unidad_solicitante_y_area_intervencion`) y su edificio y bloques, que se
  muestran como "ubicación anterior" hasta que se les elijan áreas. Las columnas `facultad`,
  `edificio` y la tabla `ProyectoBloque` ya no se escriben, pero no se borraron.

## Dónde se usa cada dato

| Documento | Antes | Ahora |
|---|---|---|
| PDF del presupuesto y de la lista | Facultad / Edificio / Bloques | Unidad solicitante + Área de intervención (a todo el ancho) |
| Solicitud de cotización, "Unidad" | facultad | unidad solicitante |
| Carta: `{facultad}` | facultad | unidad solicitante; nuevo marcador `{unidad}` (los dos valen lo mismo) |
| Carta: cargo sugerido "DECANO DE LA…" | siempre | solo si la unidad empieza con "Facultad" |
| Especificación, "Dependencia" | facultad | unidad solicitante (decidido por el desarrollador el 2026-10-28) |
| Especificación, lugar de entrega por defecto | "Administración de la <facultad>" | texto de la configuración del formulario (`ConfiguracionEspecificacion.lugarEntregaPorDefecto`; vacío = no sale) |
| Paso Presupuesto, "Área de intervención" de la cabecera | sugería la facultad | sugiere el área del proyecto |

Las emisiones y cartas ya hechas no cambian: guardan su propia copia.

## Componentes

- `InfraestructuraProvider` (contrato) y `UsfxGeoInfraestructuraProvider` (lee `UsfxGEO` con la
  conexión `dbGeo`). Si Desarrollo entrega vistas o un servicio web, se cambia solo esa clase.
- `ProyectoService`: valida cada área contra la fuente y copia los nombres; sugerencias de unidad.
- `ProyectoController`: `bloques`, `niveles`, `ambientes` y `buscar-ambiente` (JSON para el formulario).

## Proyectistas y el JSON de parámetros (mismo día)

- Los proyectistas pasaron del JSON a la tabla `redes.Proyectista` (nombre, activo), que el encargado
  administra en Configuración → "Proyectistas" (como los tipos de proyecto). Es una lista de nombres,
  no de usuarios. El proyecto sigue guardando una copia del nombre.
- Del JSON `parametros-proyecto.json` se quitaron los edificios, los bloques y los proyectistas. Solo
  quedan las facultades, como sugerencia para la unidad solicitante. "Facultad de Ciencias
  Tecnológicas" se corrigió a "Facultad de Ciencias y Tecnología".
- Ojo: la copia antigua de la PC del Ing. sí usa los edificios del JSON. Una versión nueva para esa
  PC debe llevar el código y el JSON nuevos juntos.

## Para los diagramas (pendiente del tutor)

- **Clases**: clase `Proyectista` (catálogo de nombres, activo); `Proyecto.unidadSolicitante`; clase `ProyectoAreaIntervencion` (composición 0..* desde
  Proyecto) con referencias a un componente externo; `ConfiguracionEspecificacion.lugarEntregaPorDefecto`.
  Facultad, edificio y bloques salen del modelo (quedan solo como datos históricos).
- **Casos de uso**: no hay "Gestionar unidades" ni "Gestionar edificios". Se puede agregar "Buscar
  área de intervención" (incluido en "Gestionar proyecto"), parecido a "Buscar ítem" en SIGMA.
- **Componentes**: "Infraestructura física (UsfxGEO, Desarrollo)" como sistema externo de solo
  lectura, junto a `dbo.CatalogoSigma`.
