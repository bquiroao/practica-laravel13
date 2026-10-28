<?php
declare(strict_types=1);
final class PdoLabResultDecisionRepository implements LabResultDecisionRepository
{
    public function __construct(private PDO $pdo) {}
    public function findById(string $resultId, string $hospitalId): ?LabResult
    {
        try {
            $s = $this->pdo->prepare('SELECT id,hospital_id,order_item_id,examination_id,specimen_id,version,value,unit,status FROM lab_result WHERE id = :id AND hospital_id = :hospital LIMIT 1');
            $s->execute([':id' => $resultId, ':hospital' => $hospitalId]); $r = $s->fetch(PDO::FETCH_ASSOC);
            return $r === false ? null : $this->map($r);
        } catch (PDOException $e) { throw new PersistenceFailure("No se pudo consultar el resultado", 0, $e); }
    }
    public function decideAtomically(ReviewDecisionRecord $record): DecisionWriteStatus
    {
        try {
            $this->pdo->beginTransaction();
            $s = $this->pdo->prepare('SELECT version,status FROM lab_result WHERE id=:id AND hospital_id=:hospital');
            $s->execute([':id'=>$record->resultId, ':hospital'=>$record->hospitalId]); $current=$s->fetch(PDO::FETCH_ASSOC);
            if ($current === false) { $this->pdo->rollBack(); return DecisionWriteStatus::NOT_FOUND; }
            if ((int)$current['version'] !== $record->expectedVersion || $current['status'] !== LabResultStatus::PENDING->value) { $this->pdo->rollBack(); return DecisionWriteStatus::CONFLICT; }
            $u=$this->pdo->prepare("UPDATE lab_result SET status=:new_status WHERE id=:id AND hospital_id=:hospital AND version=:version AND status='PENDING'");
            $u->execute([':new_status'=>$record->decision===ReviewDecision::VALIDATE ? 'VALIDATED':'REJECTED', ':id'=>$record->resultId, ':hospital'=>$record->hospitalId, ':version'=>$record->expectedVersion]);
            if ($u->rowCount() !== 1) { $this->pdo->rollBack(); return DecisionWriteStatus::CONFLICT; }
            $i=$this->pdo->prepare('INSERT INTO lab_result_decision (result_id,version,hospital_id,actor_id,decision,reason,decided_at) VALUES (:id,:version,:hospital,:actor,:decision,:reason,:at)');
            $i->execute([':id'=>$record->resultId, ':version'=>$record->expectedVersion, ':hospital'=>$record->hospitalId, ':actor'=>$record->actorId, ':decision'=>$record->decision->value, ':reason'=>$record->reason, ':at'=>$record->decidedAt->format(DateTimeInterface::ATOM)]);
            $this->pdo->commit(); return DecisionWriteStatus::SAVED;
        } catch (PDOException) { if ($this->pdo->inTransaction()) $this->pdo->rollBack(); return DecisionWriteStatus::FAILED; }
    }
    private function map(array $r): LabResult { return new LabResult((string)$r['id'],(string)$r['hospital_id'],(string)$r['order_item_id'],(string)$r['examination_id'],(string)$r['specimen_id'],(int)$r['version'],(string)$r['value'],(string)$r['unit'],LabResultStatus::from((string)$r['status'])); }
}