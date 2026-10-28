<?php

declare(strict_types=1);

enum Decision
{
    case ALLOWED;
    case DENIED;
    case UNAVAILABLE;
}

enum ValidationDecision
{
    case VALID;
    case INVALID;
    case UNAVAILABLE;
}

enum IssueStatus
{
    case CREATED;
    case INVALID_REQUEST;
    case FORBIDDEN;
    case INVALID_CONTEXT;
    case INVALID_TESTS;
    case PERSISTENCE_FAILURE;
    case UNAVAILABLE;
}

enum SearchStatus
{
    case FOUND;
    case EMPTY;
    case FORBIDDEN;
    case UNAVAILABLE;
}

enum UpdateStatus
{
    case UPDATED;
    case REJECTED;
    case NOT_FOUND;
    case CONFLICT;
    case PERSISTENCE_FAILURE;
    case UNAVAILABLE;
}

enum WriteState
{
    case SAVED;
    case UPDATED;
    case NOT_FOUND;
    case CONFLICT;
    case UNAVAILABLE;
}

enum LookupState
{
    case FOUND;
    case NOT_FOUND;
    case UNAVAILABLE;
}

final readonly class IssueOrderCommand
{
    /**
     * @param list<string> $testCodes
     */
    public function __construct(
        public string $actorId,
        public string $patientId,
        public string $encounterId,
        public array $testCodes,
    ) {
    }
}

final readonly class NewLabOrder
{
    /**
     * @param list<string> $testCodes
     */
    public function __construct(
        public string $patientId,
        public string $encounterId,
        public string $requestedBy,
        public array $testCodes,
        public string $status,
    ) {
    }
}

final readonly class LabOrder
{
    /**
     * @param list<string> $testCodes
     */
    public function __construct(
        public string $id,
        public string $patientId,
        public string $encounterId,
        public string $requestedBy,
        public array $testCodes,
        public string $status,
    ) {
    }
}

final readonly class IssueResult
{
    public function __construct(
        public IssueStatus $status,
        public ?LabOrder $order = null,
    ) {
    }
}

final readonly class SearchResult
{
    /**
     * @param list<LabOrder> $orders
     */
    public function __construct(
        public SearchStatus $status,
        public array $orders = [],
    ) {
    }
}

final readonly class WriteResult
{
    public function __construct(
        public WriteState $state,
        public ?LabOrder $order = null,
    ) {
    }
}

final readonly class OrderLookupResult
{
    public function __construct(
        public LookupState $state,
        public ?LabOrder $order = null,
    ) {
    }
}

interface ClinicalAuthorization
{
    public function authorize(string $actorId, string $patientId, string $encounterId): Decision;
}

interface ClinicalContextValidator
{
    public function validateContext(string $patientId, string $encounterId): ValidationDecision;
}

interface LabCatalog
{
    /**
     * @param list<string> $testCodes
     */
    public function validateTests(array $testCodes): ValidationDecision;
}

interface OrderLifecyclePolicy
{
    public function initialStatus(): string;

    public function allowsTransition(string $current, string $next): bool;
}

interface StatusUpdateSourceAuthorization
{
    public function isTrusted(string $source): bool;
}

interface LabOrderRepository
{
    public function createAtomically(NewLabOrder $order): WriteResult;

    public function findByPatientEncounter(string $patientId, string $encounterId): SearchResult;

    public function findById(string $orderId): OrderLookupResult;

    public function updateStatus(string $orderId, string $expectedStatus, string $nextStatus): WriteState;
}

final class IssueLabOrder
{
    public function __construct(
        private ClinicalAuthorization $authorization,
        private ClinicalContextValidator $context,
        private LabCatalog $catalog,
        private OrderLifecyclePolicy $lifecycle,
        private LabOrderRepository $orders,
    ) {
    }

    public function execute(IssueOrderCommand $command): IssueResult
    {
        if (
            trim($command->actorId) === ''
            || trim($command->patientId) === ''
            || trim($command->encounterId) === ''
            || $command->testCodes === []
        ) {
            return new IssueResult(IssueStatus::INVALID_REQUEST);
        }
        foreach ($command->testCodes as $testCode) {
            if (trim($testCode) === '') {
                return new IssueResult(IssueStatus::INVALID_REQUEST);
            }
        }

        $authorization = $this->authorization->authorize(
            $command->actorId,
            $command->patientId,
            $command->encounterId,
        );
        if ($authorization === Decision::DENIED) {
            return new IssueResult(IssueStatus::FORBIDDEN);
        }
        if ($authorization === Decision::UNAVAILABLE) {
            return new IssueResult(IssueStatus::UNAVAILABLE);
        }

        $context = $this->context->validateContext($command->patientId, $command->encounterId);
        if ($context === ValidationDecision::INVALID) {
            return new IssueResult(IssueStatus::INVALID_CONTEXT);
        }
        if ($context === ValidationDecision::UNAVAILABLE) {
            return new IssueResult(IssueStatus::UNAVAILABLE);
        }

        $catalog = $this->catalog->validateTests($command->testCodes);
        if ($catalog === ValidationDecision::INVALID) {
            return new IssueResult(IssueStatus::INVALID_TESTS);
        }
        if ($catalog === ValidationDecision::UNAVAILABLE) {
            return new IssueResult(IssueStatus::UNAVAILABLE);
        }

        $write = $this->orders->createAtomically(new NewLabOrder(
            $command->patientId,
            $command->encounterId,
            $command->actorId,
            $command->testCodes,
            $this->lifecycle->initialStatus(),
        ));

        return $write->state === WriteState::SAVED && $write->order !== null
            ? new IssueResult(IssueStatus::CREATED, $write->order)
            : new IssueResult(IssueStatus::PERSISTENCE_FAILURE);
    }
}

final class FindLabOrders
{
    public function __construct(
        private ClinicalAuthorization $authorization,
        private LabOrderRepository $orders,
    ) {
    }

    public function execute(string $actorId, string $patientId, string $encounterId): SearchResult
    {
        if (trim($actorId) === '' || trim($patientId) === '' || trim($encounterId) === '') {
            return new SearchResult(SearchStatus::UNAVAILABLE);
        }

        $authorization = $this->authorization->authorize($actorId, $patientId, $encounterId);
        if ($authorization === Decision::DENIED) {
            return new SearchResult(SearchStatus::FORBIDDEN);
        }
        if ($authorization === Decision::UNAVAILABLE) {
            return new SearchResult(SearchStatus::UNAVAILABLE);
        }

        return $this->orders->findByPatientEncounter($patientId, $encounterId);
    }
}

final class ApplyLabOrderStatus
{
    public function __construct(
        private StatusUpdateSourceAuthorization $sources,
        private OrderLifecyclePolicy $lifecycle,
        private LabOrderRepository $orders,
    ) {
    }

    public function execute(string $source, string $orderId, string $nextStatus): UpdateStatus
    {
        if (trim($source) === '' || trim($orderId) === '' || trim($nextStatus) === '') {
            return UpdateStatus::REJECTED;
        }
        if (!$this->sources->isTrusted($source)) {
            return UpdateStatus::REJECTED;
        }

        $lookup = $this->orders->findById($orderId);
        if ($lookup->state === LookupState::NOT_FOUND) {
            return UpdateStatus::NOT_FOUND;
        }
        if ($lookup->state === LookupState::UNAVAILABLE || $lookup->order === null) {
            return UpdateStatus::UNAVAILABLE;
        }
        if (!$this->lifecycle->allowsTransition($lookup->order->status, $nextStatus)) {
            return UpdateStatus::REJECTED;
        }

        return match ($this->orders->updateStatus($orderId, $lookup->order->status, $nextStatus)) {
            WriteState::UPDATED => UpdateStatus::UPDATED,
            WriteState::NOT_FOUND => UpdateStatus::NOT_FOUND,
            WriteState::CONFLICT => UpdateStatus::CONFLICT,
            WriteState::UNAVAILABLE, WriteState::SAVED => UpdateStatus::PERSISTENCE_FAILURE,
        };
    }
}
