<?php

declare(strict_types=1);
require_once __DIR__.'/../bootstrap.php';
use NativeWeek3\Domain\{DemoOrderGateway,Notifier};
use NativeWeek3\Persistence\PdoEvaluationRepository;
use NativeWeek3\Application\Evaluate;
$n=0;
function check(mixed $want,mixed $got,string $label): void {global $n;if($want!==$got)throw new RuntimeException($label);++$n;echo "PASS: $label\n";}
function db(): PDO {$p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$p->exec('PRAGMA foreign_keys=ON');$p->exec(file_get_contents(__DIR__.'/../schema.sql'));return $p;}
$gateway=new class implements DemoOrderGateway {public ?string $value='PENDIENTE';public int $calls=0;public function status(string $id):?string {++$this->calls;return $this->value;}};
$notifier=new class implements Notifier {public array $messages=[];public string $value='SENT';public function notify(string $message):string {$this->messages[]=$message;return $this->value;}};
$p=db();$use=new Evaluate($gateway,new PdoEvaluationRepository($p),$notifier);
$r=$use->execute('DEMO-1','CONTRACT_MOBILE');
check('RECORDED',$r['status'],'camino feliz registra evidencia');
check('SENT',$r['notification'],'notificador doble confirma envío');
check(1,(int)$p->query('SELECT count(*) FROM evaluations')->fetchColumn(),'PDO guarda evaluación');
check(1,(int)$p->query('SELECT count(*) FROM evaluation_events')->fetchColumn(),'PDO guarda evento técnico');
check(false,str_contains($notifier->messages[0],'DEMO-1'),'notificación sin identificador');
$before=$gateway->calls;
check('INVALID_EVALUATION',$use->execute('PACIENTE-REAL','CONTRACT_MOBILE')['status'],'regla rechaza ID no demo');
check($before,$gateway->calls,'entrada inválida no consulta HIS');
check('INVALID_EVALUATION',$use->execute('DEMO-1','NATIVE_SUPPORTED')['status'],'no aceptar soporte nativo inventado');
$gateway->value='INVENTADO';
check('INVALID_EVALUATION',$use->execute('DEMO-1','CONTRACT_MOBILE')['status'],'estado desconocido no genera éxito');
check(1,count($notifier->messages),'rechazos no notifican');
$gateway->value=null;
check('HIS_UNAVAILABLE',$use->execute('DEMO-1','CONTRACT_MOBILE')['status'],'HIS sin dato no inventa estado');
$gateway->value='PENDIENTE';
$broken=db();$broken->exec("CREATE TRIGGER fail_event BEFORE INSERT ON evaluation_events BEGIN SELECT RAISE(ABORT,'simulated event failure'); END");
$failed=(new Evaluate($gateway,new PdoEvaluationRepository($broken),$notifier))->execute('DEMO-1','CONTRACT_MOBILE');
check('PERSISTENCE_FAILURE',$failed['status'],'error PDO en segundo INSERT explícito');
check(0,(int)$broken->query('SELECT count(*) FROM evaluations')->fetchColumn(),'rollback elimina primera escritura');
check(0,(int)$broken->query('SELECT count(*) FROM evaluation_events')->fetchColumn(),'sin evento parcial');
check(1,count($notifier->messages),'falla de persistencia no notifica');
$notifier->value='UNAVAILABLE';
check('UNAVAILABLE',$use->execute('DEMO-1','CONTRACT_DESKTOP')['notification'],'capacidad ausente separada de lectura');
echo "OK: $n aserciones. PDO SQLite real temporal; HIS y notificador dobles.\n";