<?php

declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
use OrdersWeek3\Application\{Issue,Find};use OrdersWeek3\Persistence\PdoRepository;use OrdersWeek3\Presentation\DemoRules;
use OrdersWeek4\Persistence\InMemoryRepository;use OrdersWeek4\Presentation\{OrderController,JsonView};
$mode=$argv[1]??'memory';
if(!in_array($mode,['memory','pdo'],true)){fwrite(STDERR,"Usa memory o pdo.\n");exit(2);}
if($mode==='pdo'){$p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$p->exec('PRAGMA foreign_keys=ON');$p->exec(file_get_contents(__DIR__.'/../../semana-03/micro-his/schema.sql'));$repo=new PdoRepository($p);}else{$repo=new InMemoryRepository();}
$rules=new DemoRules();$c=new OrderController(new Issue($rules,$repo),new Find($rules,$repo));
$input=['patient'=>'PAC-DEMO','encounter'=>'EPI-DEMO','tests'=>['TEST-A','TEST-B']];
$model=['issue'=>$c->issue('MED-DEMO',$input),'followUp'=>$c->find('MED-DEMO',$input)];
echo (new JsonView())->render($model);
exit($model['issue']['status']==='CREATED'&&$model['followUp']['status']==='FOUND'?0:1);