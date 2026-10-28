<?php

declare(strict_types=1);

namespace NativePrototype\Before;

interface NativeDeviceCapabilities
{
    public function notify(string $title, string $message): bool;

    public function share(string $text): bool;

    public function saveEvidence(string $fileName, string $contents): bool;

    public function scanBarcode(): ?string;

    public function captureImage(): ?string;

    public function print(string $contents): bool;
}

final class DesktopDevice implements NativeDeviceCapabilities
{
    public function notify(string $title, string $message): bool
    {
        return true;
    }

    public function share(string $text): bool
    {
        throw new \BadMethodCallException('El prototipo de escritorio no comparte contenido.');
    }

    public function saveEvidence(string $fileName, string $contents): bool
    {
        return true;
    }

    public function scanBarcode(): ?string
    {
        throw new \BadMethodCallException('El prototipo de escritorio no tiene lector configurado.');
    }

    public function captureImage(): ?string
    {
        throw new \BadMethodCallException('El prototipo de escritorio no usa cámara.');
    }

    public function print(string $contents): bool
    {
        throw new \BadMethodCallException('El prototipo de escritorio no tiene impresión configurada.');
    }
}

final class RunOrderStatusPrototype
{
    public function __construct(private NativeDeviceCapabilities $device)
    {
    }

    public function notifyStatus(string $status): bool
    {
        return $this->device->notify(
            'Prototipo HIS',
            "La consulta de demostración finalizó: {$status}",
        );
    }
}
