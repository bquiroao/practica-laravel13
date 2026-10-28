<?php
declare(strict_types=1);
require_once __DIR__.'/../../semana-04/ejemplo/bootstrap.php';
require_once __DIR__.'/../../semana-07/despues/Refactor.php';
use LabResults\Domain\{LabResultRecord,PendingLabResult,ValidatedLabResult,ResultVersion,ResultState};
use LabResults\Application\{LabResultRepository,ResultIdentifierGenerator};
use LabResults\Persistence\InMemoryLabResultRepository;
use LabResults\Refactor\{Clock,ReferenceRangeProvider,ReferenceRange,ResultValue,RangeFlag,EntryService,CaptureCommand,CorrectCommand,Reason};
function eq(mixed $e,mixed $a):void{if($e!==$a)throw new RuntimeException('Esperado '.var_export($e,true).' recibido '.var_export($a,true));}
function invalid(callable $f):void{try{$f();}catch(InvalidArgumentException){return;}throw new RuntimeException('Se esperaba InvalidArgumentException');}
final class IDs implements ResultIdentifierGenerator{private int $i=0;public function generate():string{return 'V'.++$this->i;}}
final class FixedClock implements Clock{public function now():DateTimeImmutable{return new DateTimeImmutable('2026-10-27T12:00:00-06:00');}}
final class Ranges implements ReferenceRangeProvider{public function forItem(string $id):?ReferenceRange{return new ReferenceRange(4,6,'mmol/L');}}
final class SpyRepo implements LabResultRepository{
 public int $reads=0;public int $writes=0;public bool $conflict=false;public bool $fail=false;
 public function __construct(public InMemoryLabResultRepository $inner=new InMemoryLabResultRepository()){}
 public function currentFor(string $id):?LabResultRecord{$this->reads++;return $this->inner->currentFor($id);}
 public function saveInitial(PendingLabResult $r):bool{$this->writes++;if($this->fail)throw new RuntimeException('Fallo simulado');return !$this->conflict && $this->inner->saveInitial($r);}
 public function appendCorrection(string $expected,PendingLabResult $r):bool{$this->writes++;if($this->fail)throw new RuntimeException('Fallo simulado');return !$this->conflict && $this->inner->appendCorrection($expected,$r);}
}
function service(?LabResultRepository $r=null):EntryService{return new EntryService($r??new InMemoryLabResultRepository(),new IDs(),new FixedClock(),new Ranges());}
function cap(string $value='5 mmol/L',string $actor='TECH-DEMO'):CaptureCommand{return new CaptureCommand('ITEM-DEMO',$value,$actor);}
$tests=[];
$tests['U01 valor decimal coma y punto equivalentes']=function(){eq('5.25 mmol/L',ResultValue::parse(' 5,25 mmol/L ')->canonical());eq(ResultValue::parse('5,25 mmol/L')->canonical(),ResultValue::parse('5.25 mmol/L')->canonical());};
$tests['U02 formatos invalidos y unidad obligatoria']=function(){foreach(['','   ','abc mmol/L','5','5.2.1 mmol/L'] as $raw)invalid(fn()=>ResultValue::parse($raw));};
$tests['U03 limites de rango inclusivos y vecinos']=function(){$r=new ReferenceRange(4,6,'mmol/L');foreach([['3.999 mmol/L',RangeFlag::LOW],['4 mmol/L',RangeFlag::NORMAL],['6 mmol/L',RangeFlag::NORMAL],['6.001 mmol/L',RangeFlag::HIGH]] as [$v,$f])eq($f,$r->classify(ResultValue::parse($v)));invalid(fn()=>new ReferenceRange(7,4,'mmol/L'));};
$tests['U04 correccion inmutable enlazada y pendiente']=function(){$p=PendingLabResult::capture('V1','ITEM','5 mmol/L','TECH',(new FixedClock())->now());$c=$p->corrected('V2','5.5 mmol/L','Transcripcion','TECH2',(new FixedClock())->now());eq('V1',$c->version()->previousVersionId);eq(2,$c->version()->versionNumber);eq(ResultState::PENDING,$c->version()->state);eq('5 mmol/L',$p->version()->value);invalid(fn()=>$p->corrected('V2','5.5 mmol/L','  ','TECH2',(new FixedClock())->now()));};
$tests['W01 validacion temprana no lee ni escribe']=function(){$r=new SpyRepo();eq(Reason::INVALID_INPUT,service($r)->capture(cap('  '))->reason);eq(0,$r->reads);eq(0,$r->writes);};
$tests['W02 unidad incompatible no llega a persistencia']=function(){$r=new SpyRepo();eq(Reason::UNIT_MISMATCH,service($r)->capture(cap('5 mg/dL'))->reason);eq(0,$r->reads);eq(0,$r->writes);};
$tests['W03 conflicto al escribir no crea nueva version']=function(){$r=new SpyRepo();$u=service($r);$u->capture(cap());$r->conflict=true;$o=$u->correct(new CorrectCommand('ITEM-DEMO','V1','5.5 mmol/L','Motivo','TECH'));eq(Reason::STORE_CONFLICT,$o->reason);eq(1,$r->inner->versionCount('ITEM-DEMO'));eq(2,$r->writes);};
$tests['B01 captura canonica pendiente']=function(){$o=service()->capture(cap('5,2 mmol/L'));eq(Reason::CAPTURED,$o->reason);eq('5.2 mmol/L',$o->result->version()->value);eq(ResultState::PENDING,$o->result->version()->state);eq('TECH-DEMO',$o->result->version()->enteredBy);};
$tests['B02 correccion valida y motivo vacio']=function(){$r=new InMemoryLabResultRepository();$u=service($r);$u->capture(cap());eq(Reason::INVALID_INPUT,$u->correct(new CorrectCommand('ITEM-DEMO','V1','5.5 mmol/L','  ','TECH'))->reason);eq(1,$r->versionCount('ITEM-DEMO'));eq(Reason::CORRECTED,$u->correct(new CorrectCommand('ITEM-DEMO','V1','5.5 mmol/L','Transcripcion','TECH'))->reason);eq(2,$r->versionCount('ITEM-DEMO'));};
$tests['B03 version obsoleta y duplicado inicial']=function(){$u=service();$u->capture(cap());eq(Reason::ITEM_HAS_RESULT,$u->capture(cap())->reason);eq(Reason::STALE_VERSION,$u->correct(new CorrectCommand('ITEM-DEMO','OBSOLETA','5.1 mmol/L','Motivo','TECH'))->reason);};
$tests['B04 validado inmutable y recurso inexistente']=function(){$v=new ValidatedLabResult(new ResultVersion('V-VALID','ITEM-DEMO','5 mmol/L',ResultState::VALIDATED,1,null,null,'BIO',(new FixedClock())->now()));$r=new InMemoryLabResultRepository([$v]);eq(Reason::ALREADY_VALIDATED,service($r)->correct(new CorrectCommand('ITEM-DEMO','V-VALID','5.1 mmol/L','Motivo','TECH'))->reason);eq(1,$r->versionCount('ITEM-DEMO'));eq(Reason::NOT_FOUND,service()->correct(new CorrectCommand('NO-EXISTE','V1','5.1 mmol/L','Motivo','TECH'))->reason);};
$tests['B05 unidad distinta rechazada y fuera de rango no valida']=function(){eq(Reason::UNIT_MISMATCH,service()->capture(cap('5 mg/dL'))->reason);$o=service()->capture(cap('7 mmol/L'));eq(Reason::CAPTURED,$o->reason);eq(RangeFlag::HIGH,$o->flag);eq(ResultState::PENDING,$o->result->version()->state);};
$tests['B06 autor vacio bloqueado']=function(){eq(Reason::INVALID_INPUT,service()->capture(cap('5 mmol/L',' '))->reason);};
if(in_array('--defectos',$argv,true)){
 $tests=[];
 $tests['D01 actor no autorizado aceptado sin verificar rol hospital']=function(){$o=service()->capture(cap('5 mmol/L','SIN-PERMISO'));eq(Reason::CAPTURED,$o->reason);eq('SIN-PERMISO',$o->result->version()->enteredBy);};
 $tests['D02 precision canonical cambia valor persistido']=function(){$o=service()->capture(cap('6.00001 mmol/L'));eq(Reason::CAPTURED,$o->reason);eq('6 mmol/L',$o->result->version()->value);eq(RangeFlag::HIGH,$o->flag);};
 $tests['D03 excepcion de persistencia escapa del servicio']=function(){$r=new SpyRepo();$r->fail=true;$caught=false;try{service($r)->capture(cap());}catch(RuntimeException $e){$caught=$e->getMessage()==='Fallo simulado';}eq(true,$caught);eq(0,$r->inner->versionCount('ITEM-DEMO'));};
 echo "REPRODUCCION: PASS indica defecto observado, no corregido.\n";
}
$failed=0;foreach($tests as $id=>$test){try{$test();echo "PASS $id\n";}catch(Throwable $e){$failed++;echo "FAIL $id: {$e->getMessage()}\n";}}
echo count($tests)." casos, $failed fallidos.\n";exit($failed===0?0:1);