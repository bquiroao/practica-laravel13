<?php

declare(strict_types=1);

/*
 * Revisa el código fuente con token_get_all(): las reglas de la vista arquitectónica
 * no son solo un dibujo, se comprueban en cada ejecución.
 *   Presentación → Aplicación → Dominio ← Persistencia
 */

$raiz = dirname(__DIR__, 2) . '/src';

/** @return array<string, list<string>> archivo relativo => nombres de clase NotasSoap\... usados */
$dependencias = static function (string $capa) use ($raiz): array {
    $salida = [];
    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$raiz/$capa", FilesystemIterator::SKIP_DOTS));
    foreach ($it as $archivo) {
        $tokens = token_get_all((string) file_get_contents((string) $archivo));
        $nombres = [];
        foreach ($tokens as $t) {
            if (is_array($t) && in_array($t[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                $n = ltrim($t[1], '\\');
                if (str_starts_with($n, 'NotasSoap\\')) {
                    $nombres[] = $n;
                }
            }
        }
        $salida[substr((string) $archivo, strlen($raiz) + 1)] = array_values(array_unique($nombres));
    }

    return $salida;
};

/** @param list<string> $permitidos prefijos permitidos */
$verificarCapa = static function (string $capa, array $permitidos) use ($dependencias): void {
    $violaciones = [];
    foreach ($dependencias($capa) as $archivo => $nombres) {
        foreach ($nombres as $n) {
            $ok = false;
            foreach ($permitidos as $p) {
                $ok = $ok || str_starts_with($n, $p);
            }
            if (!$ok) {
                $violaciones[] = "$archivo usa $n";
            }
        }
    }
    afirmar($violaciones === [], implode('; ', $violaciones));
};

/** @return list<array{0: string, 1: list<array|string>}> */
$archivosDe = static function (array $capas) use ($raiz): array {
    $lista = [];
    foreach ($capas as $capa) {
        $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$raiz/$capa", FilesystemIterator::SKIP_DOTS));
        foreach ($it as $archivo) {
            $lista[] = [$capa . substr((string) $archivo, strlen("$raiz/$capa")), token_get_all((string) file_get_contents((string) $archivo))];
        }
    }

    return $lista;
};

prueba('ARQ-01', 'Dominio no depende de ninguna otra capa', function () use ($verificarCapa): void {
    $verificarCapa('Dominio', ['NotasSoap\\Dominio\\']);
});

prueba('ARQ-02', 'Aplicación solo depende de Dominio', function () use ($verificarCapa): void {
    $verificarCapa('Aplicacion', ['NotasSoap\\Aplicacion\\', 'NotasSoap\\Dominio\\']);
});

prueba('ARQ-03', 'Presentación usa casos de uso, comandos, resultados y excepciones; nunca Persistencia, puertos ni entidades', function () use ($verificarCapa): void {
    $verificarCapa('Presentacion', ['NotasSoap\\Presentacion\\', 'NotasSoap\\Aplicacion\\CasosDeUso\\', 'NotasSoap\\Aplicacion\\Comandos\\', 'NotasSoap\\Aplicacion\\Resultados\\', 'NotasSoap\\Aplicacion\\Excepciones\\', 'NotasSoap\\Dominio\\Excepciones\\']);
});

prueba('ARQ-04', 'Persistencia implementa puertos y no conoce Presentación ni casos de uso', function () use ($verificarCapa): void {
    $verificarCapa('Persistencia', ['NotasSoap\\Persistencia\\', 'NotasSoap\\Dominio\\', 'NotasSoap\\Aplicacion\\Puertos\\']);
});

prueba('ARQ-05', 'PDO y SQL solo aparecen en Persistencia', function () use ($archivosDe): void {
    $violaciones = [];
    foreach ($archivosDe(['Dominio', 'Aplicacion', 'Presentacion']) as [$archivo, $tokens]) {
        foreach ($tokens as $t) {
            if (!is_array($t)) {
                continue;
            }
            if (in_array($t[0], [T_STRING, T_NAME_FULLY_QUALIFIED], true) && in_array(ltrim($t[1], '\\'), ['PDO', 'PDOStatement', 'PDOException'], true)) {
                $violaciones[] = "$archivo menciona {$t[1]}";
            }
            if ($t[0] === T_CONSTANT_ENCAPSED_STRING && preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\s/i', $t[1]) === 1) {
                $violaciones[] = "$archivo contiene SQL";
            }
        }
    }
    afirmar($violaciones === [], implode('; ', $violaciones));
});

prueba('ARQ-06', 'Persistencia usa solo sentencias preparadas: sin query()/exec() ni SQL armado con variables', function () use ($archivosDe): void {
    $violaciones = [];
    foreach ($archivosDe(['Persistencia']) as [$archivo, $tokens]) {
        $n = count($tokens);
        for ($i = 0; $i < $n; $i++) {
            $t = $tokens[$i];
            if (is_array($t) && $t[0] === T_OBJECT_OPERATOR && is_array($tokens[$i + 1] ?? null) && in_array(strtolower($tokens[$i + 1][1]), ['query', 'exec'], true)) {
                $violaciones[] = "$archivo llama ->{$tokens[$i + 1][1]}()";
            }
            // Cadena con variables interpoladas que contiene SQL.
            if ($t === '"') {
                $cadena = '';
                $conVariable = false;
                for ($j = $i + 1; $j < $n && $tokens[$j] !== '"'; $j++) {
                    $cadena .= is_array($tokens[$j]) ? $tokens[$j][1] : $tokens[$j];
                    $conVariable = $conVariable || (is_array($tokens[$j]) && $tokens[$j][0] === T_VARIABLE);
                }
                if ($conVariable && preg_match('/\b(SELECT|INSERT|UPDATE|DELETE|WHERE)\b/i', $cadena) === 1) {
                    $violaciones[] = "$archivo arma SQL con variables";
                }
                $i = $j;
            }
            // Concatenación de SQL con una variable: 'SELECT ...' . $x
            if (is_array($t) && $t[0] === T_CONSTANT_ENCAPSED_STRING && preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\s/i', $t[1]) === 1) {
                for ($j = $i + 1; $j < $n && is_array($tokens[$j]) && $tokens[$j][0] === T_WHITESPACE; $j++);
                if (($tokens[$j] ?? null) === '.') {
                    $violaciones[] = "$archivo concatena SQL";
                }
            }
        }
    }
    afirmar($violaciones === [], implode('; ', $violaciones));
});

prueba('ARQ-07', 'Dominio y Aplicación no hacen entrada/salida ni leen globales (echo, header, $_SERVER, archivos, getenv)', function () use ($archivosDe): void {
    $prohibidas = ['header', 'http_response_code', 'file_get_contents', 'file_put_contents', 'fopen', 'getenv', 'error_log', 'print_r', 'var_dump'];
    $violaciones = [];
    foreach ($archivosDe(['Dominio', 'Aplicacion']) as [$archivo, $tokens]) {
        foreach ($tokens as $i => $t) {
            if (!is_array($t)) {
                continue;
            }
            if (in_array($t[0], [T_ECHO, T_PRINT, T_EXIT], true)) {
                $violaciones[] = "$archivo usa {$t[1]}";
            }
            if ($t[0] === T_VARIABLE && in_array($t[1], ['$_SERVER', '$_GET', '$_POST', '$_ENV', '$GLOBALS'], true)) {
                $violaciones[] = "$archivo lee {$t[1]}";
            }
            if ($t[0] === T_STRING && in_array(strtolower($t[1]), $prohibidas, true) && ($tokens[$i + 1] ?? null) === '(') {
                $violaciones[] = "$archivo llama {$t[1]}()";
            }
        }
    }
    afirmar($violaciones === [], implode('; ', $violaciones));
});

prueba('ARQ-08', 'Sin framework: ningún archivo usa Illuminate, Symfony, Laminas ni vendor/autoload', function () use ($archivosDe, $raiz): void {
    $violaciones = [];
    foreach ($archivosDe(['Dominio', 'Aplicacion', 'Presentacion', 'Persistencia']) as [$archivo, $tokens]) {
        foreach ($tokens as $t) {
            if (is_array($t) && preg_match('/^\\\\?(Illuminate|Symfony|Laminas|Slim|Laravel)\\\\/', $t[1]) === 1) {
                $violaciones[] = "$archivo usa {$t[1]}";
            }
        }
    }
    afirmar(!is_file(dirname($raiz) . '/composer.json'), 'no debe existir composer.json');
    afirmar(!str_contains((string) file_get_contents(dirname($raiz) . '/bootstrap/autoload.php'), 'vendor/autoload'), 'autoload propio');
    afirmar($violaciones === [], implode('; ', $violaciones));
});

/* ---------- Semana 4: MVC y patrón Repository ---------- */

prueba('ARQ-09', 'El controlador no tiene SQL, PDO ni reglas de negocio: solo arma el comando, llama al caso de uso y entrega a la vista', function () use ($archivosDe): void {
    $prohibidos = ['preg_match', 'trim', 'strlen', 'PDO', 'PDOStatement'];
    $violaciones = [];
    foreach ($archivosDe(['Presentacion/Controladores']) as [$archivo, $tokens]) {
        foreach ($tokens as $i => $t) {
            if (!is_array($t)) {
                continue;
            }
            $nombre = ltrim($t[1], '\\');
            if (in_array($t[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true) && str_starts_with($nombre, 'NotasSoap\\Dominio\\')) {
                $violaciones[] = "$archivo usa $nombre (el Dominio se usa solo a través de los casos de uso)";
            }
            if ($t[0] === T_STRING && in_array($nombre, $prohibidos, true)) {
                $violaciones[] = "$archivo usa $nombre";
            }
            if ($t[0] === T_CONSTANT_ENCAPSED_STRING && preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b/i', $t[1]) === 1) {
                $violaciones[] = "$archivo contiene SQL";
            }
            if (in_array($t[0], [T_IF, T_SWITCH, T_MATCH, T_FOREACH, T_FOR, T_WHILE], true)) {
                $violaciones[] = "$archivo tiene lógica de control ({$t[1]}): las decisiones van en el Modelo";
            }
        }
    }
    afirmar($violaciones === [], implode('; ', $violaciones));
});

prueba('ARQ-10', 'Cada puerto de repositorio tiene dos adaptadores: uno PDO y uno en memoria', function () use ($raiz): void {
    $puertos = array_map(static fn (string $f): string => 'NotasSoap\\Dominio\\Puertos\\' . basename($f, '.php'), glob("$raiz/Dominio/Puertos/*.php") ?: []);
    $puertos[] = 'NotasSoap\\Aplicacion\\Puertos\\BandejaSalida';
    $puertos[] = 'NotasSoap\\Aplicacion\\Puertos\\Transaccion';
    $puertos[] = 'NotasSoap\\Aplicacion\\Puertos\\RegistroIdempotencia';
    $clases = static function (string $dir, string $ns) use ($raiz): array {
        return array_map(static fn (string $f): string => $ns . basename($f, '.php'), glob("$raiz/Persistencia/$dir/*.php") ?: []);
    };
    $faltan = [];
    foreach (['Pdo' => 'NotasSoap\\Persistencia\\Pdo\\', 'EnMemoria' => 'NotasSoap\\Persistencia\\EnMemoria\\'] as $dir => $ns) {
        foreach ($puertos as $puerto) {
            $hay = array_filter($clases($dir, $ns), static fn (string $c): bool => class_exists($c) && is_subclass_of($c, $puerto));
            if ($hay === []) {
                $faltan[] = "$puerto sin adaptador en Persistencia/$dir";
            }
        }
    }
    afirmar(count($puertos) === 8, 'se esperaban 8 puertos, hay ' . count($puertos));
    afirmar($faltan === [], implode('; ', $faltan));
});

prueba('ARQ-11', 'Las vistas solo pintan: no llaman casos de uso ni conocen Persistencia', function () use ($archivosDe): void {
    $violaciones = [];
    foreach ($archivosDe(['Presentacion/Vistas']) as [$archivo, $tokens]) {
        foreach ($tokens as $t) {
            if (is_array($t) && in_array($t[0], [T_NAME_QUALIFIED, T_NAME_FULLY_QUALIFIED], true)) {
                $n = ltrim($t[1], '\\');
                if (str_starts_with($n, 'NotasSoap\\Aplicacion\\CasosDeUso\\') || str_starts_with($n, 'NotasSoap\\Persistencia\\') || str_starts_with($n, 'NotasSoap\\Dominio\\')) {
                    $violaciones[] = "$archivo usa $n";
                }
            }
        }
    }
    afirmar($violaciones === [], implode('; ', $violaciones));
});

prueba('ARQ-12', 'Los casos de uso dependen de las interfaces Repository, nunca de un adaptador concreto', function (): void {
    $violaciones = [];
    foreach (['RegistrarNotaConDiagnostico', 'FirmarNota', 'ConsultarNota'] as $caso) {
        $ctor = (new ReflectionClass('NotasSoap\\Aplicacion\\CasosDeUso\\' . $caso))->getConstructor();
        foreach ($ctor?->getParameters() ?? [] as $p) {
            $tipo = (string) $p->getType();
            if (!interface_exists($tipo)) {
                $violaciones[] = "$caso recibe \$" . $p->getName() . " de tipo concreto $tipo";
            }
        }
    }
    afirmar($violaciones === [], implode('; ', $violaciones));
});

/* ---------- Semana 5: cliente-servidor ---------- */

prueba('ARQ-13', 'La librería Cliente solo conoce el contrato: no importa clases del servidor, y el servidor no importa el Cliente', function () use ($verificarCapa, $dependencias): void {
    $verificarCapa('Cliente', ['NotasSoap\\Cliente']); // su propio namespace y nada más
    $violaciones = [];
    foreach (['Dominio', 'Aplicacion', 'Presentacion', 'Persistencia'] as $capa) {
        foreach ($dependencias($capa) as $archivo => $nombres) {
            foreach ($nombres as $n) {
                if (str_starts_with($n, 'NotasSoap\\Cliente\\')) {
                    $violaciones[] = "$archivo usa $n";
                }
            }
        }
    }
    afirmar($violaciones === [], implode('; ', $violaciones));
});

prueba('ARQ-14', 'Toda ruta declara un permiso y no hay dos rutas con el mismo método y plantilla', function (): void {
    $vistas = [];
    foreach ((new NotasSoap\Pruebas\Dobles\Escenario())->kernel->rutas() as $r) {
        afirmar(preg_match('/^notas_soap\.[a-z_]+$/', $r->permiso) === 1, "permiso inválido en {$r->metodo} {$r->plantilla}");
        $vistas[] = $r->metodo . ' ' . $r->plantilla;
    }
    igual(count($vistas), count(array_unique($vistas)), 'rutas únicas');
    afirmar(count($vistas) === 5, 'se esperaban 5 operaciones (semana 6: + correcciones)');
});
