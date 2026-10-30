<?php

declare(strict_types=1);
require_once __DIR__.'/../bootstrap.php';
use MicroHis\Application\{ReceiveSpecimen,SpecimenIdentifierGenerator};use MicroHis\Domain\SpecimenAcceptancePolicy;use MicroHis\Persistence\PdoSpecimenRepository;
use ReceptionWeek4\Persistence\InMemoryRepository;use ReceptionWeek4\Presentation\{ReceptionController,JsonView};
$n=0;function check(mixed $e,mixed $a,string $s):void{global $n;if($e!==$a)throw new RuntimeException($s);++$n;echo "PASS: $s\n";}
function db():PDO{$p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$p->exec(file_get_contents(__DIR__.'/../../../semana-03/ejemplo/database/schema.sql'));return $p;}
$p=db();$memory=new InMemoryRepository();$input=['order'=>'ORD-DEMO','patient'=>'PAC-DEMO','exam'=>'TEST-DEMO','type'=>'blood','label'=>true,'container'=>true,'seal'=>true];
foreach(['memory'=>$memory,'pdo'=>new PdoSpecimenRepository($p)] as $name=>$repo){
 $gen=new class implements SpecimenIdentifierGenerator{private int $n=0;public function generate():string{return 'SP-DEMO-'.++$this->n;}};
 $c=new ReceptionController(new ReceiveSpecimen(new SpecimenAcceptancePolicy(),$repo,$gen));
 $good=$c->handle($input);check('RECORDED',$good['status'],"$name: guarda recepción");check('accepted',$good['decision'],"$name: aceptación por política existente");check([],$good['reasons'],"$name: sin motivos en aceptada");
 $bad=$c->handle(array_replace($input,['container'=>false]));check('RECORDED',$bad['status'],"$name: rechazo también se registra");check('rejected',$bad['decision'],"$name: dominio decide recipiente roto");check(true,count($bad['reasons'])>0,"$name: rechazo tiene motivo");
 check('INVALID_INPUT',$c->handle(array_replace($input,['label'=>'false']))['status'],"$name: cadena no se convierte a booleano verdadero");
 check('INVALID_INPUT',$c->handle(array_replace($input,['order'=>' ']))['status'],"$name: referencia vacía rechazada por dominio");
 check('INVALID_INPUT',$c->handle(array_replace($input,['type'=>'unknown']))['status'],"$name: tipo de entrada desconocido");
 $view=json_decode((new JsonView())->render($bad),true,512,JSON_THROW_ON_ERROR);check($bad,$view['result'],"$name: vista conserva decisión/motivo");
 $duplicate=new class implements SpecimenIdentifierGenerator{public function generate():string{return 'SP-DEMO-1';}};
 check('OPERATION_UNAVAILABLE',(new ReceptionController(new ReceiveSpecimen(new SpecimenAcceptancePolicy(),$repo,$duplicate)))->handle($input)['status'],"$name: colisión no sobrescribe ni confirma");
}
check(2,$memory->count(),'InMemory conserva dos recepciones');check(2,(int)$p->query('SELECT count(*) FROM specimen_receptions')->fetchColumn(),'PDO conserva dos recepciones');
$p->exec('DROP TABLE specimen_receptions');$generator=new class implements SpecimenIdentifierGenerator{public function generate():string{return 'SP-DEMO-3';}};
$r=(new ReceptionController(new ReceiveSpecimen(new SpecimenAcceptancePolicy(),new PdoSpecimenRepository($p),$generator)))->handle($input);
check('OPERATION_UNAVAILABLE',$r['status'],'PDO roto no confirma recepción');check(false,isset($r['identifier']),'error no presenta identificador como guardado');
echo "OK: $n aserciones MVC/Repository. PDO real temporal, datos ficticios.\n";