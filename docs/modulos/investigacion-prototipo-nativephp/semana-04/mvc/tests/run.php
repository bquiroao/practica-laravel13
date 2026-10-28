<?php

declare(strict_types=1);
require_once __DIR__.'/../bootstrap.php';
use NativeWeek3\Domain\{DemoOrderGateway,Notifier};
use NativeWeek3\Application\Evaluate;
use NativeWeek3\Persistence\PdoEvaluationRepository;
use NativeWeek4\Persistence\InMemoryEvaluationRepository;
use NativeWeek4\Presentation\{EvaluationController,JsonView};
$n=0;function check(mixed $e,mixed $a,string $s):void{global $n;if($e!==$a)throw new RuntimeException($s);++$n;echo "PASS: $s\n";}
function db():PDO{$p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$p->exec('PRAGMA foreign_keys=ON');$p->exec(file_get_contents(__DIR__.'/../../../semana-03/micro-his/schema.sql'));return $p;}
$pdo=db();$memory=new InMemoryEvaluationRepository();
foreach(['memory'=>$memory,'pdo'=>new PdoEvaluationRepository($pdo)] as $mode=>$repository){
 $gateway=new class implements DemoOrderGateway {public int $calls=0;public function status(string $id):?string{++$this->calls;return $id==='DEMO-1'?'PENDIENTE':null;}};
 $notifier=new class implements Notifier {public int $calls=0;public function notify(string $m):string{++$this->calls;return 'UNAVAILABLE';}};
 $c=new EvaluationController(new Evaluate($gateway,$repository,$notifier));
 $first=$c->handle(['demoOrderId'=>'DEMO-1','target'=>'CONTRACT_MOBILE']);
 check('RECORDED',$first['status'],"$mode: mismo controlador registra");
 check(1,$first['evidenceId'],"$mode: identificador generado");
 check('UNAVAILABLE',$first['notification'],"$mode: capacidad separada");
 check(2,$c->handle(['demoOrderId'=>'DEMO-1','target'=>'CONTRACT_DESKTOP'])['evidenceId'],"$mode: segunda escritura no sobrescribe primera");
 check('INVALID_EVALUATION',$c->handle(['demoOrderId'=>'DEMO-1','target'=>'SUPPORT_PROVEN'])['status'],"$mode: regla en dominio");
 check('INVALID_INPUT',$c->handle(['demoOrderId'=>['bad'],'target'=>'CONTRACT_MOBILE'])['status'],"$mode: tipo inválido no invoca aplicación");
 check('HIS_UNAVAILABLE',$c->handle(['demoOrderId'=>'DEMO-404','target'=>'CONTRACT_MOBILE'])['status'],"$mode: falla consulta explícita");
 check(2,$notifier->calls,"$mode: errores no notifican");
 $rendered=json_decode((new JsonView())->render($first),true,512,JSON_THROW_ON_ERROR);
 check('CONTRACT_ONLY',$rendered['scope'],"$mode: vista etiqueta alcance");
 check($first,$rendered['result'],"$mode: vista no altera resultado");
}
check(2,$memory->count(),'InMemory conserva ambas evaluaciones');
check(2,(int)$pdo->query('SELECT count(*) FROM evaluations')->fetchColumn(),'PDO conserva ambas evaluaciones');
$bad=db();$bad->exec('DROP TABLE evaluation_events');
$gateway=new class implements DemoOrderGateway {public function status(string $id):?string{return 'PENDIENTE';}};
$notifier=new class implements Notifier {public function notify(string $m):string{throw new RuntimeException('No debe notificarse');}};
$failed=(new EvaluationController(new Evaluate($gateway,new PdoEvaluationRepository($bad),$notifier)))->handle(['demoOrderId'=>'DEMO-1','target'=>'CONTRACT_MOBILE']);
check('PERSISTENCE_FAILURE',$failed['status'],'controlador conserva error de persistencia sin falso éxito');
check(0,(int)$bad->query('SELECT count(*) FROM evaluations')->fetchColumn(),'rollback real intacto');
echo "OK: $n aserciones MVC/Repository. PDO SQLite temporal y SO/HIS dobles.\n";