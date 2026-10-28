<?php

declare(strict_types=1);
require_once __DIR__.'/../bootstrap.php';
use OrdersWeek3\Application\{Issue,Find};use OrdersWeek3\Persistence\PdoRepository;use OrdersWeek3\Presentation\DemoRules;
use OrdersWeek4\Persistence\InMemoryRepository;use OrdersWeek4\Presentation\{OrderController,JsonView};
$n=0;function check(mixed $e,mixed $a,string $s):void{global $n;if($e!==$a)throw new RuntimeException($s);++$n;echo "PASS: $s\n";}
function db():PDO{$p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$p->exec('PRAGMA foreign_keys=ON');$p->exec(file_get_contents(__DIR__.'/../../../semana-03/micro-his/schema.sql'));return $p;}
$rules=new DemoRules();$p=db();$memory=new InMemoryRepository();$input=['patient'=>'PAC-DEMO','encounter'=>'EPI-DEMO','tests'=>['TEST-A','TEST-B']];
foreach(['memory'=>$memory,'pdo'=>new PdoRepository($p)] as $name=>$repo){
 $c=new OrderController(new Issue($rules,$repo),new Find($rules,$repo));
 check('EMPTY',$c->find('MED-DEMO',$input)['status'],"$name: vacío exitoso");
 check('CREATED',$c->issue('MED-DEMO',$input)['status'],"$name: mismo controller emite");
 $got=$c->find('MED-DEMO',$input);
 check('FOUND',$got['status'],"$name: seguimiento confirmado");
 check(['TEST-A','TEST-B'],$got['orders'][0]['tests'],"$name: partidas completas");
 check('TEST_INITIAL',$got['orders'][0]['status'],"$name: estado guardado");
 check('MED-DEMO',$got['orders'][0]['actor'],"$name: trazabilidad actor");
 check('FORBIDDEN',$c->find('OTRO',$input)['status'],"$name: consulta denegada");
 check('FORBIDDEN',$c->issue('OTRO',$input)['status'],"$name: emisión denegada");
 check('INVALID_REQUEST',$c->issue('MED-DEMO',array_replace($input,['tests'=>[]]))['status'],"$name: regla de dominio no vive en controller");
 check('INVALID_INPUT',$c->issue('MED-DEMO',array_replace($input,['tests'=>[17]]))['status'],"$name: tipo incorrecto rechazado en frontera");
 check('INVALID_TESTS',$c->issue('MED-DEMO',array_replace($input,['tests'=>['UNKNOWN']]))['status'],"$name: catálogo conserva regla");
 check(2,$c->issue('MED-DEMO',$input)['id'],"$name: segunda orden no sobrescribe");
 $view=json_decode((new JsonView())->render($got),true,512,JSON_THROW_ON_ERROR);
 check($got,$view['result'],"$name: vista conserva resultado");
 check('DEMO_ONLY',$view['scope'],"$name: vista indica alcance");
}
check([],$memory->find('OTRO-PAC','EPI-DEMO'),'InMemory no mezcla paciente');
check([],$memory->find('PAC-DEMO','OTRO-EPI'),'InMemory no mezcla episodio');
$bad=db();$bad->exec("CREATE TRIGGER failure BEFORE INSERT ON lab_order_tests WHEN NEW.code='TEST-B' BEGIN SELECT RAISE(ABORT,'test failure'); END");
$c=new OrderController(new Issue($rules,new PdoRepository($bad)),new Find($rules,new PdoRepository($bad)));
check('PERSISTENCE_FAILURE',$c->issue('MED-DEMO',$input)['status'],'controller mantiene error PDO');
check(0,(int)$bad->query('SELECT count(*) FROM lab_orders')->fetchColumn(),'rollback cabecera');
check(0,(int)$bad->query('SELECT count(*) FROM lab_order_tests')->fetchColumn(),'rollback partidas');
$bad->exec('DROP TABLE lab_orders');
check('UNAVAILABLE',$c->find('MED-DEMO',$input)['status'],'lectura rota no se convierte en vacío');
echo "OK: $n aserciones MVC/Repository; PDO SQLite temporal real.\n";