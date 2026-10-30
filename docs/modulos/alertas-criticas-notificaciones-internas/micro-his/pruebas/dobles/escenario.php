<?php
// Arma los casos de uso con dobles y datos ficticios (como un Composition Root de prueba).
declare(strict_types=1);

require_once __DIR__ . '/RepositorioDoble.php';

use AlertasCriticas\Application\{DespachadorNotificaciones, NotificarNivel, RegistrarAlertaCritica};
use AlertasCriticas\Domain\{AlertaRepository, PuertoNotificacion};
use AlertasCriticas\Persistence\{DirectorioDestinatariosEnMemoria, NotificadorSimulado, RelojFijo};

const DIRECTORIO_FICTICIO = [
    'HOSP-GUA-01' => [
        'MED-INT' => [0 => ['enf.turno01', 'med.tratante01'], 1 => ['jefe.servicio01'], 2 => ['coord.medico01']],
        'PED' => [0 => [], 1 => ['jefe.servicio02'], 2 => ['coord.medico01']],
    ],
    'HOSP-XEL-02' => ['MED-INT' => [0 => ['enf.turno21'], 1 => ['jefe.servicio21'], 2 => ['coord.medico21']]],
];

function datosAlerta(array $cambios = []): array
{
    return $cambios + ['tenant' => 'HOSP-GUA-01', 'codigo_paciente' => 'PAC-00451', 'servicio' => 'MED-INT',
        'origen' => 'ingreso-resultados-laboratorio', 'id_origen' => 'LAB-77801', 'prioridad' => 'CRITICA',
        'descripcion' => 'Potasio sérico 6.8 mmol/L (ref. 3.5-5.1)'];
}

/** @return array{repo: AlertaRepository, notificador: PuertoNotificacion, reloj: RelojFijo, registrar: RegistrarAlertaCritica} */
function escenario(?AlertaRepository $repo = null, ?PuertoNotificacion $notificador = null): array
{
    $repo ??= new RepositorioDoble();
    $notificador ??= new NotificadorSimulado();
    $reloj = new RelojFijo('2026-10-29 10:00:00');
    $notificar = new NotificarNivel(new DirectorioDestinatariosEnMemoria(DIRECTORIO_FICTICIO),
        new DespachadorNotificaciones($notificador));
    return [
        'repo' => $repo, 'notificador' => $notificador, 'reloj' => $reloj,
        'registrar' => new RegistrarAlertaCritica($repo, $notificar, $reloj),
    ];
}

function eventos(\AlertasCriticas\Domain\Alerta $a): array
{
    return array_map(fn ($e) => $e->evento, $a->historial());
}
