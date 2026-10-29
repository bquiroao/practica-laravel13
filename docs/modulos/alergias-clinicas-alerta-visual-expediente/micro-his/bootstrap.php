<?php
declare(strict_types=1);

/*
 * Composition Root: el único lugar que conoce las clases concretas.
 * Las capas de arriba reciben interfaces ya armadas.
 */
require_once __DIR__ . '/autoload.php';

use Alergias\Aplicacion\ConsultarAlertaExpediente;
use Alergias\Aplicacion\ReconocerAlerta;
use Alergias\Aplicacion\RegistrarAlergia;
use Alergias\Dominio\CalculadorAlerta;
use Alergias\Dominio\Reglas\ReglaPorSeveridad;
use Alergias\Dominio\Reglas\ReglaReaccionAnafilactica;
use Alergias\Persistencia\FabricaConexion;
use Alergias\Persistencia\PdoDirectorioPacientes;
use Alergias\Persistencia\PdoRepositorioAlergias;
use Alergias\Persistencia\PdoRepositorioReconocimientos;
use Alergias\Persistencia\RelojSistema;
use Alergias\Presentacion\Web\ControladorAlergia;
use Alergias\Presentacion\Web\ControladorAlerta;
use Alergias\Presentacion\Web\Router;

/** @return array{registrar: RegistrarAlergia, consultar: ConsultarAlertaExpediente, reconocer: ReconocerAlerta} */
function armarCasosDeUso(?PDO $pdo = null): array
{
    $pdo ??= FabricaConexion::desdeEntorno();
    $pacientes = new PdoDirectorioPacientes($pdo);
    $alergias = new PdoRepositorioAlergias($pdo);
    $reconocimientos = new PdoRepositorioReconocimientos($pdo);
    // OCP: agregar una regla = agregar una clase aquí; el calculador no cambia.
    $calculador = new CalculadorAlerta(new ReglaPorSeveridad(), new ReglaReaccionAnafilactica());

    return [
        'registrar' => new RegistrarAlergia($pacientes, $alergias, $calculador, new RelojSistema()),
        'consultar' => new ConsultarAlertaExpediente($pacientes, $alergias, $calculador, $reconocimientos),
        'reconocer' => new ReconocerAlerta($pacientes, $alergias, $reconocimientos, $calculador),
    ];
}

/** Entrada MVC (semana 4). El usuario es fijo y ficticio hasta que la semana 5 agrega tokens. */
function armarWeb(array $casos, ?string $usuarioId = null): Router
{
    $usuarioId ??= getenv('ALERGIAS_USUARIO_DEMO') ?: '7a1e0000-0000-4000-8000-0000000000a1';
    $alerta = new ControladorAlerta($casos['consultar'], $casos['reconocer'], $usuarioId);
    $alergia = new ControladorAlergia($casos['registrar'], $usuarioId);

    $router = new Router();
    $router->agregar('GET', '/pacientes/{codigo}/alerta', [$alerta, 'mostrar']);
    $router->agregar('POST', '/pacientes/{codigo}/alerta/reconocimiento', [$alerta, 'reconocer']);
    $router->agregar('GET', '/pacientes/{codigo}/alergias/nueva', [$alergia, 'formulario']);
    $router->agregar('POST', '/pacientes/{codigo}/alergias', [$alergia, 'registrar']);
    return $router;
}
