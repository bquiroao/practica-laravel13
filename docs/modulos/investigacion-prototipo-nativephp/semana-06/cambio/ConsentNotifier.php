<?php

declare(strict_types=1);
namespace NativeWeek6;
require_once __DIR__.'/../../semana-03/micro-his/Domain/Model.php';
use NativeWeek3\Domain\Notifier;
/** Permiso consultado por adaptador del destino, no bandera confiable del usuario. */
interface NotificationPermission { public function allowed(): bool; }
final class ConsentNotifier implements Notifier
{
    public function __construct(private NotificationPermission $permission,private Notifier $native) {}
    public function notify(string $message): string
    {
        if(!$this->permission->allowed())return 'UNAVAILABLE';
        return $this->native->notify($message);
    }
}