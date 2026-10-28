<?php

declare(strict_types=1);

enum SpecimenType: string
{
    case BLOOD = 'blood';
    case URINE = 'urine';
    case SWAB = 'swab';
}

final readonly class Specimen
{
    public function __construct(
        public string $id,
        public SpecimenType $type,
        public bool $labelPresent,
        public bool $containerIntact,
        public bool $sealIntact,
    ) {
        if ($id === '') {
            throw new InvalidArgumentException('El identificador de muestra no puede estar vacío.');
        }
    }
}

final readonly class AcceptanceDecision
{
    /**
     * @param list<string> $reasons
     */
    private function __construct(
        public bool $applicable,
        public bool $accepted,
        public array $reasons,
    ) {
        if (!$applicable && ($accepted || $reasons !== [])) {
            throw new InvalidArgumentException('Una regla no aplicable no puede aceptar ni rechazar.');
        }

        if ($accepted && $reasons !== []) {
            throw new InvalidArgumentException('Una aceptación no puede contener motivos de rechazo.');
        }

        if ($applicable && !$accepted && $reasons === []) {
            throw new InvalidArgumentException('Un rechazo debe explicar al menos un motivo.');
        }
    }

    public static function accept(): self
    {
        return new self(true, true, []);
    }

    /**
     * @param non-empty-list<string> $reasons
     */
    public static function reject(array $reasons): self
    {
        return new self(true, false, $reasons);
    }

    public static function notApplicable(): self
    {
        return new self(false, false, []);
    }
}

interface SpecimenAcceptancePolicy
{
    /**
     * Evalúa cualquier tipo de muestra. Las no conformidades son decisiones
     * de dominio; no deben convertirse en excepciones por categoría.
     */
    public function evaluate(Specimen $specimen): AcceptanceDecision;
}

final class GeneralAcceptancePolicy implements SpecimenAcceptancePolicy
{
    public function evaluate(Specimen $specimen): AcceptanceDecision
    {
        $reasons = [];

        if (!$specimen->labelPresent) {
            $reasons[] = 'La muestra no tiene identificación visible.';
        }

        if (!$specimen->containerIntact) {
            $reasons[] = 'El recipiente no está íntegro.';
        }

        return $reasons === []
            ? AcceptanceDecision::accept()
            : AcceptanceDecision::reject($reasons);
    }
}

final class BloodTubeAcceptancePolicy implements SpecimenAcceptancePolicy
{
    public function evaluate(Specimen $specimen): AcceptanceDecision
    {
        if ($specimen->type !== SpecimenType::BLOOD) {
            return AcceptanceDecision::notApplicable();
        }

        if (!$specimen->sealIntact) {
            return AcceptanceDecision::reject(['El sello del tubo de sangre no está íntegro.']);
        }

        return AcceptanceDecision::accept();
    }
}

final class ReceptionDecisionService
{
    /** @var non-empty-list<SpecimenAcceptancePolicy> */
    private array $policies;

    /**
     * @param non-empty-list<SpecimenAcceptancePolicy> $policies
     */
    public function __construct(array $policies)
    {
        if ($policies === []) {
            throw new InvalidArgumentException('Debe configurarse al menos una política de aceptación.');
        }

        foreach ($policies as $policy) {
            if (!$policy instanceof SpecimenAcceptancePolicy) {
                throw new InvalidArgumentException('Todas las políticas deben implementar SpecimenAcceptancePolicy.');
            }
        }

        $this->policies = $policies;
    }

    public function decide(Specimen $specimen): AcceptanceDecision
    {
        $reasons = [];
        $hasApplicablePolicy = false;

        foreach ($this->policies as $policy) {
            $decision = $policy->evaluate($specimen);

            if (!$decision->applicable) {
                continue;
            }

            $hasApplicablePolicy = true;
            if (!$decision->accepted) {
                array_push($reasons, ...$decision->reasons);
            }
        }

        if (!$hasApplicablePolicy) {
            return AcceptanceDecision::reject(['No hay una política aplicable a este tipo de muestra.']);
        }

        return $reasons === []
            ? AcceptanceDecision::accept()
            : AcceptanceDecision::reject($reasons);
    }
}
