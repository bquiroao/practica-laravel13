<?php

declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
use MicroHis\Application\ReceiveSpecimen;
use MicroHis\Domain\SpecimenAcceptancePolicy;
use MicroHis\Persistence\PdoSpecimenRepository;
use MicroHis\Infrastructure\Identity\RandomSpecimenIdentifierGenerator;
use ReceptionWeek4\Persistence\InMemoryRepository;
use ReceptionWeek4\Presentation\{ReceptionController,JsonView};
$mode=$argv[1]??'memory';$scenario=$argv[2]??'accept';
if(!in_array($mode,['memory','pdo'],true)||!in_array($scenario,['accept','reject'],true)){fwrite(STDERR,"Usa memory|pdo accept|reject.\n");exit(2);}
if($mode==='pdo'){$p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$p->exec(file_get_contents(__DIR__.'/../../semana-03/ejemplo/database/schema.sql'));$repo=new PdoSpecimenRepository($p);}else{$repo=new InMemoryRepository();}
$c=new ReceptionController(new ReceiveSpecimen(new SpecimenAcceptancePolicy(),$repo,new RandomSpecimenIdentifierGenerator()));
$model=$c->handle(['order'=>'ORD-DEMO','patient'=>'PAC-DEMO','exam'=>'TEST-DEMO','type'=>'blood','label'=>true,'container'=>$scenario==='accept','seal'=>true]);
echo (new JsonView())->render($model);exit($model['status']==='RECORDED'?0:1);