<?php

declare(strict_types=1);

use Qa\Aplicacion\Puertos\AlmacenEvidencias;
use Qa\Aplicacion\Puertos\AlmacenNoDisponible;
use Qa\Aplicacion\Puertos\RegistroAuditoria;
use Qa\Aplicacion\Puertos\Transaccion;
use Qa\Dominio\Evidencia\EvidenciaVerificable;
use Qa\Dominio\Excepciones\ClaveEnUso;
use Qa\Dominio\Excepciones\ErrorPersistencia;
use Qa\Dominio\Excepciones\ReglaRechazada;
use Qa\Dominio\Modelo\Campana;
use Qa\Dominio\Modelo\CasoPrueba;
use Qa\Dominio\Modelo\Defecto;
use Qa\Dominio\Modelo\EstadoDefecto;
use Qa\Dominio\Modelo\Intento;
use Qa\Dominio\Modelo\Severidad;
use Qa\Dominio\Puertos\Reloj;
use Qa\Dominio\Puertos\RepositorioCampanas;
use Qa\Dominio\Puertos\RepositorioCasos;
use Qa\Dominio\Puertos\RepositorioDefectos;

/*
 * Dobles de prueba (semana 3): implementan los puertos en memoria para probar la Aplicación sin base de datos.
 * Cada uno puede «fallar» en un método (como una base caída) y guarda una instantánea para que TransaccionFalsa
 * deshaga lo escrito si el trabajo lanza: así se prueba que no quedan escrituras a medias.
 */

interface ConInstantanea
{
    public function instantanea(): mixed;

    public function restaurar(mixed $estado): void;
}

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

final class RepositorioCasosFalso implements RepositorioCasos, ConInstantanea
{
    use FallaProgramable;

    /** @var array<string, CasoPrueba> "tenant|id|version" */
    public array $casos = [];
    /** @var array<string, array<string, bool>> "tenant|funcionalidad" => [caso => crítico] */
    public array $matriz = [];

    public function guardar(CasoPrueba $caso): void
    {
        $this->quizaFallar('guardar');
        $this->casos["{$caso->tenant}|{$caso->id}|{$caso->version}"] = $caso;
    }

    public function aprobar(CasoPrueba $caso): void
    {
        $this->quizaFallar('aprobar');
        $clave = "{$caso->tenant}|{$caso->id}|{$caso->version}";
        if (($this->casos[$clave]->estado ?? null) !== CasoPrueba::BORRADOR) {
            throw new ReglaRechazada('ESTADO_NO_PERMITE_APROBAR');
        }
        $this->casos[$clave] = $caso;
    }

    public function porId(string $tenant, string $id): ?CasoPrueba
    {
        $this->quizaFallar('porId');

        return $this->variosPorId($tenant, [$id])[$id] ?? null;
    }

    public function variosPorId(string $tenant, array $ids): array
    {
        $this->quizaFallar('variosPorId');
        $r = [];
        foreach ($this->casos as $c) {
            if ($c->tenant === $tenant && in_array($c->id, $ids, true) && (!isset($r[$c->id]) || $r[$c->id]->version < $c->version)) {
                $r[$c->id] = $c;
            }
        }

        return $r;
    }

    public function criticosDe(string $tenant, array $funcionalidades): array
    {
        $this->quizaFallar('criticosDe');
        $r = [];
        foreach ($funcionalidades as $f) {
            foreach ($this->matriz["$tenant|$f"] ?? [] as $caso => $critico) {
                if ($critico) {
                    $r[$caso] = true;
                }
            }
        }
        $r = array_keys($r);
        sort($r);

        return $r;
    }

    public function queCubren(string $tenant, string $requisito): array
    {
        return array_values(array_filter($this->casos, static fn (CasoPrueba $c): bool => $c->tenant === $tenant && in_array($requisito, $c->requisitos, true)));
    }

    public function instantanea(): mixed
    {
        return $this->casos;
    }

    public function restaurar(mixed $estado): void
    {
        $this->casos = $estado;
    }
}

final class RepositorioCampanasFalso implements RepositorioCampanas, ConInstantanea
{
    use FallaProgramable;

    /** @var array<int, array{tenant: string, campana: Campana}> se guarda serializada para no compartir el objeto */
    private array $filas = [];
    /** @var array<int, Intento> */
    public array $intentos = [];
    private int $siguiente = 1;
    private int $siguienteIntento = 1;

    public function crear(Campana $campana): Campana
    {
        $this->quizaFallar('crear');
        $creada = $campana->conId($this->siguiente++);
        $this->filas[$creada->id] = serialize($creada);

        return $creada;
    }

    public function actualizarEstado(Campana $campana): void
    {
        $this->quizaFallar('actualizarEstado');
        $actual = unserialize($this->filas[$campana->id]);
        $anterior = ['EN_CURSO' => 'PLANIFICADA', 'CERRADA' => 'EN_CURSO'][$campana->estado()->value] ?? null;
        if ($actual->estado()->value !== $anterior) {
            throw new ReglaRechazada('ESTADO_CAMBIO_CONCURRENTE');
        }
        // Se guarda el estado nuevo, pero los intentos viven aparte (como en la base).
        $this->filas[$campana->id] = serialize(new Campana($campana->id, $campana->tenant, $campana->nombre, $campana->funcionalidades,
            $campana->alcance, $campana->umbral, $campana->creadaPor, $campana->estado(), $campana->build(), $campana->decision()));
    }

    public function porId(string $tenant, int $id): ?Campana
    {
        $this->quizaFallar('porId');
        if (!isset($this->filas[$id])) {
            return null;
        }
        $c = unserialize($this->filas[$id]);
        if ($c->tenant !== $tenant) {
            return null;
        }
        $intentos = array_values(array_filter($this->intentos, static fn (Intento $i): bool => $i->campanaId === $id));

        return new Campana($c->id, $c->tenant, $c->nombre, $c->funcionalidades, $c->alcance, $c->umbral, $c->creadaPor, $c->estado(), $c->build(),
            $c->decision(), $intentos);
    }

    public function agregarIntento(string $tenant, Intento $intento): Intento
    {
        $this->quizaFallar('agregarIntento');
        foreach ($this->intentos as $i) {
            if ($i->clave === $intento->clave) {
                throw new ClaveEnUso('clave en uso');
            }
        }
        $con = $intento->conId($this->siguienteIntento++);
        $this->intentos[$con->id] = $con;

        return $con;
    }

    public function intentoPorClave(string $tenant, string $clave): ?Intento
    {
        $this->quizaFallar('intentoPorClave');
        foreach ($this->intentos as $i) {
            if ($i->clave === $clave && unserialize($this->filas[$i->campanaId])->tenant === $tenant) {
                return $i;
            }
        }

        return null;
    }

    public function instantanea(): mixed
    {
        return [$this->filas, $this->intentos, $this->siguiente, $this->siguienteIntento];
    }

    public function restaurar(mixed $estado): void
    {
        [$this->filas, $this->intentos, $this->siguiente, $this->siguienteIntento] = $estado;
    }
}

final class RepositorioDefectosFalso implements RepositorioDefectos, ConInstantanea
{
    use FallaProgramable;

    /** @var array<int, string> defectos serializados (sin compartir el objeto con el caso de uso) */
    private array $filas = [];
    /** @var list<array{id: int, evento: string}> */
    public array $historial = [];
    private int $siguiente = 1;

    public function crear(Defecto $defecto): Defecto
    {
        $this->quizaFallar('crear');
        $creado = $defecto->conId($this->siguiente++);
        $this->guardarFila($creado);
        $this->historial[] = ['id' => $creado->id, 'evento' => "REPORTADO {$defecto->propuesta->value}"];

        return $creado;
    }

    public function actualizar(Defecto $defecto): void
    {
        $this->quizaFallar('actualizar');
        $this->guardarFila($defecto);
        foreach ($defecto->historialNuevo() as $e) {
            $this->historial[] = ['id' => $defecto->id, 'evento' => $e];
        }
    }

    public function porId(string $tenant, int $id): ?Defecto
    {
        $this->quizaFallar('porId');
        $d = isset($this->filas[$id]) ? unserialize($this->filas[$id]) : null;

        return $d !== null && $d->tenant === $tenant ? $d : null;
    }

    public function deCampana(string $tenant, int $campanaId): array
    {
        return array_values(array_filter(array_map('unserialize', $this->filas), static fn (Defecto $d): bool => $d->tenant === $tenant && $d->campanaId === $campanaId));
    }

    public function criticosAbiertos(string $tenant, array $casos): int
    {
        $this->quizaFallar('criticosAbiertos');

        return count(array_filter(array_map('unserialize', $this->filas), static fn (Defecto $d): bool => $d->tenant === $tenant
            && in_array($d->casoId, $casos, true) && $d->criticoAbierto()));
    }

    /** Para preparar un escenario: un defecto ya clasificado en un estado dado. */
    public function sembrar(string $tenant, int $campanaId, string $caso, Severidad $severidad, EstadoDefecto $estado): Defecto
    {
        $d = new Defecto(null, $tenant, $campanaId, $caso, 'Sembrado', ['paso'], $severidad, true, 'tito-tester', $estado, $severidad, 'ALTA');
        $creado = $d->conId($this->siguiente++);
        $this->guardarFila($creado);

        return $creado;
    }

    private function guardarFila(Defecto $d): void
    {
        $limpio = new Defecto($d->id, $d->tenant, $d->campanaId, $d->casoId, $d->titulo, $d->pasos, $d->propuesta, $d->conEvidencia, $d->reportadoPor,
            $d->estado(), $d->severidad(), $d->prioridad());
        $this->filas[(int) $d->id] = serialize($limpio);
    }

    public function instantanea(): mixed
    {
        return [$this->filas, $this->historial, $this->siguiente];
    }

    public function restaurar(mixed $estado): void
    {
        [$this->filas, $this->historial, $this->siguiente] = $estado;
    }
}

final class AlmacenFalso implements AlmacenEvidencias
{
    public bool $caido = false;
    /** @var array<string, string> ubicación => bytes */
    public array $archivos = [];

    public function guardar(string $tenant, EvidenciaVerificable $evidencia): string
    {
        if ($this->caido) {
            throw new AlmacenNoDisponible('almacén caído');
        }
        $ubicacion = $tenant . '/' . $evidencia->huella();
        $this->archivos[$ubicacion] = $evidencia->contenido();

        return $ubicacion;
    }

    public function leer(string $ubicacion): string
    {
        if ($this->caido || !isset($this->archivos[$ubicacion])) {
            throw new AlmacenNoDisponible('almacén caído');
        }

        return $this->archivos[$ubicacion];
    }
}

final class AuditoriaFalsa implements RegistroAuditoria, ConInstantanea
{
    use FallaProgramable;

    /** @var list<array{accion: string, tenant: string, usuario: string, recurso: string}> */
    public array $movimientos = [];

    public function registrar(string $accion, string $tenant, string $usuario, string $recurso): void
    {
        $this->quizaFallar('registrar');
        $this->movimientos[] = ['accion' => $accion, 'tenant' => $tenant, 'usuario' => $usuario, 'recurso' => $recurso];
    }

    /** @return list<string> */
    public function acciones(): array
    {
        return array_column($this->movimientos, 'accion');
    }

    public function instantanea(): mixed
    {
        return $this->movimientos;
    }

    public function restaurar(mixed $estado): void
    {
        $this->movimientos = $estado;
    }
}

/** Como una transacción real: si el trabajo lanza, se restauran todos los dobles a como estaban. */
final class TransaccionFalsa implements Transaccion
{
    public int $confirmadas = 0;
    public int $revertidas = 0;

    /** @param list<ConInstantanea> $participantes */
    public function __construct(private readonly array $participantes)
    {
    }

    public function ejecutar(callable $trabajo): mixed
    {
        $antes = array_map(static fn (ConInstantanea $p): mixed => $p->instantanea(), $this->participantes);
        try {
            $resultado = $trabajo();
            $this->confirmadas++;

            return $resultado;
        } catch (\Throwable $e) {
            foreach ($this->participantes as $i => $p) {
                $p->restaurar($antes[$i]);
            }
            $this->revertidas++;
            throw $e;
        }
    }
}

final class RelojFijo implements Reloj
{
    public function ahora(): \DateTimeImmutable
    {
        return new \DateTimeImmutable('2026-10-28T15:00:00Z');
    }
}
