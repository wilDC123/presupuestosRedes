# Ficha técnica del proyecto

PDF oficio desde el botón "Imprimir ficha técnica" de la tarjeta "Datos generales" (`proyecto/ficha`,
`ProyectoController::actionFicha()`, vista `proyecto/ficha-pdf.php`, datos de `FichaTecnicaService::datosFicha()`). Se abre
en el navegador para imprimir.

## Una sola ficha por proyecto

- El código **PT es de la ficha técnica**, no del proyecto. Solo la fase 1 lo tiene (`Proyecto.codigo`); las fases
  siguientes y las órdenes de cambio usan el de su proyecto (`Proyecto::codigoFicha()`).
- La ficha sale **igual desde cualquier fase**: incluye todas las fases del proyecto.

## Contenido

1. **INFORMACIÓN GENERAL**: n.° de proyecto, nombre en mayúsculas, descripción, tipo, proceso, "Cantidad de fases" (si
   hay varias), **costo total del proyecto** y el **seguimiento** (fechas).
   - Seguimiento: con fase única, filas "Fecha de creación / revisión / aprobación / última modificación"; con varias,
     un subcuadro "Seguimiento | Fase 1 | Fase 2".
   - Costo total (`FichaTecnicaService::costoTotal()`): suma de los presupuestos **aprobados** de fases y órdenes de cambio.
     Con varias fases (u órdenes aprobadas), una fila por parte ("Fase 1 | Bs. 1,000.00", cada orden después de su fase)
     y al final "Total (presupuestos aprobados)".
2. **UNIDADES / DEPENDENCIA**.
3. Con fase única: **3. ÁREA DE INTERVENCIÓN**, **4. PERSONAL ASIGNADO** (antes "Equipo"), **5. DOCUMENTACIÓN
   ASOCIADA**. Con varias fases: **3. FASES** y dentro "3.1 FASE 1" (3.1.1 Área de intervención, 3.1.2 Personal
   asignado, 3.1.3 Documentación asociada), "3.2 FASE 2"…
   - Los títulos de primer nivel y los de cada fase van en MAYÚSCULAS; los de tercer nivel, no.

## Documentación asociada (antes "carpeta económica")

Tabla Documento | Código | **Versión** | **Ítem / Descripción** (`FichaTecnicaService::carpeta()`; 2026-11-24: la
columna Versión se movió junto a Código, y "Detalle" pasó a llamarse "Ítem / Descripción" solo en el título):

- Lista de materiales y condiciones previas (BoM): "Elaborada el …".
- Presupuesto: "Aprobado el … · Total Bs." o su estado.
- **Especificaciones vigentes**: van en **un solo casillero** de la columna "Documento" (con `rowspan`, no una fila
  vacía por cada una — antes de 2026-11-24 parecía que el casillero era de una sola especificación), con cada código
  en su propia fila de la columna "Código"; "Reemitida el …: motivo" si se reemitieron (el motivo está en la emisión
  reemplazada).
- Solicitudes de proforma: rubro · fecha.
- Proformas recibidas (solo si hay): proveedor · fecha, y "ver proforma" si se guardó como enlace (Drive).
- Las **órdenes de cambio** de la fase van dentro de su documentación asociada: barra gris "Orden de cambio N.° 1 ·
  solicitada el … · aprobada el …", "Motivo: …" y sus documentos.

## Versión y borrador

- **Versión calculada, sin tabla**: cada fase aprobada es una versión (1.0, 1.1…), en el orden en que se aprobaron (la
  fase 2 puede aprobarse antes que la 1). Una orden de cambio no cambia la versión.
- Mientras **alguna** fase tenga el presupuesto sin aprobar, la ficha es la versión siguiente con marca **BORRADOR**
  (una fase con presupuesto cancelado no cuenta).
- Fecha de la cabecera: la creación de la fase más nueva.
- **Sin firmas**. Fechas dd-mm-aaaa, carpeta en 10 pt, al pie "Impresa el … por …".
