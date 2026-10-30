<?php
declare(strict_types=1);

namespace Admision\Presentation\Api;

use Admision\Application\ContextoUsuario;
use Admision\Application\Excepciones\AccesoDenegado;
use Admision\Application\Puertos\GeneradorId;
use Admision\Application\ResultadoAdmision;
use Admision\Application\SolicitudAdmision;
use Admision\Domain\Excepciones\CamaNoElegible;
use Admision\Domain\Excepciones\ErrorDeDominio;
use Admision\Domain\Excepciones\ErrorPersistencia;
use Admision\Presentation\MapaErrores;
use Admision\Presentation\Servicios;
use Admision\Presentation\Web\Peticion;
use Admision\Presentation\Web\Respuesta;
use Admision\Presentation\Web\Router;

/** API REST JSON v1 sobre los MISMOS casos de uso que la consola y la web. Contrato: semana-05/contrato-api.md. */
final class ApiKernel
{
    private const REINTENTABLES = ['CONFLICTO_CONCURRENCIA', 'ERROR_PERSISTENCIA'];
    private readonly Router $router;
    private string $solicitudId = '';

    /** @param \Closure(array<string, mixed>): void $registrar una línea de registro por solicitud (observabilidad) */
    public function __construct(
        private readonly Servicios $servicios,
        private readonly AutenticadorTokens $autenticador,
        private readonly GeneradorId $ids,
        private readonly \Closure $registrar,
    ) {
        $this->router = new Router();
        $this->router->agregar('GET', '/api/v1/camas-disponibles', fn (Peticion $p) => $this->camas($p));
        $this->router->agregar('POST', '/api/v1/admisiones', fn (Peticion $p) => $this->crear($p));
        $this->router->agregar('GET', '/api/v1/admisiones/{id}', fn (Peticion $p, string $id) => $this->json(200, [
            'datos' => $this->aJson($this->servicios->consultarAdmision->ejecutar($id, $this->usuario($p))),
        ]));
        $this->router->agregar('GET', '/api/v1/admisiones/{id}/eventos', fn (Peticion $p, string $id) => $this->json(200, [
            'datos' => $this->servicios->trazabilidad->ejecutar($id, $this->usuario($p)),
        ]));
    }

    public function manejar(Peticion $p): Respuesta
    {
        $inicio = hrtime(true);
        $recibido = $p->cabeceras['x-request-id'] ?? '';
        $this->solicitudId = preg_match('/^[A-Za-z0-9._-]{8,64}$/', $recibido) === 1 ? $recibido : 'req-' . $this->ids->nuevo();
        try {
            $r = $this->router->despachar($p);
            if ($r->estado === 404 || $r->estado === 405) { // rutas desconocidas también responden en JSON
                $r = $this->error($r->estado === 404 ? 'RECURSO_NO_ENCONTRADO' : 'METODO_NO_PERMITIDO', 'Ruta o método no disponible en la API v1.');
            }
        } catch (ErrorDeDominio | AccesoDenegado | ErrorPersistencia | ErrorApi $e) {
            $detalles = $e instanceof CamaNoElegible ? ['motivos' => $e->motivos] : ($e instanceof ErrorApi ? $e->detalles : []);
            $r = $this->error($e->codigo(), $e->getMessage(), $detalles);
        } catch (\Throwable $e) {
            $r = $this->error('ERROR_INTERNO', 'Error inesperado. Informe el request_id.'); // sin detalles técnicos al cliente
        }
        // Observabilidad (RNF-05): una línea por solicitud, sin datos clínicos ni del paciente (RNF-03).
        ($this->registrar)([
            'request_id' => $this->solicitudId, 'metodo' => $p->metodo, 'ruta' => $p->ruta,
            'estado' => $r->estado, 'duracion_ms' => (int) ((hrtime(true) - $inicio) / 1_000_000),
        ]);
        return $r->conCabecera('X-Request-Id', $this->solicitudId);
    }

    private function camas(Peticion $p): Respuesta
    {
        $usuario = $this->usuario($p); // primero autenticar: sin token siempre es 401
        $aislamiento = $p->consulta['requiere_aislamiento'] ?? 'false';
        if (!in_array($aislamiento, ['true', 'false'], true)) {
            throw new ErrorApi('SOLICITUD_INVALIDA', 'requiere_aislamiento debe ser true o false.', ['campos' => ['requiere_aislamiento']]);
        }
        $camas = $this->servicios->camasDisponibles->ejecutar(
            (string) ($p->consulta['servicio'] ?? ''), (string) ($p->consulta['tipo_cama'] ?? ''), $aislamiento === 'true', $usuario,
        );
        return $this->json(200, ['datos' => array_map(fn ($c) => [
            'id' => $c->id, 'codigo' => $c->codigo, 'servicio' => $c->servicio, 'tipo_cama' => $c->tipo->value, 'aislamiento' => $c->aislamiento,
        ], $camas), 'total' => count($camas)]);
    }

    private function crear(Peticion $p): Respuesta
    {
        $usuario = $this->usuario($p);
        try {
            $b = json_decode($p->cuerpo, true, 8, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            throw new ErrorApi('SOLICITUD_INVALIDA', 'El cuerpo debe ser JSON válido.');
        }
        if (!is_array($b) || array_is_list($b)) {
            throw new ErrorApi('SOLICITUD_INVALIDA', 'El cuerpo debe ser un objeto JSON.');
        }
        if (array_key_exists('tenant_id', $b)) {
            throw new ErrorApi('SOLICITUD_INVALIDA', 'El hospital se toma del token; no envíe tenant_id.', ['campos' => ['tenant_id']]);
        }
        $faltan = array_values(array_filter(['codigo_paciente', 'servicio', 'tipo_cama', 'origen', 'cama_id'], fn ($c) => !is_string($b[$c] ?? null)));
        if (!is_bool($b['requiere_aislamiento'] ?? null)) {
            $faltan[] = 'requiere_aislamiento';
        }
        if ($faltan !== []) {
            throw new ErrorApi('SOLICITUD_INVALIDA', 'Faltan campos o tienen un tipo incorrecto.', ['campos' => $faltan]);
        }
        $r = $this->servicios->admitir->ejecutar(new SolicitudAdmision(
            $b['codigo_paciente'], $b['servicio'], $b['tipo_cama'], $b['requiere_aislamiento'], $b['origen'], $b['cama_id'],
        ), $usuario);
        return $this->json(201, ['datos' => $this->aJson($r)])->conCabecera('Location', '/api/v1/admisiones/' . $r->admisionId);
    }

    private function usuario(Peticion $p): ContextoUsuario
    {
        return $this->autenticador->contexto($p->cabeceras['authorization'] ?? '', $this->solicitudId);
    }

    private function aJson(ResultadoAdmision $r): array
    {
        return [
            'id' => $r->admisionId, 'codigo_paciente' => $r->codigoPaciente, 'estado' => $r->estado,
            'cama' => ['id' => $r->camaId, 'codigo' => $r->camaCodigo], 'servicio' => $r->servicio,
            'origen' => $r->origen, 'creada_por' => $r->creadaPor, 'creada_en' => $r->creadaEn,
        ];
    }

    private function error(string $codigo, string $mensaje, array $detalles = []): Respuesta
    {
        $error = ['codigo' => $codigo, 'mensaje' => $mensaje, 'reintentable' => in_array($codigo, self::REINTENTABLES, true), 'request_id' => $this->solicitudId];
        $r = $this->json(MapaErrores::estado($codigo), ['error' => $detalles === [] ? $error : [...$error, 'detalles' => $detalles]]);
        return $codigo === 'NO_AUTENTICADO' ? $r->conCabecera('WWW-Authenticate', 'Bearer') : $r;
    }

    private function json(int $estado, array $cuerpo): Respuesta
    {
        return new Respuesta($estado, json_encode($cuerpo, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR), [
            'Content-Type' => 'application/json; charset=utf-8',
        ]);
    }
}
