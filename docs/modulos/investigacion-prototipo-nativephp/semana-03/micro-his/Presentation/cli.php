<?php

declare(strict_types=1);
require_once __DIR__.'/../bootstrap.php';
use NativeWeek3\Domain\{DemoOrderGateway,Notifier};
use NativeWeek3\Persistence\PdoEvaluationRepository;
use NativeWeek3\Application\Evaluate;

// Composición de demostración local, sin conexión HIS, sesión ni runtime nativo.
$pdo=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);
$pdo->exec('PRAGMA foreign_keys=ON');
$pdo->exec(file_get_contents(__DIR__.'/../schema.sql'));
$gateway=new class implements DemoOrderGateway {
    public function status(string $id): ?string { return $id==='DEMO-1'?'PENDIENTE':null; }
};
$notifier=new class implements Notifier {
    public function notify(string $message): string { return 'UNAVAILABLE'; }
};
$result=(new Evaluate($gateway,new PdoEvaluationRepository($pdo),$notifier))->execute($argv[1]??'DEMO-1',$argv[2]??'CONTRACT_MOBILE');
echo json_encode($result,JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($result['status']==='RECORDED'?0:1);