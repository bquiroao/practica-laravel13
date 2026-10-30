<?php
declare(strict_types=1);

// Semana 3: camino feliz, reglas de dominio y error de persistencia con dobles.
use Alergias\Aplicacion\ConsultarAlertaExpediente;
use Alergias\Aplicacion\PacienteNoEncontrado;
use Alergias\Aplicacion\RegistrarAlergia;
use Alergias\Aplicacion\SolicitudRegistro;
use Alergias\Dominio\AlergiaDuplicada;
use Alergias\Dominio\CalculadorAlerta;
use Alergias\Dominio\ErrorPersistencia;
use Alergias\Dominio\NivelAlerta;
use Alergias\Dominio\ReglaDominioViolada;
use Alergias\Dominio\Reglas\ReglaPorSeveridad;
use Alergias\Dominio\Reglas\ReglaReaccionAnafilactica;

require_once __DIR__ . '/dobles.php';

$calculador = new CalculadorAlerta(new ReglaPorSeveridad(), new ReglaReaccionAnafilactica());
$medico = '7a1e0000-0000-4000-8000-0000000000a1';
$registrar = fn ($repo) => new RegistrarAlergia(new PacientesFijos(), $repo, $calculador, new RelojFijo());
$solicitud = fn (string $pac = 'PAC-00451', string $nombre = 'Penicilina', string $sev = 'life_threatening', ?string $reaccion = 'Urticaria generalizada', ?string $inicio = '2019-03-02')
    => new SolicitudRegistro($pac, null, $nombre, 'allergy', $sev, $reaccion, $inicio, $medico);

return [
    'T-01 camino feliz: registra la alergia y la alerta queda CRITICA pendiente de reconocer' => function () use ($registrar, $solicitud) {
        $repo = new AlergiasEnLista();
        $alerta = $registrar($repo)->ejecutar($solicitud());
        Arnes::igual(1, count($repo->guardadas), 'alergias guardadas');
        Arnes::igual(NivelAlerta::Critica, $alerta->nivel, 'nivel');
        Arnes::afirmar($alerta->requiereReconocimiento(), 'debe requerir reconocimiento');
        Arnes::igual('7a1e0000-0000-4000-8000-0000000000a1', $repo->guardadas[0]->verificadaPor, 'verificada por');
    },
    'T-02 RN-03: rechaza una alergia activa equivalente aunque cambien mayúsculas y espacios' => function () use ($registrar, $solicitud) {
        $repo = new AlergiasEnLista();
        $caso = $registrar($repo);
        $caso->ejecutar($solicitud());
        Arnes::lanza(fn () => $caso->ejecutar($solicitud(nombre: '  PENICILINA ')), AlergiaDuplicada::class, 'ALERGIA_DUPLICADA');
        Arnes::igual(1, count($repo->guardadas), 'no se guarda la segunda');
    },
    'T-03 RN-05: rechaza fecha de inicio futura y no guarda nada' => function () use ($registrar, $solicitud) {
        $repo = new AlergiasEnLista();
        Arnes::lanza(fn () => $registrar($repo)->ejecutar($solicitud(inicio: '2026-10-29')), ReglaDominioViolada::class, 'FECHA_INICIO_FUTURA');
        Arnes::igual(0, count($repo->guardadas), 'alergias guardadas');
    },
    'T-04 RN-06: rechaza registrar en un paciente que no está activo' => function () use ($registrar, $solicitud) {
        Arnes::lanza(fn () => $registrar(new AlergiasEnLista())->ejecutar($solicitud('PAC-00453')), ReglaDominioViolada::class, 'PACIENTE_INACTIVO');
    },
    'T-05 RN-08: severidad desconocida se muestra como ALTA' => function () use ($registrar, $solicitud) {
        $alerta = $registrar(new AlergiasEnLista())->ejecutar($solicitud(sev: 'unknown', reaccion: null));
        Arnes::igual(NivelAlerta::Alta, $alerta->nivel, 'nivel');
    },
    'T-06 RN-08: sin alergias registradas el nivel es SIN_REGISTRO y no pide reconocimiento' => function () use ($calculador) {
        $alerta = (new ConsultarAlertaExpediente(new PacientesFijos(), new AlergiasEnLista(), $calculador))->ejecutar('PAC-00452');
        Arnes::igual(NivelAlerta::SinRegistro, $alerta->nivel, 'nivel');
        Arnes::afirmar(!$alerta->requiereReconocimiento(), 'no debe pedir reconocimiento');
    },
    'T-07 error de persistencia (doble): el caso de uso no oculta ErrorPersistencia' => function () use ($registrar, $solicitud) {
        Arnes::lanza(fn () => $registrar(new AlergiasQueFallan())->ejecutar($solicitud()), ErrorPersistencia::class);
    },
    'T-08 paciente inexistente: PacienteNoEncontrado' => function () use ($registrar, $solicitud) {
        Arnes::lanza(fn () => $registrar(new AlergiasEnLista())->ejecutar($solicitud('PAC-99999')), PacienteNoEncontrado::class);
    },
    'T-09 OCP: reacción con anafilaxia sube una alergia moderada a CRITICA' => function () use ($registrar, $solicitud) {
        $alerta = $registrar(new AlergiasEnLista())->ejecutar($solicitud(sev: 'moderate', reaccion: 'Anafilaxia en 2019'));
        Arnes::igual(NivelAlerta::Critica, $alerta->nivel, 'nivel');
    },
];
