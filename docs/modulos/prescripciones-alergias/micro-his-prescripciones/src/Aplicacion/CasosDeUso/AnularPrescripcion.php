<?php

declare(strict_types=1);

namespace Prescripciones\Aplicacion\CasosDeUso;

use Prescripciones\Aplicacion\Puertos\PoliticaAcceso;
use Prescripciones\Aplicacion\Puertos\RegistroAuditoria;
use Prescripciones\Aplicacion\Puertos\Transaccion;
use Prescripciones\Aplicacion\Resultados\Resultado;
use Prescripciones\Dominio\Excepciones\ErrorPersistencia;
use Prescripciones\Dominio\Excepciones\YaAnulada;
use Prescripciones\Dominio\Modelo\Anulacion;
use Prescripciones\Dominio\Puertos\Reloj;
use Prescripciones\Dominio\Puertos\RepositorioPrescripciones;
use Prescripciones\Dominio\Reglas\PoliticaAnulacion;

/**
 * CU-07 (semana 6, cambio práctico): anular una prescripción emitida, con motivo.
 * Orden (actividad de la semana 1, D-17..D-19): permiso → existe en el hospital → la política decide → anulación + traza
 * en una transacción. No consulta el Expediente: anular reduce el riesgo y no debe quedar bloqueado por él.
 */
final class AnularPrescripcion
{
    public const PERMISO = 'prescripcion.anular';

    public function __construct(
        private readonly PoliticaAcceso $acceso,
        private readonly RepositorioPrescripciones $repositorio,
        private readonly RegistroAuditoria $auditoria,
        private readonly Transaccion $transaccion,
        private readonly Reloj $reloj,
        private readonly PoliticaAnulacion $politica = new PoliticaAnulacion(),
    ) {
    }

    public function ejecutar(string $usuario, string $hospital, int $id, string $motivo): Resultado
    {
        if (!$this->acceso->permite($usuario, self::PERMISO, $hospital)) {
            return new Resultado(Resultado::DENEGADO, 'PERMISO_DENEGADO');
        }
        try {
            $prescripcion = $this->repositorio->porId($hospital, $id);
            if ($prescripcion === null) {
                return new Resultado(Resultado::NO_ENCONTRADO, 'NO_ENCONTRADO');
            }
            $rechazo = $this->politica->rechazo($prescripcion, $motivo);
            if ($rechazo !== null) {
                return new Resultado($rechazo === 'MOTIVO_REQUERIDO' ? Resultado::RECHAZADO : Resultado::CONFLICTO, $rechazo);
            }
            $anulacion = new Anulacion($id, $hospital, trim($motivo), $usuario, $this->reloj->ahora());
            $this->transaccion->ejecutar(function () use ($anulacion, $usuario, $hospital, $id): void {
                $this->repositorio->anular($anulacion);
                $this->auditoria->registrar('PRESCRIPCION_ANULADA', $hospital, $usuario, 'prescripcion:' . $id, 'exito');
            });
        } catch (YaAnulada) {
            return new Resultado(Resultado::CONFLICTO, 'ESTADO_NO_PERMITE_ANULAR');
        } catch (ErrorPersistencia) {
            return new Resultado(Resultado::NO_DISPONIBLE, 'ERROR_PERSISTENCIA');
        }

        return new Resultado(Resultado::OK, 'ANULADA', ['id' => $id, 'anulacion' => $anulacion->aArreglo()]);
    }
}
