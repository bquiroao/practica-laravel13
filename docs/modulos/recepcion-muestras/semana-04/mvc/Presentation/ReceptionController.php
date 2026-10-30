<?php

declare(strict_types=1);
namespace ReceptionWeek4\Presentation;
use MicroHis\Application\ReceiveSpecimen;
use MicroHis\Domain\{ReceptionRequest,SpecimenType};
final class ReceptionController
{
    public function __construct(private ReceiveSpecimen $receive) {}
    public function handle(array $input):array
    {
        foreach(['order','patient','exam','type'] as $field)if(!isset($input[$field])||!is_string($input[$field]))return ['status'=>'INVALID_INPUT'];
        foreach(['label','container','seal'] as $field)if(!isset($input[$field])||!is_bool($input[$field]))return ['status'=>'INVALID_INPUT'];
        if(isset($input['reason'])&&!is_string($input['reason']))return ['status'=>'INVALID_INPUT'];
        $type=SpecimenType::tryFrom($input['type']);
        if($type===null)return ['status'=>'INVALID_INPUT'];
        try {
            $request=new ReceptionRequest($input['order'],$input['patient'],$input['exam'],$type,$input['label'],$input['container'],$input['seal'],$input['reason']??null);
            $received=$this->receive->execute($request);
            return ['status'=>'RECORDED','identifier'=>$received->identifier,'decision'=>$received->status->value,'reasons'=>$received->reasons];
        }catch(\InvalidArgumentException){return ['status'=>'INVALID_INPUT'];}
        catch(\RuntimeException){return ['status'=>'OPERATION_UNAVAILABLE'];}
    }
}