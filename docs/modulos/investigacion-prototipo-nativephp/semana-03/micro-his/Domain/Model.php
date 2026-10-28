<?php

declare(strict_types=1);
namespace NativeWeek3\Domain;

final readonly class Evaluation
{
    public function __construct(public string $demoOrderId, public string $target, public string $orderStatus)
    {
        if (!preg_match('/^DEMO-[A-Za-z0-9-]{1,59}$/D', $demoOrderId)) {
            throw new \DomainException('Solo identificadores ficticios DEMO-.');
        }
        if (!in_array($target, ['CONTRACT_DESKTOP','CONTRACT_MOBILE'], true)) {
            throw new \DomainException('Destino de contrato no permitido.');
        }
        if (!in_array($orderStatus, ['PENDIENTE','RECIBIDA','EN_PROCESO','COMPLETADA'], true)) {
            throw new \DomainException('Estado demo desconocido: no notificar.');
        }
    }
}
final class PersistenceFailure extends \RuntimeException {}
interface EvaluationRepository
{
    public function save(Evaluation $evaluation): int;
}
interface DemoOrderGateway
{
    public function status(string $demoOrderId): ?string;
}
interface Notifier
{
    /** SENT, UNAVAILABLE o UNKNOWN; sin identificadores ni datos clínicos. */
    public function notify(string $message): string;
}