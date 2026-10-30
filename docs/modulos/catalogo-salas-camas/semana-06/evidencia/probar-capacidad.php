<?php
declare(strict_types=1);

// Cambio práctico de la semana 6: «una sala no admite más camas en uso que su capacidad». El mismo guion se corrió
// antes y después del cambio, con los dos adaptadores del Repository (PDO | InMemory). Datos ficticios.
require __DIR__ . '/../../micro-his-catalogo-camas/bootstrap.php';

use MicroHis\Catalogo\Persistencia\{RepositorioEnMemoria, RepositorioPdo};
use function MicroHis\Catalogo\crearControlador;

$db = new PDO('sqlite::memory:');
$db->exec(file_get_contents(__DIR__ . '/../../micro-his-catalogo-camas/database/schema.sql'));
$filas = [];
foreach ([new RepositorioPdo($db), new RepositorioEnMemoria()] as $repo) {
    $app = crearControlador($repo);
    $cama = fn (string $c) => $app->atender('registrarCama', ['sala' => 'A', 'codigo' => $c]);
    $pasos = ['POST /salas A con capacidad 2' => $app->atender('registrarSala', ['codigo' => 'A', 'nombre' => 'Pediatría', 'capacidad' => 2]),
        'POST /salas/A/camas 101' => $cama('101'), 'POST /salas/A/camas 102' => $cama('102'), 'POST /salas/A/camas 103 (sala llena)' => $cama('103'),
        'PATCH /salas/A/camas/102/estado inactiva' => $app->atender('cambiarEstado', ['sala' => 'A', 'cama' => '102', 'estado' => 'inactive', 'motivo' => 'retirada']),
        'POST /salas/A/camas 103 (la inactiva no cuenta)' => $cama('103'),
        'POST /salas B con capacidad 0' => $app->atender('registrarSala', ['codigo' => 'B', 'nombre' => 'Cirugía', 'capacidad' => 0])];
    foreach ($pasos as $paso => $r) $filas[$paso][] = rtrim("{$r['status']} " . ($r['error'] ?? ''));
}
foreach ($filas as $paso => [$pdo, $memoria]) echo "$paso -> PDO $pdo | InMemory $memoria\n";
