<?php

declare(strict_types=1);

/*
 * Modelo del dominio de la semana 2: lo mínimo para verificar los criterios de aceptación de RF-01..RF-12.
 * En la semana 3 se separa en clases y capas del Micro-HIS.
 */

namespace Qa\Dominio;

use Qa\Dominio\Evidencia\EvidenciaVerificable;
use Qa\Dominio\Evidencia\ReferenciaExterna;

/** Una regla del dominio rechazó la operación. $campos o $detalle explican qué falta. */
final class ReglaRechazada extends \DomainException
{
    /** @param list<string> $detalle */
    public function __construct(public readonly string $codigo, public readonly array $detalle = [])
    {
        parent::__construct($codigo . ($detalle === [] ? '' : ': ' . implode(', ', $detalle)));
    }
}

enum Resultado: string
{
    case PASA = 'PASA';
    case FALLA = 'FALLA';
    case BLOQUEADO = 'BLOQUEADO';
    case NO_APLICA = 'NO_APLICA';

    /** R-05: FALLA y BLOQUEADO se tienen que poder revisar. */
    public function exigeEvidencia(): bool
    {
        return $this === self::FALLA || $this === self::BLOQUEADO;
    }
}

enum Severidad: string
{
    case CRITICA = 'CRITICA';
    case MAYOR = 'MAYOR';
    case MENOR = 'MENOR';
    case TRIVIAL = 'TRIVIAL';
}

enum EstadoDefecto: string
{
    case NUEVO = 'NUEVO';
    case ABIERTO = 'ABIERTO';
    case CORREGIDO = 'CORREGIDO';
    case CERRADO = 'CERRADO';
    case REABIERTO = 'REABIERTO';
}

/** Caso de prueba manual, versionado (R-01, R-02). Inmutable: aprobar o editar devuelven OTRA instancia. */
final class CasoPrueba
{
    /**
     * @param list<array{accion: string, esperado: string}> $pasos
     * @param list<string>                                  $requisitos
     */
    private function __construct(
        public readonly string $id,
        public readonly int $version,
        public readonly string $autor,
        public readonly string $titulo,
        public readonly array $pasos,
        public readonly string $prioridad,
        public readonly array $requisitos,
        public readonly string $estado,
        public readonly ?string $aprobador = null,
    ) {
    }

    /**
     * @param list<array{accion?: string, esperado?: string}> $pasos
     * @param list<string>                                    $requisitos
     */
    public static function disenar(string $id, string $autor, string $titulo, array $pasos, string $prioridad, array $requisitos): self
    {
        $campos = [];
        if (trim($titulo) === '') {
            $campos[] = 'titulo';
        }
        if ($pasos === []) {
            $campos[] = 'pasos';
        }
        foreach ($pasos as $i => $p) {
            if (trim($p['accion'] ?? '') === '') {
                $campos[] = "pasos[$i].accion";
            }
            if (trim($p['esperado'] ?? '') === '') {
                $campos[] = "pasos[$i].esperado";
            }
        }
        if (!in_array($prioridad, ['ALTA', 'MEDIA', 'BAJA'], true)) {
            $campos[] = 'prioridad';
        }
        if (array_filter($requisitos, static fn (string $r): bool => preg_match('/^RF-\d{2,3}$/', $r) === 1) === []) {
            $campos[] = 'requisitos';
        }
        if ($campos !== []) {
            throw new ReglaRechazada('DATOS_INVALIDOS', $campos);
        }

        return new self($id, 1, $autor, $titulo, array_values($pasos), $prioridad, array_values($requisitos), 'BORRADOR');
    }

    public function aprobar(string $aprobador): self
    {
        if ($aprobador === $this->autor) {
            throw new ReglaRechazada('APROBACION_POR_AUTOR');
        }

        return new self($this->id, $this->version, $this->autor, $this->titulo, $this->pasos, $this->prioridad, $this->requisitos, 'APROBADO', $aprobador);
    }

    /** @param list<array{accion: string, esperado: string}> $pasos Editar un caso crea la versión siguiente, en BORRADOR. */
    public function editar(string $autor, array $pasos): self
    {
        return new self($this->id, $this->version + 1, $autor, $this->titulo, $pasos, $this->prioridad, $this->requisitos, 'BORRADOR');
    }
}

/** Un resultado de un caso en una campaña (R-11): inmutable, sin métodos que lo cambien. */
final class Intento
{
    /**
     * @param list<array{tipo: string, huella: string, tamano: int, clasificacion: string, ubicacion: string}> $evidencias
     * @param list<ReferenciaExterna>                                                                          $referencias
     */
    public function __construct(
        public readonly string $casoId,
        public readonly int $numero,
        public readonly Resultado $resultado,
        public readonly string $tester,
        public readonly array $evidencias,
        public readonly array $referencias,
        public readonly ?string $defectoId,
        public readonly ?string $motivo,
    ) {
    }
}

/** Ciclo de vida del defecto (R-07, R-08). No hay ningún método que lo cierre sin un resultado de re-ejecución. */
final class Defecto
{
    public EstadoDefecto $estado = EstadoDefecto::NUEVO;
    public ?Severidad $severidad = null;
    /** @var list<string> */
    public array $historial = [];

    /** @param list<string> $pasos */
    public function __construct(
        public readonly string $id,
        public readonly string $casoId,
        public readonly string $titulo,
        public readonly array $pasos,
        public readonly Severidad $propuesta,
        public readonly bool $conEvidencia,
    ) {
    }

    public function clasificar(Severidad $severidad, string $prioridad, string $lider, ?string $motivo = null): void
    {
        if ($severidad === Severidad::CRITICA && ($this->pasos === [] || !$this->conEvidencia)) {
            throw new ReglaRechazada('DEFECTO_INCOMPLETO');
        }
        if ($severidad !== $this->propuesta && trim((string) $motivo) === '') {
            throw new ReglaRechazada('MOTIVO_REQUERIDO');
        }
        $this->severidad = $severidad;
        $this->estado = EstadoDefecto::ABIERTO;
        $this->historial[] = "clasificado {$severidad->value} {$prioridad} por {$lider}" . ($motivo ? " ({$motivo})" : '');
    }

    public function marcarCorregido(string $build, string $desarrollador): void
    {
        if ($this->estado !== EstadoDefecto::ABIERTO && $this->estado !== EstadoDefecto::REABIERTO) {
            throw new ReglaRechazada('ESTADO_NO_PERMITE_CORREGIR');
        }
        $this->estado = EstadoDefecto::CORREGIDO;
        $this->historial[] = "corregido en {$build} por {$desarrollador}";
    }

    /** Solo una re-ejecución lo cierra (PASA) o lo reabre (FALLA). */
    public function verificar(Resultado $resultado): void
    {
        if ($this->estado !== EstadoDefecto::CORREGIDO) {
            throw new ReglaRechazada('ESTADO_NO_PERMITE_VERIFICAR');
        }
        $this->estado = match ($resultado) {
            Resultado::PASA => EstadoDefecto::CERRADO,
            Resultado::FALLA => EstadoDefecto::REABIERTO,
            default => throw new ReglaRechazada('RESULTADO_NO_VERIFICA'),
        };
        $this->historial[] = 'verificado: ' . $this->estado->value;
    }

    public function criticoAbierto(): bool
    {
        return $this->severidad === Severidad::CRITICA && $this->estado !== EstadoDefecto::CERRADO;
    }
}

/** Criterio de salida (R-09): GO con 0 críticos abiertos y la tasa de aprobados ≥ el umbral. */
final class CriterioSalida
{
    public function __construct(public readonly float $umbral = 0.95)
    {
    }
}

final class Decision
{
    /** @param list<string> $causas */
    public function __construct(public readonly string $valor, public readonly float $tasa, public readonly array $causas)
    {
    }
}

/** Campaña de regresión: un build y un alcance que se congelan al iniciar (R-03). Los intentos solo se agregan. */
final class Campana
{
    public string $estado = 'PLANIFICADA';
    public ?string $build = null;
    /** @var array<string, CasoPrueba> */
    private array $alcance = [];
    /** @var list<Intento> */
    private array $intentos = [];
    public ?Decision $decision = null;

    /** @param list<CasoPrueba> $alcance */
    public function __construct(public readonly string $id, public readonly string $tenant, array $alcance)
    {
        foreach ($alcance as $c) {
            $this->alcance[$c->id] = $c;
        }
    }

    /** @param list<string> $criticosAfectados ids de los casos críticos de las funcionalidades que cambia el build (matriz) */
    public function iniciar(string $build, array $criticosAfectados): void
    {
        if ($this->estado !== 'PLANIFICADA') {
            throw new ReglaRechazada('ESTADO_NO_PERMITE_INICIAR');
        }
        $faltan = array_values(array_diff($criticosAfectados, array_keys($this->alcance)));
        if (trim($build) === '' || $faltan !== []) {
            throw new ReglaRechazada('ALCANCE_INCOMPLETO', trim($build) === '' ? ['build'] : $faltan);
        }
        $sinAprobar = array_keys(array_filter($this->alcance, static fn (CasoPrueba $c): bool => $c->estado !== 'APROBADO'));
        if ($sinAprobar !== []) {
            throw new ReglaRechazada('CASO_NO_APROBADO', $sinAprobar);
        }
        $this->build = $build;
        $this->estado = 'EN_CURSO';
    }

    public function admite(string $casoId): bool
    {
        return $this->estado === 'EN_CURSO' && isset($this->alcance[$casoId]);
    }

    public function siguienteNumero(string $casoId): int
    {
        return 1 + count(array_filter($this->intentos, static fn (Intento $i): bool => $i->casoId === $casoId));
    }

    public function agregar(Intento $intento): void
    {
        if (!$this->admite($intento->casoId)) {
            throw new ReglaRechazada('CAMPANA_NO_ADMITE_RESULTADOS');
        }
        $this->intentos[] = $intento;
    }

    /** @return list<Intento> */
    public function intentos(): array
    {
        return $this->intentos;
    }

    /** El ÚLTIMO intento de cada caso es el que cuenta (un caso que falló y se corrigió cuenta como PASA). */
    public function ultimo(string $casoId): ?Intento
    {
        $ultimo = null;
        foreach ($this->intentos as $i) {
            if ($i->casoId === $casoId) {
                $ultimo = $i;
            }
        }

        return $ultimo;
    }

    /** @param list<Defecto> $defectos */
    public function cerrar(CriterioSalida $criterio, array $defectos): Decision
    {
        $pendientes = array_values(array_filter(array_keys($this->alcance), fn (string $id): bool => $this->ultimo($id) === null));
        if ($pendientes !== []) {
            throw new ReglaRechazada('CASOS_PENDIENTES', $pendientes);
        }
        $ultimos = array_map(fn (string $id): Intento => $this->ultimo($id), array_keys($this->alcance));
        $aplicables = array_filter($ultimos, static fn (Intento $i): bool => $i->resultado !== Resultado::NO_APLICA);
        $pasan = count(array_filter($aplicables, static fn (Intento $i): bool => $i->resultado === Resultado::PASA));
        $tasa = $aplicables === [] ? 1.0 : $pasan / count($aplicables);
        $criticos = count(array_filter($defectos, static fn (Defecto $d): bool => $d->criticoAbierto()));
        $causas = [];
        if ($criticos > 0) {
            $causas[] = "$criticos defectos críticos abiertos";
        }
        if ($tasa < $criterio->umbral) {
            $causas[] = sprintf('tasa %.1f %% < %.1f %%', 100 * $tasa, 100 * $criterio->umbral);
        }
        $this->estado = 'CERRADA';

        return $this->decision = new Decision($causas === [] ? 'GO' : 'NO_GO', $tasa, $causas);
    }
}
