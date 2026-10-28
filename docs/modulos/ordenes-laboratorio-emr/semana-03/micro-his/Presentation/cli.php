<?php

declare(strict_types=1);
require_once __DIR__.'/../bootstrap.php';require_once __DIR__.'/DemoRules.php';
use OrdersWeek3\Application\{Issue,Find};use OrdersWeek3\Persistence\PdoRepository;use OrdersWeek3\Presentation\DemoRules;
$p=new PDO('sqlite::memory:',null,null,[PDO::ATTR_ERRMODE=>PDO::ERRMODE_EXCEPTION]);$p->exec('PRAGMA foreign_keys=ON');$p->exec(file_get_contents(__DIR__.'/../schema.sql'));
$r=new PdoRepository($p);$rules=new DemoRules();
$issued=(new Issue($rules,$r))->execute('MED-DEMO','PAC-DEMO','EPI-DEMO',['TEST-A','TEST-B']);
$found=(new Find($rules,$r))->execute('MED-DEMO','PAC-DEMO','EPI-DEMO');
echo json_encode(['issue'=>$issued,'followUp'=>$found],JSON_PRETTY_PRINT|JSON_UNESCAPED_UNICODE).PHP_EOL;
exit($issued['status']==='CREATED'&&$found['status']==='FOUND'?0:1);