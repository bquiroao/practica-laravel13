<?php
declare(strict_types=1);

// ANTES: la política de agendamiento depende de detalles: crea su propia base SQLite
// y lee la hora del sistema. No se puede cambiar la base ni fijar «ahora» para probar.
final class ServicioCitasAntes
{
    private PDO $db;
    public array $bitacora = [];

    public function __construct()
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->exec('CREATE TABLE citas (id INTEGER PRIMARY KEY, medico TEXT, paciente TEXT, inicio INT, fin INT, estado TEXT)');
    }

    public function solicitar(string $medico, string $paciente, int $inicio, int $fin, string $motivo): ?string
    {
        if ($inicio <= time() || $fin <= $inicio || trim($motivo) === '') return 'E2';
        $q = $this->db->prepare("SELECT medico = ? AS es_medico FROM citas WHERE estado IN ('scheduled','confirmed') AND inicio < ? AND fin > ? AND (medico = ? OR paciente = ?) ORDER BY es_medico DESC");
        $q->execute([$medico, $fin, $inicio, $medico, $paciente]);
        $cruce = $q->fetchColumn();
        if ($cruce !== false) return $cruce ? 'E4' : 'E5';
        $this->db->prepare("INSERT INTO citas (medico, paciente, inicio, fin, estado) VALUES (?, ?, ?, ?, 'scheduled')")->execute([$medico, $paciente, $inicio, $fin]);
        return null;
    }

    public function cancelar(int $id, string $motivo): ?string
    {
        $q = $this->db->prepare('SELECT estado FROM citas WHERE id = ?');
        $q->execute([$id]);
        $estado = $q->fetchColumn();
        if ($estado === false) return 'NF';
        if (trim($motivo) === '') return 'E2';
        if (!in_array($estado, ['scheduled', 'confirmed'], true)) return 'E7';
        $this->db->prepare("UPDATE citas SET estado = 'cancelled' WHERE id = ?")->execute([$id]);
        $this->bitacora[] = "cancelada $id: $motivo";
        return null;
    }
}
