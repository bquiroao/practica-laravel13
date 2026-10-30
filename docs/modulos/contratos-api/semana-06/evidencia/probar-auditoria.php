<?php
declare(strict_types=1);

// Cambio práctico de la semana 6: «luis aprueba la 1.0.0 y marta la marca obsoleta; la auditoría debe conservar a los dos» (R-10).
// El mismo guion se corrió antes y después del cambio, con los dos adaptadores del Repository. Datos ficticios.
require __DIR__ . '/../../micro-his-contratos-api/bootstrap.php';

use MicroHis\ContratosApi\Persistencia\{RepositorioEnMemoria, RepositorioPdo};
use function MicroHis\ContratosApi\crearControlador;

$db = new PDO('sqlite::memory:');
$db->exec(file_get_contents(__DIR__ . '/../../micro-his-contratos-api/database/schema.sql'));
$version = fn (string $v) => ['slug' => 'guia-citas', 'tipo' => 'manual', 'version' => $v, 'audiencia' => ['medico'], 'usuario' => 'ana'];
foreach (['PDO' => new RepositorioPdo($db), 'InMemory' => new RepositorioEnMemoria()] as $adaptador => $repo) {
    $app = crearControlador($repo);
    $app->atender('enviar', $version('1.0.0')); $app->atender('aprobar', ['id' => 1, 'usuario' => 'luis']);
    $app->atender('enviar', $version('1.1.0')); $app->atender('aprobar', ['id' => 2, 'usuario' => 'luis']);
    $r = $app->atender('obsoleta', ['id' => 1, 'usuario' => 'marta']);
    echo "[$adaptador] marta marca obsoleta la 1.0.0 = {$r['status']} | datosRevision(1) = " . json_encode($repo->datosRevision(1)) . "\n";
}
$fila = $db->query('SELECT * FROM doc_versions WHERE id = 1')->fetch(PDO::FETCH_ASSOC);
echo '[PDO] fila de la 1.0.0: reviewed_by = ' . $fila['reviewed_by'] . ', obsoleted_by = ' . ($fila['obsoleted_by'] ?? '(columna inexistente)') . "\n";
