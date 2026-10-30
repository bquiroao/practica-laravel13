<?php

declare(strict_types=1);

namespace Qa\Aplicacion\CasosDeUso;

use Qa\Aplicacion\Puertos\PoliticaAcceso;
use Qa\Aplicacion\Puertos\RegistroAuditoria;
use Qa\Aplicacion\Puertos\Transaccion;
use Qa\Aplicacion\Resultados\Salida;
use Qa\Dominio\Excepciones\ErrorPersistencia;
use Qa\Dominio\Excepciones\ReglaRechazada;
use Qa\Dominio\Modelo\Severidad;
use Qa\Dominio\Puertos\RepositorioDefectos;

/** CU-08: el Líder QA confirma o cambia la severidad (con motivo) y fija la prioridad (R-07). */
final class ClasificarDefecto
{
    public const PERMISO = 'qa.clasificar';

    public function __construct(
        private readonly PoliticaAcceso $acceso,
        private readonly RepositorioDefectos $defectos,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
    ) {
    }

    /** @param array<string, mixed> $datos severidad, prioridad, motivo? */
    public function ejecutar(string $usuario, string $tenant, int $defectoId, array $datos): Salida
    {
        if (!$this->acceso->permite($usuario, self::PERMISO, $tenant)) {
            return Salida::denegado();
        }
        try {
            $defecto = $this->defectos->porId($tenant, $defectoId);
            if ($defecto === null) {
                return Salida::noEncontrado();
            }
            $severidad = Severidad::tryFrom((string) ($datos['severidad'] ?? '')) ?? throw new ReglaRechazada('DATOS_INVALIDOS', ['severidad']);
            $defecto->clasificar($severidad, (string) ($datos['prioridad'] ?? ''), $usuario, isset($datos['motivo']) ? (string) $datos['motivo'] : null);
            $this->transaccion->ejecutar(function () use ($defecto, $usuario, $tenant): void {
                $this->defectos->actualizar($defecto);
                $this->auditoria->registrar('DEFECTO_CLASIFICADO', $tenant, $usuario, 'defecto:' . $defecto->id);
            });
        } catch (ReglaRechazada $e) {
            return Salida::rechazo($e);
        } catch (ErrorPersistencia) {
            return Salida::persistencia();
        }

        return new Salida(Salida::OK, 'DEFECTO_CLASIFICADO', ['id' => $defecto->id, 'estado' => $defecto->estado()->value,
            'severidad' => $defecto->severidad()?->value, 'prioridad' => $defecto->prioridad()]);
    }
}
