# Cambio de flujo: el presupuesto como "documento vivo"

Resumen para revisión con el tutor académico. Fecha: 2026-10-16.

## Qué cambió y por qué

Antes, elaborar el presupuesto de un proyecto exigía salir del proyecto, crear el presupuesto con un
formulario y agregar las líneas **una por una** (elegir ítem, elegir cotización, confirmar). Con una
lista real de 30 a 40 ítems eso era lento y propenso a errores: un mismo ítem repetido en la lista
(por ejemplo, cable de 2 cajas y de 1 caja) podía tomar el precio cotizado para la otra fila.

Ahora el presupuesto se elabora en **una sola pantalla**, dentro del proyecto (paso "Presupuesto"):

1. Cada ítem de la lista **es** una línea del presupuesto. No se "agregan" líneas.
2. Al entrar, el sistema propone el **menor precio vigente** cotizado para cada ítem.
3. La pantalla es el cuadro comparativo: una columna por proveedor. El encargado solo cambia lo que
   no le convence, con un clic. Se guarda automáticamente.
4. Si elige un precio que **no** es el menor, el sistema exige una **justificación**.
5. Los datos de cabecera (número, oficio DTIC, área, fecha) se piden recién al **enviar a revisión**,
   y no se puede enviar si algún ítem no tiene precio.
6. El PDF lleva la marca "BORRADOR" mientras no esté aprobado, e incluye un anexo con el cuadro
   comparativo y las justificaciones.

## Reglas de negocio que NO cambian

- **Precio congelado** (`{congelado}` en el diagrama de clases): el precio de una línea guardada
  nunca se recalcula. Elegir otro precio **reemplaza** la línea por una nueva, con el precio
  congelado en ese momento.
- **Partida congelada** junto con el precio.
- Ciclo de vida y estados: en proceso → en revisión → aprobado / observado / cancelado; observado
  vuelve a en proceso. Un presupuesto aprobado no se modifica.
- La cotización vencida se permite, con aviso.
- Una línea solo usa cotizaciones de su mismo ítem (ahora además: de su misma fila de la lista).

## Cambios en los diagramas UML

### Clases

- `PresupuestoDetalle`: nuevo atributo `justificacion: String [0..1]`.
- Restricción nueva: como máximo **una** línea por ítem de la lista del proyecto.

### Secuencia "Elaborar presupuesto"

**Antes:** buscar ítem → ver especificaciones → ver cotizaciones → confirmar línea con precio
congelado (repetido por cada ítem).

**Ahora:**

1. El Encargado de Redes abre el paso "Presupuesto" del proyecto.
2. El sistema crea el presupuesto (si no existe) y, por cada ítem de la lista sin línea, crea la línea
   con el menor precio vigente (precio y partida congelados).
3. El sistema muestra el cuadro comparativo con los precios elegidos y los totales.
4. *[opcional, repetible]* El encargado elige otro precio para un ítem → si no es el menor, ingresa la
   justificación → el sistema reemplaza la línea y devuelve los totales actualizados.
5. El encargado envía a revisión con la cabecera → el sistema verifica que todos los ítems tengan
   precio → estado "en revisión".

### Actividades (ciclo de vida del presupuesto)

- La actividad "Elaborar detalle" pasa a ser "Revisar precios sugeridos y ajustar".
- Antes de "Enviar a revisión" se agrega la decisión "¿todos los ítems tienen precio y la cabecera
  está completa?" (si no, se vuelve a ajustar).
- El ciclo Observado → Corregir → Enviar a revisión se mantiene, en la misma pantalla.

### Casos de uso

- "Elaborar presupuesto" ya no incluye "Buscar ítem" (los ítems vienen de la lista del proyecto).
  Sigue incluyendo "Calcular precio referencial", que ahora es la propuesta automática del menor
  precio.
- Nuevo caso que extiende "Elaborar presupuesto": "Justificar precio que no es el menor".

## Dónde está en el código

- `backend/services/ElaborarPresupuestoService.php`: reglas del documento vivo.
- `backend/controllers/ElaborarPresupuestoController.php` y `backend/views/elaborar-presupuesto/`:
  la pantalla y el guardado AJAX (`backend/web/js/elaborar-presupuesto.js`).
- `console/migrations/m261016_012942_justificacion_y_linea_unica_presupuesto.php`: columna
  `justificacion` e índice único.
