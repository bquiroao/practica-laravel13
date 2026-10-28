<?php

declare(strict_types=1);
namespace OrdersWeek3\Application;
use OrdersWeek3\Domain\{Order,Repository,ClinicalRules,PersistenceFailure};
final class Issue
{
    public function __construct(private ClinicalRules $rules,private Repository $repository) {}
    public function execute(string $actor,string $patient,string $encounter,array $tests): array
    {
        if(trim($actor)===''||trim($patient)===''||trim($encounter)==='')return ['status'=>'INVALID_REQUEST'];
        try {
            if(!$this->rules->authorized($actor,$patient,$encounter))return ['status'=>'FORBIDDEN'];
            if(!$this->rules->contextValid($patient,$encounter))return ['status'=>'INVALID_CONTEXT'];
            $order=new Order($actor,$patient,$encounter,$tests,$this->rules->initialStatus());
            if(!$this->rules->testsAvailable($tests))return ['status'=>'INVALID_TESTS'];
        } catch(\DomainException) {return ['status'=>'INVALID_REQUEST'];}
        catch(\Throwable) {return ['status'=>'DEPENDENCY_UNAVAILABLE'];}
        try {return ['status'=>'CREATED','id'=>$this->repository->save($order)];}
        catch(PersistenceFailure) {return ['status'=>'PERSISTENCE_FAILURE'];}
    }
}
final class Find
{
    public function __construct(private ClinicalRules $rules,private Repository $repository) {}
    public function execute(string $actor,string $patient,string $encounter): array
    {
        if(trim($actor)===''||trim($patient)===''||trim($encounter)==='')return ['status'=>'INVALID_REQUEST'];
        try {if(!$this->rules->authorized($actor,$patient,$encounter))return ['status'=>'FORBIDDEN'];}
        catch(\Throwable) {return ['status'=>'DEPENDENCY_UNAVAILABLE'];}
        try {$orders=$this->repository->find($patient,$encounter);return ['status'=>$orders===[]?'EMPTY':'FOUND','orders'=>$orders];}
        catch(PersistenceFailure) {return ['status'=>'UNAVAILABLE'];}
    }
}