<?php

declare(strict_types=1);
namespace OrdersWeek4\Persistence;
use OrdersWeek3\Domain\{Order,Repository};
final class InMemoryRepository implements Repository
{
    private array $orders=[];
    public function save(Order $order):int
    {
        $id=count($this->orders)+1;
        $this->orders[$id]=['id'=>$id,'actor'=>$order->actor,'patient'=>$order->patient,'encounter'=>$order->encounter,'status'=>$order->status,'tests'=>$order->tests];
        return $id;
    }
    public function find(string $patient,string $encounter):array
    {
        return array_values(array_filter($this->orders,fn(array $o)=>$o['patient']===$patient&&$o['encounter']===$encounter));
    }
}