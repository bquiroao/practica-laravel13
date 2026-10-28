<?php

declare(strict_types=1);
namespace OrdersWeek4\Presentation;
use OrdersWeek3\Application\{Issue,Find};
final class OrderController
{
    public function __construct(private Issue $issue,private Find $find) {}
    /** Actor proviene de contexto confiable en integración; aquí es fixture CLI. */
    public function issue(string $actor,array $input):array
    {
        if(!$this->contextShape($input)||!isset($input['tests'])||!is_array($input['tests'])||!array_is_list($input['tests']))return ['status'=>'INVALID_INPUT'];
        foreach($input['tests'] as $code)if(!is_string($code))return ['status'=>'INVALID_INPUT'];
        return $this->issue->execute($actor,$input['patient'],$input['encounter'],$input['tests']);
    }
    public function find(string $actor,array $input):array
    {
        if(!$this->contextShape($input))return ['status'=>'INVALID_INPUT'];
        return $this->find->execute($actor,$input['patient'],$input['encounter']);
    }
    private function contextShape(array $input):bool
    {
        return isset($input['patient'],$input['encounter'])&&is_string($input['patient'])&&is_string($input['encounter']);
    }
}