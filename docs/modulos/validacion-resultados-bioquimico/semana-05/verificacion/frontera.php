<?php

declare(strict_types=1);

// Mide la frontera del prototipo real de la Semana 2 (ejemplo-dip/despues).
$dir = __DIR__ . '/../../semana-02/ejemplo-dip/despues';
$core = file_get_contents($dir . '/ReviewWorkflow.php');
require_once $dir . '/ReviewWorkflow.php';
require_once $dir . '/InMemoryLabResultDecisionRepository.php';

$passed = 0;
$failed = 0;
function check(string $name, bool $ok): void
{
    global $passed, $failed;
    echo ($ok ? 'PASS' : 'FAIL') . ': ' . $name . PHP_EOL;
    $ok ? $passed++ : $failed++;
}

$pdoMentions = preg_match_all('/\bPDO\b|PDOException/', $core);
$sqlMentions = preg_match_all('/\b(SELECT|INSERT|UPDATE|DELETE)\b\s/', $core);
$ports = preg_match_all('/^interface\s+(\w+)/m', $core, $m) ? $m[1] : [];
$imports = preg_match_all('/^use\s+/m', $core);

$ctor = (new ReflectionClass(LabResult::class))->getConstructor();
$foreign = [];
foreach ($ctor->getParameters() as $p) {
    if (str_ends_with($p->getName(), 'Id') && $p->getName() !== 'resultId') {
        $foreign[] = $p->getName();
    }
}
$adapters = array_filter(get_declared_classes(), static fn ($c) => in_array(LabResultDecisionRepository::class, class_implements($c), true));

echo "Medidas del prototipo (Semana 2):" . PHP_EOL;
printf("  Menciones de PDO en el núcleo ........... %d\n", $pdoMentions);
printf("  SQL en el núcleo ........................ %d\n", $sqlMentions);
printf("  Puertos (interfaces) .................... %d (%s)\n", count($ports), implode(', ', $ports));
printf("  Adaptadores de persistencia ............. %d (%s)\n", count($adapters), implode(', ', $adapters));
printf("  Imports de otros módulos ................ %d\n", $imports);
printf("  Referencias a datos de otros módulos .... %d (%s)\n", count($foreign), implode(', ', $foreign));
echo PHP_EOL;

check('el núcleo no conoce PDO ni SQL', $pdoMentions === 0 && $sqlMentions === 0);
check('el núcleo depende solo de 3 puertos propios', count($ports) === 3);
check('el núcleo no importa clases de otros módulos', $imports === 0);
check('solo existe el adaptador InMemory: NO hay adaptador PDO (límite)', count($adapters) === 1);
check('el resultado referencia 4 identificadores ajenos (hospital, ítem de orden, examen, muestra)', count($foreign) === 4);

// Evidencia dinámica: una decisión toca DOS agregados en una sola operación atómica.
$repo = new InMemoryLabResultDecisionRepository();
$repo->seed(new LabResult('R1', 'H1', 'OI1', 'EX1', 'SP1', 1, '5.1', 'mmol/L', LabResultStatus::PENDING));
$uc = new ReviewPendingLabResult(
    new class implements ReviewerAuthorization {
        public function canReview(string $a, string $h): bool { return true; }
    },
    new class implements Clock {
        public function now(): DateTimeImmutable { return new DateTimeImmutable('2026-10-27T10:00:00-06:00'); }
    },
    $repo,
);
$out = $uc->execute(new ReviewCommand('R1', 1, 'bioq-7', 'H1', ReviewDecision::VALIDATE));
check('una sola operación cambia el estado del resultado Y registra la decisión', $out->status === ReviewOutcomeStatus::VALIDATED
    && $repo->findById('R1')->status === LabResultStatus::VALIDATED && count($repo->decisions()) === 1);
check('el puerto expone una única escritura atómica (decideAtomically)', method_exists(LabResultDecisionRepository::class, 'decideAtomically'));
check('LIMITE: la versión del resultado no cambia tras decidir (el control lo hace el estado)', $repo->findById('R1')->version === 1);

echo PHP_EOL . ($passed + $failed) . " comprobaciones, $failed fallidas." . PHP_EOL;
exit($failed === 0 ? 0 : 1);
