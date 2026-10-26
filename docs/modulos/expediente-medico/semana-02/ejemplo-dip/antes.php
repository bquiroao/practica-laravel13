<?php
declare(strict_types=1);

// ANTES: la política crea sus detalles (new PDO, new IndiceCentralHttp) y lee la hora con time():
// no se puede probar el vencimiento de 1 hora ni cambiar la base o el índice sin tocar las reglas.
final class IndiceCentralHttp { /* cliente HTTP concreto de la base CENTRAL */ }

final class ServicioExpedienteAntes
{
    private PDO $db;
    private IndiceCentralHttp $central;

    public function __construct()
    {
        $this->db = new PDO('sqlite::memory:');
        $this->db->exec('CREATE TABLE asignaciones (paciente TEXT, profesional TEXT, estado TEXT)');
        $this->central = new IndiceCentralHttp();
    }

    public function consultar(string $paciente, string $profesional): int
    {
        $q = $this->db->prepare("SELECT 1 FROM asignaciones WHERE paciente = ? AND profesional = ? AND estado = 'active'");
        $q->execute([$paciente, $profesional]);
        return $q->fetchColumn() ? 200 : 403;
    }

    public function emergencia(string $justificacion): array
    {
        return trim($justificacion) === '' ? ['status' => 422] : ['status' => 200, 'vence' => time() + 3600];
    }
}
