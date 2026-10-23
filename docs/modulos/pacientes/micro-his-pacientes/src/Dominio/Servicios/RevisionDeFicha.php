<?php

declare(strict_types=1);

namespace Pacientes\Dominio\Servicios;

use Pacientes\Dominio\Excepciones\ErrorDominio;
use Pacientes\Dominio\Modelo\Capacidades;
use Pacientes\Dominio\Modelo\DatosPaciente;
use Pacientes\Dominio\Modelo\Paciente;

/**
 * Semana 7 — Único lugar donde se cruzan las reglas de la ficha: identidad, datos demográficos,
 * edición, duplicados y confidencialidad. Antes ese cruce (y su ORDEN) estaba repetido en los
 * casos de uso de escritura. El orden importa: decide qué error se ve y evita revelar a un
 * protegido (por ejemplo, un 422 con los campos o un 409 con su código).
 * Las tres políticas no cambiaron: esta clase solo las compone.
 */
final class RevisionDeFicha
{
    public function __construct(
        private readonly PoliticaEdicion $edicion,
        private readonly PoliticaDuplicados $duplicados,
        private readonly PoliticaConfidencialidad $confidencialidad,
    ) {
    }

    /**
     * Alta: datos (R-01) → confidencialidad (R-11) → duplicados (R-02, R-03).
     *
     * @return array{paciente: Paciente, confirmadas: list<string>}
     * @throws ErrorDominio
     */
    public function paraAlta(string $hospital, DatosPaciente $datos, \DateTimeImmutable $hoy, Capacidades $puede,
        bool $confirmaCoincidencia, string $motivo): array
    {
        $paciente = Paciente::desdeDatos($hospital, $datos, $hoy);
        $this->confidencialidad->verificarRegistro($paciente, $puede->proteger, $motivo);
        $confirmadas = $this->duplicados->verificarRegistro($paciente, $confirmaCoincidencia, $puede->confirmarDuplicado,
            $motivo, $puede->verProtegidos);

        return ['paciente' => $paciente, 'confirmadas' => $confirmadas];
    }

    /**
     * Edición: visibilidad (R-11, antes de validar) → datos (R-01) → estado y motivo (R-08, R-07)
     * → cambio de protección (R-11) → documento de otro paciente (R-02).
     *
     * @throws ErrorDominio
     */
    public function paraEdicion(Paciente $actual, DatosPaciente $datos, \DateTimeImmutable $hoy, Capacidades $puede, string $motivo): Paciente
    {
        $this->confidencialidad->exigirVisibilidad($actual, $puede->verProtegidos);

        $nuevo = $actual->conDatosDe(Paciente::desdeDatos($actual->hospital, $datos, $hoy));
        if ($datos->identidadProtegida === null) {
            $nuevo = $nuevo->conProteccion($actual->identidadProtegida); // campo ausente = sin cambio
        }
        if (!$datos->documentoEnviado) {
            $nuevo = $nuevo->conIdentificador($actual->identificador);   // semana 12 (B-4): ídem para el documento
        }
        $this->edicion->verificar($actual, $nuevo, $puede->editarRestringido, $motivo);
        $this->confidencialidad->verificarCambio($actual, $nuevo, $puede->proteger, $motivo);
        if ($nuevo->cambiaIdentidadRespectoDe($actual)) {
            $this->duplicados->verificarDocumentoNuevo($actual, $nuevo);
        }

        return $nuevo;
    }
}
