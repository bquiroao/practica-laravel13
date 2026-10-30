<?php

declare(strict_types=1);

namespace E2e\Aplicacion\CasosDeUso;

use E2e\Aplicacion\Puertos\PoliticaAcceso;
use E2e\Aplicacion\Puertos\RegistroAuditoria;
use E2e\Aplicacion\Puertos\Transaccion;
use E2e\Aplicacion\Resultados\Salida;
use E2e\Dominio\Excepciones\ErrorPersistencia;
use E2e\Dominio\Excepciones\ReglaRechazada;
use E2e\Dominio\Modelo\EscenarioE2E;
use E2e\Dominio\Puertos\RepositorioEscenarios;

/** CU-01: el Ingeniero de automatización QA registra un escenario E2E con datos sintéticos (R-01). */
final class RegistrarEscenario
{
    public const PERMISO = 'e2e.disenar';

    public function __construct(
        private readonly PoliticaAcceso $acceso,
        private readonly RepositorioEscenarios $escenarios,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
    ) {
    }

    /** @param array<string, mixed> $datos id, titulo, modulo, critico, requisitos[], datos */
    public function ejecutar(string $usuario, string $tenant, array $datos): Salida
    {
        if (!$this->acceso->permite($usuario, self::PERMISO, $tenant)) {
            return Salida::denegado();
        }
        try {
            $e = EscenarioE2E::registrar($tenant, (string) ($datos['id'] ?? ''), (string) ($datos['titulo'] ?? ''), (string) ($datos['modulo'] ?? ''),
                ($datos['critico'] ?? false) === true, is_array($datos['requisitos'] ?? null) ? $datos['requisitos'] : [], (string) ($datos['datos'] ?? ''), $usuario);
            if ($this->escenarios->porId($tenant, $e->id) !== null) {
                return new Salida(Salida::CONFLICTO, 'ESCENARIO_EXISTENTE');
            }
            $this->transaccion->ejecutar(function () use ($e, $usuario, $tenant): void {
                $this->escenarios->guardar($e);
                $this->auditoria->registrar('ESCENARIO_REGISTRADO', $tenant, $usuario, 'escenario:' . $e->id);
            });
        } catch (ReglaRechazada $ex) {
            return Salida::rechazo($ex);
        } catch (ErrorPersistencia) {
            return Salida::persistencia();
        }

        return new Salida(Salida::CREADO, 'ESCENARIO_REGISTRADO', ['id' => $e->id, 'critico' => $e->critico]);
    }
}
