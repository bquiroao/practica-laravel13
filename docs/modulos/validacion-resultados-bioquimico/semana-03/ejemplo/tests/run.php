<?php
declare(strict_types=1);
require_once __DIR__.'/../bootstrap.php';
$checks=0;$fail=0; function ok(bool $condition,string $name):void{global $checks,$fail;$checks++;if($condition){echo "PASS: $name\n";}else{$fail++;echo "FAIL: $name\n";}}
final class TestAuth implements ReviewerAuthorization { public function __construct(private bool $allowed=true){} public function canReview(string $a,string $h):bool{return $this->allowed;} }
final class TestClock implements Clock { public function now():DateTimeImmutable{return new DateTimeImmutable('2026-10-27T12:00:00+00:00');} }
function result(string $status='PENDING'):LabResult{return new LabResult('R-1','H-1','O-1','E-1','S-1',1,'7.2','mg/dL',LabResultStatus::from($status));}
function controller(LabResultDecisionRepository $r,bool $allowed=true):ReviewController{return new ReviewController(new ReviewPendingLabResult(new TestAuth($allowed),new TestClock(),$r));}
$r=new InMemoryLabResultDecisionRepository();$r->seed(result());$response=controller($r)->validate('R-1',1,'bio-1','H-1');ok($response['status']===200,'happy path HTTP 200');ok($r->findById('R-1','H-1')->status===LabResultStatus::VALIDATED,'validated status persisted');ok($r->findById('R-1','H-1')->value==='7.2','value unchanged');ok($r->decisions()[0]->actorId==='bio-1' && $r->decisions()[0]->decidedAt->format('Y-m-d')==='2026-10-27','decision audit persisted');
$r=new InMemoryLabResultDecisionRepository();$r->seed(result());$c=controller($r);ok($c->reject('R-1',1,'bio-1','H-1','   ')['status']===422,'reject requires reason');ok(count($r->decisions())===0,'invalid reject has no write');ok(controller($r,false)->validate('R-1',1,'x','H-1')['status']===403,'unauthorized actor denied');$r->seed(new LabResult('R-2','H-1','O','E','S',1,'x','u',LabResultStatus::VALIDATED));ok(controller($r)->validate('R-2',1,'b','H-1')['status']===409,'already validated conflicts');$r->seed(result());ok(controller($r)->validate('R-1',2,'b','H-1')['status']===409,'stale version conflicts');ok(controller($r)->validate('R-1',1,'b','H-2')['status']===404,'cross hospital not found');
$r=new InMemoryLabResultDecisionRepository();$r->seed(result());$r->failNextWrite();ok(controller($r)->validate('R-1',1,'b','H-1')['status']===503,'in-memory persistence failure');ok($r->findById('R-1','H-1')->status===LabResultStatus::PENDING && count($r->decisions())===0,'failed memory write has no partial state');
try{$pdo=new PDO('sqlite::memory:');$pdo->setAttribute(PDO::ATTR_ERRMODE,PDO::ERRMODE_EXCEPTION);$pdo->exec("CREATE TABLE lab_result (id TEXT,hospital_id TEXT,order_item_id TEXT,examination_id TEXT,specimen_id TEXT,version INTEGER,value TEXT,unit TEXT,status TEXT,PRIMARY KEY(id,hospital_id))");$pdo->exec("INSERT INTO lab_result VALUES ('R-1','H-1','O','E','S',1,'7.2','u','PENDING')");$pc=new ReviewController(new ReviewPendingLabResult(new TestAuth(),new TestClock(),new PdoLabResultDecisionRepository($pdo)));ok($pc->validate('R-1',1,'b','H-1')['status']===503,'PDO missing decision schema returns 503');$row=$pdo->query("SELECT status FROM lab_result WHERE id='R-1'")->fetchColumn();ok($row==='PENDING','PDO failure rolled back result');}catch(Throwable $e){ok(false,'SQLite PDO available: '.$e->getMessage());}
$source=file_get_contents(__DIR__.'/../src/Presentation/ReviewController.php');ok(stripos($source,'PDO')===false && stripos($source,'SELECT')===false && stripos($source,'INSERT')===false,'controller contains no persistence tokens');$ref=new ReflectionClass(ReviewPendingLabResult::class);$params=$ref->getConstructor()->getParameters();ok(count($params)===3 && array_reduce($params,fn(bool $v,ReflectionParameter $p):bool=>$v && $p->getType() instanceof ReflectionNamedType && $p->getType()->isBuiltin()===false,true),'use case constructor takes interfaces');
foreach (['memory', 'pdo'] as $adapter) {
    if ($adapter === 'pdo') {
        $db = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
        $db->exec(file_get_contents(__DIR__.'/../database/schema.sql'));
        $seed = $db->prepare('INSERT INTO lab_result VALUES (:id,:hospital,\'O\',\'E\',\'S\',1,\'7.2\',\'u\',\'PENDING\')');
        foreach (['H-1','H-2'] as $hospital) $seed->execute([':id'=>'R-1',':hospital'=>$hospital]);
        $repo = new PdoLabResultDecisionRepository($db);
    } else {
        $repo = new InMemoryLabResultDecisionRepository();
        foreach (['H-1','H-2'] as $hospital) $repo->seed(new LabResult('R-1',$hospital,'O','E','S',1,'7.2','u',LabResultStatus::PENDING));
    }
    $c = controller($repo);
    ok($c->reject('R-1',1,'bio-1','H-1','  muestra inadecuada  ')['status']===200, "$adapter reject succeeds");
    ok($repo->findById('R-1','H-1')->status===LabResultStatus::REJECTED, "$adapter rejection persisted");
    ok($repo->findById('R-1','H-2')->status===LabResultStatus::PENDING, "$adapter same ID isolated by hospital");
    ok($c->validate('R-1',1,'bio-2','H-2')['status']===200, "$adapter second hospital validates independently");
    ok($c->validate('R-1',1,'bio-1','H-1')['status']===409, "$adapter final decision cannot be repeated");
    ok($c->validate('missing',1,'bio-1','H-1')['status']===404, "$adapter missing result");
    ok($c->validate('',1,'bio-1','H-1')['status']===422, "$adapter invalid input");
    if ($adapter === 'pdo') {
        ok((int)$db->query('SELECT COUNT(*) FROM lab_result_decision')->fetchColumn()===2,'PDO audit for both hospitals');
        ok($db->query("SELECT reason FROM lab_result_decision WHERE hospital_id='H-1'")->fetchColumn()==='muestra inadecuada','PDO rejection reason trimmed and saved');
        $db->exec('DROP TABLE lab_result_decision');
        $db->exec('DROP TABLE lab_result');
        ok($c->validate('R-1',1,'bio-1','H-1')['status']===503,'PDO read failure is not 404');
    }
}
foreach (['Domain','Application','Presentation','Persistence'] as $layer) {
    ok(is_dir(__DIR__.'/../src/'.$layer),"physical layer $layer exists");
}
foreach (['Domain','Application'] as $layer) {
    foreach (glob(__DIR__.'/../src/'.$layer.'/*.php') as $file) {
        $code=file_get_contents($file);
        ok(!preg_match('/\b(PDO|ReviewController|PdoLabResultDecisionRepository)\b/',$code),"$layer independent of infrastructure");
    }
}
echo "$checks comprobaciones, $fail fallidas.\n";exit($fail===0?0:1);