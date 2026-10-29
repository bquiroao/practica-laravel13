<?php

declare(strict_types=1);

namespace E2e\Aplicacion\CasosDeUso;

use E2e\Aplicacion\Puertos\PoliticaAcceso;
use E2e\Aplicacion\Puertos\RegistroAuditoria;
use E2e\Aplicacion\Puertos\Transaccion;
use E2e\Aplicacion\Resultados\Salida;
use E2e\Dominio\Excepciones\ErrorPersistencia;
use E2e\Dominio\Excepciones\ReglaRechazada;
use E2e\Dominio\Modelo\Version;
use E2e\Dominio\Puertos\RepositorioVersiones;

/** CU-02: el pipeline (o un desarrollador) registra la versión candidata con el digest del artefacto (R-03). */
final class RegistrarVersion
{
    public const PERMISO = 'e2e.versionar';

    public function __construct(
        private readonly PoliticaAcceso $acceso,
        private readonly RepositorioVersiones $versiones,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
    ) {
    }

    /** @param array<string, mixed> $datos numero, commit, digest, modulos[] */
    public function ejecutar(string $usuario, string $tenant, array $datos): Salida
    {
        if (!$this->acceso->permite($usuario, self::PERMISO, $tenant)) {
            return Salida::denegado();
        }
        try {
            $v = Version::registrar($tenant, (string) ($datos['numero'] ?? ''), (string) ($datos['commit'] ?? ''), (string) ($datos['digest'] ?? ''),
                is_array($datos['modulos'] ?? null) ? $datos['modulos'] : [], $usuario);
            if ($this->versiones->porNumero($tenant, $v->numero) !== null) {
                return new Salida(Salida::CONFLICTO, 'VERSION_EXISTENTE');
            }
            // Un digest identifica UN artefacto: no puede ser de dos versiones.
            if ($this->versiones->porDigest($tenant, $v->digest) !== null) {
                return new Salida(Salida::CONFLICTO, 'DIGEST_EN_USO');
            }
            $this->transaccion->ejecutar(function () use ($v, $usuario, $tenant): void {
                $this->versiones->crear($v);
                $this->auditoria->registrar('VERSION_REGISTRADA', $tenant, $usuario, 'version:' . $v->numero);
            });
        } catch (ReglaRechazada $e) {
            return Salida::rechazo($e);
        } catch (ErrorPersistencia) {
            return Salida::persistencia();
        }

        return new Salida(Salida::CREADO, 'VERSION_REGISTRADA', ['numero' => $v->numero, 'digest' => $v->digest, 'estado' => $v->estado()]);
    }
}
