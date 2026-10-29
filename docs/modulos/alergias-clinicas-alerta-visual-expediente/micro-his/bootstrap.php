<?php
declare(strict_types=1);

/*
 * Composition Root: el único lugar que conoce las clases concretas.
 * Las capas de arriba reciben interfaces ya armadas.
 */
require_once __DIR__ . '/autoload.php';

use Alergias\Aplicacion\ConsultarAlertaExpediente;
use Alergias\Aplicacion\RegistrarAlergia;
use Alergias\Dominio\CalculadorAlerta;
use Alergias\Dominio\Reglas\ReglaPorSeveridad;
use Alergias\Dominio\Reglas\ReglaReaccionAnafilactica;
use Alergias\Persistencia\FabricaConexion;
use Alergias\Persistencia\PdoDirectorioPacientes;
use Alergias\Persistencia\PdoRepositorioAlergias;
use Alergias\Persistencia\RelojSistema;

/** @return array{registrar: RegistrarAlergia, consultar: ConsultarAlertaExpediente} */
function armarCasosDeUso(?PDO $pdo = null): array
{
    $pdo ??= FabricaConexion::desdeEntorno();
    $pacientes = new PdoDirectorioPacientes($pdo);
    $alergias = new PdoRepositorioAlergias($pdo);
    // OCP: agregar una regla = agregar una clase aquí; el calculador no cambia.
    $calculador = new CalculadorAlerta(new ReglaPorSeveridad(), new ReglaReaccionAnafilactica());

    return [
        'registrar' => new RegistrarAlergia($pacientes, $alergias, $calculador, new RelojSistema()),
        'consultar' => new ConsultarAlertaExpediente($pacientes, $alergias, $calculador),
    ];
}
