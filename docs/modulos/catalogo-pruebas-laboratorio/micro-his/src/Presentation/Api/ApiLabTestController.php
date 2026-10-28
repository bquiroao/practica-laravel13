<?php
declare(strict_types=1);

namespace Lab\Presentation\Api;

use Lab\Application\ConsultarPruebasVigentes;
use Lab\Application\RegistrarPrueba;
use Lab\Application\VerDetallePrueba;
use Lab\Domain\DatosInvalidos;
use Lab\Domain\ErrorPersistencia;
use Lab\Domain\LabTest;
use Lab\Domain\PruebaNoVigente;
use Lab\Domain\VigenciaSuperpuesta;

/** API REST /api/v1/laboratorio/pruebas: misma lógica que el MVC, salida JSON. */
final class ApiLabTestController
{
    public function __construct(
        private readonly RegistrarPrueba $registrar,
        private readonly ConsultarPruebasVigentes $consultar,
        private readonly VerDetallePrueba $detalle,
    ) {
    }

    public function despachar(string $metodo, string $ruta, array $cabeceras, array $query, string $cuerpo): RespuestaJson
    {
        $requestId = $cabeceras['x-request-id'] ?? bin2hex(random_bytes(8));
        try {
            [$estado, $datos] = $this->enrutar($metodo, rtrim($ruta, '/'), $cabeceras['authorization'] ?? null, $query, $cuerpo);
            $respuesta = new RespuestaJson($estado, ['data' => $datos, 'request_id' => $requestId], $requestId);
        } catch (ErrorApi $e) {
            $respuesta = self::error($e->estado, $e->codigo, $e->getMessage(), $e->detalle, $requestId);
        } catch (DatosInvalidos $e) {
            $respuesta = self::error(422, 'DATOS_INVALIDOS', 'Revise los campos marcados.', ['campos' => $e->errores], $requestId);
        } catch (VigenciaSuperpuesta $e) {
            $respuesta = self::error(409, 'VIGENCIA_SUPERPUESTA', $e->getMessage(), [], $requestId);
        } catch (PruebaNoVigente $e) {
            $vig = $e->vigencia ? ['vigencia' => self::vigencia($e->vigencia)] : [];
            $respuesta = self::error(404, 'PRUEBA_NO_VIGENTE', $e->getMessage(), $vig, $requestId);
        } catch (ErrorPersistencia $e) {
            $respuesta = self::error(500, 'ERROR_PERSISTENCIA', $e->getMessage(), [], $requestId);
        }
        // Observabilidad: una línea por petición, sin datos clínicos.
        error_log(sprintf('[catalogo-lab] %s %s %s -> %d', $requestId, $metodo, $ruta, $respuesta->estado));
        return $respuesta;
    }

    private function enrutar(string $metodo, string $ruta, ?string $auth, array $query, string $cuerpo): array
    {
        if ($metodo === 'GET' && $ruta === '/api/v1/laboratorio/pruebas') {
            $id = Autorizacion::exigir($auth, Autorizacion::CONSULTAR);
            return [200, array_map([self::class, 'aJson'], $this->consultar->ejecutar($id['tenant'], $query))];
        }
        if ($metodo === 'GET' && preg_match('#^/api/v1/laboratorio/pruebas/([A-Z]{3}-\d{3})$#', $ruta, $m) === 1) {
            $id = Autorizacion::exigir($auth, Autorizacion::CONSULTAR);
            return [200, self::aJson($this->detalle->ejecutar($id['tenant'], $m[1], $query['fecha'] ?? ''))];
        }
        if ($metodo === 'POST' && $ruta === '/api/v1/laboratorio/pruebas') {
            $id = Autorizacion::exigir($auth, Autorizacion::GESTIONAR);
            $datos = json_decode($cuerpo, true);
            if (!is_array($datos)) {
                throw new ErrorApi(400, 'JSON_INVALIDO', 'El cuerpo debe ser un objeto JSON.');
            }
            return [201, self::aJson($this->registrar->ejecutar($datos, $id['tenant'], $id['usuario']))];
        }
        throw new ErrorApi(404, 'RUTA_NO_ENCONTRADA', 'La ruta no existe.');
    }

    public static function aJson(LabTest $p): array
    {
        $r = $p->requisito;
        return [
            'id' => $p->id, 'codigo' => $p->codigo, 'version' => $p->version, 'nombre' => $p->nombre,
            'seccion' => $p->seccion, 'tiempo_respuesta_horas' => $p->tiempoRespuestaHoras,
            'requisitos_muestra' => [
                'tipo_muestra' => $r->tipoMuestra, 'contenedor' => $r->contenedor, 'volumen_minimo_ml' => $r->volumenMinimoMl,
                'horas_ayuno' => $r->horasAyuno, 'instrucciones_preparacion' => $r->instruccionesPreparacion,
                'conservacion' => $r->conservacion, 'estabilidad_horas' => $r->estabilidadHoras,
            ],
            'vigencia' => self::vigencia($p->vigencia),
        ];
    }

    private static function vigencia(\Lab\Domain\Vigencia $v): array
    {
        return ['desde' => $v->desde->format('Y-m-d'), 'hasta' => $v->hasta?->format('Y-m-d')];
    }

    private static function error(int $estado, string $codigo, string $mensaje, array $detalle, string $requestId): RespuestaJson
    {
        return new RespuestaJson($estado, ['error' => ['codigo' => $codigo, 'mensaje' => $mensaje] + $detalle, 'request_id' => $requestId], $requestId);
    }
}
