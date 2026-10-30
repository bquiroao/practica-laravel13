<?php

declare(strict_types=1);

final class InMemoryLabOrderFixture implements
    ClinicalAuthorization,
    ClinicalContextValidator,
    LabCatalog,
    OrderLifecyclePolicy,
    StatusUpdateSourceAuthorization,
    LabOrderRepository
{
    /** @var array<string, true> */
    private array $authorizedContexts = [];

    /** @var array<string, true> */
    private array $validContexts = [];

    /** @var array<string, true> */
    private array $availableTests = [];

    /** @var array<string, true> */
    private array $trustedSources = [];

    /** @var array<string, LabOrder> */
    private array $orders = [];

    private bool $failNextWrite = false;
    private bool $failNextSearch = false;
    private bool $failNextLookup = false;
    private bool $failNextUpdate = false;
    private int $sequence = 0;

    public function authorizeContext(string $actorId, string $patientId, string $encounterId): void
    {
        $this->authorizedContexts[$this->contextKey($actorId, $patientId, $encounterId)] = true;
    }

    public function allowContext(string $patientId, string $encounterId): void
    {
        $this->validContexts[$this->patientEncounterKey($patientId, $encounterId)] = true;
    }

    public function allowTest(string $testCode): void
    {
        $this->availableTests[$testCode] = true;
    }

    public function trustSource(string $source): void
    {
        $this->trustedSources[$source] = true;
    }

    public function failNextWrite(): void
    {
        $this->failNextWrite = true;
    }

    public function failNextSearch(): void
    {
        $this->failNextSearch = true;
    }

    public function failNextLookup(): void
    {
        $this->failNextLookup = true;
    }

    public function failNextUpdate(): void
    {
        $this->failNextUpdate = true;
    }

    public function orderCount(): int
    {
        return count($this->orders);
    }

    public function authorize(string $actorId, string $patientId, string $encounterId): Decision
    {
        return isset($this->authorizedContexts[$this->contextKey($actorId, $patientId, $encounterId)])
            ? Decision::ALLOWED
            : Decision::DENIED;
    }

    public function validateContext(string $patientId, string $encounterId): ValidationDecision
    {
        return isset($this->validContexts[$this->patientEncounterKey($patientId, $encounterId)])
            ? ValidationDecision::VALID
            : ValidationDecision::INVALID;
    }

    public function validateTests(array $testCodes): ValidationDecision
    {
        foreach ($testCodes as $testCode) {
            if (!isset($this->availableTests[$testCode])) {
                return ValidationDecision::INVALID;
            }
        }

        return ValidationDecision::VALID;
    }

    public function initialStatus(): string
    {
        return 'TEST_INITIAL';
    }

    public function allowsTransition(string $current, string $next): bool
    {
        return ($current === 'TEST_INITIAL' && $next === 'TEST_NEXT')
            || ($current === 'TEST_NEXT' && $next === 'TEST_FINAL');
    }

    public function isTrusted(string $source): bool
    {
        return isset($this->trustedSources[$source]);
    }

    public function createAtomically(NewLabOrder $order): WriteResult
    {
        if ($this->failNextWrite) {
            $this->failNextWrite = false;
            return new WriteResult(WriteState::UNAVAILABLE);
        }

        $this->sequence++;
        $created = new LabOrder(
            'TEST-ORDER-' . $this->sequence,
            $order->patientId,
            $order->encounterId,
            $order->requestedBy,
            $order->testCodes,
            $order->status,
        );
        $this->orders[$created->id] = $created;

        return new WriteResult(WriteState::SAVED, $created);
    }

    public function findByPatientEncounter(string $patientId, string $encounterId): SearchResult
    {
        if ($this->failNextSearch) {
            $this->failNextSearch = false;
            return new SearchResult(SearchStatus::UNAVAILABLE);
        }

        $matches = array_values(array_filter(
            $this->orders,
            static fn (LabOrder $order): bool =>
                $order->patientId === $patientId && $order->encounterId === $encounterId,
        ));

        return $matches === []
            ? new SearchResult(SearchStatus::EMPTY)
            : new SearchResult(SearchStatus::FOUND, $matches);
    }

    public function findById(string $orderId): OrderLookupResult
    {
        if ($this->failNextLookup) {
            $this->failNextLookup = false;
            return new OrderLookupResult(LookupState::UNAVAILABLE);
        }

        $order = $this->orders[$orderId] ?? null;
        return $order === null
            ? new OrderLookupResult(LookupState::NOT_FOUND)
            : new OrderLookupResult(LookupState::FOUND, $order);
    }

    public function updateStatus(string $orderId, string $expectedStatus, string $nextStatus): WriteState
    {
        if ($this->failNextUpdate) {
            $this->failNextUpdate = false;
            return WriteState::UNAVAILABLE;
        }

        $order = $this->orders[$orderId] ?? null;
        if ($order === null) {
            return WriteState::NOT_FOUND;
        }
        if ($order->status !== $expectedStatus) {
            return WriteState::CONFLICT;
        }

        $this->orders[$orderId] = new LabOrder(
            $order->id,
            $order->patientId,
            $order->encounterId,
            $order->requestedBy,
            $order->testCodes,
            $nextStatus,
        );

        return WriteState::UPDATED;
    }

    private function contextKey(string $actorId, string $patientId, string $encounterId): string
    {
        return $actorId . '|' . $patientId . '|' . $encounterId;
    }

    private function patientEncounterKey(string $patientId, string $encounterId): string
    {
        return $patientId . '|' . $encounterId;
    }
}
