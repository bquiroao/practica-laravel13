<?php
declare(strict_types=1);

namespace Alergias\Persistencia;

use Alergias\Dominio\ErrorPersistencia;
use Alergias\Dominio\Reconocimiento;
use Alergias\Dominio\RepositorioReconocimientos;
use PDO;
use PDOException;

final class PdoRepositorioReconocimientos implements RepositorioReconocimientos
{
    public function __construct(private readonly PDO $pdo)
    {
    }

    public function existe(string $pacienteId, string $usuarioId, string $huella): bool
    {
        try {
            $sql = $this->pdo->prepare('SELECT 1 FROM allergy_alert_acknowledgements
                WHERE local_patient_id = :p AND acknowledged_by_user_ref_id = :u AND alert_fingerprint = :h');
            $sql->execute(['p' => $pacienteId, 'u' => $usuarioId, 'h' => $huella]);
            return (bool) $sql->fetchColumn();
        } catch (PDOException $e) {
            throw new ErrorPersistencia('No se pudo leer el reconocimiento.', 0, $e);
        }
    }

    public function guardar(Reconocimiento $r): void
    {
        try {
            $sql = $this->pdo->prepare('INSERT INTO allergy_alert_acknowledgements
                (local_patient_id, acknowledged_by_user_ref_id, alert_level, alert_fingerprint) VALUES (:p, :u, :n, :h)
                ON CONFLICT (local_patient_id, acknowledged_by_user_ref_id, alert_fingerprint) DO NOTHING');
            $sql->execute(['p' => $r->pacienteId, 'u' => $r->usuarioId, 'n' => $r->nivel->value, 'h' => $r->huella]);
        } catch (PDOException $e) {
            throw new ErrorPersistencia('No se pudo guardar el reconocimiento.', 0, $e);
        }
    }
}
