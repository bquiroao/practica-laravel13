<?php

declare(strict_types=1);

namespace NotasSoap\Presentacion\Http;

use NotasSoap\Dominio\Excepciones\AccesoDenegado;
use NotasSoap\Dominio\Excepciones\DiagnosticoInvalido;
use NotasSoap\Dominio\Excepciones\ErrorPersistencia;
use NotasSoap\Dominio\Excepciones\ExpedienteNoDisponible;
use NotasSoap\Dominio\Excepciones\NotaFirmada;
use NotasSoap\Dominio\Excepciones\NotaIncompleta;
use NotasSoap\Dominio\Excepciones\NotaNoEncontrada;

/**
 * Front controller: ruta → tenant (400) → token (401) → acción → errores a HTTP.
 * Rutas:
 *   POST /api/v1/notas-soap              registrar nota + diagnóstico
 *   GET  /api/v1/notas-soap/{id}         consultar
 *   POST /api/v1/notas-soap/{id}/firma   firmar
 */
final class Kernel
{
    public function __construct(
        private readonly Autenticador $autenticador,
        private readonly NotasSoapController $controller,
    ) {
    }

    public function manejar(Solicitud $s): Respuesta
    {
        $ruta = $this->resolver($s);
        if ($ruta instanceof Respuesta) {
            return $ruta;
        }

        $hospital = $s->cabecera('X-Tenant-ID');
        if ($hospital === '') {
            return Respuesta::error(400, 'tenant_requerido', 'Falta la cabecera X-Tenant-ID.');
        }
        $token = (string) preg_replace('/^Bearer\s+/i', '', $s->cabecera('Authorization'));
        $quien = $token === '' ? null : $this->autenticador->identidad($token);
        if ($quien === null) {
            return Respuesta::error(401, 'token_invalido', 'Inicie sesión de nuevo.');
        }

        try {
            return $ruta($quien, $hospital);
        } catch (AccesoDenegado $e) {
            return Respuesta::error(403, 'acceso_denegado', $e->getMessage());
        } catch (NotaNoEncontrada $e) {
            return Respuesta::error(404, 'nota_no_encontrada', $e->getMessage());
        } catch (ExpedienteNoDisponible $e) {
            return Respuesta::error(409, 'expediente_no_disponible', $e->getMessage());
        } catch (NotaFirmada $e) {
            return Respuesta::error(409, 'nota_firmada', $e->getMessage());
        } catch (NotaIncompleta $e) {
            return Respuesta::error(422, 'nota_incompleta', $e->getMessage());
        } catch (DiagnosticoInvalido $e) {
            return Respuesta::error(422, 'diagnostico_invalido', $e->getMessage());
        } catch (ErrorPersistencia) {
            return Respuesta::error(500, 'error_persistencia', 'No se pudo guardar. Intente de nuevo.');
        } catch (\Throwable) {
            return Respuesta::error(500, 'error_interno', 'Ocurrió un error inesperado.');
        }
    }

    /** @return callable(Identidad, string): Respuesta|Respuesta */
    private function resolver(Solicitud $s): callable|Respuesta
    {
        if ($s->ruta === '/api/v1/notas-soap') {
            return $s->metodo === 'POST'
                ? fn (Identidad $q, string $h): Respuesta => $this->controller->registrar($s, $q, $h)
                : Respuesta::error(405, 'metodo_no_permitido', 'Use POST.');
        }
        if (preg_match('#^/api/v1/notas-soap/([A-Za-z0-9-]+)/firma$#', $s->ruta, $m) === 1) {
            return $s->metodo === 'POST'
                ? fn (Identidad $q, string $h): Respuesta => $this->controller->firmar($q, $h, $m[1])
                : Respuesta::error(405, 'metodo_no_permitido', 'Use POST.');
        }
        if (preg_match('#^/api/v1/notas-soap/([A-Za-z0-9-]+)$#', $s->ruta, $m) === 1) {
            return $s->metodo === 'GET'
                ? fn (Identidad $q, string $h): Respuesta => $this->controller->consultar($q, $h, $m[1])
                : Respuesta::error(405, 'metodo_no_permitido', 'Use GET.');
        }

        return Respuesta::error(404, 'ruta_no_encontrada', 'La ruta no existe.');
    }
}
