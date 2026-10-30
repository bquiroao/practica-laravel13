<?php
declare(strict_types=1);

// Cambio práctico de la semana 6: m1 pide la historia longitudinal de p1 con la base CENTRAL caída. El intento debe quedar
// en la auditoría aunque falle por un error técnico (R-08, RNF-04). Antes y después del cambio, con PDO. Datos ficticios.
require __DIR__ . '/../../micro-his-expediente/bootstrap.php';

use MicroHis\Expediente\Aplicacion\Reloj;
use MicroHis\Expediente\Persistencia\{CentralPdo, HospitalPdo};
use function MicroHis\Expediente\crearControlador;

$raiz = __DIR__ . '/../../micro-his-expediente';
$hospital = new PDO('sqlite::memory:');
$hospital->exec(file_get_contents("$raiz/database/schema.sql") . "INSERT INTO local_patients VALUES ('p1', 'g1', 'active', 'linked');
    INSERT INTO care_assignments VALUES ('p1', 'm1', 'active'); INSERT INTO medical_records VALUES ('p1', 'EXP-p1');");
$caida = new PDO('sqlite::memory:');   // CENTRAL sin tablas: cualquier consulta falla
$app = crearControlador(new HospitalPdo($hospital), new CentralPdo($caida), new class implements Reloj { public function ahora(): int { return 1000; } }, 'h1');
foreach ([false, true] as $longitudinal) {
    $antes = (int) $hospital->query('SELECT COUNT(*) FROM audit_events')->fetchColumn();
    $r = $app->atender('consultar', ['paciente' => 'p1', 'profesional' => 'm1', 'longitudinal' => $longitudinal]);
    $q = $hospital->prepare("SELECT actor || ' ' || action || ' ' || patient_code || ' ' || outcome FROM audit_events LIMIT -1 OFFSET ?");
    $q->execute([$antes]); $nuevos = $q->fetchAll(PDO::FETCH_COLUMN);
    printf("consulta %s con CENTRAL caída = %s | eventos nuevos: %d [%s]\n", $longitudinal ? 'longitudinal' : 'local', rtrim("{$r['status']} " . ($r['error'] ?? '')), count($nuevos), implode('; ', $nuevos));
}
