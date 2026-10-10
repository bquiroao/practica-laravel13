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

prueba('ARQ-03', 'Presentación usa Aplicación y las excepciones de Dominio; nunca Persistencia ni puertos de Dominio', function () use ($verificarCapa): void {
    $verificarCapa('Presentacion', ['NotasSoap\\Presentacion\\', 'NotasSoap\\Aplicacion\\CasosDeUso\\', 'NotasSoap\\Aplicacion\\Comandos\\', 'NotasSoap\\Dominio\\Excepciones\\']);
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
