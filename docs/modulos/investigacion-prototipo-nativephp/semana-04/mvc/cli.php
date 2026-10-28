<?php

declare(strict_types=1);
require_once __DIR__.'/bootstrap.php';
use NativeWeek3\Domain\{DemoOrderGateway,Notifier};
use NativeWeek3\Application\Evaluate;
use NativeWeek3\Persistence\PdoEvaluationRepository;
use NativeWeek4\Persistence\InMemoryEvaluationRepository;
use NativeWeek4\Presentation\{EvaluationController,JsonView};

// Composition root: selecciona infraestructura; controlador no conoce PDO.
$mode=$argv[1]??'memory';
if(!in_array($mode,['memory','pdo'],true)){fwrite(STDERR,"Usa memory o pdo.\n");exit(2);}
if($mode==='pdo'){
    $pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
    $pdo->exec('PRAGMA foreign_keys=ON');
    $pdo->exec(file_get_contents(__DIR__.'/../../semana-03/micro-his/schema.sql'));
    $repository=new PdoEvaluationRepository($pdo);
}else{$repository=new InMemoryEvaluationRepository();}
$gateway=new class implements DemoOrderGateway {public function status(string $id):?string{return $id==='DEMO-1'?'PENDIENTE':null;}};
$notifier=new class implements Notifier {public function notify(string $message):string{return 'UNAVAILABLE';}};
$controller=new EvaluationController(new Evaluate($gateway,$repository,$notifier));
$model=$controller->handle(['demoOrderId'=>$argv[2]??'DEMO-1','target'=>$argv[3]??'CONTRACT_MOBILE']);
echo (new JsonView())->render($model);
exit($model['status']==='RECORDED'?0:1);