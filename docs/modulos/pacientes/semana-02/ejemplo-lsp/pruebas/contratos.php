<?php

declare(strict_types=1);

/*
 * Pruebas de contrato (LSP): la MISMA batería se ejecuta contra cada implementación.
 * Un subtipo es sustituible si no viola ninguna cláusula del contrato de su tipo base.
 *
 * Cada función devuelve la lista de cláusulas violadas (vacía = cumple). Usan los objetos
 * por su comportamiento, sin exigir el tipo, para poder evaluar también el diseño ANTES.
 */

use Pacientes\Dominio\ConflictoVersion;
use Pacientes\Dominio\CriterioBusqueda;
use Pacientes\Dominio\DatosPaciente;
use Pacientes\Dominio\ErrorPaciente;
use Pacientes\Dominio\Paciente;

const DPI_CONTRATO = '3012457890113';

function pacienteContrato(string $nombres, string $hospital, ?string $dpi = null, string $nacimiento = '1990-01-01'): Paciente
{
    return Paciente::desdeDatos($hospital, new DatosPaciente($nombres, 'Pérez Gómez', $nacimiento, 'unknown', null,
        $dpi === null ? null : 'DPI', $dpi), new DateTimeImmutable('2026-10-21'));
}

/** 23 pacientes «Pérez» en Oriente (el primero con DPI) y 2 en Norte. */
function semillaContrato(): array
{
    $semilla = [];
    for ($i = 0; $i < 23; $i++) {
        $semilla[] = pacienteContrato('Persona ' . chr(87 - $i), H_ORIENTE, $i === 0 ? DPI_CONTRATO : null);
    }
    $semilla[] = pacienteContrato('Persona Norte', H_NORTE);
    $semilla[] = pacienteContrato('Otra Norte', H_NORTE);

    return $semilla;
}

/**
 * Contrato de Identificador.
 *
 * @param callable(int $persona, int $escritura): array{0: object, 1: string} $fabrica
 *        $persona 1 o 2 son personas distintas; $escritura 1 o 2 es el MISMO documento escrito
 *        de dos formas. Devuelve [identificador, valor sin formato].
 * @return list<string>
 */
function contratoIdentificador(callable $fabrica): array
{
    $violaciones = [];
    [$a1, $valorA] = $fabrica(1, 1);
    [$a2] = $fabrica(1, 2);
    [$b1] = $fabrica(2, 1);

    if ($a1->claveBusqueda() === '' || $b1->claveBusqueda() === '') {
        $violaciones[] = 'I1 la clave de búsqueda es vacía';
    }
    if ($a1->claveBusqueda() !== $a2->claveBusqueda()) {
        $violaciones[] = 'I2 el mismo documento escrito distinto produce otra clave';
    }
    if ($a1->claveBusqueda() === $b1->claveBusqueda()) {
        $violaciones[] = 'I3 dos personas distintas comparten la misma clave';
    }
    if ($valorA !== '' && str_contains($a1->enmascarado(), $valorA)) {
        $violaciones[] = 'I4 el enmascarado muestra el valor completo';
    }

    return $violaciones;
}

/**
 * Contrato de ConsultaPacientes.
 *
 * @param callable(list<Paciente>): array{0: object, 1: list<string>} $fabrica crea la implementación
 *        con esos pacientes y devuelve [consulta, códigos asignados en el mismo orden]
 * @return list<string>
 */
function contratoConsultaPacientes(callable $fabrica): array
{
    $violaciones = [];
    $semilla = semillaContrato();
    try {
        [$consulta, $codigos] = $fabrica($semilla);

        $primero = $consulta->porCodigo(H_ORIENTE, $codigos[0]);
        if ($primero === null || $primero->nombres !== $semilla[0]->nombres) {
            $violaciones[] = 'Q1 porCodigo no devuelve al paciente de su hospital';
        }
        if ($consulta->porCodigo(H_NORTE, $codigos[0]) !== null) {
            $violaciones[] = 'Q2 porCodigo devuelve un paciente de otro hospital';
        }
        if ($consulta->porCodigo(H_ORIENTE, 'P-999999') !== null) {
            $violaciones[] = 'Q3 porCodigo devuelve algo para un código inexistente';
        }

        $lista = $consulta->buscar(H_ORIENTE, CriterioBusqueda::desde(nombre: 'perez'));
        foreach ($lista as $paciente) {
            if ($paciente->hospital !== H_ORIENTE) {
                $violaciones[] = 'Q4 buscar mezcla pacientes de otro hospital';
                break;
            }
        }
        if (count($lista) !== CriterioBusqueda::LIMITE) {
            $violaciones[] = sprintf('Q5 buscar devuelve %d resultados con 23 coincidencias (límite %d)', count($lista), CriterioBusqueda::LIMITE);
        }
        $ordenada = $lista;
        usort($ordenada, [Paciente::class, 'compararPorNombre']);
        if ($ordenada !== $lista) {
            $violaciones[] = 'Q6 buscar no ordena por apellidos y nombres';
        }

        $porDocumento = $consulta->buscar(H_ORIENTE, CriterioBusqueda::desde(tipoDocumento: 'DPI', numeroDocumento: DPI_CONTRATO));
        if (count($porDocumento) !== 1 || $porDocumento[0]->codigo !== $codigos[0]) {
            $violaciones[] = 'Q7 buscar por documento no encuentra exactamente a su titular';
        }

        if ($consulta->buscar(H_ORIENTE, CriterioBusqueda::desde(nombre: 'zzzz')) !== []) {
            $violaciones[] = 'Q8 una búsqueda sin coincidencias no devuelve lista vacía';
        }
    } catch (\Throwable $e) {
        $violaciones[] = 'Q0 lanza ' . get_class($e) . ' al leer: ' . $e->getMessage();
    }

    return $violaciones;
}

/**
 * Contrato de RepositorioPacientes (escritura).
 *
 * @param callable(): object $fabrica crea la implementación vacía
 * @return list<string>
 */
function contratoRepositorioPacientes(callable $fabrica): array
{
    $violaciones = [];
    $repositorio = $fabrica();

    try {
        $ana = $repositorio->registrar(pacienteContrato('Ana', H_ORIENTE));
        $beto = $repositorio->registrar(pacienteContrato('Beto', H_ORIENTE));
    } catch (\Throwable $e) {
        return ['R1 registrar lanza ' . get_class($e) . ' con un paciente válido: ' . $e->getMessage()];
    }
    if ((string) $ana->codigo === '' || $ana->version !== 1) {
        $violaciones[] = 'R2 registrar no asigna código y versión 1';
    }
    if ($ana->codigo === $beto->codigo) {
        $violaciones[] = 'R3 registrar repite códigos';
    }

    try {
        $repositorio->porCodigo(H_ORIENTE, (string) $ana->codigo); // lectura previa: calienta cualquier caché
        $guardado = $repositorio->actualizar($ana->conDatosDe(pacienteContrato('Ana María', H_ORIENTE)), 1);
        if ($guardado->version !== 2 || $guardado->nombres !== 'Ana María') {
            $violaciones[] = 'R4 actualizar con la versión vigente no guarda con versión + 1';
        }
        $leido = $repositorio->porCodigo(H_ORIENTE, (string) $ana->codigo);
        if ($leido?->version !== 2 || $leido->nombres !== 'Ana María') {
            $violaciones[] = 'R8 después de actualizar, porCodigo devuelve datos viejos';
        }
    } catch (\Throwable $e) {
        $violaciones[] = 'R4 actualizar con la versión vigente lanza ' . get_class($e);
    }

    // Otro usuario leyó la versión 1 y guarda después: la vigente ya es la 2.
    try {
        $repositorio->actualizar($ana->conDatosDe(pacienteContrato('Anabel', H_ORIENTE)), 1);
        $violaciones[] = 'R5 actualizar con una versión desactualizada no lanza ConflictoVersion: se pierde la edición anterior';
    } catch (ConflictoVersion) {
        if ($repositorio->porCodigo(H_ORIENTE, (string) $ana->codigo)?->nombres !== 'Ana María') {
            $violaciones[] = 'R6 hubo conflicto de versión pero los datos cambiaron';
        }
    } catch (\Throwable $e) {
        $violaciones[] = 'R5 lanza ' . get_class($e) . ' en lugar de ConflictoVersion';
    }

    try {
        $repositorio->actualizar(pacienteContrato('Intruso', H_NORTE)->conCodigo((string) $ana->codigo, 2), 2);
        $violaciones[] = 'R7 actualizar acepta un paciente de otro hospital';
    } catch (ErrorPaciente $e) {
        if ($e->http !== 404) {
            $violaciones[] = 'R7 paciente de otro hospital: se esperaba 404 y se obtuvo ' . $e->http;
        }
    } catch (\Throwable $e) {
        $violaciones[] = 'R7 lanza ' . get_class($e) . ' en lugar de 404';
    }

    return $violaciones;
}
