<?php
declare(strict_types=1);

// Controlador MVC con el adaptador InMemory: solo traduce resultados a estados y vistas.
use Lab\Persistence\InMemoryLabTestRepository;
use Lab\Presentation\Http\Vista;

$router = static fn () => crearRouter(['tenant' => 'HOSP-01'], new InMemoryLabTestRepository());

return [
    'T-10 MVC: POST /pruebas válido responde 201 y la vista muestra el detalle' => function () use ($router): void {
        $r = $router()->despachar('POST', '/pruebas', [], datos('hem-001'));
        afirmar($r->estado === 201 && $r->vista === 'detalle', "Se esperaba 201/detalle y llegó {$r->estado}/{$r->vista}");
        afirmar(str_contains(Vista::render($r), 'Tubo tapa lila (EDTA)'), 'La vista debe mostrar los requisitos de muestra');
    },
    'T-11 MVC: datos inválidos 422 y vigencia superpuesta 409 regresan al formulario con errores' => function () use ($router): void {
        $rt = $router();
        $r = $rt->despachar('POST', '/pruebas', [], datos('hem-001', ['codigo' => 'hem1']));
        afirmar($r->estado === 422 && isset($r->datos['errores']['codigo']), 'Se esperaba 422 con error en codigo');
        $rt->despachar('POST', '/pruebas', [], datos('glu-001-v1'));
        $r = $rt->despachar('POST', '/pruebas', [], datos('glu-001-v1'));
        afirmar($r->estado === 409 && $r->vista === 'formulario', 'Se esperaba 409 en el formulario');
    },
    'T-12 MVC: el controlador no contiene SQL ni acceso a PDO' => function (): void {
        $codigo = (string) file_get_contents(__DIR__ . '/../src/Presentation/Http/LabTestController.php');
        afirmar(preg_match('/\b(SELECT|INSERT|UPDATE|PDO|prepare)\b/', $codigo) === 0, 'El controlador menciona SQL o PDO');
    },
];
