<?php

declare(strict_types=1);

namespace MicroHis\Persistence;

use PDO;
use MicroHis\Application\SpecimenRepository;
use MicroHis\Domain\SpecimenReception;

final class PdoSpecimenRepository implements SpecimenRepository
{
    public function __construct(private PDO $connection)
    {
    }

    public function save(SpecimenReception $reception): void
    {
        $statement = $this->connection->prepare(
            'INSERT INTO specimen_receptions
                (identifier, order_reference, patient_reference, exam_code, specimen_type, status, rejection_reasons, received_at)
             VALUES
                (:identifier, :order_reference, :patient_reference, :exam_code, :specimen_type, :status, :rejection_reasons, :received_at)',
        );

        $statement->execute([
            'identifier' => $reception->identifier,
            'order_reference' => $reception->orderReference,
            'patient_reference' => $reception->patientReference,
            'exam_code' => $reception->examCode,
            'specimen_type' => $reception->type->value,
            'status' => $reception->status->value,
            'rejection_reasons' => json_encode($reception->reasons, JSON_THROW_ON_ERROR),
            'received_at' => $reception->receivedAt->format(DATE_ATOM),
        ]);
    }
}
