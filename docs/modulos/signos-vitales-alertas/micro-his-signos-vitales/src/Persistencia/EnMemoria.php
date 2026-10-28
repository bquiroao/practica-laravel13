<?php
declare(strict_types=1);

// Adaptador en memoria: mismo contrato que TomasPdo, sin base. Sirve para pruebas rápidas y como doble que falla.
namespace SignosVitales\Persistencia;

use SignosVitales\Aplicacion\{ErrorPersistencia, FuenteClinica, RepositorioTomas};
use SignosVitales\Dominio\{RangoSigno, Toma};

final class EnMemoria implements RepositorioTomas, FuenteClinica {
    private array $tomas = [];
    public bool $fallarAlGuardar = false;

    /** $episodios: id => [paciente_id, hospital_id, fecha_nacimiento, activo]; $rangos: lista de [edad mínima, edad máxima, rangos] */
    public function __construct(private array $episodios = [], private array $rangos = []) {}

    public function episodioActivo(string $pacienteId, string $episodioId): ?array {
        $e = $this->episodios[$episodioId] ?? null;
        return ($e !== null && $e['activo'] && $e['paciente_id'] === $pacienteId) ? ['hospital_id' => $e['hospital_id'], 'fecha_nacimiento' => $e['fecha_nacimiento']] : null;
    }

    public function rangosPara(int $edad): array {
        foreach ($this->rangos as [$min, $max, $rangos]) { if ($edad >= $min && $edad <= $max) { return $rangos; } }
        return [];
    }

    public function guardar(Toma $toma): void {
        if ($this->fallarAlGuardar) { throw new ErrorPersistencia('Falla simulada al guardar'); }
        $this->tomas[$toma->id] = $toma;
    }

    public function deEpisodio(string $episodioId): array {
        $propias = array_filter($this->tomas, fn (Toma $t) => $t->episodioId === $episodioId);
        usort($propias, fn (Toma $a, Toma $b) => [$b->tomadaEn, $a->id] <=> [$a->tomadaEn, $b->id]);
        return array_map(fn (Toma $t) => ['toma_id' => $t->id, 'tomada_en' => $t->tomadaEn->format('c'), 'autor_id' => $t->autorId, 'mediciones' => array_combine(
            array_map(fn ($m) => $m->tipo->value, $t->mediciones),
            array_map(fn ($m) => ['valor' => $m->valor, 'clasificacion' => $t->clasificaciones[$m->tipo->value]->value], $t->mediciones))], $propias);
    }
}
