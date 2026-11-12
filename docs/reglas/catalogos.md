# Catálogos

Regla común: **solo se eligen registros activos**, pero quien ya tenía uno desactivado lo conserva (métodos
`reglaElegible()` / `opciones($conservar)`). **Uno en uso no se elimina, se desactiva.**

## Subcatálogo de ítems y SIGMA

- `redes.SubcatalogoItem`: subcatálogo **curado** de ítems de redes, con referencia **lógica** a
  `dbo.CatalogoSigma.IdSigma` (sin FK). **Nunca** duplicar el catálogo SIGMA completo: se elige a mano, bajo demanda.
- `dbo.CatalogoSigma` es **de solo lectura**: nunca se modifica su estructura ni sus datos.
- **Nombre DTIC vs. nombre SIGMA**: `SubcatalogoItem.descripcion` es el nombre DTIC (corto y genérico), guardado siempre
  en MAYÚSCULAS. Es el que sale en pantallas y documentos. Al elegir un ítem de SIGMA se prellena con la `Descripcion`
  de SIGMA en mayúsculas y el usuario la ajusta. El nombre SIGMA no se copia (relación `getCatalogoSigma()`) y solo sale
  en el reporte "Relación con el catálogo SIGMA".
- Varios ítems DTIC pueden apuntar al mismo `idSigma`: se avisa "ya usado por…", no se bloquea.
- **Partida presupuestaria** (5 dígitos, `CatalogoSigma.IdGasto`): `SubcatalogoItem.partida` (copiada de SIGMA o
  escrita a mano) y `PresupuestoDetalle.partida` (**congelada** al crear la línea). Categoría y partida son datos
  distintos: una categoría mezcla varias partidas.
- Un ítem inactivo no se elige (`SubcatalogoItemService::opciones($conservar)`). Las líneas de cotización solo filtran
  el desplegable, sin validar: una solicitud puede traer un ítem que se desactivó después.
- La búsqueda en SIGMA usa `LIKE '%palabra%'` (como mucho 10 palabras, `backend\components\Busqueda`).

## Otros catálogos (menú Configuración)

| Catálogo | Tabla | Notas |
|---|---|---|
| Unidades de medida | `redes.UnidadMedida` | `SubcatalogoItem.idUnidadMedida` es FK; la lista y el presupuesto guardan la **abreviatura** como texto, congelada. Los documentos para el proveedor muestran el nombre ("Metro") |
| Tipos de proyecto | `redes.TipoProyecto` | Dicen QUÉ se hace: Ampliación, Mejoramiento, Refuncionalización, Equipamiento. FK en `Proyecto` y `Plantilla` |
| Procesos | `redes.Proceso` | Código de 2 letras, único (GD, DC, PI, DS, GR). Va en los códigos de documento: **en uso, su código no se cambia**. Un proyecto nuevo trae PI |
| Personal | `redes.Personal` | Solo nombres (no son usuarios) |
| Roles del proyecto | `redes.RolProyecto` | No dan permisos |
| Tipos de orden de cambio | `redes.TipoOrdenCambio` | La lista 7.1.1 del FR-006 |
| Tipos de trabajo complementario | `redes.TipoCondicionPrevia` | Instalaciones eléctricas, Obras civiles, Apertura de ductos, Despeje del ambiente |
| Tratamientos de la carta | `redes.TratamientoCarta` | En "Configuración de la carta"; la carta guarda copia del texto |
| Categorías | `redes.Categoria` | Agrupan la lista y el presupuesto |

- En `backend/config/parametros-proyecto.json` quedan **solo las facultades** (código + nombre), como sugerencia de
  unidades.
- **Plantillas** (`redes.Plantilla`, `PlantillaItem`): ítems sugeridos para cargar la lista de un proyecto.
