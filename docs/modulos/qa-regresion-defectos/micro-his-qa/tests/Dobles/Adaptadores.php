<?php

declare(strict_types=1);

use Qa\Aplicacion\Puertos\RegistroAuditoria;
use Qa\Dominio\Excepciones\ErrorPersistencia;
use Qa\Dominio\Modelo\Campana;
use Qa\Dominio\Modelo\CasoPrueba;
use Qa\Dominio\Modelo\Defecto;
use Qa\Dominio\Modelo\Intento;
use Qa\Dominio\Puertos\Reloj;
use Qa\Dominio\Puertos\RepositorioCampanas;
use Qa\Dominio\Puertos\RepositorioCasos;
use Qa\Dominio\Puertos\RepositorioDefectos;
use Qa\Persistencia\EnMemoria\RegistroAuditoriaEnMemoria;
use Qa\Persistencia\EnMemoria\RepositorioCampanasEnMemoria;
use Qa\Persistencia\EnMemoria\RepositorioCasosEnMemoria;
use Qa\Persistencia\EnMemoria\RepositorioDefectosEnMemoria;

/*
 * Semana 4: los dobles de la semana 3 pasaron a ser adaptadores de producción (src/Persistencia/EnMemoria).
 * Aquí quedan solo DECORADORES que agregan lo que producción no debe tener: fallar a pedido, como una base caída.
 * Se decora el puerto, así que el caso de uso no distingue un decorador de un adaptador.
 */

trait FallaProgramable
{
    /** Nombre del método que lanza ErrorPersistencia (null = ninguno). */
    public ?string $fallarEn = null;

    private function quizaFallar(string $metodo): void
    {
        if ($this->fallarEn === $metodo) {
            throw new ErrorPersistencia("falla simulada en $metodo");
        }
    }
}

final class CasosConFalla implements RepositorioCasos
{
    use FallaProgramable;

    public function __construct(public readonly RepositorioCasosEnMemoria $real)
    {
    }

    public function guardar(CasoPrueba $caso): void
    {
        $this->quizaFallar('guardar');
        $this->real->guardar($caso);
    }

    public function aprobar(CasoPrueba $caso): void
    {
        $this->quizaFallar('aprobar');
        $this->real->aprobar($caso);
    }

    public function porId(string $tenant, string $id): ?CasoPrueba
    {
        $this->quizaFallar('porId');

        return $this->real->porId($tenant, $id);
    }

    public function variosPorId(string $tenant, array $ids): array
    {
        $this->quizaFallar('variosPorId');

        return $this->real->variosPorId($tenant, $ids);
    }

    public function criticosDe(string $tenant, array $funcionalidades): array
    {
        $this->quizaFallar('criticosDe');

        return $this->real->criticosDe($tenant, $funcionalidades);
    }

    public function queCubren(string $tenant, string $requisito): array
    {
        return $this->real->queCubren($tenant, $requisito);
    }
}

final class CampanasConFalla implements RepositorioCampanas
{
    use FallaProgramable;

    public function __construct(public readonly RepositorioCampanasEnMemoria $real)
    {
    }

    public function crear(Campana $campana): Campana
    {
        $this->quizaFallar('crear');

        return $this->real->crear($campana);
    }

    public function actualizarEstado(Campana $campana): void
    {
        $this->quizaFallar('actualizarEstado');
        $this->real->actualizarEstado($campana);
    }

    public function porId(string $tenant, int $id): ?Campana
    {
        $this->quizaFallar('porId');

        return $this->real->porId($tenant, $id);
    }

    public function agregarIntento(string $tenant, Intento $intento): Intento
    {
        $this->quizaFallar('agregarIntento');

        return $this->real->agregarIntento($tenant, $intento);
    }

    public function intentoPorClave(string $tenant, string $clave): ?Intento
    {
        $this->quizaFallar('intentoPorClave');

        return $this->real->intentoPorClave($tenant, $clave);
    }
}

final class DefectosConFalla implements RepositorioDefectos
{
    use FallaProgramable;

    public function __construct(public readonly RepositorioDefectosEnMemoria $real)
    {
    }

    public function crear(Defecto $defecto): Defecto
    {
        $this->quizaFallar('crear');

        return $this->real->crear($defecto);
    }

    public function actualizar(Defecto $defecto): void
    {
        $this->quizaFallar('actualizar');
        $this->real->actualizar($defecto);
    }

    public function porId(string $tenant, int $id): ?Defecto
    {
        $this->quizaFallar('porId');

        return $this->real->porId($tenant, $id);
    }

    public function deCampana(string $tenant, int $campanaId): array
    {
        $this->quizaFallar('deCampana');

        return $this->real->deCampana($tenant, $campanaId);
    }

    public function criticosAbiertos(string $tenant, array $casos): int
    {
        $this->quizaFallar('criticosAbiertos');

        return $this->real->criticosAbiertos($tenant, $casos);
    }
}

final class AuditoriaConFalla implements RegistroAuditoria
{
    use FallaProgramable;

    public function __construct(public readonly RegistroAuditoriaEnMemoria $real)
    {
    }

    public function registrar(string $accion, string $tenant, string $usuario, string $recurso): void
    {
        $this->quizaFallar('registrar');
        $this->real->registrar($accion, $tenant, $usuario, $recurso);
    }
}

final class RelojFijo implements Reloj
{
    public function ahora(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-28T15:00:00Z');
    }
}
