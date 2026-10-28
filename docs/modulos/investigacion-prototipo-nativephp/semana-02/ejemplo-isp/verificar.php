<?php

declare(strict_types=1);

require_once __DIR__ . '/despues/NativeCapabilities.php';
require_once __DIR__ . '/despues/InMemoryAdapters.php';

use NativePrototype\After\BarcodeScanner;
use NativePrototype\After\DemoOrderStatus;
use NativePrototype\After\DemoOrderStatusGateway;
use NativePrototype\After\ExportPrototypeEvidence;
use NativePrototype\After\InMemoryDemoOrderStatusGateway;
use NativePrototype\After\InMemoryPrototypeEvidenceExporter;
use NativePrototype\After\NativeNotificationStatus;
use NativePrototype\After\NativeNotifier;
use NativePrototype\After\PrototypeEvidenceExporter;
use NativePrototype\After\PrototypeOutcomeStatus;
use NativePrototype\After\RecordingNativeNotifier;
use NativePrototype\After\RunOrderStatusPrototype;

function verifySame(mixed $expected, mixed $actual, string $message): void
{
    if ($expected !== $actual) {
        throw new RuntimeException(
            "{$message}: se esperaba " . var_export($expected, true)
            . ', se obtuvo ' . var_export($actual, true),
        );
    }

    echo "PASS: {$message}\n";
}

$assertions = 0;
$check = static function (mixed $expected, mixed $actual, string $message) use (&$assertions): void {
    verifySame($expected, $actual, $message);
    $assertions++;
};

$gateway = new InMemoryDemoOrderStatusGateway();
$gateway->add('DEMO-ORDER-1', DemoOrderStatus::IN_PROCESS);
$notifier = new RecordingNativeNotifier();
$workflow = new RunOrderStatusPrototype($gateway, $notifier);
$outcome = $workflow->execute('DEMO-ORDER-1');
$check(PrototypeOutcomeStatus::NOTIFIED, $outcome->status, 'CA-01 consulta y notifica un estado de demostración');
$check(1, count($notifier->notifications()), 'CA-01 envía una notificación');
$check(
    'La consulta de demostración finalizó: EN_PROCESO',
    $notifier->notifications()[0]['message'],
    'CA-02 limita la notificación a un estado permitido y no incluye datos personales',
);

$outcome = $workflow->execute('');
$check(PrototypeOutcomeStatus::INVALID_INPUT, $outcome->status, 'CA-03 rechaza un identificador vacío');
$check(1, count($notifier->notifications()), 'CA-03 no notifica entradas inválidas');

$outcome = $workflow->execute('DEMO-ORDER-UNKNOWN');
$check(PrototypeOutcomeStatus::HIS_UNAVAILABLE, $outcome->status, 'CA-04 distingue el flujo HIS no disponible');
$check(1, count($notifier->notifications()), 'CA-04 no notifica una consulta no resuelta');

$unavailableNotifier = new RecordingNativeNotifier(NativeNotificationStatus::UNAVAILABLE);
$workflow = new RunOrderStatusPrototype($gateway, $unavailableNotifier);
$outcome = $workflow->execute('DEMO-ORDER-1');
$check(
    PrototypeOutcomeStatus::NOTIFICATION_UNAVAILABLE,
    $outcome->status,
    'CA-05 comunica explícitamente que la capacidad nativa no está disponible',
);

$evidenceExporter = new InMemoryPrototypeEvidenceExporter();
$export = new ExportPrototypeEvidence($evidenceExporter);
$check(true, $export->execute('evidence.txt', 'demo passed'), 'CA-06 exporta evidencia con su propia capacidad');
$check(
    ['evidence.txt' => 'demo passed'],
    $evidenceExporter->files(),
    'CA-06 el adaptador conserva el contenido exportado',
);

$notifierMethods = array_map(
    static fn (ReflectionMethod $method): string => $method->getName(),
    (new ReflectionClass(NativeNotifier::class))->getMethods(),
);
$check(['notify'], $notifierMethods, 'CA-07 la interfaz de notificaciones no obliga a exportar ni escanear');

$workflowDependencies = array_map(
    static fn (ReflectionParameter $parameter): ?string => $parameter->getType()?->getName(),
    (new ReflectionMethod(RunOrderStatusPrototype::class, '__construct'))->getParameters(),
);
$check(
    [DemoOrderStatusGateway::class, NativeNotifier::class],
    $workflowDependencies,
    'CA-08 el flujo depende solo de las capacidades que usa',
);

$evidenceDependencies = array_map(
    static fn (ReflectionParameter $parameter): ?string => $parameter->getType()?->getName(),
    (new ReflectionMethod(ExportPrototypeEvidence::class, '__construct'))->getParameters(),
);
$check(
    [PrototypeEvidenceExporter::class],
    $evidenceDependencies,
    'CA-09 exportar evidencia no requiere implementar otras capacidades',
);

$scannerMethods = array_map(
    static fn (ReflectionMethod $method): string => $method->getName(),
    (new ReflectionClass(BarcodeScanner::class))->getMethods(),
);
$check(['scan'], $scannerMethods, 'CA-10 el escáner se mantiene como capacidad independiente');

echo "OK: {$assertions} aserciones.\n";
