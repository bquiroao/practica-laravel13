<?php

declare(strict_types=1);

namespace Prescripciones\Persistencia\Pdo;

use Prescripciones\Aplicacion\Puertos\FuenteAlergias;
use Prescripciones\Aplicacion\Puertos\FuenteAlergiasNoDisponible;
use Prescripciones\Dominio\Modelo\Alergia;
use Prescripciones\Dominio\Modelo\ExpedienteAlergias;

/**
 * Lee la tabla `allergies` (columnas de la migración real del HIS). La conexión ya es la de la base del hospital,
 * por eso el hospital no entra en el SQL; el tenant del paciente lo verifica antes el puerto Pacientes.
 * Contrato (R-07): cualquier falla técnica sale como FuenteAlergiasNoDisponible, nunca como lista vacía.
 */
final class PdoFuenteAlergias implements FuenteAlergias
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function alergiasDe(string $paciente, string $hospital): ExpedienteAlergias
    {
        try {
            $consulta = $this->pdo->prepare('SELECT allergen_code, allergen_name, reaction, allergy_type, severity, status
                FROM allergies WHERE local_patient_id = :paciente ORDER BY allergy_id');
            $consulta->execute(['paciente' => $paciente]);
            $filas = $consulta->fetchAll(\PDO::FETCH_ASSOC);
        } catch (\PDOException $e) {
            throw new FuenteAlergiasNoDisponible('El expediente no respondió.', 0, $e);
        }
        $alergias = array_map(static fn (array $f): Alergia => new Alergia(
            $f['allergen_code'], $f['allergen_name'], $f['allergy_type'], $f['severity'], $f['status'], $f['reaction'],
        ), $filas);

        return new ExpedienteAlergias($alergias !== [], $alergias);
    }
}
