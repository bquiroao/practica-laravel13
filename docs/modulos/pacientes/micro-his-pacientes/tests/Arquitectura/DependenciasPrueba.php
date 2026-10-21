<?php

declare(strict_types=1);

// Pruebas de arquitectura: verifican la regla de dependencias leyendo el código fuente.

/** @return array<string, string> ruta relativa => contenido */
function fuentesDeCapa(string $capa): array
{
    $base = dirname(__DIR__, 2) . '/src/' . $capa;
    $archivos = [];
    $iterador = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($base, FilesystemIterator::SKIP_DOTS));
    foreach ($iterador as $archivo) {
        if ($archivo->getExtension() === 'php') {
            $archivos[$capa . str_replace('\\', '/', substr($archivo->getPathname(), strlen($base)))] = (string) file_get_contents($archivo->getPathname());
        }
    }

    return $archivos;
}

/** Código sin comentarios, para que un docblock no cuente como dependencia o como SQL. */
function sinComentarios(string $codigo): string
{
    $limpio = '';
    foreach (token_get_all($codigo) as $token) {
        if (is_array($token) && in_array($token[0], [T_COMMENT, T_DOC_COMMENT], true)) {
            continue;
        }
        $limpio .= is_array($token) ? $token[1] : $token;
    }

    return $limpio;
}

/**
 * Literales SQL construidos de forma insegura: concatenados con «.» o con variables interpoladas.
 *
 * @return list<string>
 */
function sqlInseguro(string $codigo): array
{
    $esSql = static fn (string $texto): bool => preg_match('/\b(SELECT|INSERT\s+INTO|UPDATE|DELETE\s+FROM)\b/i', $texto) === 1;
    $tokens = array_values(array_filter(
        token_get_all($codigo),
        static fn ($t): bool => !(is_array($t) && in_array($t[0], [T_WHITESPACE, T_COMMENT, T_DOC_COMMENT], true)),
    ));

    $hallazgos = [];
    foreach ($tokens as $i => $token) {
        if (is_array($token) && $token[0] === T_ENCAPSED_AND_WHITESPACE && $esSql($token[1])) {
            $hallazgos[] = 'interpolación en línea ' . $token[2];
        }
        if (is_array($token) && $token[0] === T_CONSTANT_ENCAPSED_STRING && $esSql($token[1])
            && (($tokens[$i + 1] ?? null) === '.' || ($tokens[$i - 1] ?? null) === '.')) {
            $hallazgos[] = 'concatenación en línea ' . $token[2];
        }
    }

    return $hallazgos;
}

/** @return list<string> capas de Pacientes\ referenciadas por el código */
function capasReferenciadas(string $codigo): array
{
    preg_match_all('/\\\\?Pacientes\\\\(Dominio|Aplicacion|Persistencia|Presentacion)\\\\/', $codigo, $m);

    return array_values(array_unique($m[1]));
}

const PERMITIDAS = [
    'Dominio' => ['Dominio'],
    'Aplicacion' => ['Aplicacion', 'Dominio'],
    'Persistencia' => ['Persistencia', 'Aplicacion', 'Dominio'],
    'Presentacion' => ['Presentacion', 'Aplicacion'],
];

prueba('ARQ-01', 'Regla de dependencias: Presentación→Aplicación→Dominio←Persistencia', function (): void {
    $violaciones = [];
    foreach (PERMITIDAS as $capa => $permitidas) {
        foreach (fuentesDeCapa($capa) as $archivo => $codigo) {
            foreach (array_diff(capasReferenciadas(sinComentarios($codigo)), $permitidas) as $prohibida) {
                $violaciones[] = "$archivo → $prohibida";
            }
        }
    }
    afirmar($violaciones === [], 'dependencias prohibidas: ' . implode(', ', $violaciones));
});

prueba('ARQ-02', 'PDO y SQL solo existen en la capa de Persistencia', function (): void {
    $violaciones = [];
    foreach (['Dominio', 'Aplicacion', 'Presentacion'] as $capa) {
        foreach (fuentesDeCapa($capa) as $archivo => $codigo) {
            if (preg_match('/\bPDO\b|\bSELECT\s|\bINSERT\s+INTO\b|\bUPDATE\s+\w+\s+SET\b|\bDELETE\s+FROM\b/i', sinComentarios($codigo)) === 1) {
                $violaciones[] = $archivo;
            }
        }
    }
    afirmar($violaciones === [], 'SQL/PDO fuera de Persistencia: ' . implode(', ', $violaciones));
});

prueba('ARQ-03', 'Persistencia usa solo sentencias preparadas: sin query()/exec() ni SQL concatenado', function (): void {
    $violaciones = [];
    foreach (fuentesDeCapa('Persistencia') as $archivo => $codigo) {
        if (preg_match('/->(query|exec)\s*\(/', sinComentarios($codigo)) === 1) {
            $violaciones[] = "$archivo usa query()/exec()";
        }
        foreach (sqlInseguro($codigo) as $hallazgo) {
            $violaciones[] = "$archivo: SQL con $hallazgo";
        }
    }
    afirmar($violaciones === [], implode(', ', $violaciones));
});

prueba('ARQ-04', 'El Dominio no hace E/S ni conoce HTTP: sin superglobales, archivos, salida ni códigos de estado', function (): void {
    $violaciones = [];
    foreach (fuentesDeCapa('Dominio') as $archivo => $codigo) {
        $limpio = sinComentarios($codigo);
        if (preg_match('/\$_(SERVER|GET|POST|REQUEST)|file_(put|get)_contents|\bheader\s*\(|\becho\b|http_response_code|\b(200|201|404|409|422|503)\b/', $limpio) === 1) {
            $violaciones[] = $archivo;
        }
    }
    afirmar($violaciones === [], 'E/S o HTTP en el dominio: ' . implode(', ', $violaciones));
});

prueba('ARQ-05', 'Las claves de cifrado y hash solo existen en Persistencia (el Dominio solo conoce el canónico)', function (): void {
    $violaciones = [];
    foreach (['Dominio', 'Aplicacion', 'Presentacion'] as $capa) {
        foreach (fuentesDeCapa($capa) as $archivo => $codigo) {
            if (preg_match('/hash_hmac|sodium_|openssl_/', sinComentarios($codigo)) === 1) {
                $violaciones[] = $archivo;
            }
        }
    }
    afirmar($violaciones === [], 'criptografía fuera de Persistencia: ' . implode(', ', $violaciones));
});
