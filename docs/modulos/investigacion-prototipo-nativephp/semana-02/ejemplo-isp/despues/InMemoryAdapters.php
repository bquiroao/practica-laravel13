<?php

declare(strict_types=1);

namespace NativePrototype\After;

final class InMemoryDemoOrderStatusGateway implements DemoOrderStatusGateway
{
    /** @var array<string, DemoOrderStatus> */
    private array $orders = [];

    public function add(string $demoOrderId, DemoOrderStatus $status): void
    {
        $this->orders[$demoOrderId] = $status;
    }

    public function statusForDemoOrder(string $demoOrderId): ?DemoOrderStatus
    {
        return $this->orders[$demoOrderId] ?? null;
    }
}

final class RecordingNativeNotifier implements NativeNotifier
{
    /** @var list<array{title: string, message: string}> */
    private array $notifications = [];

    public function __construct(private NativeNotificationStatus $nextStatus = NativeNotificationStatus::SENT)
    {
    }

    public function notify(string $title, string $message): NativeNotificationStatus
    {
        if ($this->nextStatus === NativeNotificationStatus::SENT) {
            $this->notifications[] = ['title' => $title, 'message' => $message];
        }

        return $this->nextStatus;
    }

    /** @return list<array{title: string, message: string}> */
    public function notifications(): array
    {
        return $this->notifications;
    }
}

final class InMemoryPrototypeEvidenceExporter implements PrototypeEvidenceExporter
{
    /** @var array<string, string> */
    private array $files = [];

    public function export(string $fileName, string $contents): bool
    {
        $this->files[$fileName] = $contents;
        return true;
    }

    /** @return array<string, string> */
    public function files(): array
    {
        return $this->files;
    }
}
