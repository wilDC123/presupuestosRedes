# Impresos (PDF)

Todos con **mPDF**, armados por `backend\components\PresupuestoPdfGenerator`. Las vistas PDF usan un CSS simple
(mPDF entiende un subconjunto) y estilos en línea donde hace falta.

- **Hoja oficio** (8,5 × 13 pulgadas = 216 × 330 mm; en mPDF "Folio"): `PresupuestoPdfGenerator::HOJA_OFICIO`. El
  reporte SIGMA va horizontal (`HOJA_OFICIO_HORIZONTAL`).
- **Escala de grises**: títulos #e0e0e0, categorías #d0d0d0, subtotales #f2f2f2. Solo los logos y el membrete de la
  carta tienen color.
- **Letra Carlito** (mismas medidas que Calibri, licencia libre, `backend/fonts/carlito`) en todos menos la carta.
- **Cabecera común** (`backend/views/pdf/_cabecera.php`, `<htmlpageheader>` de mPDF), repetida en cada página: logo
  DTIC USFX y su texto a la izquierda, "PROCESO: …" y el título al centro, logo RedUSFX a la derecha, y abajo Código |
  Versión | Fecha | Página (fecha dd-mm-aaaa). Al juntar varios documentos en un PDF, cada uno sale con su cabecera y
  su numeración (`PresupuestoPdfGenerator::empezarDocumento()`).
- **Control de elaboración** (`backend/views/pdf/_control-elaboracion.php`), una tabla al fondo de la última página:
  "ELABORADO POR | APROBADO POR", filas Firma (2 cm para firmar), Nombre (prefijo + nombre) y Cargo, y "Fecha de
  aprobación". Con una sola firma (lista, solicitud), a todo el ancho. El formulario ET la pide en 11 pt
  (`letraGrande`). Las firmas salen de su configuración o de la copia congelada del documento.
- **Cantidades** (`Monto::cantidad()`): enteras sin decimales ("2", "1,200"); con fracción, 2 decimales ("30.50").
  Así en la lista, la solicitud, el formulario ET, el presupuesto (también la mano de obra: "0") y la relación SIGMA.
  Los precios siempre con 2 decimales.
- **Marcas de agua**: BORRADOR (presupuesto sin aprobar, lista en edición, ficha con alguna fase sin aprobar);
  REEMPLAZADO / ANULADO / VENCIDO (formulario ET).
- Sin pie de página (los contactos van en el membrete de la carta).
- Temporales de mPDF en `@runtime/mpdf`.
