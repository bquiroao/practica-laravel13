<?php
declare(strict_types=1);

// Punto de entrada: solo despacha la petición al controlador que corresponde.
require __DIR__ . '/../bootstrap.php';

$ruta = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?: '/';
crearEnrutador(crearCasoDeUso())->despachar($_SERVER['REQUEST_METHOD'] ?? 'GET', $ruta, $_GET)->enviar();
