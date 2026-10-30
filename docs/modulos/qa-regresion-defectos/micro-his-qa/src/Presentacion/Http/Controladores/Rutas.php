<?php

declare(strict_types=1);

namespace Qa\Presentacion\Http\Controladores;

use Qa\Presentacion\Http\Ruta;

/** Tabla de rutas: método + patrón → acción de un controlador. Es lo único que el Kernel recibe. */
final class Rutas
{
    public const BASE = '/api/v1/qa';

    /** @return list<Ruta> */
    public static function definir(CasosControlador $casos, CampanasControlador $campanas, DefectosControlador $defectos): array
    {
        $b = preg_quote(self::BASE, '#');

        return [
            new Ruta('POST', "#^{$b}/casos$#", $casos->disenar(...)),
            new Ruta('POST', "#^{$b}/casos/(?<id>TC-\d{3,6})/aprobacion$#", $casos->aprobar(...)),
            new Ruta('POST', "#^{$b}/campanas$#", $campanas->planificar(...)),
            new Ruta('POST', "#^{$b}/campanas/(?<id>\d{1,12})/inicio$#", $campanas->iniciar(...)),
            new Ruta('POST', "#^{$b}/campanas/(?<id>\d{1,12})/resultados$#", $campanas->registrar(...)),
            new Ruta('POST', "#^{$b}/campanas/(?<id>\d{1,12})/cierre$#", $campanas->cerrar(...)),
            new Ruta('GET', "#^{$b}/campanas/(?<id>\d{1,12})/trazabilidad$#", $campanas->trazabilidad(...)),
            new Ruta('POST', "#^{$b}/defectos/(?<id>\d{1,12})/clasificacion$#", $defectos->clasificar(...)),
            new Ruta('POST', "#^{$b}/defectos/(?<id>\d{1,12})/correccion$#", $defectos->corregir(...)),
        ];
    }
}
