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
    preg_match_all('/\\\\?Pacientes\\\\(Dominio|Aplicacion|Persistencia|Presentacion|Cliente)\\\\/', $codigo, $m);

    return array_values(array_unique($m[1]));
}

const PERMITIDAS = [
    'Dominio' => ['Dominio'],
    'Aplicacion' => ['Aplicacion', 'Dominio'],
    'Persistencia' => ['Persistencia', 'Aplicacion', 'Dominio'],
    'Presentacion' => ['Presentacion', 'Aplicacion'],
    // Semana 5: el cliente de otros módulos solo conoce el CONTRATO HTTP, ninguna clase del servidor.
    'Cliente' => ['Cliente'],
];

prueba('ARQ-01', 'Regla de dependencias: Presentación→Aplicación→Dominio←Persistencia; Cliente aislado', function (): void {
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

/** @return list<class-string> clases declaradas en una carpeta de src/ */
function clasesEn(string $carpeta): array
{
    $clases = [];
    foreach (glob(dirname(__DIR__, 2) . '/src/' . $carpeta . '/*.php') ?: [] as $archivo) {
        $clase = 'Pacientes\\' . str_replace('/', '\\', $carpeta) . '\\' . basename($archivo, '.php');
        if (class_exists($clase)) {
            $clases[] = $clase;
        }
    }

    return $clases;
}

prueba('ARQ-06', 'Repository: cada puerto de persistencia tiene un adaptador PDO y uno InMemory', function (): void {
    $puertos = [
        \Pacientes\Dominio\Puertos\ConsultaPacientes::class,
        \Pacientes\Dominio\Puertos\RepositorioPacientes::class,
        \Pacientes\Dominio\Puertos\IndiceDuplicados::class,
        \Pacientes\Dominio\Puertos\RegistroAuditoria::class,
        \Pacientes\Aplicacion\Puertos\Transaccion::class,
        \Pacientes\Aplicacion\Puertos\AlmacenIdempotencia::class,
    ];
    $faltan = [];
    foreach ($puertos as $puerto) {
        foreach (['Persistencia/Pdo', 'Persistencia/EnMemoria'] as $carpeta) {
            $implementan = array_filter(clasesEn($carpeta), static fn (string $c): bool => is_subclass_of($c, $puerto));
            if ($implementan === []) {
                $faltan[] = (new ReflectionClass($puerto))->getShortName() . " sin adaptador en $carpeta";
            }
        }
        afirmar(str_contains($puerto, '\\Puertos\\'), "$puerto no está en una carpeta de puertos");
    }
    afirmar($faltan === [], implode(', ', $faltan));
});

prueba('ARQ-07', 'Controlador: solo recibe casos de uso; sin SQL, sin new, sin códigos de negocio y sin armar respuestas', function (): void {
    $clase = \Pacientes\Presentacion\Http\Controladores\PacientesControlador::class;
    foreach ((new ReflectionClass($clase))->getConstructor()->getParameters() as $parametro) {
        afirmar(str_starts_with((string) $parametro->getType(), 'Pacientes\\Aplicacion\\CasosDeUso\\'), 'recibe ' . $parametro->getType());
    }
    $codigo = sinComentarios((string) file_get_contents((new ReflectionClass($clase))->getFileName()));
    $prohibidos = [
        'new ' => '/\bnew\s+[A-Z\\\\]/',
        'SQL' => '/\b(SELECT|INSERT|UPDATE|DELETE)\b/',
        'código de negocio' => "/'(pacientes\\.[a-z_]+|PACIENTE_[A-Z_]+|VERSION_[A-Z_]+|DOCUMENTO_[A-Z_]+|active|inactive|deceased|DPI|PASAPORTE)'/",
        'formato de respuesta' => '/Respuesta::json|new Respuesta|json_encode|http_response_code/',
    ];
    foreach ($prohibidos as $que => $patron) {
        afirmar(preg_match($patron, $codigo) !== 1, "el controlador contiene $que");
    }
});

prueba('ARQ-08', 'Vistas: Respuesta::json y new Respuesta solo aparecen en Presentacion/Http/Vistas', function (): void {
    $violaciones = [];
    foreach (fuentesDeCapa('Presentacion') as $archivo => $codigo) {
        if (str_contains($archivo, '/Vistas/') || str_ends_with($archivo, '/Respuesta.php')) {
            continue;
        }
        if (preg_match('/Respuesta::json\s*\(|new\s+Respuesta\s*\(/', sinComentarios($codigo)) === 1) {
            $violaciones[] = $archivo;
        }
    }
    afirmar($violaciones === [], 'respuestas armadas fuera de las Vistas: ' . implode(', ', $violaciones));
});
