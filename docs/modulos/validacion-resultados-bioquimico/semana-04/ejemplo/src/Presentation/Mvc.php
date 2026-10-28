<?php

declare(strict_types=1);

final readonly class ReviewHttpRequest
{
    /**
     * @param array<string, mixed> $body
     */
    public function __construct(
        public string $method,
        public string $path,
        public array $body,
        public string $actorId,
        public string $hospitalId,
    ) {
    }
}

final readonly class ReviewHttpResponse
{
    /**
     * @param array<string, mixed> $body
     */
    public function __construct(
        public int $statusCode,
        public array $body,
    ) {
    }
}

final class ReviewJsonView
{
    public function render(ReviewOutcome $outcome): ReviewHttpResponse
    {
        $statusCode = match ($outcome->status) {
            ReviewOutcomeStatus::VALIDATED, ReviewOutcomeStatus::REJECTED => 200,
            ReviewOutcomeStatus::DENIED => 403,
            ReviewOutcomeStatus::INVALID_INPUT, ReviewOutcomeStatus::INVALID_CONTEXT => 422,
            ReviewOutcomeStatus::NOT_FOUND => 404,
            ReviewOutcomeStatus::INVALID_STATE, ReviewOutcomeStatus::CONFLICT => 409,
            ReviewOutcomeStatus::PERSISTENCE_FAILURE => 503,
        };

        $body = ['status' => $outcome->status->value];
        if ($outcome->record !== null) {
            $body['decision'] = [
                'resultId' => $outcome->record->resultId,
                'version' => $outcome->record->expectedVersion,
                'hospitalId' => $outcome->record->hospitalId,
                'actorId' => $outcome->record->actorId,
                'decision' => $outcome->record->decision->value,
                'reason' => $outcome->record->reason,
                'decidedAt' => $outcome->record->decidedAt->format(DATE_ATOM),
            ];
        }

        return new ReviewHttpResponse($statusCode, $body);
    }
}

final class ReviewController
{
    public function __construct(
        private ReviewPendingLabResult $review,
        private ReviewJsonView $view,
    ) {
    }

    public function decide(ReviewHttpRequest $request, string $resultId): ReviewHttpResponse
    {
        $expectedVersion = filter_var(
            $request->body['expectedVersion'] ?? null,
            FILTER_VALIDATE_INT,
        );
        $decisionValue = $request->body['decision'] ?? null;

        if (
            $request->method !== 'POST'
            || trim($request->actorId) === ''
            || trim($request->hospitalId) === ''
            || !is_int($expectedVersion)
            || !is_string($decisionValue)
            || ($decision = ReviewDecision::tryFrom($decisionValue)) === null
        ) {
            return new ReviewHttpResponse(422, ['status' => ReviewOutcomeStatus::INVALID_INPUT->value]);
        }

        $reason = $request->body['reason'] ?? '';
        if (!is_string($reason)) {
            return new ReviewHttpResponse(422, ['status' => ReviewOutcomeStatus::INVALID_INPUT->value]);
        }

        try {
            $outcome = $this->review->execute(new ReviewCommand(
                resultId: $resultId,
                expectedVersion: $expectedVersion,
                actorId: $request->actorId,
                hospitalId: $request->hospitalId,
                decision: $decision,
                reason: $reason,
            ));
        } catch (ReviewRepositoryUnavailable) {
            return new ReviewHttpResponse(
                503,
                ['status' => ReviewOutcomeStatus::PERSISTENCE_FAILURE->value],
            );
        }

        return $this->view->render($outcome);
    }
}

final class ReviewRouter
{
    public function __construct(private ReviewController $controller)
    {
    }

    public function dispatch(ReviewHttpRequest $request): ReviewHttpResponse
    {
        if (!preg_match('#^/results/([^/]+)/decision$#', $request->path, $matches)) {
            return new ReviewHttpResponse(404, ['status' => 'NOT_FOUND']);
        }

        if ($request->method !== 'POST') {
            return new ReviewHttpResponse(405, ['status' => 'METHOD_NOT_ALLOWED']);
        }

        return $this->controller->decide($request, rawurldecode($matches[1]));
    }
}
