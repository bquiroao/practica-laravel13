<?php

declare(strict_types=1);

namespace NotasSoap\Presentacion;

use NotasSoap\Contratos\AutenticadorToken;
use NotasSoap\Dominio\Excepciones\AccesoDenegado;
use NotasSoap\Dominio\Excepciones\DiagnosticoInvalido;
use NotasSoap\Dominio\Excepciones\ErrorPersistencia;
use NotasSoap\Dominio\Excepciones\ExpedienteNoDisponible;
use NotasSoap\Dominio\Excepciones\NotaFirmada;
use NotasSoap\Dominio\Excepciones\NotaIncompleta;
use NotasSoap\Dominio\SolicitudRegistroNota;
use NotasSoap\Dominio\UsuarioAutenticado;
use NotasSoap\Servicios\FirmarNota;
use NotasSoap\Servicios\RegistrarNotaConDiagnostico;

/**
 * Traduce HTTP a casos de uso y excepciones a códigos HTTP.
 * Única razón de cambio: el contrato de la API. No tiene reglas de negocio ni SQL.
 *
 * Petición simulada: ['headers' => [...], 'body' => [...]]
 * Respuesta: ['status' => int, 'body' => array]
 */
final class NotaSoapController
{
    public function __construct(
        private readonly AutenticadorToken $autenticador,
        private readonly RegistrarNotaConDiagnostico $registrar,
        private readonly FirmarNota $firmar,
    ) {
    }

    /** @param array{headers: array<string, string>, body: array<string, mixed>} $peticion */
    public function registrar(array $peticion): array
    {
        return $this->conUsuario($peticion, function (UsuarioAutenticado $usuario, string $tenant) use ($peticion): array {
            $b = $peticion['body'];
            $resultado = $this->registrar->ejecutar($usuario, new SolicitudRegistroNota(
                $tenant,
                (string) ($b['medical_record_id'] ?? ''),
                (string) ($b['care_episode_id'] ?? ''),
                (string) ($b['subjetivo'] ?? ''),
                (string) ($b['objetivo'] ?? ''),
                (string) ($b['analisis'] ?? ''),
                (string) ($b['plan'] ?? ''),
                (string) ($b['diagnostico']['code'] ?? ''),
                (string) ($b['diagnostico']['description'] ?? ''),
                (string) ($b['diagnostico']['type'] ?? ''),
            ));

            return ['status' => 201, 'body' => [
                'clinical_note_id' => $resultado['nota']->id,
                'author_user_ref_id' => $resultado['nota']->autorId,
                'status' => $resultado['nota']->estado,
                'version_no' => $resultado['nota']->version,
                'diagnosis_id' => $resultado['diagnostico']->id,
            ]];
        });
    }

    /** @param array{headers: array<string, string>, body: array<string, mixed>} $peticion */
    public function firmar(array $peticion, string $notaId): array
    {
        return $this->conUsuario($peticion, function (UsuarioAutenticado $usuario) use ($notaId): array {
            $nota = $this->firmar->ejecutar($usuario, $notaId);

            return ['status' => 200, 'body' => ['clinical_note_id' => $nota->id, 'status' => $nota->estado]];
        });
    }

    private function conUsuario(array $peticion, callable $accion): array
    {
        $tenant = $peticion['headers']['X-Tenant-ID'] ?? '';
        if ($tenant === '') {
            return $this->error(400, 'tenant_requerido', 'Falta la cabecera X-Tenant-ID.');
        }
        $token = preg_replace('/^Bearer\s+/', '', $peticion['headers']['Authorization'] ?? '');
        $usuario = $token === '' ? null : $this->autenticador->usuarioDesdeToken((string) $token);
        if ($usuario === null) {
            return $this->error(401, 'token_invalido', 'Inicie sesión de nuevo.');
        }

        try {
            return $accion($usuario, $tenant);
        } catch (AccesoDenegado $e) {
            return $this->error(403, 'acceso_denegado', $e->getMessage());
        } catch (ExpedienteNoDisponible $e) {
            return $this->error(409, 'expediente_no_disponible', $e->getMessage());
        } catch (NotaIncompleta $e) {
            return $this->error(422, 'nota_incompleta', $e->getMessage());
        } catch (DiagnosticoInvalido $e) {
            return $this->error(422, 'diagnostico_invalido', $e->getMessage());
        } catch (NotaFirmada $e) {
            return $this->error(409, 'nota_firmada', $e->getMessage());
        } catch (ErrorPersistencia) {
            return $this->error(500, 'error_persistencia', 'No se pudo guardar. Intente de nuevo.');
        }
    }

    private function error(int $status, string $codigo, string $mensaje): array
    {
        return ['status' => $status, 'body' => ['error' => ['code' => $codigo, 'message' => $mensaje]]];
    }
}
