<?php

declare(strict_types=1);

namespace Pacientes\Persistencia;

use Pacientes\Persistencia\EnMemoria\IndiceDuplicadosEnMemoria;
use Pacientes\Persistencia\EnMemoria\RegistroAuditoriaEnMemoria;
use Pacientes\Persistencia\EnMemoria\RepositorioPacientesEnMemoria;
use Pacientes\Persistencia\EnMemoria\TransaccionEnMemoria;

/** Adaptadores InMemory: para pruebas, demostraciones y ejecutar la API sin base de datos. */
final class FabricaEnMemoria
{
    public static function crear(): ConjuntoAdaptadores
    {
        $repositorio = new RepositorioPacientesEnMemoria();
        $auditoria = new RegistroAuditoriaEnMemoria();

        return new ConjuntoAdaptadores(
            $repositorio,
            $repositorio,
            new IndiceDuplicadosEnMemoria($repositorio),
            $auditoria,
            new TransaccionEnMemoria([$repositorio, $auditoria]),
        );
    }
}
