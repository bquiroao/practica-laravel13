<?php

declare(strict_types=1);

namespace Prescripciones\Adaptadores\Pdo;

use Prescripciones\Aplicacion\Puertos\FuenteAlergias;
use Prescripciones\Aplicacion\Puertos\FuenteAlergiasNoDisponible;
use Prescripciones\Dominio\Alergia;
use Prescripciones\Dominio\ExpedienteAlergias;

/**
 * Adaptador de bajo nivel: lee la tabla real `allergies` de la base de CADA hospital
 * (database/migrations/hospital/2026_08_11_000401_…). Depende del puerto, no al revés.
 * Traduce cualquier falla técnica a FuenteAlergiasNoDisponible: el contrato prohíbe devolver vacío.
 */
final class FuenteAlergiasPdo implements FuenteAlergias
{
    private const SQL = 'SELECT allergen_code, allergen_name, reaction, allergy_type, severity, status
                           FROM allergies WHERE local_patient_id = :paciente';

    /** @param array<string, \PDO> $conexiones hospital => conexión a su base */
    public function __construct(private readonly array $conexiones)
    {
    }

    public function alergiasDe(string $paciente, string $hospital): ExpedienteAlergias
    {
        $pdo = $this->conexiones[$hospital] ?? throw new FuenteAlergiasNoDisponible("Sin conexión al expediente de $hospital.");
        try {
            $consulta = $pdo->prepare(self::SQL);
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
