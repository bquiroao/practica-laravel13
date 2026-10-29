<?php
declare(strict_types=1);

// Semana 4 (parte C): la MISMA suite de contrato contra el adaptador PDO y el InMemory.
use Alergias\Dominio\Alergeno;
use Alergias\Dominio\Alergia;
use Alergias\Dominio\AlergiaDuplicada;
use Alergias\Dominio\NivelAlerta;
use Alergias\Dominio\PacienteRef;
use Alergias\Dominio\Reconocimiento;
use Alergias\Dominio\Severidad;
use Alergias\Dominio\TipoAlergia;
use Alergias\Persistencia\EnMemoria\AlergiasEnMemoria;
use Alergias\Persistencia\EnMemoria\PacientesEnMemoria;
use Alergias\Persistencia\EnMemoria\ReconocimientosEnMemoria;
use Alergias\Persistencia\PdoDirectorioPacientes;
use Alergias\Persistencia\PdoRepositorioAlergias;
use Alergias\Persistencia\PdoRepositorioReconocimientos;

require_once __DIR__ . '/../bootstrap.php';
require_once __DIR__ . '/base.php';

const PAC_451 = '5a000000-0000-4000-8000-000000000451';
$fabricas = [
    'pdo' => function () {
        $pdo = baseDePrueba();
        return [new PdoDirectorioPacientes($pdo), new PdoRepositorioAlergias($pdo), new PdoRepositorioReconocimientos($pdo)];
    },
    'memoria' => fn () => [PacientesEnMemoria::demo(), AlergiasEnMemoria::demo(), new ReconocimientosEnMemoria()],
];
$latex = fn (string $id, string $nombre = 'Látex', string $tipo = 'allergy') => Alergia::registrar(
    $id, new PacienteRef(PAC_451, 'PAC-00451', 'active'), new Alergeno(null, $nombre), TipoAlergia::from($tipo),
    Severidad::Grave, 'Dermatitis', new DateTimeImmutable('2020-01-10'), '7a1e0000-0000-4000-8000-0000000000a1', new DateTimeImmutable('2026-10-28'),
);

$contrato = [
    'C-1 porCodigo encuentra al paciente y devuelve null si no existe' => function (array $r) {
        Arnes::igual('active', $r[0]->porCodigo('PAC-00451')?->estado, 'estado');
        Arnes::igual(null, $r[0]->porCodigo('PAC-99999'), 'inexistente');
    },
    'C-2 guardar y leer devuelve los mismos datos' => function (array $r) use ($latex) {
        $r[1]->guardar($latex('1c000000-0000-4000-8000-000000000001'));
        $leida = array_values(array_filter($r[1]->activasDePaciente(PAC_451), fn ($a) => $a->alergeno->etiqueta() === 'Látex'))[0];
        Arnes::igual(['Grave', 'Dermatitis', '2020-01-10'], [$leida->severidad->name, $leida->reaccion, $leida->inicio?->format('Y-m-d')], 'campos');
    },
    'C-3 guardar un equivalente activo lanza AlergiaDuplicada; otro tipo sí se acepta' => function (array $r) use ($latex) {
        $r[1]->guardar($latex('1c000000-0000-4000-8000-000000000001'));
        Arnes::lanza(fn () => $r[1]->guardar($latex('1c000000-0000-4000-8000-000000000002', 'LÁTEX')), AlergiaDuplicada::class);
        $r[1]->guardar($latex('1c000000-0000-4000-8000-000000000003', 'Látex', 'intolerance'));
        Arnes::igual(3, count($r[1]->activasDePaciente(PAC_451)), 'activas (ibuprofeno + 2 látex)');
    },
    'C-4 existeActivaEquivalente usa la misma normalización que el índice único' => function (array $r) use ($latex) {
        $r[1]->guardar($latex('1c000000-0000-4000-8000-000000000001', 'Látex'));
        Arnes::afirmar($r[1]->existeActivaEquivalente(PAC_451, TipoAlergia::Alergia, (new Alergeno(null, ' LÁTEX '))->clave()), 'debe existir');
    },
    'C-5 reconocimiento: guardar dos veces es idempotente y existe() distingue usuario' => function (array $r) {
        $rec = new Reconocimiento(PAC_451, '7a1e0000-0000-4000-8000-0000000000a1', NivelAlerta::Alta, 'abc123');
        $r[2]->guardar($rec);
        $r[2]->guardar($rec);
        Arnes::afirmar($r[2]->existe(PAC_451, '7a1e0000-0000-4000-8000-0000000000a1', 'abc123'), 'médico');
        Arnes::afirmar(!$r[2]->existe(PAC_451, '7a1e0000-0000-4000-8000-0000000000a2', 'abc123'), 'enfermera');
    },
];

$pruebas = [];
$n = 25;
foreach ($contrato as $titulo => $verificacion) {
    foreach ($fabricas as $adaptador => $fabrica) {
        $pruebas[sprintf('T-%02d [%s] %s', $n++, $adaptador, $titulo)] = fn () => $verificacion($fabrica());
    }
}
return $pruebas;
