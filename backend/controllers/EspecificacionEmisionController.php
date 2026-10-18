<?php

declare(strict_types=1);

namespace backend\controllers;

use backend\models\EspecificacionEmision;
use backend\services\EspecificacionEmisionService;
use Yii;
use yii\web\Controller;
use yii\web\Response;

/**
 * Registro de los formularios NET emitidos (capa de Presentacion): reemplaza
 * el Excel "1000 Especificaciones" de DTIC. Solo consulta; las emisiones se
 * crean y reemiten desde la lista de items de cada proyecto.
 */
class EspecificacionEmisionController extends Controller
{
    private EspecificacionEmisionService $service;

    public function init(): void
    {
        parent::init();
        $this->service = new EspecificacionEmisionService();
    }

    /**
     * Filtros por GET: ?anio=, ?estado=, ?dependencia=, ?buscar=.
     */
    public function actionIndex(): string
    {
        $filtros = $this->filtros();

        return $this->render('index', [
            'dataProvider' => $this->service->registro($filtros),
            'filtros' => $filtros,
            'opciones' => $this->service->opcionesRegistro(),
            'estados' => [
                EspecificacionEmision::ESTADO_VIGENTE => 'Vigentes',
                'vencida' => 'Vencidas',
                EspecificacionEmision::ESTADO_REEMPLAZADA => 'Reemplazadas',
                EspecificacionEmision::ESTADO_ANULADA => 'Anuladas',
            ],
        ]);
    }

    /**
     * El registro con los mismos filtros, como CSV para abrir en Excel.
     */
    public function actionExportar(): Response
    {
        return Yii::$app->response->sendContentAsFile(
            $this->service->exportarCsv($this->filtros()),
            'formularios_net_' . date('Y-m-d') . '.csv',
            ['mimeType' => 'text/csv']
        );
    }

    /**
     * @return array{anio: string, estado: string, dependencia: string, buscar: string}
     */
    private function filtros(): array
    {
        $peticion = Yii::$app->request;

        return [
            'anio' => trim((string) $peticion->get('anio', '')),
            'estado' => trim((string) $peticion->get('estado', '')),
            'dependencia' => trim((string) $peticion->get('dependencia', '')),
            'buscar' => trim((string) $peticion->get('buscar', '')),
        ];
    }
}
