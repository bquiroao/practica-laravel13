<?php

declare(strict_types=1);

namespace NotasSoap\Presentacion\Http;

use NotasSoap\Aplicacion\Excepciones\ClaveIdempotenciaReutilizada;
use NotasSoap\Dominio\Excepciones\AccesoDenegado;
use NotasSoap\Dominio\Excepciones\DiagnosticoInvalido;
use NotasSoap\Dominio\Excepciones\ErrorPersistencia;
use NotasSoap\Dominio\Excepciones\ExpedienteNoDisponible;
use NotasSoap\Dominio\Excepciones\MotivoCorreccionInsuficiente;
use NotasSoap\Dominio\Excepciones\NotaFirmada;
use NotasSoap\Dominio\Excepciones\NotaIncompleta;
use NotasSoap\Dominio\Excepciones\NotaNoEncontrada;
use NotasSoap\Dominio\Excepciones\NotaNoFirmada;
use NotasSoap\Dominio\Excepciones\NotaYaCorregida;
use NotasSoap\Presentacion\Entrada\CuerpoInvalido;
use NotasSoap\Presentacion\Http\Autorizacion\PoliticaPermisos;
use NotasSoap\Presentacion\Http\Observabilidad\RegistroAcceso;
use NotasSoap\Presentacion\Vistas\SelectorVista;

/**
 * Front controller del servidor:
 *   X-Request-ID → ruta (404/405) → X-Tenant-ID (400) → token (401) → permiso de la ruta (403)
 *   → acción del controlador → errores a HTTP → registro de acceso.
 * Toda respuesta lleva X-Request-ID; los errores salen con la vista que pidió el cliente (JSON o HTML).
 */
final class Kernel
{
    private const FORMATO_REQUEST_ID = '/^[A-Za-z0-9-]{8,64}$/';

    /** @param list<Ruta> $rutas */
    public function __construct(
        private readonly Autenticador $autenticador,
        private readonly array $rutas,
        private readonly SelectorVista $vistas,
        private readonly PoliticaPermisos $permisos,
        private readonly RegistroAcceso $registroAcceso,
    ) {
    }

    public function manejar(Solicitud $s): Respuesta
    {
        $inicio = hrtime(true);
        $recibido = $s->cabecera('X-Request-ID');
        $requestId = preg_match(self::FORMATO_REQUEST_ID, $recibido) === 1 ? $recibido : self::nuevoRequestId();
        $quien = null;

        $respuesta = $this->atender($s, $quien)->conCabecera('X-Request-ID', $requestId);

        try {
            $this->registroAcceso->registrar([
                'request_id' => $requestId,
                'metodo' => $s->metodo,
                'ruta' => $s->ruta,
                'estado' => $respuesta->estado,
                'duracion_ms' => round((hrtime(true) - $inicio) / 1e6, 3),
                'usuario' => $quien?->usuarioId,
                'tenant' => $s->cabecera('X-Tenant-ID') === '' ? null : $s->cabecera('X-Tenant-ID'),
                'repetida' => $respuesta->cabecera('Idempotent-Replayed') === 'true',
            ]);
        } catch (\Throwable) {
            // Si el registro de acceso falla, la respuesta al cliente no cambia (OBS-03).
        }

        return $respuesta;
    }

    private function atender(Solicitud $s, ?Identidad &$quien): Respuesta
    {
        $vista = $this->vistas->para($s);
        [$ruta, $parametros, $metodosPermitidos] = $this->resolver($s);
        if ($ruta === null) {
            return $metodosPermitidos === []
                ? $vista->error(404, 'ruta_no_encontrada', 'La ruta no existe.')
                : $vista->error(405, 'metodo_no_permitido', 'Use ' . implode(' o ', $metodosPermitidos) . '.')->conCabecera('Allow', implode(', ', $metodosPermitidos));
        }

        $hospital = $s->cabecera('X-Tenant-ID');
        if ($hospital === '') {
            return $vista->error(400, 'tenant_requerido', 'Falta la cabecera X-Tenant-ID.');
        }
        $token = (string) preg_replace('/^Bearer\s+/i', '', $s->cabecera('Authorization'));
        $quien = $token === '' ? null : $this->autenticador->identidad($token);
        if ($quien === null) {
            return $vista->error(401, 'token_invalido', 'Inicie sesión de nuevo.');
        }
        if (!$this->permisos->permite($quien, $ruta->permiso)) {
            return $vista->error(403, 'acceso_denegado', 'No tiene permiso para esta operación.');
        }

        try {
            return ($ruta->accion)($s, $quien, $hospital, $parametros);
        } catch (CuerpoInvalido $e) {
            return $vista->error(400, 'cuerpo_invalido', $e->getMessage());
        } catch (AccesoDenegado $e) {
            return $vista->error(403, 'acceso_denegado', $e->getMessage());
        } catch (NotaNoEncontrada $e) {
            return $vista->error(404, 'nota_no_encontrada', $e->getMessage());
        } catch (ExpedienteNoDisponible $e) {
            return $vista->error(409, 'expediente_no_disponible', $e->getMessage());
        } catch (NotaFirmada $e) {
            return $vista->error(409, 'nota_firmada', $e->getMessage());
        } catch (NotaNoFirmada $e) {
            return $vista->error(409, 'nota_no_firmada', $e->getMessage());
        } catch (NotaYaCorregida $e) {
            return $vista->error(409, 'nota_ya_corregida', $e->getMessage());
        } catch (MotivoCorreccionInsuficiente $e) {
            return $vista->error(422, 'motivo_insuficiente', $e->getMessage());
        } catch (NotaIncompleta $e) {
            return $vista->error(422, 'nota_incompleta', $e->getMessage());
        } catch (DiagnosticoInvalido $e) {
            return $vista->error(422, 'diagnostico_invalido', $e->getMessage());
        } catch (ClaveIdempotenciaReutilizada $e) {
            return $vista->error(422, 'idempotency_key_reutilizada', $e->getMessage());
        } catch (ErrorPersistencia) {
            return $vista->error(500, 'error_persistencia', 'No se pudo guardar. Intente de nuevo.');
        } catch (\Throwable) {
            return $vista->error(500, 'error_interno', 'Ocurrió un error inesperado.');
        }
    }

    /** @return array{0: ?Ruta, 1: array<string, string>, 2: list<string>} */
    private function resolver(Solicitud $s): array
    {
        $metodos = [];
        foreach ($this->rutas as $ruta) {
            $parametros = $ruta->coincide($s->ruta);
            if ($parametros === null) {
                continue;
            }
            if ($ruta->metodo === $s->metodo) {
                return [$ruta, $parametros, []];
            }
            $metodos[] = $ruta->metodo;
        }

        return [null, [], $metodos];
    }

    /** UUID v4 para las peticiones que no traen un X-Request-ID válido. */
    private static function nuevoRequestId(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);

        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }

    /** @return list<Ruta> para las pruebas de contrato (API-01) */
    public function rutas(): array
    {
        return $this->rutas;
    }
}
