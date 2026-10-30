<?php

declare(strict_types=1);
namespace OrdersWeek3\Domain;
final readonly class Order
{
    /** @param list<string> $tests */
    public function __construct(public string $actor,public string $patient,public string $encounter,public array $tests,public string $status)
    {
        if(trim($actor)===''||trim($patient)===''||trim($encounter)===''||$tests===[]||trim($status)==='')throw new \DomainException('Orden incompleta.');
        foreach($tests as $code)if(!is_string($code)||trim($code)==='')throw new \DomainException('Prueba inválida.');
    }
}
final class PersistenceFailure extends \RuntimeException {}
interface Repository
{
    public function save(Order $order): int;
    /** @return list<array<string,mixed>> */
    public function find(string $patient,string $encounter): array;
}
interface ClinicalRules
{
    public function authorized(string $actor,string $patient,string $encounter): bool;
    public function contextValid(string $patient,string $encounter): bool;
    /** @param list<string> $codes */
    public function testsAvailable(array $codes): bool;
    public function initialStatus(): string;
}