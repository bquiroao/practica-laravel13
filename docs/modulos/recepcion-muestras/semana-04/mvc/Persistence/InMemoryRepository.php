<?php

declare(strict_types=1);
namespace ReceptionWeek4\Persistence;
use MicroHis\Application\SpecimenRepository;
use MicroHis\Domain\SpecimenReception;
final class InMemoryRepository implements SpecimenRepository
{
    /** @var array<string,SpecimenReception> */
    private array $saved=[];
    public function save(SpecimenReception $reception):void
    {
        if(isset($this->saved[$reception->identifier]))throw new \RuntimeException('Identificador duplicado.');
        $this->saved[$reception->identifier]=$reception;
    }
    public function count():int{return count($this->saved);}
}