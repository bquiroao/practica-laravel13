<?php

declare(strict_types=1);

namespace MicroHis\Presentation\Cli;

use InvalidArgumentException;
use MicroHis\Application\ReceiveSpecimen;
use MicroHis\Domain\ReceptionRequest;
use MicroHis\Domain\ReceptionStatus;
use MicroHis\Domain\SpecimenType;

final class ReceiveSpecimenCommand
{
    public function __construct(private ReceiveSpecimen $useCase)
    {
    }

    /**
     * @param array<string, string|false> $options
     */
    public function run(array $options): int
    {
        $required = ['order', 'patient', 'exam', 'type', 'label', 'container', 'seal'];
        foreach ($required as $name) {
            if (!isset($options[$name]) || $options[$name] === false) {
                throw new InvalidArgumentException("Falta el argumento --{$name}.");
            }
        }

        $type = SpecimenType::tryFrom((string) $options['type']);
        if ($type === null) {
            throw new InvalidArgumentException('El tipo debe ser blood, urine o swab.');
        }

        $request = new ReceptionRequest(
            orderReference: (string) $options['order'],
            patientReference: (string) $options['patient'],
            examCode: (string) $options['exam'],
            type: $type,
            labelPresent: $this->parseBoolean((string) $options['label'], 'label'),
            containerIntact: $this->parseBoolean((string) $options['container'], 'container'),
            sealIntact: $this->parseBoolean((string) $options['seal'], 'seal'),
            rejectionReason: isset($options['reject']) && $options['reject'] !== false
                ? (string) $options['reject']
                : null,
        );

        $reception = $this->useCase->execute($request);
        echo sprintf(
            "%s: identificador=%s orden=%s estado=%s\n",
            $reception->status === ReceptionStatus::ACCEPTED ? 'ACEPTADA' : 'RECHAZADA',
            $reception->identifier,
            $reception->orderReference,
            $reception->status->value,
        );

        foreach ($reception->reasons as $reason) {
            echo "Motivo: {$reason}\n";
        }

        return 0;
    }

    private function parseBoolean(string $value, string $name): bool
    {
        return match ($value) {
            'yes' => true,
            'no' => false,
            default => throw new InvalidArgumentException("--{$name} debe ser yes o no."),
        };
    }
}
