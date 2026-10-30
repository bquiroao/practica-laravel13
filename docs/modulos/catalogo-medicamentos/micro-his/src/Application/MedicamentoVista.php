<?php
declare(strict_types=1);

namespace Catalogo\Application;

use Catalogo\Domain\Modelo\Fecha;
use Catalogo\Domain\Modelo\Medicamento;

/** Convierte una versión en un arreglo plano para consola, vistas HTML y JSON. */
final class MedicamentoVista
{
    /** @return array<string, string|int|null> */
    public static function aArreglo(Medicamento $m, ?\DateTimeImmutable $hoy = null): array
    {
        $f = $m->ficha;
        $datos = [
            'codigo' => $m->codigo->valor,
            'version' => $m->version,
            'nombre' => $f->nombre,
            'principio_activo' => $f->principioActivo,
            'concentracion_valor' => $f->concentracion->valor,
            'concentracion_unidad' => $f->concentracion->unidad->value,
            'forma_farmaceutica' => $f->forma->value,
            'via_administracion' => $f->via->value,
            'presentacion' => $f->presentacion,
            'registro_sanitario' => $f->registroSanitario,
            'vigente_desde' => Fecha::texto($m->vigencia->desde),
            'vigente_hasta' => Fecha::texto($m->vigencia->hasta),
            'motivo_cierre' => $m->motivoCierre,
        ];
        if ($hoy !== null) {
            $datos['vigente_hoy'] = $m->vigenteEn(Fecha::dia($hoy)) ? 'sí' : 'no';
        }
        return $datos;
    }

    /**
     * @param list<Medicamento> $lista
     * @return list<array<string, string|int|null>>
     */
    public static function lista(array $lista, ?\DateTimeImmutable $hoy = null): array
    {
        return array_map(fn (Medicamento $m) => self::aArreglo($m, $hoy), $lista);
    }
}
