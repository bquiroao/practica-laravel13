<?php

declare(strict_types=1);
namespace NativeWeek3\Application;
use NativeWeek3\Domain\{Evaluation,EvaluationRepository,DemoOrderGateway,Notifier,PersistenceFailure};

final class Evaluate
{
    public function __construct(private DemoOrderGateway $gateway, private EvaluationRepository $repository, private Notifier $notifier) {}
    /** @return array{status:string,evidenceId?:int,notification?:string} */
    public function execute(string $id, string $target): array
    {
        try {
            // Valida entrada antes de consultar infraestructura; estado provisional reconocido.
            new Evaluation($id, $target, 'PENDIENTE');
            try { $status=$this->gateway->status($id); }
            catch (\Throwable) { return ['status'=>'HIS_UNAVAILABLE']; }
            if ($status === null) { return ['status'=>'HIS_UNAVAILABLE']; }
            $evaluation=new Evaluation($id,$target,$status);
            $evidenceId=$this->repository->save($evaluation);
        } catch (\DomainException) {
            return ['status'=>'INVALID_EVALUATION'];
        } catch (PersistenceFailure) {
            return ['status'=>'PERSISTENCE_FAILURE'];
        }
        // No efecto nativo si la evidencia no se confirmó. No hay transacción distribuida.
        try { $notification=$this->notifier->notify('Consulta de demostración finalizada: '.$status); }
        catch (\Throwable) { $notification='UNKNOWN'; }
        if (!in_array($notification,['SENT','UNAVAILABLE','UNKNOWN'],true)) { $notification='UNKNOWN'; }
        return ['status'=>'RECORDED','evidenceId'=>$evidenceId,'notification'=>$notification];
    }
}