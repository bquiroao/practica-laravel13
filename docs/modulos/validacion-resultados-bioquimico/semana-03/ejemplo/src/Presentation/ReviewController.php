<?php
declare(strict_types=1);
final class ReviewController
{
    public function __construct(private ReviewPendingLabResult $useCase) {}
    public function validate(string $resultId,int $expectedVersion,string $actorId,string $hospitalId): array { return $this->respond($this->useCase->execute(new ReviewCommand($resultId,$expectedVersion,$actorId,$hospitalId,ReviewDecision::VALIDATE))); }
    public function reject(string $resultId,int $expectedVersion,string $actorId,string $hospitalId,string $reason): array { return $this->respond($this->useCase->execute(new ReviewCommand($resultId,$expectedVersion,$actorId,$hospitalId,ReviewDecision::REJECT,$reason))); }
    private function respond(ReviewOutcome $outcome): array
    {
        $status=$outcome->status; $code=match($status){ReviewOutcomeStatus::VALIDATED,ReviewOutcomeStatus::REJECTED=>200,ReviewOutcomeStatus::DENIED=>403,ReviewOutcomeStatus::NOT_FOUND=>404,ReviewOutcomeStatus::INVALID_INPUT,ReviewOutcomeStatus::INVALID_CONTEXT=>422,ReviewOutcomeStatus::INVALID_STATE,ReviewOutcomeStatus::CONFLICT=>409,ReviewOutcomeStatus::PERSISTENCE_FAILURE=>503};
        $body=['status'=>$status->value];
        if($outcome->record!==null) $body['decision']=['result_id'=>$outcome->record->resultId,'version'=>$outcome->record->expectedVersion,'actor_id'=>$outcome->record->actorId,'decision'=>$outcome->record->decision->value,'decided_at'=>$outcome->record->decidedAt->format(DateTimeInterface::ATOM)];
        return ['status'=>$code,'body'=>$body];
    }
}