# Partida presupuestaria + nombres DTIC vs. SIGMA (lee CLAUDE.md y spec_tecnica_sistema.md primero)

## Contexto
La parte financiera consolida los presupuestos por **partida presupuestaria**
(`dbo.CatalogoSigma.IdGasto`, 5 dígitos, ej. 39700). El sistema hoy no la guarda.

Un presupuesto real de redes de la DTIC (categorías = secciones del Excel):
- Materiales y accesorios para redes: cable UTP, patch cord, conectores RJ45, jack,
  marcador (39500), cinta aislante (39700), cinta de etiquetadora (39500), velcro (33100).
- Materiales ducteado: regleta tapacable, tornillo+taco fisher (34500), broca SDS (34800),
  caja MEC (39700).
- Dispositivos de comunicación: switch PoE (43120), cámara domo y NVR (43500) = activo fijo
  (partidas 43xxx); disco duro (39800).
- Mano de obra: "a ser implementado por personal de la DTIC" (sin ítem SIGMA, partida 25xxx).

Conclusiones: (a) una categoría DTIC mezcla varias partidas, por eso categoría y
partida son datos distintos y se guardan los dos; (b) SIGMA es genérico (un mismo
ítem SIGMA sirve para "Switch PoE 24p" y "Switch 48p", o patch cord 1 m y 3 m),
así que varios ítems DTIC pueden apuntar al mismo `idSigma`; (c) los nombres SIGMA son
largos y la DTIC usa los suyos (SIGMA "JACK; TIPO RJ-45 HEMBRA AUTOPONCHABLE CAT 6;
UTILIZACION RED/DATOS" → DTIC "Jack RJ45 hembra categoría 6").

## Estado actual del código (ya revisado, no lo asumas distinto)
- `redes.SubcatalogoItem.idSigma` es `uniqueidentifier NULL` (referencia lógica a
  `dbo.CatalogoSigma.IdSigma`, sin FK física). El modelo lo trata como string.
- `SubcatalogoItemService` tiene `idsSigmaExistentes()`, `prellenarDesdeSigma()`,
  `crear()`/`actualizar()` (hacen `load()` + `save()`).
- `CatalogoSigma` (solo lectura; propiedades `IdSigma, Clase, Descripcion,
  RamaComercial, Especificacion, IdGasto, PrecioReferencial, CodigoEstado, ...`).
  `buscar()` hoy busca la frase completa con LIKE sobre Descripcion/RamaComercial/Clase.
- `PresupuestoDetalle` NO tiene campo de texto libre ni de partida.
  `PresupuestoService::agregarLinea()` valida y llama a `congelarPrecio()` (privado).
- `Cotizacion` tiene `fecha` y `validezDias`; `CotizacionDetalle` tiene
  `idSubcatalogo` e `idEspecificacion`.
- Hoy el subcatálogo está vacío (el usuario lo limpió para recargarlo), así que los
  UPDATE de relleno de las migraciones pueden afectar 0 filas: no deben fallar por eso.

## Reglas de siempre
- Migraciones nuevas con `safeUp`/`safeDown`, esquema `redes`.
- NUNCA modificar `dbo.CatalogoSigma` (ni estructura ni datos).
- Controladores delgados; la lógica va en `backend/services/` (arquitectura de 4 capas).
- Explícame el porqué de cada cambio: estoy aprendiendo Yii2.
- **Antes de tocar archivos, muéstrame el plan completo.** Luego trabaja UN paso a la
  vez y espera mi confirmación entre pasos. **No ejecutes `php yii migrate` sin que yo
  lo confirme**, y haz un commit por paso (mensaje en español, descriptivo).

## Paso 0: verificar la base real (solo lectura, antes de escribir migraciones)
Con una consulta de solo lectura (sin modificar nada), confirma y repórtame:
- Tipos y collation reales en `dbo.CatalogoSigma` de `IdSigma`, `IdGasto`,
  `CodigoEstado`, `Clase`, `RamaComercial`, `Descripcion`.
- Cuántos registros tienen `IdGasto` NULL, vacío o con largo distinto de 5, y qué
  valores distintos tiene `CodigoEstado`.
- Si `IdGasto` es numérico, cómo convertirlo a `CHAR(5)` sin perder ceros a la izquierda.
Ajusta los pasos siguientes a lo que encuentres y avísame si algo contradice este documento.

## Paso 1: partida en SubcatalogoItem
- Migración: agregar `partida` CHAR(5) NULL a `redes.SubcatalogoItem`. En la misma
  migración, llenar la partida de los ítems existentes con `dbo.CatalogoSigma.IdGasto`
  (UPDATE con JOIN por idSigma; debe funcionar aunque no haya filas). `safeDown` solo
  borra la columna.
- Modelo `SubcatalogoItem`: propiedad `partida`, label "Partida", regla string de
  exactamente 5 dígitos (`/^\d{5}$/`), normalizar '' a null como ya se hace con idSigma.
- Relación `getCatalogoSigma()` (hasOne por idSigma → IdSigma) para mostrar el nombre
  oficial SIGMA sin copiarlo.
- **Orden de validación (importante en Yii2):** la validación corre dentro de `save()`.
  En `SubcatalogoItemService::crear()` / `actualizar()`, después de `load()` y ANTES de
  `save()`, si el ítem tiene `idSigma` y SIGMA trae `IdGasto`, copiar esa partida al
  ítem. Si tiene `idSigma` pero SIGMA no trae partida, o si no tiene `idSigma` (ej. mano
  de obra), la partida es obligatoria y se ingresa a mano (mensaje claro de error).
  Explícame por qué va en el Service y no en la vista.
- Formulario: campo partida editable solo cuando no se pudo copiar desde SIGMA.

## Paso 2: nombre DTIC vs. nombre SIGMA
- `prellenarDesdeSigma()` hoy copia la Descripcion completa de SIGMA a `descripcion`.
  Cambiarlo para que `descripcion` arranque con la **Clase** SIGMA y el usuario la
  edite. `descripcion` es el nombre DTIC y es el que usan listados, presupuesto y PDF.
- En el formulario, debajo y en solo lectura: descripción SIGMA completa (vía
  `getCatalogoSigma()`) y la partida, para que el usuario sepa qué eligió.
- `idsSigmaExistentes()` hoy impide reutilizar un ítem SIGMA. Varios ítems DTIC deben
  poder apuntar al mismo idSigma. Cambiar a un aviso "ya usado por: <nombres DTIC>" que
  NO bloquea. Ojo: `uniqueidentifier` puede volver en mayúsculas/minúsculas distintas
  según el origen; compara normalizando (`strtolower`).
- El nombre DTIC debe ser genérico ("Regleta tapacable", "Switch PoE"). Los detalles de
  una línea concreta ("2 metros", "12mm x 4 metros", "según especificación") NO van en el
  subcatálogo: van en la observación de la línea (Paso 4).
- Listado (`subcatalogo-item/index.php`): columnas nombre DTIC, categoría, nombre SIGMA
  (puede ser vacío), partida, unidad, activo. Filtro por partida.

## Paso 3: mejorar `CatalogoSigma::buscar()`
- Filtrar solo registros vigentes según lo que encontraste en el Paso 0 (se espera
  `CodigoEstado = 'V'`).
- Separar el texto en palabras y exigir que TODAS aparezcan (AND), cada una en
  Clase OR Descripcion OR RamaComercial. "jack cat 6" debe encontrar
  "JACK; TIPO RJ-45 ... CAT 6".
- Mostrar `IdGasto` en la tabla de resultados.
- Seguir usando condiciones `like` de Yii (parámetros bindeados), mantener `limit(100)`
  y avisar en pantalla cuando se alcance el límite. Conserva la nota de rendimiento
  sobre Full-Text Search. Nota: "RJ45" no encuentra "RJ-45"; no lo trates como bug.

## Paso 4: partida congelada y observación en PresupuestoDetalle
- Migración: agregar a `redes.PresupuestoDetalle`: `partida` CHAR(5) NULL y
  `observacion` VARCHAR(300) NULL (texto libre de la línea: medidas, "según
  especificación técnica", etc.). Rellenar `partida` de líneas existentes desde su
  SubcatalogoItem (puede afectar 0 filas).
- Modelo: propiedades, labels y reglas. `observacion` aparece en el formulario de
  línea, en `view.php` y en `pdf.php` junto al nombre DTIC.
- En `PresupuestoService::agregarLinea()`: copiar la partida del ítem al crear la
  línea, UNA sola vez, con el mismo criterio que `congelarPrecio()` (nunca se
  recalcula). Documéntalo en el comentario como regla de negocio crítica.

## Paso 5: validación línea ↔ cotización (commit propio, no tiene que ver con partida)
- En `agregarLinea()`, si la línea tiene `idCotizacionDetalle`, verificar que
  `CotizacionDetalle.idSubcatalogo == PresupuestoDetalle.idSubcatalogo`. Si no
  coincide, error de validación en el campo de cotización.
- Cotización vencida (`fecha + validezDias < hoy`): permitir pero mostrar flash warning.
- En `cambiarEstado()` hacia 'aprobado': advertencia (sin bloquear) si alguna línea usa
  una cotización vencida.
- Verifica primero que `fecha` y `validezDias` puedan ser NULL y define qué hacer
  en ese caso (no vencida).

## Paso 6: resumen por partida
- `PresupuestoService::resumenPorPartida(Presupuesto)`: agrupa las líneas por la
  `partida` guardada en PresupuestoDetalle → partida, nº de líneas, subtotal; ordenado
  por partida; líneas sin partida → "Sin partida".
- Mostrarlo en `presupuesto/view.php` debajo del detalle por categoría, y como sección
  final en `pdf.php` ("Resumen por partida presupuestaria"). El total general debe
  coincidir con el total por categorías; verifícalo con un caso de prueba y dime el
  resultado.
- NO implementes el reporte consolidado de todas las versiones aprobadas: déjalo anotado
  como pendiente en CLAUDE.md (ya existe esa nota).

## Fuera de alcance de esta tarea (solo anótalo en CLAUDE.md como pendiente)
- Código UNSPSC / "Catálogo de Bienes y Servicios de cuarto nivel" (SIGEP): se evaluará
  cuando se consigan las guías operativas del SIGEP y se sepa si la universidad ya lo
  relaciona con SIGMA en alguna tabla.
- Índice único sobre `SubcatalogoItem.descripcion`: se decide después de ver cómo queda
  el subcatálogo recargado.

## Al terminar cada paso
Dime: archivos tocados, comando de migración (sin ejecutarlo hasta que confirme) y
cómo probarlo en el navegador (http://presupuestos-redes.test:8080).

## Al final de todo
Actualiza CLAUDE.md y spec_tecnica_sistema.md con: columna `partida` en SubcatalogoItem
y PresupuestoDetalle, `observacion` en PresupuestoDetalle, reutilización de idSigma por
varios ítems DTIC, nombre DTIC vs. nombre SIGMA, y resumen por partida.
