<?php
declare(strict_types=1);
namespace NativePrototype\Evaluation;
/** Política del experimento; no descubre capacidades reales de un dispositivo. */
final class ViabilityPolicy
{
    public function evaluate(bool $runtimeVerified, bool $platformVerified, bool $notificationGranted, bool $hisReachable): string
    {
        if (!$runtimeVerified || !$platformVerified) return 'NOT_DEMONSTRATED';
        if (!$notificationGranted || !$hisReachable) return 'CONDITIONAL';
        return 'VIABLE_FOR_DEMO';
    }
}
/** Sin escrituras offline: una caída nunca convierte un snapshot en dato actual. */
final class SyncPolicy
{
    public function resolve(?string $remoteStatus, ?string $previousSnapshot): array
    {
        if ($remoteStatus !== null) return ['status'=>$remoteStatus,'fresh'=>true,'source'=>'HIS'];
        return ['status'=>$previousSnapshot,'fresh'=>false,'source'=>$previousSnapshot===null?'NONE':'STALE_SNAPSHOT'];
    }
}