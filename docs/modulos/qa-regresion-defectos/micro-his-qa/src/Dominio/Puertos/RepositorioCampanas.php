<?php

declare(strict_types=1);

namespace Qa\Dominio\Puertos;

use Qa\Dominio\Excepciones\ErrorPersistencia;
use Qa\Dominio\Modelo\Campana;
use Qa\Dominio\Modelo\Intento;

/** Campañas con sus intentos. Los intentos solo se agregan (R-11): no hay método para cambiarlos ni borrarlos. */
interface RepositorioCampanas
{
    /** @return Campana con id @throws ErrorPersistencia */
    public function crear(Campana $campana): Campana;

    /** Guarda estado, build y decisión. @throws ErrorPersistencia */
    public function actualizarEstado(Campana $campana): void;

    /** Con sus intentos en orden. @throws ErrorPersistencia */
    public function porId(string $tenant, int $id): ?Campana;

    /** @return Intento con id @throws ErrorPersistencia */
    public function agregarIntento(string $tenant, Intento $intento): Intento;

    /** Para la idempotencia (R-12). @throws ErrorPersistencia */
    public function intentoPorClave(string $tenant, string $clave): ?Intento;
}
