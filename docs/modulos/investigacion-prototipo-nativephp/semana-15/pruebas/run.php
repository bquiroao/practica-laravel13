<?php
declare(strict_types=1);
require_once __DIR__.'/../../semana-02/ejemplo-isp/despues/NativeCapabilities.php';
require_once __DIR__.'/../../semana-02/ejemplo-isp/despues/InMemoryAdapters.php';
require_once __DIR__.'/../src/Evaluation.php';
use NativePrototype\After\{DemoOrderStatus,DemoOrderStatusGateway,NativeNotifier,NativeNotificationStatus,PrototypeOutcomeStatus,RunOrderStatusPrototype,InMemoryDemoOrderStatusGateway,RecordingNativeNotifier,ExportPrototypeEvidence,InMemoryPrototypeEvidenceExporter};
use NativePrototype\Evaluation\{ViabilityPolicy,SyncPolicy};
function eq(mixed $expected,mixed $actual):void{if($expected!==$actual)throw new RuntimeException('Esperado '.var_export($expected,true).' recibido '.var_export($actual,true));}
function fixture(NativeNotificationStatus $permission=NativeNotificationStatus::SENT):array{$g=new InMemoryDemoOrderStatusGateway();$g->add('DEMO-1',DemoOrderStatus::PENDING);$n=new RecordingNativeNotifier($permission);return [new RunOrderStatusPrototype($g,$n),$n];}
final class GatewaySpy implements DemoOrderStatusGateway{public int $calls=0;public function __construct(private ?DemoOrderStatus $status=null){}public function statusForDemoOrder(string $id):?DemoOrderStatus{$this->calls++;return $this->status;}}
$tests=[];
$tests['U01 viabilidad y riesgos de plataforma']=function(){ $p=new ViabilityPolicy();eq('NOT_DEMONSTRATED',$p->evaluate(false,true,true,true));eq('NOT_DEMONSTRATED',$p->evaluate(true,false,true,true));eq('CONDITIONAL',$p->evaluate(true,true,false,true));eq('CONDITIONAL',$p->evaluate(true,true,true,false));eq('VIABLE_FOR_DEMO',$p->evaluate(true,true,true,true));};
$tests['U02 sincronizacion conserva snapshot marcado obsoleto']=function(){$p=new SyncPolicy();eq(['status'=>'PENDIENTE','fresh'=>false,'source'=>'STALE_SNAPSHOT'],$p->resolve(null,'PENDIENTE'));eq(['status'=>null,'fresh'=>false,'source'=>'NONE'],$p->resolve(null,null));eq(['status'=>'RECIBIDA','fresh'=>true,'source'=>'HIS'],$p->resolve('RECIBIDA','PENDIENTE'));};
$tests['U03 capacidad nativa sin permiso no envia']=function(){$n=new RecordingNativeNotifier(NativeNotificationStatus::UNAVAILABLE);eq(NativeNotificationStatus::UNAVAILABLE,$n->notify('Prototipo','Estado demo'));eq([],$n->notifications());};
$tests['U04 exportacion aislada de notificaciones']=function(){$e=new InMemoryPrototypeEvidenceExporter();eq(true,(new ExportPrototypeEvidence($e))->execute('demo.txt','Estado ficticio'));eq(['demo.txt'=>'Estado ficticio'],$e->files());};
$tests['W01 rama entrada invalida corta gateway y notifier']=function(){$g=new GatewaySpy(DemoOrderStatus::PENDING);$n=new RecordingNativeNotifier();eq(PrototypeOutcomeStatus::INVALID_INPUT,(new RunOrderStatusPrototype($g,$n))->execute('   ')->status);eq(0,$g->calls);eq([],$n->notifications());};
$tests['W02 rama gateway nulo corta notificacion']=function(){$g=new GatewaySpy();$n=new RecordingNativeNotifier();eq(PrototypeOutcomeStatus::HIS_UNAVAILABLE,(new RunOrderStatusPrototype($g,$n))->execute('DEMO-1')->status);eq(1,$g->calls);eq([],$n->notifications());};
$tests['W03 ramas capacidad enviada y no disponible']=function(){foreach([NativeNotificationStatus::SENT,NativeNotificationStatus::UNAVAILABLE] as $s){[$u,$n]=fixture($s);eq($s===NativeNotificationStatus::SENT?PrototypeOutcomeStatus::NOTIFIED:PrototypeOutcomeStatus::NOTIFICATION_UNAVAILABLE,$u->execute('DEMO-1')->status);eq($s===NativeNotificationStatus::SENT?1:0,count($n->notifications()));}};
$tests['B01 equivalencia orden conocida']=function(){[$u,$n]=fixture();eq(PrototypeOutcomeStatus::NOTIFIED,$u->execute('DEMO-1')->status);};
$tests['B02 limites vacio y espacios']=function(){[$u,$n]=fixture();foreach(['',' ','   ',"\t\n"] as $id)eq(PrototypeOutcomeStatus::INVALID_INPUT,$u->execute($id)->status);};
$tests['B03 orden inexistente no simula exito']=function(){[$u,$n]=fixture();eq(PrototypeOutcomeStatus::HIS_UNAVAILABLE,$u->execute('NO-EXISTE')->status);};
$tests['B04 permiso denegado capacidad no disponible']=function(){[$u,$n]=fixture(NativeNotificationStatus::UNAVAILABLE);eq(PrototypeOutcomeStatus::NOTIFICATION_UNAVAILABLE,$u->execute('DEMO-1')->status);};
$tests['B05 notificacion no contiene identificador ni paciente']=function(){[$u,$n]=fixture();$u->execute('DEMO-1');eq([['title'=>'Prototipo HIS','message'=>'La consulta de demostración finalizó: PENDIENTE']],$n->notifications());};
$tests['B06 estados de demostracion reconocidos']=function(){foreach(DemoOrderStatus::cases() as $s){$g=new InMemoryDemoOrderStatusGateway();$g->add('DEMO-1',$s);$n=new RecordingNativeNotifier();eq(PrototypeOutcomeStatus::NOTIFIED,(new RunOrderStatusPrototype($g,$n))->execute('DEMO-1')->status);}};
if(in_array('--defectos',$argv,true)){
 $tests=[];
 $tests['D01 gateway lanza y el flujo no devuelve error explicito']=function(){$g=new class implements DemoOrderStatusGateway{public function statusForDemoOrder(string $id):?DemoOrderStatus{throw new RuntimeException('Fallo de red simulado');}};$n=new RecordingNativeNotifier();$caught=false;try{(new RunOrderStatusPrototype($g,$n))->execute('DEMO-1');}catch(RuntimeException $e){$caught=$e->getMessage()==='Fallo de red simulado';}eq(true,$caught);eq([],$n->notifications());};
 $tests['D02 notificador lanza y no devuelve capacidad no disponible']=function(){$g=new InMemoryDemoOrderStatusGateway();$g->add('DEMO-1',DemoOrderStatus::PENDING);$n=new class implements NativeNotifier{public function notify(string $t,string $m):NativeNotificationStatus{throw new RuntimeException('Permiso revocado simulado');}};$caught=false;try{(new RunOrderStatusPrototype($g,$n))->execute('DEMO-1');}catch(RuntimeException $e){$caught=$e->getMessage()==='Permiso revocado simulado';}eq(true,$caught);};
 echo "MODO REPRODUCCION: PASS significa defecto observado, no corregido.\n";
}
$failed=0;foreach($tests as $id=>$test){try{$test();echo "PASS $id\n";}catch(Throwable $e){$failed++;echo "FAIL $id: {$e->getMessage()}\n";}}
echo count($tests)." casos, $failed fallidos.\n";exit($failed===0?0:1);