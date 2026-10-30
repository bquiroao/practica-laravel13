<?php

declare(strict_types=1);

namespace E2e\Aplicacion\CasosDeUso;

use E2e\Aplicacion\Puertos\PoliticaAcceso;
use E2e\Aplicacion\Puertos\RegistroAuditoria;
use E2e\Aplicacion\Puertos\Transaccion;
use E2e\Aplicacion\Resultados\Salida;
use E2e\Dominio\Excepciones\ErrorPersistencia;
use E2e\Dominio\Excepciones\ReglaRechazada;
use E2e\Dominio\Modelo\Despliegue;
use E2e\Dominio\Puertos\Reloj;
use E2e\Dominio\Puertos\RepositorioDespliegues;
use E2e\Dominio\Puertos\RepositorioEvaluaciones;
use E2e\Dominio\Puertos\RepositorioVersiones;

/** CU-11 (semana 6): el Operador de despliegue registra un despliegue hecho con la guía; R-11 decide si se puede. */
final class RegistrarDespliegue
{
    public const PERMISO = 'e2e.desplegar';

    public function __construct(
        private readonly PoliticaAcceso $acceso,
        private readonly RepositorioVersiones $versiones,
        private readonly RepositorioEvaluaciones $evaluaciones,
        private readonly RepositorioDespliegues $despliegues,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
        private readonly Reloj $reloj,
        private readonly int $vigenciaHoras,
    ) {
    }

    /** @param array<string, mixed> $datos digest, entorno, guia, smoke (PASA o FALLA) */
    public function ejecutar(string $usuario, string $tenant, string $numero, array $datos): Salida
    {
        if (!$this->acceso->permite($usuario, self::PERMISO, $tenant)) {
            return Salida::denegado();
        }
        try {
            $v = $this->versiones->porNumero($tenant, $numero);
            if ($v === null) {
                return Salida::noEncontrado();
            }
            $entorno = (string) ($datos['entorno'] ?? '');
            $nuevo = Despliegue::registrar($v, $this->evaluaciones->deVersion($tenant, $numero), (string) ($datos['digest'] ?? ''), $entorno,
                (string) ($datos['guia'] ?? ''), (string) ($datos['smoke'] ?? ''), $this->despliegues->vigente($tenant, $entorno), $this->reloj->ahora(),
                $this->vigenciaHoras, $usuario);
            $d = $this->transaccion->ejecutar(function () use ($nuevo, $usuario, $tenant): Despliegue {
                $d = $this->despliegues->registrar($nuevo);
                $this->auditoria->registrar('DESPLIEGUE_' . $d->estado, $tenant, $usuario, "version:{$d->version}/{$d->entorno}");

                return $d;
            });
        } catch (ReglaRechazada $e) {
            return Salida::rechazo($e);
        } catch (ErrorPersistencia) {
            return Salida::persistencia();
        }

        return new Salida(Salida::CREADO, 'DESPLIEGUE_REGISTRADO', ['id' => $d->id, 'version' => $d->version, 'digest' => $d->digest, 'entorno' => $d->entorno,
            'estado' => $d->estado, 'guia' => $d->guia, 'revertido_a' => $d->revertidoA]);
    }
}
