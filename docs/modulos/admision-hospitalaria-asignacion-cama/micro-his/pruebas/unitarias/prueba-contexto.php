<?php
declare(strict_types=1);

use Admision\Application\Excepciones\AccesoDenegado;
use Admision\Application\Puertos\GeneradorUuidV4;
use Admision\Domain\Modelo\Rol;
use Pruebas\Afirmar;
use Pruebas\Datos;

return [
    'ContextoUsuario permite los roles indicados y niega los demás (RN-06)' => function () {
        Datos::contexto('usr-adm-01')->exigirRol(Rol::ADMISIONISTA, Rol::SUPERVISOR_CAMAS);
        Datos::contexto('usr-sup-01')->exigirRol(Rol::ADMISIONISTA, Rol::SUPERVISOR_CAMAS);
        Afirmar::lanza(AccesoDenegado::class, fn () => Datos::contexto('usr-med-01')->exigirRol(Rol::ADMISIONISTA, Rol::SUPERVISOR_CAMAS));
    },
    'El responsable conserva usuario, rol y solicitud (RN-05)' => function () {
        $r = Datos::contexto('usr-sup-01', 'sol-0042')->responsable();
        Afirmar::igual(['usr-sup-01', Rol::SUPERVISOR_CAMAS, 'sol-0042'], [$r->usuarioId, $r->rol, $r->solicitudId]);
    },
    'GeneradorUuidV4 produce UUID versión 4 distintos' => function () {
        $g = new GeneradorUuidV4();
        $a = $g->nuevo();
        Afirmar::verdadero(preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-4[0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\z/', $a) === 1, "formato UUID v4: {$a}");
        Afirmar::verdadero($a !== $g->nuevo(), 'dos UUID distintos');
    },
];
