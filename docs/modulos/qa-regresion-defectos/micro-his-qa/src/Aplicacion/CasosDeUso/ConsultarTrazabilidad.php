<?php

declare(strict_types=1);

namespace Qa\Aplicacion\CasosDeUso;

use Qa\Aplicacion\Puertos\PoliticaAcceso;
use Qa\Aplicacion\Resultados\Salida;
use Qa\Dominio\Excepciones\ErrorPersistencia;
use Qa\Dominio\Modelo\Defecto;
use Qa\Dominio\Modelo\EstadoDefecto;
use Qa\Dominio\Modelo\Severidad;
use Qa\Dominio\Puertos\RepositorioCampanas;
use Qa\Dominio\Puertos\RepositorioCasos;
use Qa\Dominio\Puertos\RepositorioDefectos;

/**
 * CU-11/CU-12: trazabilidad de la campaña, requisito → caso (versión) → último intento → evidencias → defectos.
 * Solo huellas y tamaños de las evidencias: el contenido puede tener datos clínicos y no sale por aquí (R-13).
 */
final class ConsultarTrazabilidad
{
    public const PERMISO = 'qa.consultar';

    public function __construct(
        private readonly PoliticaAcceso $acceso,
        private readonly RepositorioCasos $casos,
        private readonly RepositorioCampanas $campanas,
        private readonly RepositorioDefectos $defectos,
    ) {
    }

    public function ejecutar(string $usuario, string $tenant, int $campanaId): Salida
    {
        if (!$this->acceso->permite($usuario, self::PERMISO, $tenant)) {
            return Salida::denegado();
        }
        try {
            $campana = $this->campanas->porId($tenant, $campanaId);
            if ($campana === null) {
                return Salida::noEncontrado();
            }
            $casos = $this->casos->variosPorId($tenant, array_keys($campana->alcance));
            $defectos = $this->defectos->deCampana($tenant, $campanaId);
            $criticos = $this->casos->criticosDe($tenant, $campana->funcionalidades);
        } catch (ErrorPersistencia) {
            return Salida::persistencia();
        }

        $filas = [];
        $requisitos = [];
        foreach ($campana->alcance as $casoId => $version) {
            $ultimo = $campana->ultimo($casoId);
            $delCaso = array_values(array_filter($defectos, static fn (Defecto $d): bool => $d->casoId === $casoId));
            $filas[] = [
                'caso' => $casoId,
                'version' => $version,
                'requisitos' => $casos[$casoId]->requisitos ?? [],
                'intentos' => count(array_filter($campana->intentos(), static fn ($i): bool => $i->casoId === $casoId)),
                'ultimo_resultado' => $ultimo?->resultado->value ?? 'PENDIENTE',
                'evidencias' => $ultimo === null ? [] : array_map(static fn ($e): array => ['tipo' => $e->tipo, 'huella' => $e->huella,
                    'tamano' => $e->tamano, 'clasificacion' => $e->clasificacion], $ultimo->evidencias),
                'defectos' => array_map(static fn (Defecto $d): array => ['id' => $d->id, 'severidad' => ($d->severidad() ?? $d->propuesta)->value,
                    'clasificado' => $d->severidad() !== null, 'estado' => $d->estado()->value,
                    // R-09 y R-15: lo mismo que cuenta CerrarCampana, para que el Líder QA vea ANTES de cerrar qué lo impide.
                    'bloquea_go' => $d->criticoAbierto() || ($d->severidad() === Severidad::MAYOR && $d->estado() !== EstadoDefecto::CERRADO
                        && in_array($d->casoId, $criticos, true))], $delCaso),
            ];
            foreach ($casos[$casoId]->requisitos ?? [] as $rf) {
                $requisitos[$rf][] = $casoId;
            }
        }
        ksort($requisitos);

        return new Salida(Salida::OK, 'TRAZABILIDAD', [
            'campana' => $campana->id,
            'nombre' => $campana->nombre,
            'estado' => $campana->estado()->value,
            'build' => $campana->build(),
            'decision' => $campana->decision() === null ? null : ['valor' => $campana->decision()->valor, 'tasa_aprobados' => $campana->decision()->tasa,
                'causas' => $campana->decision()->causas],
            'casos' => $filas,
            'requisitos' => $requisitos,
        ]);
    }
}
