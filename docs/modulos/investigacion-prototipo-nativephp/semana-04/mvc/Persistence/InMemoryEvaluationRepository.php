<?php

declare(strict_types=1);
namespace NativeWeek4\Persistence;
use NativeWeek3\Domain\{Evaluation,EvaluationRepository};
/** Adaptador volátil; no afirma durabilidad ni soporte de múltiples procesos. */
final class InMemoryEvaluationRepository implements EvaluationRepository
{
    /** @var array<int,Evaluation> */
    private array $evaluations=[];
    private array $events=[];
    public function save(Evaluation $evaluation): int
    {
        $id=count($this->evaluations)+1;
        $this->evaluations[$id]=$evaluation;
        $this->events[$id]='CONTRACT_RECORDED';
        return $id;
    }
    public function count(): int {return count($this->evaluations);}
}