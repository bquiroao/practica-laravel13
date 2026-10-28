<?php

declare(strict_types=1);

namespace LabResults\Refactor;

use DateTimeImmutable;
use InvalidArgumentException;
use LabResults\Application\LabResultRepository;
use LabResults\Application\ResultIdentifierGenerator;
use LabResults\Domain\LabResultRecord;
use LabResults\Domain\PendingLabResult;

/* ---------- Domain: el valor y su unidad se validan en un solo lugar ---------- */

enum RangeFlag: string
{
    case LOW = 'LOW';
    case NORMAL = 'NORMAL';
    case HIGH = 'HIGH';
}

/** Un valor numérico con unidad. Su forma canónica es "5.1 mmol/L", que es lo que ya guarda la persistencia. */
final readonly class ResultValue
{
    private function __construct(public float $number, public string $unit)
    {
    }

    public static function parse(string $raw): self
    {
        if (!preg_match('/^\s*(-?\d+(?:[.,]\d+)?)\s+(\S+)\s*$/u', $raw, $m)) {
            throw new InvalidArgumentException('El valor debe tener la forma "número unidad".');
        }

        return new self((float) str_replace(',', '.', $m[1]), $m[2]);
    }

    public function canonical(): string
    {
        return rtrim(rtrim(number_format($this->number, 4, '.', ''), '0'), '.') . ' ' . $this->unit;
    }
}

final readonly class ReferenceRange
{
    public function __construct(public float $low, public float $high, public string $unit)
    {
        if ($low > $high) {
            throw new InvalidArgumentException('El rango es inválido.');
        }
    }

    public function classify(ResultValue $value): RangeFlag
    {
        return match (true) {
            $value->number < $this->low => RangeFlag::LOW,
            $value->number > $this->high => RangeFlag::HIGH,
            default => RangeFlag::NORMAL,
        };
    }
}

/* ---------- Application: puertos, comandos y resultado sin texto de interfaz ---------- */

interface Clock
{
    public function now(): DateTimeImmutable;
}

interface ReferenceRangeProvider
{
    public function forItem(string $orderItemId): ?ReferenceRange;
}

enum Reason: string
{
    case CAPTURED = 'CAPTURED';
    case CORRECTED = 'CORRECTED';
    case INVALID_INPUT = 'INVALID_INPUT';
    case UNIT_MISMATCH = 'UNIT_MISMATCH';
    case ITEM_HAS_RESULT = 'ITEM_HAS_RESULT';
    case NOT_FOUND = 'NOT_FOUND';
    case ALREADY_VALIDATED = 'ALREADY_VALIDATED';
    case STALE_VERSION = 'STALE_VERSION';
    case STORE_CONFLICT = 'STORE_CONFLICT';

    public function succeeded(): bool
    {
        return $this === self::CAPTURED || $this === self::CORRECTED;
    }
}

final readonly class CaptureCommand
{
    public function __construct(public string $orderItemId, public string $rawValue, public string $actor)
    {
    }
}

final readonly class CorrectCommand
{
    public function __construct(
        public string $orderItemId,
        public string $expectedVersionId,
        public string $rawValue,
        public string $reason,
        public string $actor,
    ) {
    }
}

final readonly class Outcome
{
    public function __construct(
        public Reason $reason,
        public ?LabResultRecord $result = null,
        public ?RangeFlag $flag = null,
        public ?string $field = null,
    ) {
    }
}

final class EntryService
{
    public function __construct(
        private LabResultRepository $repository,
        private ResultIdentifierGenerator $ids,
        private Clock $clock,
        private ReferenceRangeProvider $ranges,
    ) {
    }

    public function capture(CaptureCommand $c): Outcome
    {
        if (($bad = $this->blank(['item' => $c->orderItemId, 'actor' => $c->actor])) !== null) {
            return new Outcome(Reason::INVALID_INPUT, field: $bad);
        }
        $checked = $this->checkValue($c->orderItemId, $c->rawValue);
        if ($checked instanceof Outcome) {
            return $checked;
        }
        [$value, $flag] = $checked;

        if ($this->repository->currentFor($c->orderItemId) !== null) {
            return new Outcome(Reason::ITEM_HAS_RESULT);
        }

        $result = PendingLabResult::capture($this->ids->generate(), $c->orderItemId, $value->canonical(), $c->actor, $this->clock->now());

        return $this->repository->saveInitial($result)
            ? new Outcome(Reason::CAPTURED, $result, $flag)
            : new Outcome(Reason::STORE_CONFLICT);
    }

    public function correct(CorrectCommand $c): Outcome
    {
        if (($bad = $this->blank(['item' => $c->orderItemId, 'version' => $c->expectedVersionId, 'reason' => $c->reason, 'actor' => $c->actor])) !== null) {
            return new Outcome(Reason::INVALID_INPUT, field: $bad);
        }
        $checked = $this->checkValue($c->orderItemId, $c->rawValue);
        if ($checked instanceof Outcome) {
            return $checked;
        }
        [$value, $flag] = $checked;

        $current = $this->repository->currentFor($c->orderItemId);
        if ($current === null) {
            return new Outcome(Reason::NOT_FOUND);
        }
        if (!$current instanceof PendingLabResult) {
            return new Outcome(Reason::ALREADY_VALIDATED, $current);
        }
        if ($current->version()->versionId !== $c->expectedVersionId) {
            return new Outcome(Reason::STALE_VERSION, $current);
        }

        $corrected = $current->corrected($this->ids->generate(), $value->canonical(), $c->reason, $c->actor, $this->clock->now());

        return $this->repository->appendCorrection($c->expectedVersionId, $corrected)
            ? new Outcome(Reason::CORRECTED, $corrected, $flag)
            : new Outcome(Reason::STORE_CONFLICT);
    }

    /** @return array{ResultValue, ?RangeFlag}|Outcome */
    private function checkValue(string $orderItemId, string $raw): array|Outcome
    {
        try {
            $value = ResultValue::parse($raw);
        } catch (InvalidArgumentException) {
            return new Outcome(Reason::INVALID_INPUT, field: 'value');
        }
        $range = $this->ranges->forItem($orderItemId);
        if ($range !== null && $range->unit !== $value->unit) {
            return new Outcome(Reason::UNIT_MISMATCH, field: 'unit');
        }

        return [$value, $range?->classify($value)];
    }

    /** @param array<string,string> $fields */
    private function blank(array $fields): ?string
    {
        foreach ($fields as $name => $v) {
            if (trim($v) === '') {
                return $name;
            }
        }

        return null;
    }
}

/* ---------- Presentation: el único lugar con texto y códigos de salida ---------- */

final class OutcomeView
{
    private const TEXT = [
        'CAPTURED' => 'Resultado capturado como pendiente.',
        'CORRECTED' => 'Corrección guardada como nueva versión pendiente.',
        'INVALID_INPUT' => 'Revisa el campo indicado.',
        'UNIT_MISMATCH' => 'La unidad no corresponde a la del rango de referencia.',
        'ITEM_HAS_RESULT' => 'El ítem ya tiene un resultado vigente.',
        'NOT_FOUND' => 'No existe un resultado para el ítem.',
        'ALREADY_VALIDATED' => 'El resultado ya fue validado y no se corrige.',
        'STALE_VERSION' => 'La versión cambió; vuelve a cargar el resultado.',
        'STORE_CONFLICT' => 'El resultado cambió antes de guardarse; reintenta.',
    ];

    public static function message(Outcome $o): string
    {
        $m = self::TEXT[$o->reason->value];
        if ($o->field !== null) {
            $m .= ' (' . $o->field . ')';
        }
        if ($o->flag !== null && $o->flag !== RangeFlag::NORMAL) {
            $m .= ' Atención: fuera del rango (' . $o->flag->value . ').';
        }

        return $m;
    }

    public static function exitCode(Outcome $o): int
    {
        return match ($o->reason) {
            Reason::CAPTURED, Reason::CORRECTED => 0,
            Reason::INVALID_INPUT, Reason::UNIT_MISMATCH => 2,
            Reason::ITEM_HAS_RESULT, Reason::ALREADY_VALIDATED, Reason::STALE_VERSION, Reason::STORE_CONFLICT => 3,
            Reason::NOT_FOUND => 4,
        };
    }
}
