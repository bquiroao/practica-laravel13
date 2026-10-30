<?php
declare(strict_types=1);
require_once __DIR__ . '/src/Domain/ReviewModel.php';
require_once __DIR__ . '/src/Application/ReviewPendingLabResult.php';
require_once __DIR__ . '/src/Persistence/InMemoryLabResultDecisionRepository.php';
require_once __DIR__ . '/src/Persistence/PdoLabResultDecisionRepository.php';
require_once __DIR__ . '/src/Presentation/ReviewController.php';

final class SystemClock implements Clock { public function now(): DateTimeImmutable { return new DateTimeImmutable('now'); } }
final class RoleBasedReviewerAuthorization implements ReviewerAuthorization
{
    /** @param array<string,array<string,string>> $roles */
    public function __construct(private array $roles) {}
    public function canReview(string $actorId, string $hospitalId): bool { return ($this->roles[$actorId][$hospitalId] ?? null) === 'BIOCHEMIST'; }
}