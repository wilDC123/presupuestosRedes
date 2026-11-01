<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\Categoria;
use backend\models\Especificacion;
use backend\models\PlantillaCategoria;
use backend\models\PlantillaItem;
use backend\models\ProyectoItem;
use backend\models\SubcatalogoItem;
use Yii;
use yii\data\ActiveDataProvider;
use yii\db\IntegrityException;

/**
 * Capa de Servicio para Categoria.
 *
 * El controlador no habla directamente con Categoria::find() / findOne() / save():
 * todo pasa por aqui. Asi, si manana los datos vienen de otro lado (ej. un
 * servicio web), solo cambia esta clase y el controlador queda igual.
 */
class CategoriaService
{
    public function listar(): ActiveDataProvider
    {
        return new ActiveDataProvider([
            'query' => Categoria::find()->orderBy(['orden' => SORT_ASC]),
            'pagination' => [
                'pageSize' => 20,
            ],
        ]);
    }

    /**
     * Categorias en su orden, cada una con la cantidad de items del
     * subcatalogo que le pertenecen. El conteo se trae en UNA consulta
     * agrupada (no una por categoria).
     *
     * @return array<int, array{categoria: Categoria, items: int}>
     */
    public function listarConConteo(): array
    {
        $conteos = SubcatalogoItem::find()
            ->select(['total' => 'COUNT(*)', 'idCategoria'])
            ->groupBy('idCategoria')
            ->indexBy('idCategoria')
            ->asArray()
            ->column();

        $filas = [];

        foreach (Categoria::find()->orderBy(['orden' => SORT_ASC])->all() as $categoria) {
            $filas[] = [
                'categoria' => $categoria,
                'items' => (int) ($conteos[$categoria->id] ?? 0),
            ];
        }

        return $filas;
    }

    /**
     * Categorias como [id => nombre], en su orden, para los desplegables de
     * los formularios y filtros. Las vistas la reciben del controlador: asi
     * ninguna vista consulta la base de datos.
     *
     * @return array<int, string>
     */
    public function opciones(): array
    {
        return Categoria::find()
            ->select(['nombre', 'id'])
            ->orderBy(['orden' => SORT_ASC])
            ->indexBy('id')
            ->column();
    }

    public function obtener(int $id): ?Categoria
    {
        return Categoria::findOne($id);
    }

    /**
     * Carga los datos del formulario (el arreglo completo de POST, Yii toma
     * solo la parte "Categoria[...]") y guarda. Devuelve false si no llegaron datos
     * o si la validacion fallo -- en ese caso los errores quedan en
     * $categoria->errors para que la vista los muestre.
     */
    public function crear(Categoria $categoria, array $datosFormulario): bool
    {
        if (!$categoria->load($datosFormulario)) {
            return false;
        }

        // El formulario rapido del listado solo pide el nombre: si no llega
        // el orden, la categoria nueva va al final. Se asigna ANTES de
        // save() porque la validacion ('orden' es obligatorio) corre ahi.
        if ($categoria->orden === null || $categoria->orden === '') {
            $categoria->orden = ((int) Categoria::find()->max('orden')) + 1;
        }

        return $categoria->save();
    }

    public function actualizar(Categoria $categoria, array $datosFormulario): bool
    {
        return $categoria->load($datosFormulario) && $categoria->save();
    }

    /**
     * Devuelve null si se elimino, o el motivo por el que no.
     *
     * REGLA DE NEGOCIO: una categoria en uso no se elimina. La usan los items
     * del subcatalogo, las listas de los proyectos, las plantillas y las
     * especificaciones; se cuentan antes, para decir exactamente cuales. Si
     * la base lo impide por otra causa, queda en el log.
     */
    public function eliminar(Categoria $categoria): ?string
    {
        $plantillas = array_unique(array_merge(
            PlantillaItem::find()->select('idPlantilla')->andWhere(['idCategoria' => $categoria->id])->column(),
            PlantillaCategoria::find()->select('idPlantilla')->andWhere(['idCategoria' => $categoria->id])->column()
        ));
        $usos = array_filter([
            'ítem(s) del subcatálogo' => (int) SubcatalogoItem::find()->andWhere(['idCategoria' => $categoria->id])->count(),
            'ítem(s) en listas de proyectos' => (int) ProyectoItem::find()->andWhere(['idCategoria' => $categoria->id])->count(),
            'plantilla(s)' => count($plantillas),
            'especificación(es)' => (int) Especificacion::find()->andWhere(['idCategoria' => $categoria->id])->count(),
        ]);

        if ($usos !== []) {
            return 'No se puede eliminar "' . $categoria->nombre . '": la usan ' . $this->enumerar($usos)
                . '. Mueva esos registros a otra categoría primero.';
        }

        try {
            $categoria->delete();
        } catch (IntegrityException $e) {
            Yii::error($e->getMessage(), __METHOD__);

            return 'No se pudo eliminar "' . $categoria->nombre . '": otra parte del sistema la usa.';
        }

        return null;
    }

    /**
     * "12 ítem(s) del subcatálogo, 2 especificación(es)".
     *
     * @param array<string, int> $usos [rotulo => cantidad]
     */
    private function enumerar(array $usos): string
    {
        return implode(', ', array_map(static fn (string $rotulo, int $cantidad): string => "$cantidad $rotulo", array_keys($usos), $usos));
    }
}
