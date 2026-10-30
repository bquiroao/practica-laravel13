<?php
// Composition Root: el ÚNICO lugar donde se eligen los adaptadores concretos y se arman los casos de uso.
declare(strict_types=1);

require_once __DIR__ . '/autoload.php';

use AlertasCriticas\Application\{AcusarAlerta, CerrarAlerta, ConsultarHistorial, DespachadorNotificaciones,
    EscalarAlertasVencidas, ListarAlertas, NotificarNivel, RegistrarAlertaCritica};
use AlertasCriticas\Domain\{AlertaRepository, PoliticaEscalamiento, PuertoNotificacion, Reloj};
use AlertasCriticas\Persistence\{ConexionPdo, DirectorioDestinatariosEnMemoria, NotificadorSimulado,
    PdoAlertaRepository, RelojSistema};

/**
 * @param array{reloj?: Reloj, repositorio?: AlertaRepository, notificador?: PuertoNotificacion} $reemplazos
 * @return array<string, object> casos de uso listos para la capa de presentación
 */
function contenedorAlertas(array $reemplazos = []): array
{
    $config = require __DIR__ . '/config.php';
    $reloj = $reemplazos['reloj'] ?? new RelojSistema($config['zona']);
    $repositorio = $reemplazos['repositorio'] ?? new PdoAlertaRepository(ConexionPdo::crear($config));
    if (!isset($reemplazos['notificador']) && !is_dir(__DIR__ . '/var')) {
        mkdir(__DIR__ . '/var', 0777, true);
    }
    // Adaptador SIMULADO: escribe en var/notificaciones-simuladas.log; no envía correo, SMS ni push.
    $notificador = $reemplazos['notificador'] ?? new NotificadorSimulado([], __DIR__ . '/var/notificaciones-simuladas.log');
    $notificar = new NotificarNivel(
        new DirectorioDestinatariosEnMemoria(require __DIR__ . '/destinatarios-ficticios.php'),
        new DespachadorNotificaciones($notificador),
    );

    return [
        'registrar' => new RegistrarAlertaCritica($repositorio, $notificar, $reloj),
        'acusar' => new AcusarAlerta($repositorio, $reloj),
        'escalar' => new EscalarAlertasVencidas($repositorio, new PoliticaEscalamiento(), $notificar, $reloj),
        'cerrar' => new CerrarAlerta($repositorio, $reloj),
        'historial' => new ConsultarHistorial($repositorio),
        'listar' => new ListarAlertas($repositorio),
    ];
}
