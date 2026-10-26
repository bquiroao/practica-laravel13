<?php

declare(strict_types=1);

namespace MicroHis\Domain;

use InvalidArgumentException;

enum SpecimenType: string
{
    case BLOOD = 'blood';
    case URINE = 'urine';
    case SWAB = 'swab';
}

enum ReceptionStatus: string
{
    case ACCEPTED = 'accepted';
    case REJECTED = 'rejected';
}

final readonly class ReceptionRequest
{
    public function __construct(
        public string $orderReference,
        public string $patientReference,
        public string $examCode,
        public SpecimenType $type,
        public bool $labelPresent,
        public bool $containerIntact,
        public bool $sealIntact,
        public ?string $rejectionReason = null,
    ) {
        foreach ([
            'orden' => $orderReference,
            'paciente' => $patientReference,
            'examen' => $examCode,
        ] as $field => $value) {
            if (trim($value) === '') {
                throw new InvalidArgumentException("La referencia de {$field} es obligatoria.");
            }
        }

        if ($rejectionReason !== null && trim($rejectionReason) === '') {
            throw new InvalidArgumentException('El motivo de rechazo no puede estar vacío.');
        }
    }
}

final readonly class ReceptionDecision
{
    /**
     * @param list<string> $reasons
     */
    private function __construct(
        public ReceptionStatus $status,
        public array $reasons,
    ) {
        if ($status === ReceptionStatus::ACCEPTED && $reasons !== []) {
            throw new InvalidArgumentException('Una recepción aceptada no puede tener motivos de rechazo.');
        }

        if ($status === ReceptionStatus::REJECTED && $reasons === []) {
            throw new InvalidArgumentException('Una recepción rechazada debe explicar el motivo.');
        }
    }

    public static function accepted(): self
    {
        return new self(ReceptionStatus::ACCEPTED, []);
    }

    /**
     * @param non-empty-list<string> $reasons
     */
    public static function rejected(array $reasons): self
    {
        return new self(ReceptionStatus::REJECTED, $reasons);
    }
}

final class SpecimenAcceptancePolicy
{
    public function decide(ReceptionRequest $request): ReceptionDecision
    {
        $reasons = [];

        if ($request->rejectionReason !== null) {
            $reasons[] = trim($request->rejectionReason);
        }

        if (!$request->labelPresent) {
            $reasons[] = 'La muestra no tiene identificación visible.';
        }

        if (!$request->containerIntact) {
            $reasons[] = 'El recipiente no está íntegro.';
        }

        if ($request->type === SpecimenType::BLOOD && !$request->sealIntact) {
            $reasons[] = 'El sello del tubo de sangre no está íntegro.';
        }

        return $reasons === []
            ? ReceptionDecision::accepted()
            : ReceptionDecision::rejected($reasons);
    }
}

final readonly class SpecimenReception
{
    /**
     * @param list<string> $reasons
     */
    public function __construct(
        public string $identifier,
        public string $orderReference,
        public string $patientReference,
        public string $examCode,
        public SpecimenType $type,
        public ReceptionStatus $status,
        public array $reasons,
        public \DateTimeImmutable $receivedAt,
    ) {
        if ($identifier === '') {
            throw new InvalidArgumentException('El identificador de muestra es obligatorio.');
        }

        if ($status === ReceptionStatus::ACCEPTED && $reasons !== []) {
            throw new InvalidArgumentException('Una recepción aceptada no puede tener motivos de rechazo.');
        }

        if ($status === ReceptionStatus::REJECTED && $reasons === []) {
            throw new InvalidArgumentException('Una recepción rechazada debe explicar el motivo.');
        }
    }
}
