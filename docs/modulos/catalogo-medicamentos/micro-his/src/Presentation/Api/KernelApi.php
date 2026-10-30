<?php
declare(strict_types=1);

namespace Catalogo\Presentation\Api;

use Catalogo\Domain\Errores\ErrorDominio;
use Catalogo\Domain\Errores\ErrorNoEncontrado;
use Catalogo\Domain\Errores\ErrorPersistencia;
use Catalogo\Domain\Errores\ErrorReglaNegocio;
use Catalogo\Domain\Errores\ErrorValidacion;
use Catalogo\Presentation\Web\Peticion;
use Catalogo\Presentation\Web\Respuesta;
use Catalogo\Presentation\Web\Router;

/**
 * Entrada de la API REST: X-Request-Id, autenticación, enrutamiento, formato único de errores y
 * una línea de registro por petición (sin datos clínicos ni tokens).
 */
final class KernelApi
{
    /** @param array<string, object> $app @param callable(string): void $registrar */
    public function __construct(private readonly array $app, private readonly Autenticador $auth, private $registrar = null)
    {
    }

    public function manejar(Peticion $p): Respuesta
    {
        $inicio = hrtime(true);
        $id = $this->requestId($p->cabeceras['x-request-id'] ?? null);
        $quien = null;
        try {
            $quien = $this->auth->identificar($p->cabeceras['authorization'] ?? null)
                ?? throw new ErrorHttp(401, 'NO_AUTENTICADO', 'Falta un token válido (Authorization: Bearer ...)');
            $respuesta = self::rutas(new ApiMedicamentos($this->app, $quien))->despachar($p, fn (int $estado, string $mensaje, array $permitidos = []) => throw new ErrorHttp(
                $estado,
                $estado === 405 ? 'METODO_NO_PERMITIDO' : 'RUTA_NO_ENCONTRADA',
                $mensaje,
                $permitidos === [] ? [] : ['Allow' => implode(', ', $permitidos)],
            ));
        } catch (ErrorHttp $e) {
            $respuesta = RespuestaJson::error($e->estado, $e->codigo, $e->getMessage(), [], $id);
            foreach ($e->cabeceras as $nombre => $valor) {
                $respuesta = $respuesta->conCabecera($nombre, $valor);
            }
        } catch (ErrorDominio $e) {
            $estado = match (true) {
                $e instanceof ErrorValidacion => 422,
                $e instanceof ErrorReglaNegocio => 409,
                $e instanceof ErrorNoEncontrado => 404,
                $e instanceof ErrorPersistencia => 503,
                default => 500,
            };
            $respuesta = RespuestaJson::error($estado, $e->codigo, $e->getMessage(), $e->detalles, $id);
            if ($e instanceof ErrorPersistencia) {
                $this->registro(['request_id' => $id, 'nivel' => 'error', 'causa' => $e->getPrevious()?->getMessage()]);
                $respuesta = $respuesta->conCabecera('Retry-After', '5');
            }
        } catch (\Throwable $e) {
            $this->registro(['request_id' => $id, 'nivel' => 'error', 'excepcion' => $e::class, 'detalle' => $e->getMessage()]);
            $respuesta = RespuestaJson::error(500, 'INTERNO', 'Error inesperado. Informe este request_id.', [], $id);
        }
        $this->registro([
            'request_id' => $id, 'metodo' => $p->metodo, 'ruta' => $p->ruta, 'estado' => $respuesta->estado,
            'ms' => round((hrtime(true) - $inicio) / 1e6, 1), 'usuario' => $quien?->usuario, 'hospital' => $quien?->hospitalId,
        ]);
        return $respuesta->conCabecera('X-Request-Id', $id);
    }

    private static function rutas(ApiMedicamentos $c): Router
    {
        $r = new Router();
        $r->agregar('GET', '/api/v1/opciones', $c->opciones(...));
        $r->agregar('GET', '/api/v1/medicamentos', $c->buscar(...));
        $r->agregar('POST', '/api/v1/medicamentos', $c->registrar(...));
        $r->agregar('GET', '/api/v1/medicamentos/{codigo}', $c->consultar(...));
        $r->agregar('DELETE', '/api/v1/medicamentos/{codigo}', $c->eliminar(...));
        $r->agregar('GET', '/api/v1/medicamentos/{codigo}/versiones', $c->historial(...));
        $r->agregar('POST', '/api/v1/medicamentos/{codigo}/versiones', $c->registrarVersion(...));
        $r->agregar('POST', '/api/v1/medicamentos/{codigo}/baja', $c->baja(...));
        return $r;
    }

    /** Reutiliza el X-Request-Id del cliente si es seguro; si no, genera uno. */
    private function requestId(?string $recibido): string
    {
        if ($recibido !== null && preg_match('/^[A-Za-z0-9-]{8,64}$/', $recibido) === 1) {
            return $recibido;
        }
        return bin2hex(random_bytes(8));
    }

    /** @param array<string, mixed> $datos */
    private function registro(array $datos): void
    {
        $linea = json_encode(['servicio' => 'catalogo-medicamentos'] + $datos, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
        ($this->registrar ?? 'error_log')((string) $linea);
    }
}
