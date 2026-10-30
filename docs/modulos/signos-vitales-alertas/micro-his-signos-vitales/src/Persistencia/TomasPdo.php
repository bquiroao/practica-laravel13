<?php
declare(strict_types=1);

// Persistencia: SQL con sentencias preparadas. Toma, mediciones y alertas en una sola transacción (RNF-01).
namespace SignosVitales\Persistencia;

use SignosVitales\Aplicacion\{ErrorPersistencia, FuenteClinica, RepositorioTomas};
use SignosVitales\Dominio\{Clasificacion, RangoSigno, Toma};

final class TomasPdo implements RepositorioTomas, FuenteClinica {
    public function __construct(private \PDO $pdo) { $pdo->setAttribute(\PDO::ATTR_ERRMODE, \PDO::ERRMODE_EXCEPTION); }

    public function episodioActivo(string $pacienteId, string $episodioId): ?array {
        $fila = $this->leer('SELECT e.hospital_id, p.fecha_nacimiento FROM episodios e JOIN pacientes p ON p.id = e.paciente_id
                             WHERE e.id = :e AND e.paciente_id = :p AND e.estado = :estado', [':e' => $episodioId, ':p' => $pacienteId, ':estado' => 'activo']);
        return $fila[0] ?? null;
    }

    /** @return array<string, RangoSigno> rangos del catálogo para esa edad (vacío si no hay, por ejemplo pediatría) */
    public function rangosPara(int $edad): array {
        $rangos = [];
        foreach ($this->leer('SELECT * FROM rangos_signos_vitales WHERE :edad BETWEEN edad_min AND edad_max', [':edad' => $edad]) as $f) {
            $rangos[$f['tipo']] = new RangoSigno((float) $f['normal_min'], (float) $f['normal_max'], (float) $f['critico_bajo'], (float) $f['critico_alto']);
        }
        return $rangos;
    }

    public function guardar(Toma $t): void {
        try {
            $this->pdo->beginTransaction();
            $this->pdo->prepare('INSERT INTO tomas_signos_vitales (id, paciente_id, episodio_id, autor_id, tomada_en) VALUES (?, ?, ?, ?, ?)')
                ->execute([$t->id, $t->pacienteId, $t->episodioId, $t->autorId, $t->tomadaEn->format('c')]);
            $medicion = $this->pdo->prepare('INSERT INTO mediciones_signo (toma_id, tipo, valor, clasificacion) VALUES (?, ?, ?, ?)');
            foreach ($t->mediciones as $m) { $medicion->execute([$t->id, $m->tipo->value, $m->valor, $t->clasificaciones[$m->tipo->value]->value]); }
            $alerta = $this->pdo->prepare('INSERT INTO alertas_signos_vitales (toma_id, tipo, nivel, valor, estado_envio) VALUES (?, ?, ?, ?, ?)');
            foreach ($t->alertas() as $a) {   // las críticas quedan para enviarse a M21 (bandeja de salida)
                $alerta->execute([$t->id, $a['signo'], $a['nivel'], $a['valor'], $a['nivel'] === Clasificacion::Critica->value ? 'pendiente_envio' : 'no_aplica']);
            }
            $this->pdo->commit();
        } catch (\PDOException $e) {
            if ($this->pdo->inTransaction()) { $this->pdo->rollBack(); }
            throw new ErrorPersistencia('No se pudo guardar la toma', 0, $e);
        }
    }

    public function existe(string $tomaId): bool {
        return $this->leer('SELECT 1 FROM tomas_signos_vitales WHERE id = :id', [':id' => $tomaId]) !== [];
    }

    public function deEpisodio(string $episodioId): array {
        $tomas = [];
        foreach ($this->leer('SELECT t.id, t.tomada_en, t.autor_id, m.tipo, m.valor, m.clasificacion FROM tomas_signos_vitales t
                              JOIN mediciones_signo m ON m.toma_id = t.id WHERE t.episodio_id = :e ORDER BY t.tomada_en DESC, t.id', [':e' => $episodioId]) as $f) {
            $tomas[$f['id']] ??= ['toma_id' => $f['id'], 'tomada_en' => $f['tomada_en'], 'autor_id' => $f['autor_id'], 'mediciones' => []];
            $tomas[$f['id']]['mediciones'][$f['tipo']] = ['valor' => (float) $f['valor'], 'clasificacion' => $f['clasificacion']];
        }
        return array_values($tomas);
    }

    private function leer(string $sql, array $parametros): array {
        try { $consulta = $this->pdo->prepare($sql); $consulta->execute($parametros); return $consulta->fetchAll(\PDO::FETCH_ASSOC); }
        catch (\PDOException $e) { throw new ErrorPersistencia('No se pudo leer la base', 0, $e); }
    }
}
