<?php

declare(strict_types=1);

require_once __DIR__ . '/../../semana-02/ejemplo-srp/src/LabOrderWorkflow.php';

final class OrdersWorkflowDouble implements
    ClinicalAuthorization,
    ClinicalContextValidator,
    LabCatalog,
    OrderLifecyclePolicy,
    StatusUpdateSourceAuthorization,
    LabOrderRepository
{
    public Decision $authorizationDecision = Decision::ALLOWED;
    public ValidationDecision $contextDecision = ValidationDecision::VALID;
    public ValidationDecision $catalogDecision = ValidationDecision::VALID;
    public bool $sourceTrusted = true;
    public bool $transitionAllowed = true;
    public string $initial = 'TEST_INITIAL';
    public WriteResult $createResult;
    public SearchResult $searchResult;
    public OrderLookupResult $lookupResult;
    public WriteState $updateResult = WriteState::UPDATED;
    public ?LabOrder $lastCreated = null;
    public ?array $lastSearch = null;
    public ?array $lastUpdate = null;
    public int $authorizationCalls = 0;
    public int $contextCalls = 0;
    public int $catalogCalls = 0;
    public int $trustedSourceCalls = 0;
    public int $transitionCalls = 0;
    public int $createCalls = 0;
    public int $searchCalls = 0;
    public int $lookupCalls = 0;
    public int $updateCalls = 0;

    public function __construct()
    {
        $this->createResult = new WriteResult(WriteState::SAVED, $this->order());
        $this->searchResult = new SearchResult(SearchStatus::EMPTY);
        $this->lookupResult = new OrderLookupResult(LookupState::FOUND, $this->order());
    }

    public function authorize(string $actorId, string $patientId, string $encounterId): Decision
    {
        $this->authorizationCalls++;

        return $this->authorizationDecision;
    }

    public function validateContext(string $patientId, string $encounterId): ValidationDecision
    {
        $this->contextCalls++;

        return $this->contextDecision;
    }

    public function validateTests(array $testCodes): ValidationDecision
    {
        $this->catalogCalls++;

        return $this->catalogDecision;
    }

    public function initialStatus(): string
    {
        return $this->initial;
    }

    public function allowsTransition(string $current, string $next): bool
    {
        $this->transitionCalls++;

        return $this->transitionAllowed;
    }

    public function isTrusted(string $source): bool
    {
        $this->trustedSourceCalls++;

        return $this->sourceTrusted;
    }

    public function createAtomically(NewLabOrder $order): WriteResult
    {
        $this->createCalls++;
        $this->lastCreated = new LabOrder(
            'ORDER-TEST-001',
            $order->patientId,
            $order->encounterId,
            $order->requestedBy,
            $order->testCodes,
            $order->status,
        );

        return $this->createResult->state === WriteState::SAVED
            ? new WriteResult(WriteState::SAVED, $this->lastCreated)
            : $this->createResult;
    }

    public function findByPatientEncounter(string $patientId, string $encounterId): SearchResult
    {
        $this->searchCalls++;
        $this->lastSearch = [$patientId, $encounterId];

        return $this->searchResult;
    }

    public function findById(string $orderId): OrderLookupResult
    {
        $this->lookupCalls++;

        return $this->lookupResult;
    }

    public function updateStatus(string $orderId, string $expectedStatus, string $nextStatus): WriteState
    {
        $this->updateCalls++;
        $this->lastUpdate = [$orderId, $expectedStatus, $nextStatus];

        return $this->updateResult;
    }

    public function order(
        string $id = 'ORDER-TEST-001',
        string $patientId = 'PATIENT-1',
        string $encounterId = 'ENCOUNTER-1',
        string $requestedBy = 'CLINICIAN-1',
        array $testCodes = ['TEST-CBC'],
        string $status = 'TEST_INITIAL',
    ): LabOrder {
        return new LabOrder($id, $patientId, $encounterId, $requestedBy, $testCodes, $status);
    }
}

function issueCommand(
    string $actorId = 'CLINICIAN-1',
    string $patientId = 'PATIENT-1',
    string $encounterId = 'ENCOUNTER-1',
    array $testCodes = ['TEST-CBC'],
): IssueOrderCommand {
    return new IssueOrderCommand($actorId, $patientId, $encounterId, $testCodes);
}

function issueWorkflow(OrdersWorkflowDouble $double): IssueLabOrder
{
    return new IssueLabOrder($double, $double, $double, $double, $double);
}

function searchWorkflow(OrdersWorkflowDouble $double): FindLabOrders
{
    return new FindLabOrders($double, $double);
}

function updateWorkflow(OrdersWorkflowDouble $double): ApplyLabOrderStatus
{
    return new ApplyLabOrderStatus($double, $double, $double);
}
