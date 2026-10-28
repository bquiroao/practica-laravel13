<?php

declare(strict_types=1);

namespace Qa\Aplicacion\CasosDeUso;

use Qa\Aplicacion\Puertos\PoliticaAcceso;
use Qa\Aplicacion\Puertos\RegistroAuditoria;
use Qa\Aplicacion\Puertos\Transaccion;
use Qa\Aplicacion\Resultados\Salida;
use Qa\Dominio\Excepciones\ErrorPersistencia;
use Qa\Dominio\Excepciones\ReglaRechazada;
use Qa\Dominio\Puertos\RepositorioDefectos;

/** CU-09: el Desarrollador marca el defecto CORREGIDO en un build. No lo cierra: eso lo hace una re-ejecución (R-08). */
final class MarcarCorregido
{
    public const PERMISO = 'qa.corregir';

    public function __construct(
        private readonly PoliticaAcceso $acceso,
        private readonly RepositorioDefectos $defectos,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
    ) {
    }

    public function ejecutar(string $usuario, string $tenant, int $defectoId, string $build): Salida
    {
        if (!$this->acceso->permite($usuario, self::PERMISO, $tenant)) {
            return Salida::denegado();
        }
        try {
            $defecto = $this->defectos->porId($tenant, $defectoId);
            if ($defecto === null) {
                return Salida::noEncontrado();
            }
            $defecto->marcarCorregido(trim($build), $usuario);
            $this->transaccion->ejecutar(function () use ($defecto, $usuario, $tenant): void {
                $this->defectos->actualizar($defecto);
                $this->auditoria->registrar('DEFECTO_CORREGIDO', $tenant, $usuario, 'defecto:' . $defecto->id);
            });
        } catch (ReglaRechazada $e) {
            return Salida::rechazo($e);
        } catch (ErrorPersistencia) {
            return Salida::persistencia();
        }

        return new Salida(Salida::OK, 'DEFECTO_CORREGIDO', ['id' => $defecto->id, 'estado' => $defecto->estado()->value]);
    }
}
