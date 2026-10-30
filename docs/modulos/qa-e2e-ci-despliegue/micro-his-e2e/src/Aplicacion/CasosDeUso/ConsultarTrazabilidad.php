<?php

declare(strict_types=1);

namespace E2e\Aplicacion\CasosDeUso;

use E2e\Aplicacion\Puertos\PoliticaAcceso;
use E2e\Aplicacion\Resultados\Salida;
use E2e\Dominio\Excepciones\ErrorPersistencia;
use E2e\Dominio\Modelo\Decision;
use E2e\Dominio\Modelo\Despliegue;
use E2e\Dominio\Modelo\Ejecucion;
use E2e\Dominio\Modelo\Evidencia;
use E2e\Dominio\Modelo\ResultadoEscenario;
use E2e\Dominio\Puertos\RepositorioDespliegues;
use E2e\Dominio\Puertos\RepositorioEjecuciones;
use E2e\Dominio\Puertos\RepositorioEvaluaciones;
use E2e\Dominio\Puertos\RepositorioVersiones;

/** CU-12: versión ↔ plan ↔ ejecuciones ↔ resultados ↔ evidencias (solo huellas) ↔ evaluaciones del gate ↔ despliegues. */
final class ConsultarTrazabilidad
{
    public const PERMISO = 'e2e.consultar';

    public function __construct(
        private readonly PoliticaAcceso $acceso,
        private readonly RepositorioVersiones $versiones,
        private readonly RepositorioEjecuciones $ejecuciones,
        private readonly RepositorioEvaluaciones $evaluaciones,
        private readonly RepositorioDespliegues $despliegues,
    ) {
    }

    public function ejecutar(string $usuario, string $tenant, string $numero): Salida
    {
        if (!$this->acceso->permite($usuario, self::PERMISO, $tenant)) {
            return Salida::denegado();
        }
        try {
            $v = $this->versiones->porNumero($tenant, $numero);
            if ($v === null) {
                return Salida::noEncontrado();
            }
            $ejecuciones = $this->ejecuciones->deVersion($tenant, $numero);
            $evaluaciones = $this->evaluaciones->deVersion($tenant, $numero);
            $despliegues = $this->despliegues->deVersion($tenant, $numero);
        } catch (ErrorPersistencia) {
            return Salida::persistencia();
        }

        return new Salida(Salida::OK, 'TRAZABILIDAD', [
            'version' => $v->numero, 'commit' => $v->commit, 'digest' => $v->digest, 'estado' => $v->estado(),
            'plan' => $v->plan(), 'plan_congelado' => $v->planCongelado(),
            'ejecuciones' => array_map(static fn (Ejecucion $e): array => ['id' => $e->id, 'entorno' => $e->entorno, 'digest' => $e->digest,
                'estado' => $e->estado(), 'motivo' => $e->motivo(),
                'resultados' => array_map(static fn (ResultadoEscenario $r): array => ['escenario' => $r->escenario, 'critico' => $r->critico,
                    'resultado' => $r->resultado->value, 'intentos' => $r->intentos,
                    'evidencias' => array_map(static fn (Evidencia $x): array => ['tipo' => $x->tipo, 'sha256' => $x->sha256, 'tamano' => $x->tamano], $r->evidencias)],
                    $e->resultados())], $ejecuciones),
            'evaluaciones' => array_map(static fn (Decision $d): array => ['ejecucion' => $d->ejecucion, 'decision' => $d->valor, 'causas' => $d->causas,
                'politica' => $d->politica, 'evaluada_en' => $d->evaluadaEn->format(DATE_ATOM), 'por' => $d->evaluadaPor], $evaluaciones),
            'despliegues' => array_map(static fn (Despliegue $d): array => ['id' => $d->id, 'entorno' => $d->entorno, 'digest' => $d->digest, 'estado' => $d->estado,
                'guia' => $d->guia, 'operador' => $d->operador, 'revertido_a' => $d->revertidoA, 'en' => $d->en->format(DATE_ATOM)], $despliegues),
        ]);
    }
}
