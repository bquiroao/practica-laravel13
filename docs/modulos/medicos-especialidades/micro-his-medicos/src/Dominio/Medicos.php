<?php
declare(strict_types=1);

// Dominio: reglas de especialidades y de disponibilidad (las mismas de la semana 2). Fechas 'Y-m-d' y horas 'H:i'.
// No conoce HTTP, SQL ni otras capas (lo comprueba ARQ-01).
namespace Medicos\Dominio;

final class ErrorDominio extends \DomainException {
    public function __construct(public readonly string $codigo, string $mensaje) { parent::__construct($mensaje); }
}
/** Vigencia [desde, hasta): igual que daterange(valid_from, valid_to, '[)') del esquema del grupo; hasta null = abierta. */
final class Vigencia {
    public function __construct(public readonly string $desde, public readonly ?string $hasta = null) {
        if (!self::esFecha($desde) || ($hasta !== null && (!self::esFecha($hasta) || $hasta <= $desde))) { throw new ErrorDominio('vigencia_invalida', 'Vigencia con fechas inválidas'); }
    }
    public static function esFecha(string $f): bool { $d = \DateTimeImmutable::createFromFormat('!Y-m-d', $f); return $d !== false && $d->format('Y-m-d') === $f; }
    public function cruzaCon(self $o): bool { return $this->desde < ($o->hasta ?? '9999-12-31') && $o->desde < ($this->hasta ?? '9999-12-31'); }
    public function incluye(string $fecha): bool { return $this->desde <= $fecha && ($this->hasta === null || $fecha < $this->hasta); }
}
final class Asignacion {
    public function __construct(public readonly string $especialidadId, public readonly Vigencia $vigencia, public readonly bool $principal) {}
}
final class ReglasEspecialidad {
    /** RF-03: la misma especialidad no se cruza consigo misma y solo hay una principal a la vez. @param Asignacion[] $actuales */
    public static function exigirAsignable(array $actuales, Asignacion $nueva): void {
        foreach ($actuales as $a) {
            if ($a->vigencia->cruzaCon($nueva->vigencia) && ($a->especialidadId === $nueva->especialidadId || ($a->principal && $nueva->principal))) {
                throw new ErrorDominio('especialidad_duplicada', 'Ya tiene esa especialidad o una principal en esas fechas');
            }
        }
    }
}
final class Agenda {
    public const MINUTOS = 30, MAX_DIAS = 14;
    public static function exigirRango(string $desde, string $hasta, string $hoy): void {
        $ok = Vigencia::esFecha($desde) && Vigencia::esFecha($hasta) && $desde >= $hoy && $hasta >= $desde
            && (new \DateTimeImmutable($desde))->diff(new \DateTimeImmutable($hasta))->days <= self::MAX_DIAS;
        if (!$ok) { throw new ErrorDominio('rango_invalido', 'El rango debe empezar hoy o después y durar como máximo 14 días'); }
    }
    /** Franjas libres de un día = horario de ese día − citas que se cruzan. $horario: [[inicio, fin]] 'H:i'; $ocupadas: [[inicio, fin]] 'Y-m-d H:i'. */
    public static function franjas(string $fecha, array $horario, array $ocupadas): array {
        $libres = [];
        foreach ($horario as [$ini, $fin]) {
            for ($t = new \DateTimeImmutable("$fecha $ini"); $t->format('H:i') < substr($fin, 0, 5); $t = $t->modify('+' . self::MINUTOS . ' minutes')) {
                [$a, $b] = [$t->format('Y-m-d H:i'), $t->modify('+' . self::MINUTOS . ' minutes')->format('Y-m-d H:i')];
                if (!array_filter($ocupadas, fn ($c) => $c[0] < $b && $a < $c[1])) { $libres[] = $a; }
            }
        }
        return $libres;
    }
}
