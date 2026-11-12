# Proyecto: Sistema de Presupuestos de Instalaciones de Redes (USFX - DTIC)

## Qué es este proyecto
Proyecto de grado: sistema en línea para elaborar y gestionar presupuestos de instalaciones de redes en la
Universidad San Francisco Xavier de Chuquisaca (USFX), para la Unidad de Redes y Telecomunicaciones de la DTIC.
Un solo actor: el **Encargado de Redes**.

Recorrido de un proyecto: **Datos** (proyecto, unidades, área, equipo) → **Plantilla** → **Lista de ítems y
especificaciones** (emite los formularios ET) → **Cotización** (solicitudes de proforma y proformas recibidas) →
**Presupuesto** (precios, estados, PDF, carta de entrega). Un proyecto puede tener varias **fases** y cada fase
aprobada, **órdenes de cambio**; la **ficha técnica** resume todo.

## Documentación
Las reglas de cada parte, con las clases y métodos donde viven, están en **`docs/reglas/`**. Leer el archivo del tema
antes de cambiar algo:

| Tema | Archivo |
|---|---|
| Códigos (PT, LM, ET, SC, PR, número de proyecto), correlativos, versiones | `docs/reglas/codificacion.md` |
| Proyecto, fases, orden de cambio, unidades, área de intervención, equipo, historial, vista del proyecto | `docs/reglas/proyecto-y-fases.md` |
| Ficha técnica del proyecto | `docs/reglas/ficha-tecnica.md` |
| Subcatálogo, SIGMA, partida y demás catálogos | `docs/reglas/catalogos.md` |
| Lista de ítems, lugar de entrega, secciones sin precio, especificaciones y formulario ET | `docs/reglas/lista-y-especificaciones.md` |
| Solicitud de proforma y cotizaciones | `docs/reglas/cotizacion.md` |
| Presupuesto y carta de entrega | `docs/reglas/presupuesto.md` |
| Lo común a todos los PDF | `docs/reglas/impresos.md` |
| Seguridad, servidor, base de datos, SQL Server 2012 | `docs/reglas/servidor-y-seguridad.md` |

Además: `docs/glosario.md` (nombres de pantalla ↔ código ↔ tabla que no coinciden), `docs/pendientes.md` (lo que falta
decidir o hacer), `docs/guia-instalacion-dtic.md`, `docs/modelo-logico-datos.md` (diagramas ER y diccionario) y
`docs/historia/` (cómo y por qué se llegó a cada regla; no es la referencia vigente).

**Al cambiar una regla, actualizar su archivo en `docs/reglas/`** (y `docs/modelo-logico-datos.md` si cambian tablas).
Lo que falta decidir va a `docs/pendientes.md`.

## Stack tecnológico (NO cambiar sin autorización del tutor académico)
- Backend: PHP 8.3 + Yii2 Advanced (v2.0.55). `composer.json` exige `>=8.3` y PHPStan analiza como 8.3
- Frontend: jQuery y Bootstrap 5; validación en frontend Y backend
- Base de datos: SQL Server (la institución usa **2012**, compatibilidad 110; local 2022)
- Servidor de la institución: Nginx sobre Ubuntu Server (Linux: **distingue mayúsculas** en nombres de archivo)
- PDF: mPDF
- Entorno local: Laragon con PHP 8.3.33 (`C:\laragon\bin\php\php-8.3.33-Win32-vs16-x64`), Nginx en el puerto 8080
- Driver SQL Server para PHP: msphpsql v5.12.0

## Estructura del proyecto (Yii2 Advanced)
- `backend/` → AQUÍ vive el módulo de redes: `controllers/` (delgados: reciben y responden), `services/` (reglas de
  negocio y transacciones), `models/`, `views/` (no consultan la base), `components/`, `web/js`, `web/css/redes.css`
- `frontend/` → sitio público de la plantilla, no se usa
- `common/` → configuración compartida; `common/config/main-local.php` tiene la conexión (NUNCA se sube a git)
- `console/` → migraciones y comandos de consola

## Base de datos
- Base real: `POAPresupuestos` (servidor institucional 172.16.1.250:1433); copia local en localhost:1433 (Windows
  Authentication)
- Esquema propio: **`redes`** — aquí van TODAS las tablas nuevas, creadas con **migraciones** de Yii2, nunca con
  scripts SQL sueltos
- `dbo.CatalogoSigma` (catálogo nacional del gobierno, ~72.000 ítems; la copia local tiene 51.636): **solo lectura**,
  NUNCA modificar su estructura ni sus datos
- `UsfxGEO` (infraestructura física, de Desarrollo): solo lectura, conexión `dbGeo`

## Reglas esenciales (el detalle, en `docs/reglas/`)
- **Precio congelado**: el precio, la partida y la unidad de una línea se copian al crearla y nunca se recalculan;
  elegir otro precio reemplaza la línea.
- **Lo emitido no cambia**: un formulario ET emitido guarda copias congeladas; un error se corrige reemitiendo (mismo
  código, versión + 1).
- **Los códigos los da el sistema** (`CodificacionService::tomar()`, siempre dentro de una transacción) y nunca se
  reutilizan.
- **Cada fila de `redes.Proyecto` es una fase** (o una orden de cambio); las fases comparten `numeroProyecto`.
- **El historial se escribe desde los servicios**, dentro de su transacción (`HistorialService::registrar()`).
- **Un ítem de la lista es una línea del presupuesto**; un presupuesto por fase u orden de cambio.
- **Catálogos**: solo se eligen activos; quien ya tenía uno desactivado lo conserva; uno en uso no se elimina, se
  desactiva.
- **Montos**: hasta 2 decimales y máximo 99,999,999.99 (`backend\components\Monto`).

## Alcance
**Sí incluye**: proyectos y fases, órdenes de cambio, lista de ítems, librería de especificaciones y formularios ET,
solicitudes de proforma y cotizaciones, presupuesto con sus estados, carta de entrega, ficha técnica, codificación del
lineamiento RD-GD-LI-001, historial, catálogos.

**No incluye**:
- Cómputos métricos (se quitaron el 2026-10-21: el módulo nunca se usó)
- Control de ejecución o avance de obra
- Gestión de usuarios y roles propia (la administra el sistema institucional; `redes.Usuario` son usuarios
  temporales, sin roles ni pantallas, solo para poder ingresar)
- El módulo de modificación presupuestaria en sí (solo genera una "pre-modificación")
- Adquisiciones / compras

## Convenciones de código
- Tablas en `redes`: PascalCase singular (`redes.Proyecto`, `redes.PresupuestoDetalle`)
- Migraciones con `safeUp()` y `safeDown()`; compatibles con SQL Server 2012 (ver
  `docs/reglas/servidor-y-seguridad.md`). Para crear un esquema:
  `IF NOT EXISTS (SELECT 1 FROM sys.schemas WHERE name = '...') BEGIN EXEC(...) END`
- `declare(strict_types=1)` en todos los archivos PHP
- Comentarios: explicar el **porqué** técnico de las decisiones
- Atributos que pone un servicio: sin regla en `rules()` o con "!" (no deben llegar de un formulario)
- Textos de pantalla en español, sin abreviar; fechas dd/mm/aaaa (pantallas) y dd-mm-aaaa (impresos)

## Entorno de desarrollo — comandos útiles
- El PATH de Windows (del SISTEMA) debe apuntar al PHP 8.3.33 de Laragon; con el 8.2 todo comando se detiene
  ("Composer detected issues in your platform ... >= 8.3.0"). Verificar con `php -v`.
- Migraciones: `php yii migrate`; crear una: `php yii migrate/create nombre_descriptivo`
- Tests: `php vendor/bin/codecept run -c backend` (Unit y Functional); solo unitarios:
  `php vendor/bin/codecept run Unit -c backend`. Usan la base LOCAL (`common/config/test-local.php`, no se sube a
  git); cada test corre en una transacción que se deshace al terminar
- Análisis estático: `composer static` (PHPStan nivel 5; debe terminar en "No errors"). Usa una línea base
  (`phpstan-baseline.neon`) con los avisos que ya existían: solo muestra los NUEVOS. Al corregir un aviso viejo:
  `php vendor/bin/phpstan analyse --generate-baseline phpstan-baseline.neon`
- **Antes de cada PR**: tests y `composer static` sin errores
- URL local: http://presupuestos-redes.test:8080 — usuario de prueba: admin / admin123
- Usuarios temporales por consola: `php yii usuario` (lista), `usuario/crear <usuario>`, `usuario/clave`,
  `usuario/desactivar`, `usuario/activar` (un usuario no se borra, se desactiva)
- Con `sqlcmd` a mano, usar `-I` (si no, fallan los cambios en tablas con columnas calculadas)

## Diseño UML (tesis)
Diagramas de casos de uso (un actor, "Encargado de Redes"), clases, secuencia ("Elaborar presupuesto"), actividades
(ciclo de vida del presupuesto) y componentes (lo desarrollado: módulo Redes + esquema `redes`; lo externo:
`dbo.CatalogoSigma`, UsfxGEO, sistema de presupuestos, usuarios y roles, modificación presupuestaria). El modelo cambió
desde que se dibujaron (fases, órdenes de cambio, especificaciones en tres niveles, carta, historial…): **lo que falta
actualizar está en `docs/pendientes.md`**, sección "Tutor".

## Nivel del desarrollador
Persona sin experiencia previa en PHP/Yii2, aprendiendo mientras construye el proyecto. Al explicar o generar código:
preferir explicaciones claras del "por qué", no solo el "qué". No dar por sentadas las convenciones de Yii2: explicarlas
brevemente la primera vez que aparecen.
