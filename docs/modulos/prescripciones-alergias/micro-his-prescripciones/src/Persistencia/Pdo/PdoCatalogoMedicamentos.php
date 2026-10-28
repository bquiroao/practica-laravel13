<?php

declare(strict_types=1);

namespace Prescripciones\Persistencia\Pdo;

use Prescripciones\Aplicacion\Puertos\CatalogoMedicamentos;
use Prescripciones\Dominio\Excepciones\ErrorPersistencia;
use Prescripciones\Dominio\Modelo\Alergia;
use Prescripciones\Dominio\Modelo\Medicamento;
use Prescripciones\Dominio\Reglas\VerificadorAlergias;

final class PdoCatalogoMedicamentos implements CatalogoMedicamentos
{
    public function __construct(private readonly \PDO $pdo)
    {
    }

    public function buscar(string $codigo): ?Medicamento
    {
        $f = $this->fila('SELECT medication_code, name, active_ingredient_code, active_ingredient, pharmacological_group, unit, max_daily_dose,
            array_to_json(routes) AS routes, active FROM medications WHERE medication_code = :codigo', ['codigo' => $codigo]);

        return $f === null ? null : new Medicamento($f['medication_code'], $f['name'], $f['active_ingredient_code'], $f['active_ingredient'],
            $f['pharmacological_group'], $f['unit'], (float) $f['max_daily_dose'], json_decode((string) $f['routes'], true, 2, \JSON_THROW_ON_ERROR),
            (bool) $f['active']);
    }

    /** Busca por código ATC y, si no hay, por nombre normalizado (las claves se guardan en minúsculas y sin tildes). */
    public function grupoDelAlergeno(Alergia $alergia): ?string
    {
        $claves = array_filter([
            $alergia->codigo !== null ? strtolower(trim($alergia->codigo)) : null,
            $alergia->nombre !== null ? VerificadorAlergias::normalizar($alergia->nombre) : null,
        ]);
        foreach ($claves as $clave) {
            $f = $this->fila('SELECT pharmacological_group FROM allergen_groups WHERE allergen_key = :clave', ['clave' => $clave]);
            if ($f !== null) {
                return $f['pharmacological_group'];
            }
        }

        return null;
    }

    /** @param array<string, mixed> $parametros @return array<string, mixed>|null */
    private function fila(string $sql, array $parametros): ?array
    {
        try {
            $consulta = $this->pdo->prepare($sql);
            $consulta->execute($parametros);
            $fila = $consulta->fetch(\PDO::FETCH_ASSOC);

            return $fila === false ? null : $fila;
        } catch (\PDOException $e) {
            throw new ErrorPersistencia('No se pudo leer el catálogo.', 0, $e);
        }
    }
}
