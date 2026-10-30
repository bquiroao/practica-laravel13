<?php
declare(strict_types=1);

namespace AlertasCriticas\Presentation\Web;

/**
 * Identidad de DEMOSTRACIÓN (semana 4): el usuario ficticio se elige en un selector y viaja en una cookie.
 * No es autenticación. En la semana 5 se reemplaza por tokens ficticios con rol y tenant.
 */
final class SesionDemo
{
    public const USUARIOS = [
        'enf.turno01' => ['tenant' => 'HOSP-GUA-01', 'rol' => 'enfermeria'],
        'med.tratante01' => ['tenant' => 'HOSP-GUA-01', 'rol' => 'medico'],
        'jefe.servicio01' => ['tenant' => 'HOSP-GUA-01', 'rol' => 'jefe_servicio'],
        'coord.medico01' => ['tenant' => 'HOSP-GUA-01', 'rol' => 'coordinacion'],
        'enf.turno21' => ['tenant' => 'HOSP-XEL-02', 'rol' => 'enfermeria'],
    ];

    public static function desde(Peticion $peticion): self
    {
        $usuario = (string) ($peticion->cookies['usuario_demo'] ?? '');
        return new self(isset(self::USUARIOS[$usuario]) ? $usuario : 'enf.turno01');
    }

    private function __construct(public readonly string $usuario) {}

    public function tenant(): string
    {
        return self::USUARIOS[$this->usuario]['tenant'];
    }

    public function rol(): string
    {
        return self::USUARIOS[$this->usuario]['rol'];
    }
}
