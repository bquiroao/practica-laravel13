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
use NotasSoap\Presentacion\Controladores\NotasSoapController;
use NotasSoap\Presentacion\Entrada\CuerpoInvalido;
use NotasSoap\Presentacion\Vistas\SelectorVista;

/**
 * Front controller: ruta → tenant (400) → token (401) → acción del controlador → error a HTTP.
 * Los errores también se muestran con la vista que pidió el cliente (JSON o HTML).
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
        private readonly SelectorVista $vistas,
    ) {
    }

    public function manejar(Solicitud $s): Respuesta
    {
        $vista = $this->vistas->para($s);
        $accion = $this->resolver($s);
        if (is_array($accion)) {
            return $vista->error(...$accion);
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

        try {
            return $accion($quien, $hospital);
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
        } catch (NotaIncompleta $e) {
            return $vista->error(422, 'nota_incompleta', $e->getMessage());
        } catch (DiagnosticoInvalido $e) {
            return $vista->error(422, 'diagnostico_invalido', $e->getMessage());
        } catch (ErrorPersistencia) {
            return $vista->error(500, 'error_persistencia', 'No se pudo guardar. Intente de nuevo.');
        } catch (\Throwable) {
            return $vista->error(500, 'error_interno', 'Ocurrió un error inesperado.');
        }
    }

    /** @return callable(Identidad, string): Respuesta|array{0: int, 1: string, 2: string} */
    private function resolver(Solicitud $s): callable|array
    {
        if ($s->ruta === '/api/v1/notas-soap') {
            return $s->metodo === 'POST'
                ? fn (Identidad $q, string $h): Respuesta => $this->controller->registrar($s, $q, $h)
                : [405, 'metodo_no_permitido', 'Use POST.'];
        }
        if (preg_match('#^/api/v1/notas-soap/([A-Za-z0-9-]+)/firma$#', $s->ruta, $m) === 1) {
            return $s->metodo === 'POST'
                ? fn (Identidad $q, string $h): Respuesta => $this->controller->firmar($s, $q, $h, $m[1])
                : [405, 'metodo_no_permitido', 'Use POST.'];
        }
        if (preg_match('#^/api/v1/notas-soap/([A-Za-z0-9-]+)$#', $s->ruta, $m) === 1) {
            return $s->metodo === 'GET'
                ? fn (Identidad $q, string $h): Respuesta => $this->controller->consultar($s, $q, $h, $m[1])
                : [405, 'metodo_no_permitido', 'Use GET.'];
        }

        return [404, 'ruta_no_encontrada', 'La ruta no existe.'];
    }
}
