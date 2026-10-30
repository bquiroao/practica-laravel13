<?php
declare(strict_types=1);

namespace Catalogo\Domain\Modelo;

use Catalogo\Domain\Errores\ErrorValidacion;

/**
 * RN-03: qué se busca y a qué fecha. El texto se compara contenido en nombre, principio activo
 * o código, sin distinguir mayúsculas, y % y _ se toman como texto literal.
 */
final class CriterioBusqueda
{
    private function __construct(
        public readonly ?string $texto,
        public readonly ?FormaFarmaceutica $forma,
        public readonly \DateTimeImmutable $fecha,
        public readonly int $limite = Limites::RESULTADOS_MAX,
    ) {
    }

    /** @param array<string, mixed> $filtros q, forma, fecha (opcional; por defecto $hoy) */
    public static function desdeFiltros(array $filtros, \DateTimeImmutable $hoy): self
    {
        $errores = [];
        $texto = isset($filtros['q']) && is_string($filtros['q']) && trim($filtros['q']) !== '' ? trim($filtros['q']) : null;
        if ($texto !== null) {
            $largo = mb_strlen($texto, 'UTF-8');
            if ($largo < Limites::BUSQUEDA_MIN || $largo > Limites::BUSQUEDA_MAX) {
                $errores['q'] = sprintf('Escriba entre %d y %d caracteres', Limites::BUSQUEDA_MIN, Limites::BUSQUEDA_MAX);
            }
        }
        $forma = null;
        if (isset($filtros['forma']) && $filtros['forma'] !== '') {
            $forma = is_string($filtros['forma']) ? FormaFarmaceutica::tryFrom($filtros['forma']) : null;
            if ($forma === null) {
                $errores['forma'] = 'Forma farmacéutica no reconocida';
            }
        }
        if ($texto === null && $forma === null && !isset($errores['forma'])) {
            $errores['q'] = 'Indique un texto o una forma farmacéutica';
        }
        $fecha = Fecha::dia($hoy);
        if (isset($filtros['fecha']) && $filtros['fecha'] !== '') {
            try {
                $fecha = Fecha::desdeTexto('fecha', $filtros['fecha']);
            } catch (ErrorValidacion $e) {
                $errores += $e->detalles;
            }
        }
        if ($errores !== []) {
            throw new ErrorValidacion($errores);
        }
        return new self($texto === null ? null : mb_strtolower($texto, 'UTF-8'), $forma, $fecha);
    }

    /** ¿Esta versión cumple el criterio? Lo usan los adaptadores en memoria (misma semántica que el SQL). */
    public function coincide(Medicamento $m): bool
    {
        if (!$m->vigenteEn($this->fecha)) {
            return false;
        }
        if ($this->forma !== null && $m->ficha->forma !== $this->forma) {
            return false;
        }
        if ($this->texto === null) {
            return true;
        }
        foreach ([$m->ficha->nombre, $m->ficha->principioActivo, $m->codigo->valor] as $campo) {
            if (str_contains(mb_strtolower($campo, 'UTF-8'), $this->texto)) {
                return true;
            }
        }
        return false;
    }
}
