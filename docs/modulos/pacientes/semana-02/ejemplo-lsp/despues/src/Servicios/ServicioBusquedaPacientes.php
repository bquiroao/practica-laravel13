<?php

declare(strict_types=1);

namespace Pacientes\Servicios;

use Pacientes\Contratos\ConsultaPacientes;
use Pacientes\Contratos\RegistroAuditoria;
use Pacientes\Dominio\CriterioBusqueda;
use Pacientes\Dominio\ErrorPaciente;
use Pacientes\Dominio\EventoAuditoria;
use Pacientes\Dominio\Paciente;
use Pacientes\Dominio\Respuesta;

/**
 * CU-02 y CU-03: buscar y ver el detalle. Depende de ConsultaPacientes, así que funciona
 * igual con el repositorio principal, la réplica de lectura o la caché.
 */
final class ServicioBusquedaPacientes
{
    public function __construct(
        private readonly ConsultaPacientes $consulta,
        private readonly RegistroAuditoria $auditoria,
        private readonly ControlAcceso $acceso,
    ) {
    }

    /** @param array<string, ?string> $criterios codigo, tipoDocumento, numeroDocumento, paisDocumento, nombre, nacimiento */
    public function buscar(string $actor, string $hospital, array $criterios): Respuesta
    {
        try {
            $this->acceso->exigir($actor, $hospital, 'pacientes.buscar');
            $criterio = CriterioBusqueda::desde(...$criterios);
            $encontrados = $this->consulta->buscar($hospital, $criterio);
            $this->auditoria->registrar(new EventoAuditoria('PACIENTE_BUSCADO', $actor, $hospital, null, [
                'criterio' => $criterio->resumenAuditable(),
                'resultados' => count($encontrados),
            ]));

            return new Respuesta(200, [
                'resultados' => array_map(static fn (Paciente $p): array => $p->resumen(), $encontrados),
                'total' => count($encontrados),
            ]);
        } catch (ErrorPaciente $e) {
            return Respuesta::desdeError($e);
        } catch (\Throwable) {
            return Respuesta::errorInterno();
        }
    }

    public function detalle(string $actor, string $hospital, string $codigo): Respuesta
    {
        try {
            $this->acceso->exigir($actor, $hospital, 'pacientes.leer');
            // Mismo 404 si no existe o si es de otro hospital: no se revela en cuál está.
            $paciente = $this->consulta->porCodigo($hospital, $codigo)
                ?? throw new ErrorPaciente(404, 'PACIENTE_NO_ENCONTRADO');
            $this->auditoria->registrar(new EventoAuditoria('PACIENTE_CONSULTADO', $actor, $hospital, $paciente->codigo, []));

            return new Respuesta(200, ['paciente' => $paciente->detalle()]);
        } catch (ErrorPaciente $e) {
            return Respuesta::desdeError($e);
        } catch (\Throwable) {
            return Respuesta::errorInterno();
        }
    }
}
