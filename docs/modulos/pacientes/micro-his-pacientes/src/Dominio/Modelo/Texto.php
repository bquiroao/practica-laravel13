<?php

declare(strict_types=1);

namespace Pacientes\Dominio\Modelo;

/** Normalización de nombres para comparar y buscar: minúsculas, sin tildes, espacios simples. */
final class Texto
{
    public static function normalizar(string $texto): string
    {
        $texto = mb_strtolower(trim($texto), 'UTF-8');
        $texto = strtr($texto, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n']);

        return preg_replace('/\s+/u', ' ', $texto) ?? $texto;
    }

    public static function contarLetras(string $texto): int
    {
        return (int) preg_match_all('/\p{L}/u', $texto);
    }
}
