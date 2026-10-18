# Plan: especificaciones técnicas con versiones y emisiones NET

Fecha: 2026-10-17. Origen: revisión del "Banco de especificaciones" de DTIC (Drive del Ing.)
y conversación con el Ing. sobre cómo se emiten las especificaciones.

## 1. Qué se encontró en el banco

- 852 archivos (837 .docx, 11 PDF, 4 Excel). No son 850 especificaciones distintas: son unas
  100-150 fichas técnicas reutilizadas, cada vez con un código NET nuevo. Sin contar NET,
  cantidad, dependencia y condiciones de entrega, los 837 .docx se reducen a 467 contenidos
  exactamente distintos, y muchos de ellos difieren en una sola línea.
- Todos siguen la misma estructura: cabecera USFX/DTIC, instrucciones al proveedor,
  `NET:880/26`, título, cantidad, dependencia, y una tabla
  "Especificación | Propuesta | Cumple | No cumple" con las secciones I (requisitos: marca y
  modelo, URL, características), II (garantía) y III (condiciones: validez, tiempo y lugar de
  entrega). Al pie: "Formulario válido de 30 días", fecha de emisión, "Elaborado por / firma".
- `1000 Especificaciones.xlsx` es el registro de los NET emitidos (una hoja por año). El
  correlativo NO se reinicia cada año (2023 empieza en 10, 2025 en 550, 2026 en 760); el
  sufijo `/26` es solo el año.
- Los Excel de fibra óptica son desgloses de presupuesto por puntos (material + mano de
  obra), no especificaciones.

## 2. Decisiones (con el usuario y el Ing.)

- Las especificaciones viven EN EL SISTEMA, con sus características fila por fila. El
  Word/PDF es una salida que el sistema genera. Los .docx de Drive quedan como histórico.
- La especificación emitida es un documento oficial firmado y sellado: NUNCA cambia. Un error
  (por ejemplo, la facultad equivocada) se corrige emitiendo una nueva con NET nuevo.
- Los cambios técnicos entran solo en emisiones nuevas (por ejemplo, al vencer los 30 días y
  volver a pedirla).
- La ficha base se mejora por VERSIONES ("versión 1, versión 2… uso las últimas"). Lo emitido
  conserva la versión que tenía.
- El NET inicial lo fija el encargado en el sistema; luego sube de 1 en 1. Enlazar una ficha a
  un ítem de la lista ES emitir (asigna NET). Un número anulado no se reutiliza.
- Los textos fijos del formulario (cabecera, instrucciones, garantía, nota final) se editan
  desde el sistema; cada emisión guarda una copia de los que usó.

## 3. Modelo de datos

| Tabla | Qué es |
|---|---|
| `redes.Especificacion` (ya existe) | La ficha de la librería: título, categoría, ítem de referencia, activo |
| `redes.EspecificacionVersion` | v1, v2… de una ficha: modelo sugerido, garantía, adjunto opcional, nota del cambio |
| `redes.EspecificacionCaracteristica` | Las filas técnicas de una versión: orden, nombre, valor |
| `redes.EspecificacionEmision` | El documento NET firmado (ver abajo) |
| `redes.ConfiguracionEspecificacion` | Una sola fila: siguiente NET, textos fijos, valores por defecto |

Emisión: `numeroNet` + `anio` (→ "NET:880/26"), `idVersion`, `idProyecto`, `idProyectoItem`
(NULL si se borró el ítem: el documento no desaparece), copias congeladas (título, cantidad,
unidad, dependencia, condiciones de entrega, textos, elaborado por), `fechaEmision`,
`vigenciaDias`, `estado` (vigente / reemplazada / anulada; "vencida" se calcula),
`idReemplazadaPor`, `motivo`.

- Índice único sobre `numeroNet`: la base impide NET repetidos.
- Índice único filtrado sobre `idProyectoItem` (solo emisiones vigentes): como máximo una
  emisión vigente por ítem.
- `ProyectoItem.idEspecificacion` se mantiene (qué ficha eligió el ítem).
- `CotizacionDetalle` pasa a apuntar a la emisión (el proveedor cotizó contra un NET
  concreto): columna nueva `idEspecificacionEmision`; la vieja `idEspecificacion` se quita en
  la limpieza.

## 4. Reglas de negocio

- Versión sin emisiones: se corrige directamente. Versión con emisiones: guardar cambios crea
  la versión siguiente; la anterior queda de solo lectura.
- Emitir (al enlazar en la lista): toma el siguiente NET dentro de una transacción con
  bloqueo, copia los datos, crea la emisión vigente.
- Cambiar o quitar la ficha de un ítem, o borrar el ítem: la emisión vigente pasa a anulada.
- Reemitir (por ítem): NET nuevo con la última versión; diálogo para corregir cantidad o
  condiciones y anotar el motivo; la anterior queda "reemplazada por".
- Avisos (no bloquean): cantidad distinta de la emitida, emisión vencida, hay versión nueva.
- `siguienteNet` solo puede fijarse por encima del último NET emitido.
- Reemitir se permite aunque la lista esté enviada a cotización; se bloquea con el
  presupuesto aprobado o cancelado.
- Dependencia = facultad del proyecto. Elaborado por = usuario de la sesión.
- El formulario se genera en PDF (mPDF). Word (PhpWord) solo si el Ing. lo pide.

## 5. Pantallas

1. Librería: formulario de la ficha con tabla de características (agregar, quitar, ordenar)
   e historial de versiones.
2. Lista de ítems: NET, estado, "Ver formulario (PDF)" y "Reemitir" por ítem.
3. Formulario NET en PDF con el formato institucional.
4. Registro de emisiones NET (reemplaza el Excel): filtros por año, estado y facultad.
5. Configuración de especificaciones: NET inicial, textos fijos, valores por defecto.
6. Los PDF de lista, solicitud y presupuesto toman el NET de la emisión vigente.

## 6. Carga inicial desde el banco

`php yii especificacion/importar "<carpeta>"`: lee los .docx (reutilizando
`EspecificacionMetadataExtractor`), agrupa por contenido técnico, se queda con la versión más
reciente de cada familia. Primero genera un CSV de vista previa; con `--confirmar` crea las
fichas como v1 (categoría a asignar desde la pantalla). Las emisiones antiguas no se importan.

## 7. Orden de trabajo

1. Migración (solo agrega): tablas nuevas, la especificación existente pasa a v1, columna
   `CotizacionDetalle.idEspecificacionEmision`.
2. Modelos y `EspecificacionService` (versiones, bloqueo de versión usada) + tests.
3. Pantalla de la librería con características y versiones.
4. `EmisionService` y configuración (NET, emitir, reemitir, anular) enganchado a la lista +
   tests. Migración de limpieza: quitar `codigoNet`, `dependencia`, `fechaEmision`,
   `modeloSugerido`, `documentoRuta` de la ficha y `CotizacionDetalle.idEspecificacion`.
5. PDF del formulario NET y adaptar los 3 PDF existentes.
6. Registro de emisiones.
7. Comando de importación y carga de las fichas del banco.
8. Documentación: `CLAUDE.md` y resumen para el tutor (cambia el diagrama de clases:
   Ficha, Versión, Característica, Emisión; y el de secuencia: Emitir/Reemitir).

## 8. Ajustes hechos durante la implementación

- Paso 4: `CotizacionDetalle.idEspecificacion` NO se quita. Una cotización suelta (sin
  solicitud) elige una ficha y no tiene NET; la línea guarda la ficha y, si viene de una
  solicitud, también la emisión (`idEspecificacionEmision`), que se conserva aunque el NET
  se reemita después.
- Paso 4: la emisión guarda una copia de la garantía (`EspecificacionEmision.garantia`): la
  de la versión o, si no tenía, la de la configuración. Sin esa copia, cambiar la garantía por
  defecto cambiaría documentos ya firmados.
- Paso 4: "Reemitir" toma siempre la cantidad ACTUAL de la lista (no se escribe a mano). Si la
  cantidad está mal, se corrige en la lista y se reemite; así el NET y la lista nunca dicen
  cosas distintas. En el diálogo se pueden cambiar el lugar, la validez y el tiempo de entrega.
- Paso 4: el lugar de entrega por defecto es la facultad del proyecto.
- Paso 4: una ficha enlazada ANTES de que existieran las emisiones no se emite sola al volver a
  guardar la lista (consumiría un NET sin aviso): la lista muestra "Emitir formulario NET".
