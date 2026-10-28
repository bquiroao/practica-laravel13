<?php

declare(strict_types=1);

namespace NativePrototype\After;

enum DemoOrderStatus: string
{
    case PENDING = 'PENDIENTE';
    case RECEIVED = 'RECIBIDA';
    case IN_PROCESS = 'EN_PROCESO';
    case COMPLETED = 'COMPLETADA';
}

enum NativeNotificationStatus: string
{
    case SENT = 'SENT';
    case UNAVAILABLE = 'UNAVAILABLE';
}

enum PrototypeOutcomeStatus: string
{
    case NOTIFIED = 'NOTIFIED';
    case NOTIFICATION_UNAVAILABLE = 'NOTIFICATION_UNAVAILABLE';
    case HIS_UNAVAILABLE = 'HIS_UNAVAILABLE';
    case INVALID_INPUT = 'INVALID_INPUT';
}

interface DemoOrderStatusGateway
{
    public function statusForDemoOrder(string $demoOrderId): ?DemoOrderStatus;
}

interface NativeNotifier
{
    public function notify(string $title, string $message): NativeNotificationStatus;
}

interface PrototypeEvidenceExporter
{
    public function export(string $fileName, string $contents): bool;
}

interface BarcodeScanner
{
    public function scan(): ?string;
}

final readonly class PrototypeOutcome
{
    public function __construct(public PrototypeOutcomeStatus $status)
    {
    }
}

final class RunOrderStatusPrototype
{
    public function __construct(
        private DemoOrderStatusGateway $gateway,
        private NativeNotifier $notifier,
    ) {
    }

    public function execute(string $demoOrderId): PrototypeOutcome
    {
        if (trim($demoOrderId) === '') {
            return new PrototypeOutcome(PrototypeOutcomeStatus::INVALID_INPUT);
        }

        $status = $this->gateway->statusForDemoOrder($demoOrderId);
        if ($status === null) {
            return new PrototypeOutcome(PrototypeOutcomeStatus::HIS_UNAVAILABLE);
        }

        $notificationStatus = $this->notifier->notify(
            'Prototipo HIS',
            "La consulta de demostración finalizó: {$status->value}",
        );

        return new PrototypeOutcome(
            $notificationStatus === NativeNotificationStatus::SENT
                ? PrototypeOutcomeStatus::NOTIFIED
                : PrototypeOutcomeStatus::NOTIFICATION_UNAVAILABLE,
        );
    }
}

final class ExportPrototypeEvidence
{
    public function __construct(private PrototypeEvidenceExporter $exporter)
    {
    }

    public function execute(string $fileName, string $contents): bool
    {
        return $this->exporter->export($fileName, $contents);
    }
}
