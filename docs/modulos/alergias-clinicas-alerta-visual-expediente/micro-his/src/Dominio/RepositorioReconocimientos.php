<?php
declare(strict_types=1);

namespace Alergias\Dominio;

/** Tabla propuesta allergy_alert_acknowledgements (no existe en HOSPITAL). */
interface RepositorioReconocimientos
{
    public function existe(string $pacienteId, string $usuarioId, string $huella): bool;

    /** Idempotente: guardar dos veces el mismo reconocimiento no duplica. @throws ErrorPersistencia */
    public function guardar(Reconocimiento $reconocimiento): void;
}
