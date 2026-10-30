<?php
declare(strict_types=1);

// Semana 4 (parte A): reconocimiento de la alerta contra PostgreSQL (tabla propuesta).
use Alergias\Aplicacion\SolicitudRegistro;
use Alergias\Dominio\ReglaDominioViolada;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/base.php';

$medico = '7a1e0000-0000-4000-8000-0000000000a1';
$enfermera = '7a1e0000-0000-4000-8000-0000000000a2';
$alergia = fn (string $nombre, string $sev) => new SolicitudRegistro('PAC-00451', null, $nombre, 'allergy', $sev, null, null, $medico);

return [
    'T-15 RN-09: tras reconocer, la misma huella queda reconocida solo para ese usuario' => function () use ($alergia, $medico, $enfermera) {
        $casos = armarCasosDeUso(baseDePrueba());
        $vista = $casos['registrar']->ejecutar($alergia('Penicilina', 'severe'));
        $casos['reconocer']->ejecutar('PAC-00451', $medico, $vista->huella);
        Arnes::afirmar($casos['consultar']->ejecutar('PAC-00451', $medico)->reconocida, 'el médico ya reconoció');
        Arnes::afirmar($casos['consultar']->ejecutar('PAC-00451', $enfermera)->requiereReconocimiento(), 'la enfermera aún no');
    },
    'T-16 RN-09: una alergia nueva cambia la huella y el reconocimiento anterior deja de valer' => function () use ($alergia, $medico) {
        $casos = armarCasosDeUso(baseDePrueba());
        $vista = $casos['registrar']->ejecutar($alergia('Penicilina', 'severe'));
        $casos['reconocer']->ejecutar('PAC-00451', $medico, $vista->huella);
        $casos['registrar']->ejecutar($alergia('Látex', 'life_threatening'));
        Arnes::afirmar($casos['consultar']->ejecutar('PAC-00451', $medico)->requiereReconocimiento(), 'debe volver a pedir reconocimiento');
        Arnes::lanza(fn () => $casos['reconocer']->ejecutar('PAC-00451', $medico, $vista->huella), ReglaDominioViolada::class, 'ALERTA_CAMBIO');
    },
    'T-17 reconocer dos veces la misma huella no duplica filas (idempotente)' => function () use ($alergia, $medico) {
        $pdo = baseDePrueba();
        $casos = armarCasosDeUso($pdo);
        $vista = $casos['registrar']->ejecutar($alergia('Penicilina', 'severe'));
        $casos['reconocer']->ejecutar('PAC-00451', $medico, $vista->huella);
        $casos['reconocer']->ejecutar('PAC-00451', $medico, $vista->huella);
        $filas = $pdo->prepare('SELECT count(*) FROM allergy_alert_acknowledgements');
        $filas->execute();
        Arnes::igual(1, (int) $filas->fetchColumn(), 'reconocimientos');
    },
    'T-18 una alerta MODERADA no admite reconocimiento (no hay nada que confirmar)' => function () use ($medico) {
        $casos = armarCasosDeUso(baseDePrueba());
        $vista = $casos['consultar']->ejecutar('PAC-00451');
        Arnes::lanza(fn () => $casos['reconocer']->ejecutar('PAC-00451', $medico, $vista->huella), ReglaDominioViolada::class, 'RECONOCIMIENTO_NO_REQUERIDO');
    },
];
