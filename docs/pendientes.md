# Pendientes

Lo que falta decidir o hacer, agrupado por quién lo resuelve. Al cerrar uno, quitarlo de aquí (si cambia una regla,
anotarla en `docs/reglas/`).

## Ing. de DTIC

- Confirmar la **línea del proyecto** en los documentos ("PROYECTO: 001 – NOMBRE (FASE 2)", sin negrilla) y la de la
  orden de cambio ("… – ORDEN DE CAMBIO N.° 1"), y si la orden de cambio lleva código LM propio.
- Confirmar el formato del **número de proyecto** (hoy "001": tres cifras, sin prefijo).
- Confirmar **desde qué número siguen PT, PR y ET** ("Codificación de documentos") si el registro del Drive ya tiene
  números usados.
- ¿El control de elaboración lleva también **"Revisado por"**? (el lineamiento 8.12 lo menciona; hoy 2 firmas).
- ¿Se adjuntan las especificaciones a la **solicitud de proforma**? (hoy, a elección al imprimir).
- **Zona horaria del SQL Server** del servidor: algunas fechas las pone SQL Server (`GETDATE()`) y otras PHP (La Paz).
  Verificar con `SELECT SYSDATETIMEOFFSET();` (debe dar -04:00); si no, poner esas fechas desde PHP.
- UsfxGEO: ¿seguir leyendo sus tablas o usar vistas? ¿Qué son los estados E, C, D, R?
- Lugar de entrega habitual de los proyectos que no son de una facultad (Rectorado, Canal, DTIC), para el texto por
  defecto de la configuración del formulario ET (hoy vacío).
- Revisar la nota final del formulario ET ("Señor proveedor ser evaluado…": parece faltarle "para"; está tal cual el
  formulario oficial).
- Llevar las 44 dudas del orden de la librería de especificaciones (título "UN disco" con DOS discos, FACE PLATE 4
  TOMAS con "2 posiciones", fichas vacías, datos repetidos, restos del Word).

## DTIC (servidor e institucional)

- Integrar el ingreso con el **sistema de usuarios institucional** (cambiar `identityClass` en
  `backend/config/main.php`); hasta entonces, usuarios temporales en `redes.Usuario`. Después: que quien crea el
  proyecto quede como proyectista (los usuarios temporales no tienen nombre completo).
- `redes.Personal` debería venir del sistema institucional (web service).
- **Conexión a la base en producción**: en Linux probablemente haga falta un usuario SQL Server dedicado (Windows Auth
  funciona solo en local).
- Acceso al **catálogo SIGMA completo** (la copia local tiene 51.636 de ~72.000 ítems).
- Correr en el servidor `php yii especificacion/ordenar …` (orden de la librería).
- Asignar el **ítem de referencia** a las fichas importadas (en la base local se hizo a mano con 78 fichas; los id
  cambian entre bases) y la **categoría** a las 165 fichas importadas sin categoría.
- Informar formalmente que se usó una **copia local de la base institucional** para desarrollar.
- Si delante del servidor hay un **proxy**, configurar Yii para leer la IP real (límite de intentos de ingreso).
- Al integrarse al repositorio institucional: una rama dentro del proyecto Yii2 existente, con su propio controlador,
  dentro del módulo de ejecución / modificaciones presupuestarias.

## Tutor (tesis)

Actualizar los diagramas UML (ver la sección "Diseño UML" de `CLAUDE.md`):

- **Clases** — agregar: `TipoProyecto`, `Proceso`, `ConfiguracionSolicitudCotizacion`, `ConfiguracionPresupuesto`,
  `Presupuesto.manoDeObra`, `PresupuestoDetalle.fuentePrecio`, `CartaPresupuesto` (0..1 por presupuesto) y
  `ConfiguracionCarta`, `TratamientoCarta`, `CorrelativoDocumento`, los atributos de codificación
  (`Presupuesto.fechaAprobacion`, `numeroAnterior`, firmas congeladas; `EspecificacionEmision.serie`, `codigoAnterior`,
  `versionDocumento`, `proceso`), `ProyectoMaterialDtic`, `ProyectoCondicionPrevia`, `TipoCondicionPrevia`,
  `ListaMaterialesAprobacion` (versión emitida), `Proyecto.unidadSolicitante` / `unidadDependencia`,
  `ProyectoAreaIntervencion`, `Personal`, `RolProyecto`, `ProyectoParticipante`, `HistorialProyecto`,
  `Proyecto.lugarEntrega`, `numeroProyecto`, `codigoLista`, `SolicitudCotizacion.codigoDocumento`, la orden de cambio
  (auto-asociación `ordenesCambio`, `TipoOrdenCambio`). Dividir `Especificacion` en ficha, versión, característica y
  emisión. **Quitar** `ComputoMetrico` (se eliminó del sistema) y la auto-relación "versión anterior" del presupuesto
  (hoy un presupuesto por fase u orden de cambio).
- **Casos de uso** — agregar: Gestionar procesos, tipos de proyecto, personal, roles, tipos de trabajo complementario y
  tipos de orden de cambio; Configurar codificación de documentos; Elaborar carta de entrega; Consultar historial;
  Agregar fase; Registrar / eliminar orden de cambio. Quitar los de cómputos métricos.
- **Secuencia y actividades** — el flujo "documento vivo" del presupuesto, la cotización opcional (precio directo), emitir
  y reemitir el formulario ET, enviar la lista a cotización (emite una versión), y que las operaciones terminan
  registrando en el historial.
- **Componentes** — agregar "Infraestructura física (UsfxGEO)" como componente externo.
- Informar que `redes.Usuario` (usuarios temporales) no es la "gestión de usuarios" que está fuera de alcance.

Resúmenes para el tutor en `docs/historia/cambios-*.md`.

## Mejoras posibles

- Enlazar especificaciones desde las **plantillas** (`PlantillaItem`), para que se copien a la lista al cargarla.
- Registrar en el **historial** las filas de la lista, las especificaciones y las cotizaciones.
- Índice único sobre `SubcatalogoItem.descripcion` (después de ver cómo queda el subcatálogo).
- Código UNSPSC / "Catálogo de Bienes y Servicios de cuarto nivel" (SIGEP), fuera de alcance por ahora.
- Búsqueda en SIGMA con Full-Text Search si se vuelve lenta.
