<?php
declare(strict_types=1);

namespace AlertasCriticas\Presentation\Api;

/**
 * Tokens FICTICIOS de curso (Authorization: Bearer <token>). No son JWT ni credenciales reales:
 * en el HIS los emitiría el módulo de autenticación y rbac. Se documentan en el contrato API.
 */
final class AutenticadorFicticio
{
    public const TOKENS = [
        'tok-lab-gua' => ['sistema:ingreso-resultados-laboratorio', 'sistema_origen', 'HOSP-GUA-01'],
        'tok-enf-gua' => ['enf.turno01', 'enfermeria', 'HOSP-GUA-01'],
        'tok-med-gua' => ['med.tratante01', 'medico', 'HOSP-GUA-01'],
        'tok-jefe-gua' => ['jefe.servicio01', 'jefe_servicio', 'HOSP-GUA-01'],
        'tok-coord-gua' => ['coord.medico01', 'coordinacion', 'HOSP-GUA-01'],
        'tok-plan' => ['sistema:planificador', 'planificador', '*'],
        'tok-enf-xel' => ['enf.turno21', 'enfermeria', 'HOSP-XEL-02'],
    ];

    public function identificar(string $authorization): ?Identidad
    {
        if (!preg_match('/^Bearer\s+(\S+)$/', $authorization, $m) || !isset(self::TOKENS[$m[1]])) {
            return null;
        }
        [$usuario, $rol, $tenant] = self::TOKENS[$m[1]];
        return new Identidad($usuario, $rol, $tenant);
    }
}
