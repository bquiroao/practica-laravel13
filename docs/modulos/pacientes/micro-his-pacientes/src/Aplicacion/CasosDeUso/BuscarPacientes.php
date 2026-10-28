<?php

declare(strict_types=1);

namespace Pacientes\Aplicacion\CasosDeUso;

use Pacientes\Aplicacion\Comandos\ComandoBuscar;
use Pacientes\Aplicacion\Resultados\Resultado;
use Pacientes\Aplicacion\Servicios\ControlAcceso;
use Pacientes\Dominio\Excepciones\ErrorDominio;
use Pacientes\Dominio\Excepciones\ErrorPersistencia;
use Pacientes\Dominio\Modelo\CriterioBusqueda;
use Pacientes\Dominio\Modelo\EventoAuditoria;
use Pacientes\Dominio\Modelo\Paciente;
use Pacientes\Dominio\Puertos\ConsultaPacientes;
use Pacientes\Dominio\Puertos\RegistroAuditoria;

/** CU-02: búsqueda acotada al hospital activo, con datos mínimos (R-10, R-04). */
final class BuscarPacientes
{
    public function __construct(
        private readonly ConsultaPacientes $consulta,
        private readonly RegistroAuditoria $auditoria,
        private readonly ControlAcceso $acceso,
    ) {
    }

    public function ejecutar(ComandoBuscar $comando): Resultado
    {
        try {
            $this->acceso->exigir($comando->actor, $comando->hospital, ControlAcceso::BUSCAR);
            $c = $comando->criterios;
            $criterio = CriterioBusqueda::desde($c['codigo'] ?? null, $c['tipo_documento'] ?? null, $c['numero_documento'] ?? null,
                $c['pais_documento'] ?? null, $c['nombre'] ?? null, $c['nacimiento'] ?? null);
            $verProtegidos = $this->acceso->capacidades($comando->actor, $comando->hospital)->verProtegidos;
            $criterio = $criterio->incluyendoProtegidos($verProtegidos); // R-11

            $encontrados = $this->consulta->buscar($comando->hospital, $criterio);
            $this->auditoria->registrar(new EventoAuditoria(EventoAuditoria::BUSCADO, $comando->actor, $comando->hospital, null, [
                'criterio' => $criterio->resumenAuditable(),
                'resultados' => count($encontrados),
            ]));

            return Resultado::ok([
                'resultados' => array_map(static fn (Paciente $p): array => $p->resumen($verProtegidos), $encontrados),
                'total' => count($encontrados),
            ]);
        } catch (ErrorDominio $e) {
            return Resultado::desdeError($e);
        } catch (ErrorPersistencia) {
            return Resultado::noDisponible();
        }
    }
}
