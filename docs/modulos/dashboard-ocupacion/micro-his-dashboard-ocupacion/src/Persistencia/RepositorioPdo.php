<?php
declare(strict_types=1);

namespace MicroHis\DashboardOcupacion\Persistencia;

use MicroHis\DashboardOcupacion\Aplicacion\{ErrorPersistencia, RepositorioOcupacion};
use MicroHis\DashboardOcupacion\Dominio\Corte;
use PDO, PDOException, PDOStatement;

/** Adaptador PDO de la interfaz Repository (los tres puertos). Solo sentencias preparadas; sobre las camas, solo SELECT. */
final class RepositorioPdo implements RepositorioOcupacion
{
    public function __construct(private PDO $db) { $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }

    private function ejecutar(string $sql, array $parametros = []): PDOStatement
    {
        try { $q = $this->db->prepare($sql); $q->execute($parametros); return $q; }
        catch (PDOException $e) { throw new ErrorPersistencia('No se pudo leer la ocupación.', 0, $e); }
    }

    public function camasDeSalasActivas(): array
    {
        $filas = $this->ejecutar("SELECT w.code AS sala, b.code AS cama, b.status AS estado,
                                         EXISTS (SELECT 1 FROM bed_stays s WHERE s.bed_id = b.bed_id AND s.ended_at IS NULL) AS con_estancia
                                  FROM wards w LEFT JOIN beds b ON b.ward_id = w.ward_id WHERE w.status = 'active' ORDER BY w.code, b.code")->fetchAll(PDO::FETCH_ASSOC);
        return array_map(fn (array $f) => ['con_estancia' => (bool) $f['con_estancia']] + $f, $filas);
    }

    public function guardarCorte(Corte $corte): void   // ZERO_FRACTION: 50.0 % vuelve como 50.0, no como el entero 50
    {
        $this->ejecutar('INSERT INTO occupancy_snapshots (taken_at, payload) VALUES (?, ?)', [$corte->hora, json_encode($corte->salas, JSON_PRESERVE_ZERO_FRACTION)]);
    }

    public function ultimoCorte(): ?Corte
    {
        $f = $this->ejecutar('SELECT taken_at, payload FROM occupancy_snapshots ORDER BY id DESC LIMIT 1')->fetch(PDO::FETCH_ASSOC);
        return $f ? new Corte((int) $f['taken_at'], json_decode($f['payload'], true)) : null;
    }
}
