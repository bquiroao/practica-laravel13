<?php

declare(strict_types=1);

namespace Qa\Aplicacion\CasosDeUso;

use Qa\Aplicacion\Puertos\PoliticaAcceso;
use Qa\Aplicacion\Puertos\RegistroAuditoria;
use Qa\Aplicacion\Puertos\Transaccion;
use Qa\Aplicacion\Resultados\Salida;
use Qa\Dominio\Excepciones\ErrorPersistencia;
use Qa\Dominio\Excepciones\ReglaRechazada;
use Qa\Dominio\Modelo\CasoPrueba;
use Qa\Dominio\Puertos\RepositorioCasos;

/** CU-01: el Analista QA diseña un caso manual (R-01). Queda en BORRADOR hasta que otro lo apruebe. */
final class DisenarCaso
{
    public const PERMISO = 'qa.disenar';

    public function __construct(
        private readonly PoliticaAcceso $acceso,
        private readonly RepositorioCasos $casos,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
    ) {
    }

    /** @param array<string, mixed> $datos id, titulo, precondiciones, pasos[{accion, esperado}], prioridad, requisitos[] */
    public function ejecutar(string $usuario, string $tenant, array $datos): Salida
    {
        if (!$this->acceso->permite($usuario, self::PERMISO, $tenant)) {
            return Salida::denegado();
        }
        try {
            $caso = CasoPrueba::disenar($tenant, (string) ($datos['id'] ?? ''), $usuario, (string) ($datos['titulo'] ?? ''),
                (string) ($datos['precondiciones'] ?? ''), is_array($datos['pasos'] ?? null) ? $datos['pasos'] : [],
                (string) ($datos['prioridad'] ?? ''), is_array($datos['requisitos'] ?? null) ? $datos['requisitos'] : []);
            if ($this->casos->porId($tenant, $caso->id) !== null) {
                return new Salida(Salida::CONFLICTO, 'CASO_EXISTENTE');
            }
            $this->transaccion->ejecutar(function () use ($caso, $usuario, $tenant): void {
                $this->casos->guardar($caso);
                $this->auditoria->registrar('CASO_DISENADO', $tenant, $usuario, 'caso:' . $caso->id);
            });
        } catch (ReglaRechazada $e) {
            return Salida::rechazo($e);
        } catch (ErrorPersistencia) {
            return Salida::persistencia();
        }

        return new Salida(Salida::CREADO, 'CASO_DISENADO', ['id' => $caso->id, 'version' => $caso->version, 'estado' => $caso->estado]);
    }
}
