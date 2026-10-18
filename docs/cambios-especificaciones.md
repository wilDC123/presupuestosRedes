# Cambio de modelo: especificaciones técnicas con versiones y formularios NET

Resumen para revisión con el tutor académico. Fecha: 2026-10-18.
Plan detallado y decisiones: `docs/plan-especificaciones.md`.

## Qué cambió y por qué

El Ing. de DTIC compartió su "Banco de especificaciones" de Drive: 852 archivos (837 .docx). Al
revisarlos se vio que **no son 850 especificaciones distintas**: son unas 100 a 150 fichas técnicas
emitidas una y otra vez, cada vez con un **código NET** nuevo, otra cantidad y otra dependencia.
Por ejemplo, "Cable de red Categoría 6" aparece decenas de veces con el mismo contenido técnico. El
Excel "1000 Especificaciones" es el registro de esos códigos NET.

El modelo anterior guardaba en `Especificacion` el código NET, la dependencia y la fecha: es decir,
modelaba **un documento emitido**, no una ficha reutilizable. Ahora se separan tres cosas:

1. **La ficha** (librería): qué es el bien. Título, categoría, activa o no.
2. **La versión de la ficha**: su contenido técnico (características fila por fila, modelo
   sugerido, garantía). La ficha se mejora por versiones: v1, v2…
3. **La emisión (formulario NET)**: el documento que se firma, se sella y se entrega al proveedor,
   con su código NET, cantidad, dependencia y condiciones de entrega.

Las especificaciones **viven en el sistema** (antes vivían en Word, en Drive). El formulario NET en
PDF lo genera el sistema con el formato institucional.

## Reglas de negocio (confirmadas con el Ing. de DTIC)

- **Una emisión no cambia nunca.** Es un documento oficial firmado y sellado. Un error (por ejemplo,
  la facultad equivocada) se corrige **reemitiendo**: se emite otro formulario con NET nuevo y el
  anterior queda "reemplazado por" el nuevo. Nada se borra.
- **Los cambios técnicos entran solo en emisiones nuevas.** Una versión de la ficha que ya se usó en
  una emisión no se modifica: editarla crea la versión siguiente. Las emisiones nuevas usan siempre
  la última versión; las ya emitidas conservan la suya.
- **Enlazar es emitir.** Al elegir una ficha para un ítem de la lista del proyecto, el sistema emite
  su formulario con el siguiente NET. Cambiar o quitar la ficha, o quitar el ítem, **anula** la
  emisión vigente (queda en el historial).
- **Correlativo NET.** El primer número lo fija el encargado; después sube de 1 en 1 y no se
  reinicia por año (el año va después de la barra: `NET:880/26`). Un número nunca se reutiliza.
- **Vigencia.** El formulario vale 30 días (configurable). Pasado ese plazo el sistema avisa que
  hay que reemitirlo; no bloquea.
- **Copias congeladas.** La emisión guarda copia de todo lo que imprime (título, cantidad, unidad,
  dependencia, garantía, textos del formulario, condiciones de entrega), igual que el precio
  congelado del presupuesto. Reimprimir un NET da siempre el mismo documento.
- **Avisos que no bloquean:** la cantidad de la lista ya no coincide con la emitida, el formulario
  venció, hay una versión más nueva de la ficha.
- La especificación sigue siendo **opcional** por ítem.

## Cambios en los diagramas UML

### Clases

`Especificacion` se divide en cuatro clases y se agrega una de configuración:

| Clase | Atributos principales | Relaciones |
|---|---|---|
| `Especificacion` (ficha) | titulo, activo | → `Categoria` 0..1; → `SubcatalogoItem` 0..1 (ítem de referencia) |
| `EspecificacionVersion` | numero, modeloSugerido, garantia, adjunto, notaCambio | `Especificacion` 1 ◆— 1..* (composición) |
| `EspecificacionCaracteristica` | orden, nombre, valor | `EspecificacionVersion` 1 ◆— 0..* (composición) |
| `EspecificacionEmision` | numeroNet, anio, /codigoNet, cantidad {congelado}, unidad {congelado}, dependencia {congelado}, garantia {congelado}, condiciones de entrega {congelado}, fechaEmision, vigenciaDias, estado, /vencida, motivo | → `EspecificacionVersion` 1; → `Proyecto` 1; → `ProyectoItem` 0..1; auto-relación 0..1 `reemplazadaPor` |
| `ConfiguracionEspecificacion` | siguienteNet, textos del formulario, valores por defecto | (una sola instancia) |

- `/codigoNet` y `/vencida` son atributos **derivados**: se calculan, no se guardan.
- Nuevo enumerado `EstadoEmision`: vigente, reemplazada, anulada.
- Restricciones nuevas: `{numeroNet único}`; `{como máximo una emisión vigente por ítem}`;
  `{una versión con emisiones no se modifica}`.
- `ProyectoItem` → `Especificacion` 0..1 se mantiene (qué ficha eligió el ítem).
- `CotizacionDetalle` gana → `EspecificacionEmision` 0..1 (contra qué NET cotizó el proveedor) y
  conserva → `Especificacion` 0..1 (las cotizaciones sueltas eligen una ficha y no tienen NET).

### Secuencia nueva: "Emitir formulario NET"

1. El Encargado de Redes elige una ficha para un ítem en la lista del proyecto y guarda.
2. El sistema verifica que el NET inicial esté fijado y que la ficha tenga contenido técnico.
3. El sistema anula la emisión vigente del ítem (si la había) y toma el siguiente NET (bloqueando el
   contador para que dos personas no reciban el mismo número).
4. El sistema crea la emisión con la versión actual de la ficha y las copias congeladas.
5. El encargado abre "Ver formulario" → el sistema genera el PDF con el formato institucional.

### Secuencia nueva: "Reemitir formulario NET"

1. El encargado elige "Reemitir" en un ítem y escribe el motivo (y, si quiere, otro lugar o plazo de
   entrega).
2. El sistema marca la emisión anterior como "reemplazada".
3. El sistema emite una nueva con NET nuevo, la última versión de la ficha y la cantidad actual de
   la lista, y enlaza la anterior con la nueva ("reemplazada por").

### Secuencia "Elaborar presupuesto"

Sin cambios en el flujo. Los impresos citan "Según especificación técnica NET:880/26" con el código
de la emisión vigente del ítem.

### Casos de uso

- Nuevo: **"Gestionar librería de especificaciones"** (crear, editar —con versiones—, desactivar).
- Nuevo: **"Emitir formulario NET"**, incluido en "Elaborar lista de ítems" cuando se enlaza una
  ficha (`<<include>>` condicionado a que el ítem tenga especificación; la especificación es
  opcional).
- Nuevo: **"Reemitir formulario NET"**, `<<extend>>` de "Emitir formulario NET".
- Nuevo: **"Consultar registro de formularios NET"** (reemplaza el Excel; incluye exportar).
- Nuevo: **"Configurar formulario NET"** (NET inicial y textos fijos).

### Actividades (nueva): ciclo de vida de una emisión

Emitida (vigente) → [pasan los días de vigencia] → Vencida → Reemitir → Reemplazada.
Vigente → [se corrige un error] → Reemitir → Reemplazada.
Vigente → [se cambia o quita la ficha, o se quita el ítem] → Anulada.

### Componentes

Sin componentes externos nuevos. El formulario NET usa la misma librería de PDF (mPDF) que el
presupuesto. La carga inicial lee los .docx del banco de DTIC con un comando de consola.

## Carga inicial

`php yii especificacion/importar "<carpeta del banco>"` agrupa los .docx por familia (título +
modelo sugerido), toma la emisión más reciente de cada una y descarta los marcados "NO" y los que no
tienen tabla. Sin `--confirmar` solo genera un CSV para revisar. En la base de desarrollo se
cargaron las 165 familias emitidas desde 2025 (`--desde=2025`); las emisiones antiguas quedan en el
Excel y en Drive.

## Dónde está en el código

- Modelos: `backend/models/Especificacion.php`, `EspecificacionVersion.php`,
  `EspecificacionCaracteristica.php`, `EspecificacionEmision.php`, `ConfiguracionEspecificacion.php`.
- Reglas: `backend/services/EspecificacionService.php` (versiones),
  `EspecificacionEmisionService.php` (emitir, reemitir, anular, registro, PDF),
  `EspecificacionImportacionService.php` (carga inicial).
- Pantallas: librería (`backend/views/especificacion/`), lista de ítems
  (`backend/views/lista-items/index.php`), registro (`backend/views/especificacion-emision/`),
  formulario PDF (`backend/views/especificacion/formulario-pdf.php`).
- Migraciones: `m261017_231816_versiones_y_emisiones_especificacion`,
  `m261018_000758_limpieza_especificacion_y_garantia_emision`.
- Tests: `backend/tests/Unit/Especificacion*Test.php` (`php vendor/bin/codecept run Unit -c backend`).
