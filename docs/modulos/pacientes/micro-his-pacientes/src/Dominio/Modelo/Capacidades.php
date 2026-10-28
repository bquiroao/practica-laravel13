<?php

declare(strict_types=1);

namespace Pacientes\Dominio\Modelo;

/**
 * Semana 7 — Lo que el actor puede hacer con una ficha más allá de la operación misma.
 * Reemplaza los booleanos sueltos («puede…») que cada caso de uso calculaba y reenviaba.
 * El Dominio no conoce los nombres de los permisos: los traduce ControlAcceso (Aplicación).
 * Fail-closed: por defecto no puede nada.
 */
final class Capacidades
{
    public function __construct(
        /** pacientes.leer_protegido: ve, busca por nombre y edita a un protegido */
        public readonly bool $verProtegidos = false,
        /** pacientes.proteger: pone o quita la identidad protegida */
        public readonly bool $proteger = false,
        /** pacientes.confirmar_duplicado: registra pese a una coincidencia probable */
        public readonly bool $confirmarDuplicado = false,
        /** pacientes.editar_restringido: edita fichas inactivas o de fallecidos */
        public readonly bool $editarRestringido = false,
    ) {
    }

    public static function ninguna(): self
    {
        return new self();
    }
}
