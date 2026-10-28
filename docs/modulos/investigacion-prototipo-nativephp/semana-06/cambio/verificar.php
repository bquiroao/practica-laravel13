<?php

declare(strict_types=1);
require_once __DIR__.'/ConsentNotifier.php';
require_once __DIR__.'/../../semana-03/micro-his/bootstrap.php';
use NativeWeek6\{NotificationPermission,ConsentNotifier};
use NativeWeek3\Domain\{Notifier,DemoOrderGateway};
use NativeWeek3\Application\Evaluate;
use NativeWeek3\Persistence\PdoEvaluationRepository;
$n=0;function check(mixed $e,mixed $a,string $s):void{global $n;if($e!==$a)throw new RuntimeException($s);++$n;echo "PASS: $s\n";}
$permission=new class implements NotificationPermission {public bool $allowed=false;public function allowed():bool{return $this->allowed;}};
$native=new class implements Notifier {public array $messages=[];public function notify(string $m):string{$this->messages[]=$m;return 'SENT';}};
$gateway=new class implements DemoOrderGateway {public function status(string $id):?string{return 'PENDIENTE';}};
function database():PDO{$p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$p->exec('PRAGMA foreign_keys=ON');$p->exec(file_get_contents(__DIR__.'/../../semana-03/micro-his/schema.sql'));return $p;}
// Antes: notificador directo sin política de permiso adicional.
check('SENT',$native->notify('Mensaje demo'),'antes: adaptador directo invocado');$native->messages=[];
$p=database();$notifier=new ConsentNotifier($permission,$native);$flow=new Evaluate($gateway,new PdoEvaluationRepository($p),$notifier);
$r=$flow->execute('DEMO-1','CONTRACT_MOBILE');
check('RECORDED',$r['status'],'permiso denegado conserva consulta registrada');
check('UNAVAILABLE',$r['notification'],'denegacion no es enviado');
check(0,count($native->messages),'denegado no invoca adaptador nativo');
$permission->allowed=true;$r=$flow->execute('DEMO-1','CONTRACT_MOBILE');
check('SENT',$r['notification'],'permiso concedido delega');
check(1,count($native->messages),'concedido invoca una vez');
check(false,str_contains($native->messages[0],'DEMO-1'),'mensaje sin identificador');
$bad=database();$bad->exec('DROP TABLE evaluation_events');
$r=(new Evaluate($gateway,new PdoEvaluationRepository($bad),$notifier))->execute('DEMO-1','CONTRACT_MOBILE');
check('PERSISTENCE_FAILURE',$r['status'],'error de persistencia sigue explícito');
check(1,count($native->messages),'persistencia fallida no agrega efecto');
check(0,(int)$bad->query('SELECT count(*) FROM evaluations')->fetchColumn(),'rollback sigue íntegro');
$permission->allowed=false;$r=$flow->execute('DEMO-1','CONTRACT_MOBILE');
check('UNAVAILABLE',$r['notification'],'permiso retirado se reevalúa en siguiente ejecución');
check(1,count($native->messages),'no usa permiso anterior almacenado');
echo "OK: $n aserciones del cambio. Permiso y SO son dobles, no ejecución nativa.\n";