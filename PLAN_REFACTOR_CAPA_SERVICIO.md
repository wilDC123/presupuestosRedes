# Plan: Agregar capa de Servicio (arquitectura de 4 capas)

Este documento es para ejecutarse con Claude Code en VS Code, dentro del repo
`presupuestosRedes`. Complementa a `CLAUDE.md` y `spec_tecnica_sistema.md` (léelos
primero si no están ya en contexto) — NO los reemplaza ni contradice.

## Por qué

En la revisión del tutor (ingeniero de DTIC) sobre los mockups, el punto central fue:
tu sistema necesita una arquitectura donde el controlador no sepa de dónde viene la
información (base de datos hoy, posible servicio web SIGMA mañana). Hoy eso no pasa:
los controladores llaman directo a los modelos ActiveRecord
(ej. `Presupuesto::crearNuevaVersion()` se invoca directo desde `PresupuestoController`).

La solución acordada: **4 capas**, no las 7 que maneja el ingeniero en su empresa
(eso es para proyectos mucho más grandes; 4 es lo correcto y defendible para este
sistema):

1. **Datos + ORM** (fusionadas) → ya existen: `backend/models/*.php` (ActiveRecord).
   No se tocan los `rules()`, `attributeLabels()`, relaciones (`hasOne`/`hasMany`), etc.
2. **Servicio** (negocio + servicio fusionadas) → **NUEVA**, carpeta `backend/services/`.
   Aquí va TODA la lógica que hoy vive mezclada en modelos y controladores: crear
   nueva versión de presupuesto, validar transiciones de estado, congelar precio
   unitario, calcular totales, decidir si algo puede editarse, etc.
3. **Presentación** → ya existe: `backend/controllers/*.php`. Se refactorizan para
   que NO llamen a `Modelo::find()` ni a métodos de negocio del modelo directamente,
   sino que deleguen al Servicio correspondiente.
4. **Cliente** → ya existe: `backend/views/*.php`. No cambia en este plan (las
   vistas actuales del CRUD base se mantienen; reconstruir vistas de los flujos
   nuevos del sistema es una fase aparte, posterior a este refactor).

No se toca: migraciones, esquema de base de datos, nombres de tablas/columnas,
`common/`, `frontend/`, `console/`.

## Regla general del patrón

- Cada Service es una clase simple, SIN heredar de nada de Yii2 (no es un
  componente, no es un behavior — una clase PHP normal).
- Un Service puede usar internamente el ActiveRecord (`Proyecto::find()`, etc.)
  — eso es lo normal hoy. El punto no es prohibir ActiveRecord, es que el
  CONTROLADOR ya no lo llame directo.
- El controlador instancia el Service (o lo resuelve via `Yii::createObject()`,
  cualquiera de las dos formas es válida — usa instanciación directa `new` por
  simplicidad, no hace falta contenedor de dependencias para este proyecto) y
  solo le pide resultados ya procesados.
- Nombrado: `backend\services\{Entidad}Service`, archivo
  `backend/services/{Entidad}Service.php`.

## Ejemplo 1 — caso simple: ProyectoService

Antes (`backend/controllers/ProyectoController.php`, método actual):

```php
public function actionIndex(): string
{
    $dataProvider = new ActiveDataProvider([
        'query' => Proyecto::find()->orderBy(['fechaCreacion' => SORT_DESC]),
        'pagination' => ['pageSize' => 20],
    ]);

    return $this->render('index', ['dataProvider' => $dataProvider]);
}
```

Nuevo archivo `backend/services/ProyectoService.php`:

```php
<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\Proyecto;
use yii\data\ActiveDataProvider;

/**
 * Lógica de negocio para Proyecto. El controlador no debe hablar
 * directamente con Proyecto::find() — todo pasa por aquí.
 */
class ProyectoService
{
    public function listar(): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => Proyecto::find()->orderBy(['fechaCreacion' => SORT_DESC]),
            'pagination' => ['pageSize' => 20],
        ]);
    }

    public function obtener(int $id): ?Proyecto
    {
        return Proyecto::findOne($id);
    }

    public function crear(array $datosFormulario): Proyecto
    {
        $proyecto = new Proyecto();
        $proyecto->load($datosFormulario, '');
        $proyecto->save();

        return $proyecto;
    }

    public function actualizar(Proyecto $proyecto, array $datosFormulario): bool
    {
        $proyecto->load($datosFormulario, '');

        return $proyecto->save();
    }

    public function eliminar(Proyecto $proyecto): bool
    {
        return $proyecto->delete() !== false;
    }
}
```

`backend/controllers/ProyectoController.php` después del refactor:

```php
<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\Proyecto;
use backend\services\ProyectoService;
use Yii;
use yii\filters\VerbFilter;
use yii\web\Controller;
use yii\web\NotFoundHttpException;

class ProyectoController extends Controller
{
    private ProyectoService $service;

    public function init(): void
    {
        parent::init();
        $this->service = new ProyectoService();
    }

    public function behaviors(): array
    {
        return [
            'verbs' => [
                'class' => VerbFilter::class,
                'actions' => ['delete' => ['POST']],
            ],
        ];
    }

    public function actionIndex(): string
    {
        return $this->render('index', [
            'dataProvider' => $this->service->listar(),
        ]);
    }

    public function actionView(int $id): string
    {
        return $this->render('view', ['model' => $this->findModel($id)]);
    }

    public function actionCreate()
    {
        $model = new Proyecto();

        if ($this->request->isPost) {
            $model = $this->service->crear($this->request->post('Proyecto'));
            if ($model->hasErrors() === false) {
                return $this->redirect(['view', 'id' => $model->id]);
            }
        }

        return $this->render('create', ['model' => $model]);
    }

    public function actionUpdate(int $id)
    {
        $model = $this->findModel($id);

        if ($this->request->isPost
            && $this->service->actualizar($model, $this->request->post('Proyecto'))) {
            return $this->redirect(['view', 'id' => $model->id]);
        }

        return $this->render('update', ['model' => $model]);
    }

    public function actionDelete(int $id)
    {
        $this->service->eliminar($this->findModel($id));

        return $this->redirect(['index']);
    }

    protected function findModel(int $id): Proyecto
    {
        $model = $this->service->obtener($id);

        if ($model === null) {
            throw new NotFoundHttpException('El proyecto solicitado no existe.');
        }

        return $model;
    }
}
```

**Importante:** compara esto contra el `ProyectoController.php` REAL que ya existe en
el repo antes de sobrescribir — puede tener detalles (validación extra, relaciones
cargadas con `with()`, etc.) que hay que preservar. Este ejemplo es la forma, no un
reemplazo ciego.

## Ejemplo 2 — caso con lógica de negocio real: PresupuestoService

Este es el que más importa, porque hoy `crearNuevaVersion()` y `puedeEditarse()`
viven como métodos en `backend/models/Presupuesto.php` (mezclando Datos/ORM con
Negocio). Deben moverse al Service:

```php
<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\Presupuesto;
use backend\models\PresupuestoDetalle;
use yii\data\ActiveDataProvider;

class PresupuestoService
{
    public function listar(): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => Presupuesto::find()->with('proyecto')->orderBy(['id' => SORT_DESC]),
            'pagination' => ['pageSize' => 20],
        ]);
    }

    public function obtener(int $id): ?Presupuesto
    {
        return Presupuesto::findOne($id);
    }

    /**
     * Regla de negocio: un presupuesto 'aprobado' o 'cancelado' no se edita
     * directamente. Ver spec_tecnica_sistema.md, sección redes.Presupuesto.
     */
    public function puedeEditarse(Presupuesto $presupuesto): bool
    {
        return !in_array($presupuesto->estado, [
            Presupuesto::ESTADO_APROBADO,
            Presupuesto::ESTADO_CANCELADO,
        ], true);
    }

    /**
     * Crea una nueva FASE del presupuesto (no una copia/snapshot de la
     * anterior). Confirmado con el autor del proyecto: cuando un presupuesto
     * aprobado necesita un cambio, NO se clonan las líneas existentes — se
     * abre una fase nueva (fase 2, fase 3...) del mismo proyecto, que arranca
     * VACÍA y se llena de cero con sus propias líneas. La fase anterior queda
     * intacta como historial (por eso idVersionAnterior apunta a ella).
     *
     * Esto coincide con lo que ya decía CLAUDE.md como pendiente ("crea la
     * nueva versión vacía a propósito") — spec_tecnica_sistema.md describía
     * el flujo con copia de líneas; ESO quedó desactualizado y hay que
     * corregirlo ahí también (ver nota al final de este archivo).
     */
    public function crearNuevaVersion(int $idOriginal): ?Presupuesto
    {
        $original = $this->obtener($idOriginal);

        if ($original === null) {
            return null;
        }

        $nueva = new Presupuesto();
        $nueva->idProyecto = $original->idProyecto;
        $nueva->idVersionAnterior = $original->id;
        $nueva->numeroVersion = $original->numeroVersion + 1;
        $nueva->numeroPresupuesto = $original->numeroPresupuesto;
        $nueva->oficioDtic = $original->oficioDtic;
        $nueva->areaIntervencion = $original->areaIntervencion;
        $nueva->fecha = $original->fecha;
        $nueva->estado = Presupuesto::ESTADO_EN_PROCESO;

        // Sin copiar PresupuestoDetalle: la fase nueva empieza vacía.
        return $nueva->save() ? $nueva : null;
    }

    public function cambiarEstado(Presupuesto $presupuesto, string $nuevoEstado): bool
    {
        // TODO: aquí va la validación de transiciones válidas que describe
        // spec_tecnica_sistema.md: en_proceso -> en_revision ->
        // (observado -> vuelve a en_proceso) -> aprobado / cancelado
        $presupuesto->estado = $nuevoEstado;

        return $presupuesto->save();
    }

    public function eliminarLinea(int $idLinea): bool
    {
        $linea = PresupuestoDetalle::findOne($idLinea);

        return $linea !== null && $linea->delete() !== false;
    }
}
```

Luego, en `backend/models/Presupuesto.php`, **eliminar** el método estático
`crearNuevaVersion()` (y mover `puedeEditarse()` también si querés que el modelo
quede solo como Datos/ORM puro) — y actualizar `PresupuestoController` para que
use `PresupuestoService` en vez de llamar al modelo directamente.

## Checklist — réplica a las demás entidades

Seguir el mismo patrón, en este orden (mismo orden de dependencias que ya está en
`spec_tecnica_sistema.md`):

- [x] `CategoriaService` (simple, sin lógica especial)
- [x] `ProveedorService` (simple)
- [x] `ProyectoService` (ejemplo arriba)
- [x] `SubcatalogoItemService` — ojo: incluye el método que hoy busca en
      `dbo.CatalogoSigma` vía `CatalogoSigma::buscar()`. Ese método de búsqueda
      también debería quedar invocado desde aquí, no directo desde el controlador.
- [x] `EspecificacionService` (simple, un item puede tener varias especificaciones)
- [x] `CotizacionService` + lógica de `CotizacionDetalle` (una cotización, varias
      líneas; revisar si hoy el controlador guarda ambos en una transacción —
      si es así, esa transacción se mueve al Service)
- [x] `PresupuestoService` (ejemplo completo arriba — el más importante)
- [x] `ComputoMetricoService` (simple — recordar: el sistema solo ALMACENA, nunca
      calcula)

Para cada uno:
1. Crear `backend/services/{Entidad}Service.php` con los métodos que el
   controlador actual necesita (leer el controlador real primero para saber
   cuáles son — no inventar métodos que no se usan).
2. Mover ahí cualquier lógica de negocio que esté en el modelo o en el
   controlador (validaciones de reglas de negocio, cálculos, transacciones).
3. Refactorizar el controlador para que solo llame al Service.
4. Probar manualmente cada CRUD (index/view/create/update/delete) en el
   navegador después de cada entidad — no esperar a terminar todas para probar.

## Qué NO hacer en este plan

- No tocar las vistas (`backend/views/*`) — siguen funcionando igual, solo
  reciben los mismos datos de antes.
- No tocar migraciones ni modelos `rules()`/relaciones.
- No introducir un contenedor de inyección de dependencias ni interfaces — es
  innecesario para el tamaño de este proyecto. `new {Entidad}Service()` directo
  en el controlador es suficiente y más fácil de explicar en la defensa.
- No fusionar esto con la reconstrucción del frontend/mockups — es un paso
  aparte, después de que esta capa esté lista y probada.

## Corrección pendiente en `spec_tecnica_sistema.md`

Confirmado con el autor del proyecto: la sección **"Flujo de versionado"** de
`spec_tecnica_sistema.md` dice que al crear una nueva versión se copian las
líneas (`PresupuestoDetalle`) de la anterior — **eso es incorrecto**, quedó
desactualizado. La regla real es la que implementa el `PresupuestoService` de
este documento: cada nueva versión/fase arranca **vacía**.

Antes de ejecutar este plan, actualizar `spec_tecnica_sistema.md` en la sección
"Flujo de versionado" para que diga:

```
1. Usuario intenta agregar/modificar algo en un Presupuesto con estado
   'aprobado'
2. Sistema detecta que no es editable
3. Sistema crea un nuevo Presupuesto con numeroVersion+1 e idVersionAnterior
   apuntando al original, en estado 'en_proceso', SIN copiar los
   PresupuestoDetalle de la versión anterior (arranca vacío)
4. El usuario arma desde cero las líneas de esta nueva fase
```

Nota de terminología para pensar (no bloqueante): en la entrevista y en tu
mensaje más reciente se habla de "fases" (fase 1, fase 2...), mientras que el
modelo de datos usa `numeroVersion`/`idVersionAnterior`. Son el mismo concepto
con dos nombres distintos. Si para tu defensa y documentación quieres usar
"fase" de forma consistente, se puede renombrar más adelante (columna,
método, vistas) — no es necesario resolverlo ahora para avanzar con este plan.
