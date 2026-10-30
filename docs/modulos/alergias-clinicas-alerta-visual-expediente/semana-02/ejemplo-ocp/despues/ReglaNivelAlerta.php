<?php
declare(strict_types=1);

// DESPUÉS: el punto de extensión. Cada regla es una clase; el calculador solo conoce esta interfaz.
interface ReglaNivelAlerta
{
    /** @param array{severidad: string, reaccion: ?string} $alergia */
    public function nivelPara(array $alergia): ?string;
}

final class ReglaPorSeveridad implements ReglaNivelAlerta
{
    public function nivelPara(array $alergia): ?string
    {
        return match ($alergia['severidad']) {
            'life_threatening' => 'CRITICA',
            'severe', 'unknown' => 'ALTA',
            'moderate' => 'MODERADA',
            default => 'INFORMATIVA',
        };
    }
}

final class ReglaReaccionAnafilactica implements ReglaNivelAlerta
{
    public function nivelPara(array $alergia): ?string
    {
        return str_contains(mb_strtolower($alergia['reaccion'] ?? ''), 'anafila') ? 'CRITICA' : null;
    }
}
