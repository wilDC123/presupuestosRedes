<?php

declare(strict_types=1);

namespace backend\services;

use backend\models\Proyecto;
use backend\models\ProyectoAreaIntervencion;
use backend\services\contracts\InfraestructuraProvider;
use Yii;
use yii\db\Exception as DbException;

/**
 * El AREA DE INTERVENCION de un proyecto (fase): donde se trabaja, edificio
 * -> bloque -> piso -> espacio, elegida de la infraestructura de la
 * universidad (UsfxGEO, solo lectura, a traves de InfraestructuraProvider) o
 * escrita a mano.
 *
 * Reglas que viven aqui:
 *  - cada area elegida debe existir y estar vigente en UsfxGEO; se guarda su
 *    referencia y una COPIA de los nombres (una ya guardada se conserva
 *    aunque la den de baja),
 *  - una escrita a mano no tiene ningun id; edificio obligatorio,
 *  - sin areas repetidas,
 *  - si UsfxGEO no responde, el formulario sigue funcionando con el aviso
 *    AVISO_INFRAESTRUCTURA (consultarInfraestructura()).
 *
 * ProyectoService la usa al guardar el proyecto (armarAreas() y
 * reemplazarAreas(), dentro de su transaccion); el formulario y sus
 * consultas AJAX, para elegir.
 */
class AreaIntervencionService
{
    private InfraestructuraProvider $infraestructura;

    /**
     * Sin fuente, se le pide al contenedor de Yii (la configurada en
     * backend/config/main.php); los tests pasan fuentes de prueba.
     */
    public function __construct(?InfraestructuraProvider $infraestructura = null)
    {
        $this->infraestructura = $infraestructura ?? Yii::$container->get(InfraestructuraProvider::class);
    }

    /** Lo que se le dice al usuario cuando UsfxGEO no responde. */
    public const AVISO_INFRAESTRUCTURA = 'La infraestructura de la universidad (UsfxGEO) no responde en este momento. Puede escribir el área a mano («¿No está en la lista?») o intentar más tarde.';

    /** Cuantos ambientes muestra el buscador del formulario. */
    public const MAX_AMBIENTES_BUSCADOS = 30;

    /**
     * Largo maximo de cada nombre de un area escrita a mano (las columnas de
     * redes.ProyectoAreaIntervencion) y como se llama en pantalla.
     */
    private const LARGO_AREA_MANUAL = [
        'edificio' => [200, 'edificio'],
        'bloque' => [200, 'bloque'],
        'nivel' => [100, 'piso'],
        'ambiente' => [250, 'espacio'],
    ];

    /**
     * Las areas del modelo ($proyecto->areas: las guardadas, las heredadas de
     * la fase anterior o las que llegaron en un POST con errores), cada una
     * con su "texto", su "grupo" (edificio · bloque) y su "detalle" (piso y
     * ambiente) para mostrarla en el formulario. Las guardadas usan sus
     * nombres copiados; las escritas a mano, lo que se escribio; las demas
     * se piden a la fuente. "manual" dice cual es cual.
     *
     * @return array<int, array<string, mixed>> las claves de normalizarArea() + manual, texto, grupo y detalle
     */
    public function areasDelFormulario(Proyecto $proyecto): array
    {
        $guardadas = [];

        foreach ($proyecto->isNewRecord ? [] : $proyecto->areasIntervencion as $area) {
            $guardadas[self::claveArea($area->claves())] = $area;
        }

        $areas = [];

        foreach (is_array($proyecto->areas) ? $proyecto->areas : [] as $datos) {
            $claves = self::normalizarArea(is_array($datos) ? $datos : []);

            if ($claves === null) {
                continue;
            }

            $area = $guardadas[self::claveArea($claves)] ?? null;

            if ($area === null && isset($claves['manual'])) {
                $area = new ProyectoAreaIntervencion(array_diff_key($claves, ['manual' => true]));
            } elseif ($area === null) {
                try {
                    $ruta = $this->infraestructura->ruta($claves['idEdificio'], $claves['idBloqueArea'], $claves['numeroNivel'], $claves['idEspacio']);
                } catch (DbException $e) {
                    $ruta = null;
                }

                if ($ruta === null) {
                    $areas[] = $claves + ['manual' => false, 'texto' => $texto = 'Área no vigente (edificio ' . $claves['idEdificio'] . ')', 'grupo' => $texto, 'detalle' => ''];
                    continue;
                }

                $area = new ProyectoAreaIntervencion($claves + $ruta);
            }

            $areas[] = $claves + ['manual' => $area->esManual(), 'texto' => $area->texto(), 'grupo' => $area->grupo(), 'detalle' => $area->detalle()];
        }

        return $areas;
    }

    /**
     * Edificios vigentes para el campo "Edificio" del formulario, cada uno con
     * cuantos proyectos ya lo tienen como area (el formulario pone primero
     * los usados: casi siempre se trabaja en los mismos edificios).
     *
     * @return array<int, array{id: string, nombre: string, usos: int}>|null ordenados por nombre; null si UsfxGEO no responde
     */
    public function edificiosParaFormulario(): ?array
    {
        $vigentes = $this->consultarInfraestructura(static fn (InfraestructuraProvider $geo): array => $geo->edificios());
        if ($vigentes === null) {
            return null;
        }

        $usos = ProyectoAreaIntervencion::find()
            ->select(['total' => 'COUNT(DISTINCT idProyecto)', 'idEdificio'])
            ->groupBy('idEdificio')
            ->indexBy('idEdificio')
            ->column();

        $edificios = [];

        foreach ($vigentes as $id => $nombre) {
            $edificios[] = ['id' => (string) $id, 'nombre' => $nombre, 'usos' => (int) ($usos[(string) $id] ?? 0)];
        }

        return $edificios;
    }

    /**
     * Lo que encuentra el buscador del formulario:
     *  - 'edificios': los edificios cuyo nombre coincide (van primero: asi
     *    "ciencias y tecnologia" muestra el edificio aunque tenga cientos de
     *    ambientes),
     *  - 'ambientes': cada uno con su area completa y el "texto" para mostrar,
     *  - 'hayMas': true si hay mas ambientes de los que se muestran (el
     *    formulario pide escribir algo mas preciso).
     *
     *  - 'error': solo si UsfxGEO no responde (AVISO_INFRAESTRUCTURA).
     *
     * @return array{edificios: array<int, array{idEdificio: string, edificio: string}>, ambientes: array<int, array<string, mixed>>, hayMas: bool, error?: string}
     */
    public function buscarAreas(string $texto): array
    {
        if (mb_strlen(trim($texto), 'UTF-8') < 2) {
            return ['edificios' => [], 'ambientes' => [], 'hayMas' => false];
        }

        // Se pide uno mas de los que se muestran, solo para saber si hay mas.
        $encontrado = $this->consultarInfraestructura(static fn (InfraestructuraProvider $geo): array => [
            'ambientes' => $geo->buscarAmbientes($texto, self::MAX_AMBIENTES_BUSCADOS + 1),
            'edificios' => $geo->buscarEdificios($texto),
        ]);
        if ($encontrado === null) {
            return ['edificios' => [], 'ambientes' => [], 'hayMas' => false, 'error' => self::AVISO_INFRAESTRUCTURA];
        }
        $ambientes = $encontrado['ambientes'];

        return [
            'edificios' => $encontrado['edificios'],
            'ambientes' => array_map(static function (array $fila): array {
                $fila['texto'] = (new ProyectoAreaIntervencion($fila))->texto();

                return $fila;
            }, array_slice($ambientes, 0, self::MAX_AMBIENTES_BUSCADOS)),
            'hayMas' => count($ambientes) > self::MAX_AMBIENTES_BUSCADOS,
        ];
    }

    /**
     * Hace una consulta a UsfxGEO (otra base, de Desarrollo) sin que su caida
     * tumbe la pantalla: si no responde, deja el error en el log y devuelve
     * null, y quien llama muestra AVISO_INFRAESTRUCTURA (sin esto, si UsfxGEO no
     * responde no se podria crear ni editar ningun proyecto). Lo usan el
     * formulario, el buscador y el paso a paso.
     *
     * @template T
     * @param callable(InfraestructuraProvider): T $consulta
     * @return T|null
     */
    public function consultarInfraestructura(callable $consulta): mixed
    {
        try {
            return $consulta($this->infraestructura);
        } catch (DbException $e) {
            Yii::error('UsfxGEO no responde: ' . $e->getMessage(), __METHOD__);

            return null;
        }
    }

    /**
     * Arma las areas de intervencion con lo que llego del formulario: cada
     * una debe existir y estar vigente en la fuente de infraestructura, de la
     * que se copian los nombres. Un area que el proyecto ya tenia guardada se
     * conserva tal cual (con sus nombres de entonces), aunque despues la
     * hayan dado de baja en UsfxGEO. Las escritas a mano se guardan con lo
     * que se escribio (sin consultar la fuente). Las repetidas, una vez.
     *
     * @return ProyectoAreaIntervencion[] sin guardar, en el orden recibido
     */
    public function armarAreas(Proyecto $proyecto): array
    {
        $guardadas = [];

        if (!$proyecto->isNewRecord) {
            foreach ($proyecto->areasIntervencion as $area) {
                $guardadas[self::claveArea($area->claves())] = $area;
            }
        }

        $areas = [];

        foreach (is_array($proyecto->areas) ? $proyecto->areas : [] as $datos) {
            $claves = self::normalizarArea(is_array($datos) ? $datos : []);

            if ($claves === null) {
                continue;
            }

            $clave = self::claveArea($claves);

            if (isset($areas[$clave])) {
                continue;
            }

            if (isset($guardadas[$clave])) {
                $areas[$clave] = new ProyectoAreaIntervencion($guardadas[$clave]->getAttributes(null, ['id', 'idProyecto', 'orden']));
                continue;
            }

            if (isset($claves['manual'])) {
                $error = self::errorAreaManual($claves);

                if ($error !== null) {
                    $proyecto->addError('areas', $error);
                    continue;
                }

                $areas[$clave] = new ProyectoAreaIntervencion(array_diff_key($claves, ['manual' => true]));
                continue;
            }

            try {
                $ruta = $this->infraestructura->ruta($claves['idEdificio'], $claves['idBloqueArea'], $claves['numeroNivel'], $claves['idEspacio']);
            } catch (DbException $e) {
                Yii::error($e->getMessage(), __METHOD__);
                $proyecto->addError('areas', 'No se pudo consultar la infraestructura de la universidad (UsfxGEO). Intente de nuevo más tarde.');

                return [];
            }

            if ($ruta === null) {
                $proyecto->addError('areas', 'Una de las áreas de intervención no existe o ya no está vigente en la infraestructura de la universidad.');
                continue;
            }

            $areas[$clave] = new ProyectoAreaIntervencion($claves + $ruta);
        }

        return array_values($areas);
    }

    /**
     * Deja en la base las areas armadas por armarAreas() (borra las de antes).
     * Se llama dentro de la transaccion de quien guarda el proyecto.
     *
     * @param ProyectoAreaIntervencion[] $areas
     */
    public function reemplazarAreas(Proyecto $proyecto, array $areas): void
    {
        ProyectoAreaIntervencion::deleteAll(['idProyecto' => $proyecto->id]);

        foreach ($areas as $orden => $area) {
            $area->idProyecto = $proyecto->id;
            $area->orden = $orden;
            $area->save(false);
        }

        // Olvida las areas que el modelo tenia cargadas, para que la proxima
        // lectura de $proyecto->areasIntervencion traiga las nuevas.
        unset($proyecto->areasIntervencion);
    }

    /**
     * Deja un area como la espera el resto del servicio ('' -> null):
     *  - de UsfxGEO: sus identificadores, como los espera la fuente,
     *  - escrita a mano (llega con "manual"): sus nombres, con los espacios
     *    de mas quitados.
     * null si no tiene edificio (fila vacia: se ignora).
     *
     * @return array{idEdificio: string, idBloqueArea: ?int, numeroNivel: ?string, idEspacio: ?string}|array{manual: true, edificio: string, bloque: ?string, nivel: ?string, ambiente: ?string}|null
     */
    private static function normalizarArea(array $datos): ?array
    {
        $texto = static function ($valor): ?string {
            $valor = is_scalar($valor) ? trim(preg_replace('/\s+/u', ' ', (string) $valor)) : '';

            return $valor === '' ? null : $valor;
        };

        if (!empty($datos['manual'])) {
            $edificio = $texto($datos['edificio'] ?? null);

            return $edificio === null ? null : [
                'manual' => true,
                'edificio' => $edificio,
                'bloque' => $texto($datos['bloque'] ?? null),
                'nivel' => $texto($datos['nivel'] ?? null),
                'ambiente' => $texto($datos['ambiente'] ?? null),
            ];
        }

        $idEdificio = $texto($datos['idEdificio'] ?? null);

        if ($idEdificio === null) {
            return null;
        }

        $idBloque = $texto($datos['idBloqueArea'] ?? null);

        return [
            'idEdificio' => $idEdificio,
            'idBloqueArea' => $idBloque !== null && ctype_digit($idBloque) ? (int) $idBloque : null,
            'numeroNivel' => $texto($datos['numeroNivel'] ?? null),
            'idEspacio' => $texto($datos['idEspacio'] ?? null),
        ];
    }

    /**
     * Para no guardar dos veces la misma area. Las escritas a mano se
     * comparan por sus nombres, sin importar mayusculas.
     */
    private static function claveArea(array $claves): string
    {
        if (isset($claves['manual'])) {
            return 'manual|' . mb_strtolower(implode('|', [$claves['edificio'], $claves['bloque'] ?? '', $claves['nivel'] ?? '', $claves['ambiente'] ?? '']), 'UTF-8');
        }

        return implode('|', [$claves['idEdificio'], $claves['idBloqueArea'] ?? '', $claves['numeroNivel'] ?? '', $claves['idEspacio'] ?? '']);
    }

    /**
     * El error de un area escrita a mano, o null si esta bien. Solo se
     * revisa el largo: el resto es texto libre.
     */
    private static function errorAreaManual(array $claves): ?string
    {
        foreach (self::LARGO_AREA_MANUAL as $campo => [$maximo, $nombre]) {
            if ($claves[$campo] !== null && mb_strlen($claves[$campo], 'UTF-8') > $maximo) {
                return "En un área escrita a mano, el $nombre tiene más de $maximo caracteres.";
            }
        }

        return null;
    }
}
