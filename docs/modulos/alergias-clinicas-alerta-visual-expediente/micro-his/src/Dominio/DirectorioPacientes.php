<?php
declare(strict_types=1);

namespace Alergias\Dominio;

/** Lectura mínima de local_patients (dueño: módulo pacientes). */
interface DirectorioPacientes
{
    /** @throws ErrorPersistencia */
    public function porCodigo(string $codigo): ?PacienteRef;
}
