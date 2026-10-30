<?php
declare(strict_types=1);

namespace Catalogo\Presentation\Web;

/** Tabla de rutas de la entrada MVC (semana 4). */
final class RutasWeb
{
    public static function crear(ControladorMedicamentos $c): Router
    {
        $r = new Router();
        $r->agregar('GET', '/', fn () => Respuesta::redirigir('/medicamentos'));
        $r->agregar('GET', '/medicamentos', $c->buscar(...));
        $r->agregar('GET', '/medicamentos/nuevo', $c->nuevo(...));
        $r->agregar('POST', '/medicamentos', $c->registrar(...));
        $r->agregar('GET', '/medicamentos/{codigo}', $c->detalle(...));
        $r->agregar('GET', '/medicamentos/{codigo}/version', $c->formularioVersion(...));
        $r->agregar('POST', '/medicamentos/{codigo}/version', $c->registrarVersion(...));
        $r->agregar('POST', '/medicamentos/{codigo}/baja', $c->baja(...));
        $r->agregar('POST', '/medicamentos/{codigo}/eliminar', $c->eliminar(...));
        return $r;
    }
}
