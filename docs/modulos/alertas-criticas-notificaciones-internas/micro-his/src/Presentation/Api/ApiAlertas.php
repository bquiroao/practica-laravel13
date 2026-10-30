<?php
declare(strict_types=1);

namespace AlertasCriticas\Presentation\Api;

use AlertasCriticas\Domain\{AlertaNoEncontradaException, ErrorPersistenciaException, ReglaDominioException};
use AlertasCriticas\Presentation\Web\{Peticion, Respuesta, Router};

/**
 * API REST JSON v1 sobre los MISMOS casos de uso. Autentica, autoriza por rol, toma el tenant del token,
 * traduce excepciones a un formato único de error y propaga X-Request-Id. Sin SQL ni reglas de negocio.
 */
final class ApiAlertas
{
    private Router $router;

    /** @param array<string, object> $casos */
    public function __construct(private array $casos, private AutenticadorFicticio $autenticador)
    {
        $r = new Router();
        $r->agregar('POST', '/api/v1/alertas', fn ($p) => $this->registrar($p));
        $r->agregar('GET', '/api/v1/alertas', fn ($p) => $this->listar($p));
        $r->agregar('GET', '/api/v1/alertas/{id}', fn ($p, $id) => $this->detalle($p, $id));
        $r->agregar('POST', '/api/v1/alertas/{id}/acuse', fn ($p, $id) => $this->acusar($p, $id));
        $r->agregar('POST', '/api/v1/alertas/{id}/cierre', fn ($p, $id) => $this->cerrar($p, $id));
        $r->agregar('POST', '/api/v1/escalamientos', fn ($p) => $this->escalar($p));
        $this->router = $r;
    }

    public function atender(Peticion $p): Respuesta
    {
        $rid = self::requestId($p);
        $identidad = $this->autenticador->identificar($p->cabecera('Authorization'));
        if ($identidad === null) {
            $r = RespuestaJson::error(401, 'no_autenticado', 'Falta un token válido (Authorization: Bearer …).', $rid);
            return new Respuesta(401, $r->cuerpo, $r->cabeceras + ['WWW-Authenticate' => 'Bearer']);
        }
        $p = new Peticion($p->metodo, $p->ruta, $p->consulta, ['_identidad' => $identidad, '_rid' => $rid], [], $p->cabeceras, $p->cuerpo);
        try {
            $respuesta = $this->router->despachar($p);
        } catch (ReglaDominioException $e) {
            return RespuestaJson::error($e->regla === 'RN-07' ? 403 : 422, 'regla_incumplida', $e->getMessage(), $rid, $e->regla);
        } catch (AlertaNoEncontradaException) {
            return RespuestaJson::error(404, 'no_encontrada', 'La alerta no existe en su hospital.', $rid);
        } catch (ErrorPersistenciaException) {
            return RespuestaJson::error(503, 'persistencia_no_disponible', 'No se pudo acceder a los datos; reintente.', $rid);
        } catch (\JsonException) {
            return RespuestaJson::error(400, 'cuerpo_invalido', 'El cuerpo debe ser un objeto JSON válido.', $rid);
        }
        return match ($respuesta->estado) {
            404 => RespuestaJson::error(404, 'ruta_inexistente', 'La ruta no existe en la API v1.', $rid),
            405 => RespuestaJson::error(405, 'metodo_no_permitido', 'Método no permitido para esta ruta.', $rid),
            default => $respuesta,
        };
    }

    private function registrar(Peticion $p): Respuesta
    {
        [$id, $rid] = $this->exigir($p, 'registrar');
        if ($id instanceof Respuesta) { return $id; }
        $c = $this->json($p);
        $r = $this->casos['registrar']->ejecutar([
            'tenant' => $id->tenant, 'origen' => preg_replace('/^sistema:/', '', $id->usuario), // origen y tenant salen del token
            'codigo_paciente' => (string) ($c['codigo_paciente'] ?? ''), 'servicio' => (string) ($c['servicio'] ?? ''),
            'id_origen' => (string) ($c['id_origen'] ?? ''), 'prioridad' => (string) ($c['prioridad'] ?? ''),
            'descripcion' => (string) ($c['descripcion'] ?? '')]);
        $resp = RespuestaJson::exito($r['duplicada'] ? 200 : 201,
            ['alerta' => RespuestaJson::alerta($r['alerta']), 'duplicada' => $r['duplicada']], $rid);
        return new Respuesta($resp->estado, $resp->cuerpo, $resp->cabeceras + ['Location' => "/api/v1/alertas/{$r['alerta']->id}"]);
    }

    private function listar(Peticion $p): Respuesta
    {
        [$id, $rid] = $this->exigir($p, 'consultar');
        if ($id instanceof Respuesta) { return $id; }
        $alertas = array_map(fn ($a) => RespuestaJson::alerta($a), $this->casos['listar']->ejecutar($id->tenant));
        return RespuestaJson::exito(200, ['alertas' => $alertas, 'total' => count($alertas)], $rid);
    }

    private function detalle(Peticion $p, string $alerta): Respuesta
    {
        [$id, $rid] = $this->exigir($p, 'consultar');
        if ($id instanceof Respuesta) { return $id; }
        return RespuestaJson::exito(200, ['alerta' => RespuestaJson::alerta($this->casos['historial']->ejecutar($alerta, $id->tenant), true)], $rid);
    }

    private function acusar(Peticion $p, string $alerta): Respuesta
    {
        [$id, $rid] = $this->exigir($p, 'acusar');
        if ($id instanceof Respuesta) { return $id; }
        $r = $this->casos['acusar']->ejecutar($alerta, $id->usuario, $id->tenant);
        return RespuestaJson::exito(200, ['alerta' => RespuestaJson::alerta($r['alerta']), 'cambio' => $r['cambio']], $rid);
    }

    private function cerrar(Peticion $p, string $alerta): Respuesta
    {
        [$id, $rid] = $this->exigir($p, 'cerrar');
        if ($id instanceof Respuesta) { return $id; }
        $a = $this->casos['cerrar']->ejecutar($alerta, $id->usuario, (string) ($this->json($p)['nota'] ?? ''), $id->tenant);
        return RespuestaJson::exito(200, ['alerta' => RespuestaJson::alerta($a)], $rid);
    }

    private function escalar(Peticion $p): Respuesta
    {
        [$id, $rid] = $this->exigir($p, 'escalar');
        if ($id instanceof Respuesta) { return $id; }
        return RespuestaJson::exito(200, $this->casos['escalar']->ejecutar(), $rid);
    }

    /** @return array{0: Identidad|Respuesta, 1: string} */
    private function exigir(Peticion $p, string $accion): array
    {
        $id = $p->formulario['_identidad'];
        $rid = $p->formulario['_rid'];
        if (!Permisos::permite($id->rol, $accion)) {
            return [RespuestaJson::error(403, 'sin_permiso', "El rol «{$id->rol}» no puede {$accion}.", $rid), $rid];
        }
        return [$id, $rid];
    }

    private function json(Peticion $p): array
    {
        $datos = json_decode($p->cuerpo === '' ? '{}' : $p->cuerpo, true, 16, JSON_THROW_ON_ERROR);
        if (!is_array($datos)) {
            throw new \JsonException('No es un objeto');
        }
        return $datos;
    }

    private static function requestId(Peticion $p): string
    {
        $entrante = $p->cabecera('X-Request-Id');
        return preg_match('/^[A-Za-z0-9-]{8,64}$/', $entrante) ? $entrante : bin2hex(random_bytes(8));
    }
}
