<?php

declare(strict_types=1);
require_once __DIR__.'/../bootstrap.php';require_once __DIR__.'/../Presentation/DemoRules.php';
use OrdersWeek3\Application\{Issue,Find};use OrdersWeek3\Persistence\PdoRepository;use OrdersWeek3\Presentation\DemoRules;
$n=0;function check(mixed $want,mixed $got,string $label):void{global $n;if($want!==$got)throw new RuntimeException($label);++$n;echo "PASS: $label\n";}
function db():PDO{$p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$p->exec('PRAGMA foreign_keys=ON');$p->exec(file_get_contents(__DIR__.'/../schema.sql'));return $p;}
$p=db();$rules=new DemoRules();$r=new PdoRepository($p);$issue=new Issue($rules,$r);$find=new Find($rules,$r);
check('EMPTY',$find->execute('MED-DEMO','PAC-DEMO','EPI-DEMO')['status'],'consulta exitosa vacía');
check('CREATED',$issue->execute('MED-DEMO','PAC-DEMO','EPI-DEMO',['TEST-A','TEST-B'])['status'],'emisión camino feliz');
$got=$find->execute('MED-DEMO','PAC-DEMO','EPI-DEMO');
check('FOUND',$got['status'],'consulta orden persistida');
check('TEST_INITIAL',$got['orders'][0]['status'],'estado de política demo conservado');
check(['TEST-A','TEST-B'],$got['orders'][0]['tests'],'ambas partidas persistidas');
check('MED-DEMO',$got['orders'][0]['actor'],'trazabilidad solicitante');
check('PAC-DEMO',$got['orders'][0]['patient'],'trazabilidad paciente');
check('EPI-DEMO',$got['orders'][0]['encounter'],'trazabilidad episodio');
check('INVALID_REQUEST',$issue->execute('MED-DEMO','PAC-DEMO','EPI-DEMO',[])['status'],'regla al menos una prueba');
check('INVALID_TESTS',$issue->execute('MED-DEMO','PAC-DEMO','EPI-DEMO',['UNKNOWN'])['status'],'regla catálogo disponible');
check('INVALID_CONTEXT',$issue->execute('MED-DEMO','PAC-DEMO','EPI-INVALID',['TEST-A'])['status'],'contexto inválido sin emisión');
check('FORBIDDEN',$issue->execute('OTRO','PAC-DEMO','EPI-DEMO',['TEST-A'])['status'],'emisión denegada');
check('FORBIDDEN',$find->execute('OTRO','PAC-DEMO','EPI-DEMO')['status'],'consulta denegada');
check(1,(int)$p->query('SELECT count(*) FROM lab_orders')->fetchColumn(),'rechazos no crean órdenes');
// Fallo en segunda partida: primera partida y cabecera deben revertirse.
$bad=db();$bad->exec("CREATE TRIGGER fail_part BEFORE INSERT ON lab_order_tests WHEN NEW.code='TEST-B' BEGIN SELECT RAISE(ABORT,'simulated failure'); END");
check('PERSISTENCE_FAILURE',(new Issue($rules,new PdoRepository($bad)))->execute('MED-DEMO','PAC-DEMO','EPI-DEMO',['TEST-A','TEST-B'])['status'],'error real PDO sin éxito');
check(0,(int)$bad->query('SELECT count(*) FROM lab_orders')->fetchColumn(),'rollback cabecera');
check(0,(int)$bad->query('SELECT count(*) FROM lab_order_tests')->fetchColumn(),'rollback partida previa');
$p->exec('DROP TABLE lab_order_tests');
check('UNAVAILABLE',$find->execute('MED-DEMO','PAC-DEMO','EPI-DEMO')['status'],'lectura fallida no se presenta como vacía');
check('FORBIDDEN',$find->execute('OTRO','PAC-DEMO','EPI-DEMO')['status'],'deniega antes de consultar BD rota');
echo "OK: $n aserciones. PDO SQLite real temporal; reglas clínicas de demostración.\n";