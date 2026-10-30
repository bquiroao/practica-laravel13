<?php
declare(strict_types=1);

namespace AlertasCriticas\Presentation\Web;

final class Rutas
{
    public static function registrar(Router $r, AlertaController $c): void
    {
        $r->agregar('GET', '/', [$c, 'lista']);
        $r->agregar('POST', '/alertas', [$c, 'registrar']);
        $r->agregar('GET', '/alertas/{id}', [$c, 'detalle']);
        $r->agregar('POST', '/alertas/{id}/acuse', [$c, 'acusar']);
        $r->agregar('POST', '/alertas/{id}/cierre', [$c, 'cerrar']);
        $r->agregar('POST', '/escalamientos', [$c, 'escalar']);
        $r->agregar('POST', '/sesion', [$c, 'cambiarSesion']);
    }
}
