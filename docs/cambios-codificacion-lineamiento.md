# Codificación de documentos según el lineamiento RD-GD-LI-001 (2026-10-22)

La DTIC tiene el lineamiento **RD-GD-LI-001 "Lineamiento para la gestión de documentos técnicos –
Redes y Telecomunicaciones"** (versión 01, aprobado el 29-08-2024). Decidido con el desarrollador el
2026-10-22: **se aplica a los impresos del sistema**.

## Decisiones

| Pregunta | Decisión |
|---|---|
| ¿Se aplica a los impresos del sistema? | Sí |
| Código de los documentos | `RD-PI-<tipo>-NNN`: unidad RD (Redes y Telecomunicaciones), proceso PI (Proyectos de infraestructura de redes y servicios), tipo, número de 3 cifras |
| Tipos que se usan en Redes | **PT** proyecto, **PR** presupuesto, **ET** especificación técnica |
| Formulario NET (`NET:880/26`) | Pasa a `RD-PI-ET-NNN`, **empezando desde 1**. Los ya emitidos también se convirtieron (ver abajo) |
| Proyectos existentes | Se renumeran a 3 cifras (`RD-PI-PT-04` → `RD-PI-PT-004`) |
| Presupuestos | `RD-PI-PR-NNN` desde 1; los números escritos a mano antes ("690") también se convirtieron |
| ¿Quién da el número? | El sistema, a partir de un número que fija el encargado (pantalla "Codificación de documentos") |
| Solicitud de cotización | El código FR **no aplica** en el sistema: sigue como `RD-PI-PT-004 / SC-01` |
| Control de elaboración del presupuesto | Elaborado por (ingeniero responsable de redes o de proyectos) y aprobado por (V°B°, quien revisa el borrador). 2 firmas |
| ¿Qué pasa después del 999? | El lineamiento no lo dice; el sistema sigue con 1000 para no repetir nunca un código |

## Cambios en el sistema

- **Tabla nueva `redes.CorrelativoDocumento`** (`tipo` PK: PT, PR, ET; `siguiente`). Reemplaza a
  `ConfiguracionEspecificacion.siguienteNet` (se eliminó). `CodificacionService::tomar()` reparte los
  números con la fila bloqueada (UPDLOCK), igual que antes el NET. El siguiente solo puede ser mayor que
  el último usado. Pantalla y menú "Codificación de documentos" (`CodificacionController`).
- **Proyecto**: el código sale del correlativo PT (antes: el mayor código + 1, con 2 cifras). Se quitó
  el parámetro `prefijoCodigoProyecto` de `backend/config/params.php`.
- **Presupuesto**: `numeroPresupuesto` ya no se escribe: recibe `RD-PI-PR-NNN` al crearse (al entrar por
  primera vez al paso Presupuesto; un borrador viejo sin número lo recibe ahí). Columna nueva
  **`Presupuesto.fechaAprobacion`** (la pone `PresupuestoService::cambiarEstado()` al aprobar; NULL en los
  aprobados antes). `Presupuesto::VERSION_DOCUMENTO = '01'`: cada proyecto tiene un solo presupuesto y
  uno aprobado no cambia.
- **Especificación (emisión)**: columna nueva **`EspecificacionEmision.serie`** (`ET` las nuevas, `NET`
  las anteriores). El índice único pasa de `numeroNet` a (`serie`, `numeroNet`), porque la serie ET
  empezó en 1. `codigo()` (antes `codigoNet()`) da `RD-PI-ET-001` o `NET:880/26`. El registro
  ("Formularios de especificación") se ordena por emisión y busca por los dos formatos. En la carta,
  `{especificaciones}` da los códigos completos de las ET (y solo el número de las NET, como antes).
- **Cabecera de los impresos** (`views/pdf/_cabecera.php`, sección 8.8.2 del lineamiento): en el
  presupuesto y el formulario de especificación, línea "Proceso: …", título y la fila
  **Código | Versión | Fecha | Página**. La versión del formulario es la de la ficha emitida (v2 → 02).
  El formulario usa ahora esta cabecera en vez de la suya (se quitó el logo opcional
  `logo-formulario-net.png`). La solicitud de cotización mantiene su fila N.° | facultad | fecha | pág.
- **Control de elaboración** (sección 8.12) al pie del PDF del presupuesto: tabla Versión / Elaborado por /
  Aprobado por con Firma, Nombre y Cargo, y "Fecha de aprobación". Reemplaza a las dos firmas sueltas.
- **Tipografía** (sección 8.8.1, Calibri 11; pie en 9): Calibri es de Microsoft y no se puede llevar al
  servidor Linux; se usa **Carlito** (licencia OFL, mismas medidas), en `backend/fonts/carlito` (ver su
  MANIFEST). Cuerpo en 11 pt en el presupuesto, el formulario y la solicitud; tablas en 10 pt. La carta
  sigue con Pagella (es correspondencia, nivel E).
- Al juntar varios documentos en un PDF (solicitud + formularios, o todas las especificaciones), cada uno
  sale con su propia cabecera y su "Página X de Y" (`PresupuestoPdfGenerator::empezarDocumento()`).
- Migración `m261022_135030_codificacion_segun_lineamiento` (con `safeDown()`; se detiene si ya hay
  emisiones ET, para no dejar dos documentos con el mismo número).

## Conversión de los códigos viejos (2026-10-22, a pedido del desarrollador)

Primero se decidió que los documentos ya hechos conservaran su código; después se pidió convertirlos para
que todo el sistema use un solo formato. Migración `m261022_144239_convertir_codigos_viejos_al_lineamiento`:

- Todas las emisiones NET (también reemplazadas y anuladas) pasan a la serie ET con el siguiente número
  del correlativo, en el orden en que se emitieron (año y número NET). En la base local: NET:500/26 →
  RD-PI-ET-001 … NET:902/26 → RD-PI-ET-016.
- Los presupuestos con número escrito a mano pasan a RD-PI-PR, en el orden en que se crearon. En la base
  local: PRUEBA-001 → RD-PI-PR-002, 690 → RD-PI-PR-003, 691 → RD-PI-PR-004.
- El código de antes se guarda en `EspecificacionEmision.codigoAnterior` y `Presupuesto.numeroAnterior`,
  porque los documentos ya entregados lo llevan impreso. Se ve como "antes NET:900/26" en el registro de
  formularios (también en el CSV) y "(antes 690)" en el presupuesto. El registro encuentra una emisión
  buscando su NET anterior.
- `safeDown()` devuelve los códigos de antes.

## Para los diagramas (tutor)

- **Clases**: nueva clase `CorrelativoDocumento` (tipo, siguiente). `Presupuesto` gana `fechaAprobacion`
  y `numeroAnterior` (y `numeroPresupuesto` pasa a ser `{generado}`). `EspecificacionEmision` gana `serie`
  (enumerado `SerieEmision`: ET, NET) y `codigoAnterior`. `ConfiguracionEspecificacion` pierde `siguienteNet`.
- **Casos de uso**: "Configurar codificación de documentos" (Encargado de Redes).
- **Secuencias** "Emitir" y "Reemitir formulario": el número sale de `CodificacionService`, no de la
  configuración del formulario.

## Pendiente

- Confirmar con el Ing. si el formulario de especificación también debe llevar el control de elaboración
  (elaborado / aprobado). Hoy conserva "Formulario elaborado por" y "Firma" en blanco.
- El lineamiento pide en la cabecera el logo y nombre de la "Unidad" (izquierda) y del "Área/Dependencia"
  (derecha). Se dejaron los dos logos de siempre (USFX y DTIC) con los nombres en el centro; si la DTIC
  tiene un logo propio de RedUSFX, se puede poner a la derecha.
- Fijar con el encargado desde qué número siguen PT, PR y ET si el registro de Google Drive ya tiene
  números usados.
