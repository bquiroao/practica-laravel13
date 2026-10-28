<?php

declare(strict_types=1);

// Recorrido de la Semana 12: usa el caso de uso real de la Semana 3 con SQLite en memoria.
// La autorización por rol vive solo en este script; el prototipo no la implementa.

require_once __DIR__ . '/../../semana-03/ejemplo/bootstrap.php';

use MicroHis\Application\ReceiveSpecimen;
use MicroHis\Domain\ReceptionRequest;
use MicroHis\Domain\ReceptionStatus;
use MicroHis\Domain\SpecimenAcceptancePolicy;
use MicroHis\Domain\SpecimenType;
use MicroHis\Infrastructure\Identity\RandomSpecimenIdentifierGenerator;
use MicroHis\Persistence\PdoSpecimenRepository;

$pdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$pdo->exec((string) file_get_contents(__DIR__ . '/../../semana-03/ejemplo/database/schema.sql'));

$useCase = new ReceiveSpecimen(
    new SpecimenAcceptancePolicy(),
    new PdoSpecimenRepository($pdo),
    new RandomSpecimenIdentifierGenerator(),
);

$checks = 0;
$failed = 0;
$check = static function (bool $ok, string $message) use (&$checks, &$failed): void {
    $checks++;
    if (!$ok) {
        $failed++;
    }
    echo ($ok ? 'PASS' : 'FAIL') . ": {$message}\n";
};
$count = static fn (): int => (int) $pdo->query('SELECT COUNT(*) FROM specimen_receptions')->fetchColumn();
$step = static function (int $n, string $title): void {
    echo "\nPaso {$n}. {$title}\n";
};
$request = static fn (
    SpecimenType $type = SpecimenType::BLOOD,
    bool $label = true,
    bool $container = true,
    bool $seal = true,
    ?string $reason = null,
    string $order = 'ORD-1201',
): ReceptionRequest => new ReceptionRequest($order, 'PAT-1201', 'EX-HEMO', $type, $label, $container, $seal, $reason);

$roleCanReceive = static fn (string $role): bool => $role === 'tecnico_recepcion';

$step(1, 'Rol sin permiso no llega al caso de uso (guarda del recorrido)');
$before = $count();
$role = 'enfermeria';
if ($roleCanReceive($role)) {
    $useCase->execute($request());
}
$check($count() === $before, 'el rol "enfermeria" no crea ninguna recepción');

$step(2, 'Técnico acepta una muestra de sangre íntegra');
$ok = $useCase->execute($request());
$check($ok->status === ReceptionStatus::ACCEPTED, 'sangre con etiqueta, recipiente y sello íntegros queda aceptada');
$check(str_starts_with($ok->identifier, 'SP-'), 'recibe un identificador interno SP-...');
$check($count() === 1, 'hay exactamente una fila persistida');

$step(3, 'Rechazo por sello roto en sangre');
$rej = $useCase->execute($request(seal: false, order: 'ORD-1202'));
$check($rej->status === ReceptionStatus::REJECTED && $rej->reasons !== [], 'sello roto en sangre se rechaza con motivo');

$step(4, 'El mismo sello roto en orina no es causa de rechazo');
$urine = $useCase->execute($request(type: SpecimenType::URINE, seal: false, order: 'ORD-1203'));
$check($urine->status === ReceptionStatus::ACCEPTED, 'la regla del sello aplica solo a sangre');

$step(5, 'Sin etiqueta y recipiente dañado: motivos acumulados');
$multi = $useCase->execute($request(label: false, container: false, order: 'ORD-1204'));
$check(count($multi->reasons) === 2, 'se registran los dos motivos, sin perder ninguno');

$step(6, 'Identificadores únicos en recepciones de la misma orden');
$a = $useCase->execute($request(order: 'ORD-1205'));
$b = $useCase->execute($request(order: 'ORD-1205'));
$check($a->identifier !== $b->identifier, 'dos recipientes de la misma orden reciben identificadores distintos');

$step(7, 'Falla de persistencia: el error no se oculta');
$brokenPdo = new PDO('sqlite::memory:', null, null, [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
$failing = new ReceiveSpecimen(
    new SpecimenAcceptancePolicy(),
    new PdoSpecimenRepository($brokenPdo),
    new RandomSpecimenIdentifierGenerator(),
);
$raised = false;
try {
    $failing->execute($request(order: 'ORD-1206'));
} catch (PDOException) {
    $raised = true;
}
$check($raised, 'sin tabla, el guardado lanza error en lugar de devolver éxito');

$step(8, 'Límite conocido: el motivo libre del operador se acepta sin catálogo');
$free = $useCase->execute($request(reason: 'no me gustó', order: 'ORD-1207'));
$check(
    $free->status === ReceptionStatus::REJECTED && $free->reasons === ['no me gustó'],
    'LIMITE DEMOSTRADO: el prototipo guarda texto libre como motivo (origen del cambio CC-REC-01)',
);

$step(9, 'Límite conocido: el prototipo no verifica que la orden exista');
$ghost = $useCase->execute($request(order: 'ORD-NO-EXISTE'));
$check(
    $ghost->status === ReceptionStatus::ACCEPTED,
    'LIMITE DEMOSTRADO: una orden inexistente se acepta porque no hay sistema de órdenes',
);

$step(10, 'Estado final persistido');
$rejected = (int) $pdo->query("SELECT COUNT(*) FROM specimen_receptions WHERE status = 'rejected'")->fetchColumn();
$check($rejected === 3, 'hay 3 rechazos guardados: sello roto, doble motivo y texto libre');

echo "\n{$checks} comprobaciones, {$failed} fallidas.\n";
exit($failed === 0 ? 0 : 1);
