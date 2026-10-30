<?php
declare(strict_types=1);

namespace AlertasCriticas\Application;

use AlertasCriticas\Domain\{Alerta, AlertaNoEncontradaException, AlertaRepository, Reloj};

/** CU-06: cierre con nota obligatoria (RN-09). El permiso por rol se aplica en la entrada (semana 5). */
final class CerrarAlerta
{
    public function __construct(private AlertaRepository $repositorio, private Reloj $reloj) {}

    public function ejecutar(string $idAlerta, string $usuario, string $nota, string $tenant): Alerta
    {
        $alerta = $this->repositorio->buscar($idAlerta, $tenant)
            ?? throw new AlertaNoEncontradaException("No existe la alerta $idAlerta en el tenant $tenant");
        $alerta->cerrar($usuario, $nota, $this->reloj->ahora());
        $this->repositorio->guardar($alerta);
        return $alerta;
    }
}
