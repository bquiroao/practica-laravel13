<?php

declare(strict_types=1);

namespace Qa\Aplicacion\CasosDeUso;

use Qa\Aplicacion\Puertos\PoliticaAcceso;
use Qa\Aplicacion\Puertos\RegistroAuditoria;
use Qa\Aplicacion\Puertos\Transaccion;
use Qa\Aplicacion\Resultados\Salida;
use Qa\Dominio\Excepciones\ErrorPersistencia;
use Qa\Dominio\Excepciones\ReglaRechazada;
use Qa\Dominio\Puertos\RepositorioCasos;

/** CU-02: el Líder QA aprueba un caso que no escribió (R-02). Una versión aprobada ya no cambia. */
final class AprobarCaso
{
    public const PERMISO = 'qa.aprobar';

    public function __construct(
        private readonly PoliticaAcceso $acceso,
        private readonly RepositorioCasos $casos,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
    ) {
    }

    public function ejecutar(string $usuario, string $tenant, string $casoId): Salida
    {
        if (!$this->acceso->permite($usuario, self::PERMISO, $tenant)) {
            return Salida::denegado();
        }
        try {
            $caso = $this->casos->porId($tenant, $casoId);
            if ($caso === null) {
                return Salida::noEncontrado();
            }
            $aprobado = $caso->aprobar($usuario);
            $this->transaccion->ejecutar(function () use ($aprobado, $usuario, $tenant): void {
                $this->casos->aprobar($aprobado);
                $this->auditoria->registrar('CASO_APROBADO', $tenant, $usuario, "caso:{$aprobado->id}@v{$aprobado->version}");
            });
        } catch (ReglaRechazada $e) {
            return Salida::rechazo($e);
        } catch (ErrorPersistencia) {
            return Salida::persistencia();
        }

        return new Salida(Salida::OK, 'CASO_APROBADO', ['id' => $aprobado->id, 'version' => $aprobado->version, 'aprobador' => $usuario]);
    }
}
