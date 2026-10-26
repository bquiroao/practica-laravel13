<?php
declare(strict_types=1);

namespace MicroHis\DashboardBase\Persistencia;

use MicroHis\DashboardBase\Aplicacion\{ErrorPersistencia, FuenteConteos};
use PDO, PDOException;

/** Adaptador PDO del puerto FuenteConteos sobre la base del hospital (una por tenant). Solo sentencias preparadas y solo COUNT (R-06). */
final class RepositorioPdo implements FuenteConteos
{
    public function __construct(private PDO $db) { $db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION); }

    public function conteo(string $tarjeta, string $dia): int
    {
        [$sql, $parametros] = match ($tarjeta) {
            'pacientes' => ["SELECT COUNT(*) FROM local_patients WHERE status = 'active'", []],
            'citas_hoy' => ["SELECT COUNT(*) FROM appointments WHERE date(starts_at) = ? AND status IN ('scheduled', 'confirmed')", [$dia]],
            'camas_disponibles' => ["SELECT COUNT(*) FROM beds WHERE status = 'available'", []],
        };
        try { $q = $this->db->prepare($sql); $q->execute($parametros); return (int) $q->fetchColumn(); }
        catch (PDOException $e) { throw new ErrorPersistencia('No se pudo leer el resumen.', 0, $e); }
    }
}
