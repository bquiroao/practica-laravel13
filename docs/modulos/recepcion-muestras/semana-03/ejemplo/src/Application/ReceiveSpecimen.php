<?php

declare(strict_types=1);

namespace MicroHis\Application;

use DateTimeImmutable;
use MicroHis\Domain\ReceptionRequest;
use MicroHis\Domain\SpecimenAcceptancePolicy;
use MicroHis\Domain\SpecimenReception;

final class ReceiveSpecimen
{
    public function __construct(
        private SpecimenAcceptancePolicy $policy,
        private SpecimenRepository $repository,
        private SpecimenIdentifierGenerator $identifierGenerator,
    ) {
    }

    public function execute(ReceptionRequest $request): SpecimenReception
    {
        $decision = $this->policy->decide($request);
        $reception = new SpecimenReception(
            identifier: $this->identifierGenerator->generate(),
            orderReference: $request->orderReference,
            patientReference: $request->patientReference,
            examCode: $request->examCode,
            type: $request->type,
            status: $decision->status,
            reasons: $decision->reasons,
            receivedAt: new DateTimeImmutable(),
        );

        $this->repository->save($reception);

        return $reception;
    }
}
