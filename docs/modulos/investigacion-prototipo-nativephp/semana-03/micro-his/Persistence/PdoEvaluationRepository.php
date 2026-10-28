<?php

declare(strict_types=1);
namespace NativeWeek3\Persistence;
use NativeWeek3\Domain\{Evaluation,EvaluationRepository,PersistenceFailure};

final class PdoEvaluationRepository implements EvaluationRepository
{
    public function __construct(private \PDO $pdo) {}
    public function save(Evaluation $evaluation): int
    {
        try {
            $this->pdo->beginTransaction();
            $stmt=$this->pdo->prepare('INSERT INTO evaluations (demo_order_id,target,order_status) VALUES (:demo,:target,:status)');
            $stmt->execute(['demo'=>$evaluation->demoOrderId,'target'=>$evaluation->target,'status'=>$evaluation->orderStatus]);
            $id=(int)$this->pdo->lastInsertId();
            $audit=$this->pdo->prepare('INSERT INTO evaluation_events (evaluation_id,event) VALUES (:id,:event)');
            $audit->execute(['id'=>$id,'event'=>'CONTRACT_RECORDED']);
            $this->pdo->commit();
            return $id;
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw new PersistenceFailure('No se confirmó evidencia.',0,$e);
        }
    }
}