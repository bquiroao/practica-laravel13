<?php
declare(strict_types=1);

// Semana 15 — Caja negra por la API: equivalencia, límites, permisos y errores.
// Las pruebas [DEFECTO D-NN] documentan defectos reales y fallan hasta corregirlos.
use Lab\Application\RegistrarPrueba;
use Lab\Domain\DatosInvalidos;
use Lab\Persistence\Conexion;
use Lab\Persistence\InMemoryLabTestRepository;
use Lab\Persistence\PdoLabTestRepository;

$api = static fn () => crearApi(['tenant' => 'HOSP-01'], new InMemoryLabTestRepository());
$ruta = '/api/v1/laboratorio/pruebas';
$jefe = ['authorization' => 'Bearer tok-jefe'];
$post = static fn ($a, array $cambios = [], array $cab = ['authorization' => 'Bearer tok-jefe'])
    => $a->despachar('POST', '/api/v1/laboratorio/pruebas', $cab, [], json_encode(datos('hem-001', $cambios)));
$pdo = static function (): PdoLabTestRepository {
    $config = require __DIR__ . '/../config.php';
    $conexion = Conexion::crear(['base' => $config['base_pruebas']] + $config);
    crearEsquema($conexion);
    return new PdoLabTestRepository($conexion);
};

return [
    'CN-01 Equivalencia de tipo de muestra: clase válida 201, clase inválida 422' => function () use ($api, $post): void {
        afirmar($post($api(), ['tipo_muestra' => 'suero'])->estado === 201, 'suero es válido');
        $r = $post($api(), ['tipo_muestra' => 'saliva']);
        afirmar($r->estado === 422 && isset($r->cuerpo['error']['campos']['tipo_muestra']), 'saliva es inválido');
    },
    'CN-02 Límites de volumen mínimo: 0 → 422, 0.01 → 201' => function () use ($api, $post): void {
        afirmar($post($api(), ['volumen_minimo_ml' => 0])->estado === 422, '0 mL debe rechazarse');
        afirmar($post($api(), ['volumen_minimo_ml' => 0.01])->estado === 201, '0.01 mL debe aceptarse');
    },
    'CN-03 Límites de fecha desde: hoy → 201, ayer → 422 (RN-04)' => function () use ($api, $post): void {
        afirmar($post($api(), ['vigente_desde' => date('Y-m-d')])->estado === 201, 'hoy debe aceptarse');
        afirmar($post($api(), ['vigente_desde' => date('Y-m-d', strtotime('-1 day'))])->estado === 422, 'ayer debe rechazarse');
    },
    'CN-04 Permisos por rol: solo el Jefe registra; todos consultan; token inválido 401 (RN-01)' => function () use ($api, $post, $ruta): void {
        $a = $api();
        foreach (['tok-jefe' => 201, 'tok-laboratorista' => 403, 'tok-medico' => 403, 'tok-ordenes' => 403, 'tok-falso' => 401] as $tok => $esperado) {
            $estado = $post($a, ['codigo' => 'HEM-00' . ($esperado === 201 ? '1' : '2')], ['authorization' => "Bearer $tok"])->estado;
            afirmar($estado === $esperado, "POST con $tok: esperado $esperado, llegó $estado");
        }
        foreach (['tok-jefe', 'tok-laboratorista', 'tok-medico', 'tok-ordenes'] as $tok) {
            afirmar($a->despachar('GET', $ruta, ['authorization' => "Bearer $tok"], [], '')->estado === 200, "GET con $tok debe ser 200");
        }
    },
    'CN-05 Errores de entrada: cuerpo no JSON 400, ruta inexistente 404, fecha inválida 422' => function () use ($api, $jefe, $ruta): void {
        $a = $api();
        afirmar($a->despachar('POST', $ruta, $jefe, [], 'no-es-json')->estado === 400, 'cuerpo no JSON → 400');
        afirmar($a->despachar('GET', '/api/v1/laboratorio/otra', $jefe, [], '')->cuerpo['error']['codigo'] === 'RUTA_NO_ENCONTRADA', 'ruta → 404');
        afirmar($a->despachar('GET', $ruta, $jefe, ['fecha' => '2027-02-30'], '')->estado === 422, '2027-02-30 → 422');
    },
    'CN-06 [DEFECTO D-02] Volumen de 10000 mL debe dar 422 y no error de persistencia' => function () use ($pdo): void {
        esperarExcepcion(DatosInvalidos::class, fn () => (new RegistrarPrueba($pdo(), new RelojFijo()))
            ->ejecutar(datos('hem-001', ['volumen_minimo_ml' => 10000]), 'HOSP-01', 'jefe.lab'));
    },
    'CN-07 [DEFECTO D-03] Nombre de 151 caracteres debe dar 422 y no error de persistencia' => function () use ($pdo): void {
        esperarExcepcion(DatosInvalidos::class, fn () => (new RegistrarPrueba($pdo(), new RelojFijo()))
            ->ejecutar(datos('hem-001', ['nombre' => str_repeat('A', 151)]), 'HOSP-01', 'jefe.lab'));
    },
    'CN-08 [DEFECTO D-04] Buscar «%» debe dar el mismo resultado con PDO que con InMemory' => function () use ($pdo): void {
        $resultados = [];
        foreach (['PDO' => $pdo(), 'InMemory' => new InMemoryLabTestRepository()] as $nombre => $repo) {
            (new RegistrarPrueba($repo, new RelojFijo()))->ejecutar(datos('hem-001'), 'HOSP-01', 'jefe.lab');
            $resultados[$nombre] = count($repo->buscarVigentes('HOSP-01', new DateTimeImmutable('2027-01-15'), '%'));
        }
        afirmar($resultados['PDO'] === $resultados['InMemory'], "PDO encontró {$resultados['PDO']} y InMemory {$resultados['InMemory']}");
    },
];
