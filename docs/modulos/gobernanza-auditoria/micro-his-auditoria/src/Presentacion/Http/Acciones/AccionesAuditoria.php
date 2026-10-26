<?php

declare(strict_types=1);

namespace Auditoria\Presentacion\Http\Acciones;

use Auditoria\Aplicacion\CasosDeUso\ConsultarMovimientos;
use Auditoria\Aplicacion\CasosDeUso\RegistrarMovimiento;
use Auditoria\Aplicacion\CasosDeUso\VerDetalleMovimiento;
use Auditoria\Aplicacion\CasosDeUso\VerificarIntegridad;
use Auditoria\Aplicacion\Comandos\ComandoConsultar;
use Auditoria\Aplicacion\Comandos\ComandoDetalle;
use Auditoria\Aplicacion\Comandos\ComandoRegistrar;
use Auditoria\Aplicacion\Comandos\ComandoVerificar;
use Auditoria\Aplicacion\Resultados\Resultado;
use Auditoria\Presentacion\Http\Identidad;
use Auditoria\Presentacion\Http\Respuesta;
use Auditoria\Presentacion\Http\Solicitud;
use Auditoria\Presentacion\Http\TraductorResultado;

/**
 * Una acción por ruta: de HTTP a comando, y del Resultado a HTTP. Solo valida FORMATO
 * (cuerpo JSON, tipos de la consulta); las reglas de negocio son del Dominio.
 */
final class AccionesAuditoria
{
    public function __construct(
        private readonly RegistrarMovimiento $registrar,
        private readonly ConsultarMovimientos $consultar,
        private readonly VerDetalleMovimiento $detalle,
        private readonly VerificarIntegridad $verificar,
    ) {
    }

    public function registrar(Solicitud $solicitud, Identidad $identidad): Respuesta
    {
        if ($solicitud->cuerpo === null) {
            return TraductorResultado::error(400, 'CUERPO_INVALIDO');
        }
        $resultado = $this->registrar->ejecutar(new ComandoRegistrar($identidad->actor, $solicitud->cabecera('X-Tenant-ID'), $solicitud->cuerpo));
        // Location solo con 201: en el reintento (200) PHP convertiría la respuesta en 302 (hallazgo de la prueba de punta a punta).
        $creado = $resultado->estado === Resultado::CREADO;

        return TraductorResultado::respuesta($resultado, $creado ? ['Location' => '/api/v1/auditoria/movimientos/' . $resultado->datos['id']] : []);
    }

    public function consultar(Solicitud $solicitud, Identidad $identidad): Respuesta
    {
        foreach ($solicitud->consulta as $nombre => $valor) {
            if (!is_string($valor)) {
                return TraductorResultado::error(400, 'FORMATO_INVALIDO', ['campos' => [(string) $nombre => 'Un solo valor de texto.']]);
            }
        }

        return TraductorResultado::respuesta($this->consultar->ejecutar(
            new ComandoConsultar($identidad->actor, $solicitud->cabecera('X-Tenant-ID'), $solicitud->consulta)));
    }

    /** @param array<string, string> $parametros */
    public function detalle(Solicitud $solicitud, Identidad $identidad, array $parametros): Respuesta
    {
        return TraductorResultado::respuesta($this->detalle->ejecutar(
            new ComandoDetalle($identidad->actor, $solicitud->cabecera('X-Tenant-ID'), (int) $parametros['id'])));
    }

    public function verificar(Solicitud $solicitud, Identidad $identidad): Respuesta
    {
        $hospital = $solicitud->cuerpo['hospital'] ?? null;
        if ($solicitud->cuerpo === null || ($hospital !== null && !is_string($hospital))) {
            return TraductorResultado::error(400, 'CUERPO_INVALIDO', ['campos' => ['hospital' => 'Texto, o null para la cadena de red.']]);
        }

        return TraductorResultado::respuesta($this->verificar->ejecutar(new ComandoVerificar($identidad->actor, $hospital)));
    }
}
