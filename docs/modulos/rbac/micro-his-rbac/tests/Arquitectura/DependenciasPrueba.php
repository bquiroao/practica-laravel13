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

/** @return list<string> capas de Rbac\ referenciadas por el código */
function capasReferenciadas(string $codigo): array
{
    preg_match_all('/\\\\?Rbac\\\\(Dominio|Aplicacion|Persistencia|Presentacion|Cliente)\\\\/', $codigo, $m);

    return array_values(array_unique($m[1]));
}

const PERMITIDAS = [
    'Dominio' => ['Dominio'],
    'Aplicacion' => ['Aplicacion', 'Dominio'],
    'Persistencia' => ['Persistencia', 'Aplicacion', 'Dominio'],
    'Presentacion' => ['Presentacion', 'Aplicacion'],
    // La librería cliente solo conoce el contrato HTTP, nunca las clases del servidor.
    'Cliente' => ['Cliente'],
];

prueba('ARQ-01', 'Regla de dependencias: Presentación→Aplicación→Dominio←Persistencia; Cliente aislado del servidor', function (): void {
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
    foreach (['Dominio', 'Aplicacion', 'Presentacion', 'Cliente'] as $capa) {
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

prueba('ARQ-04','El Dominio no hace E/S: ni superglobales, ni archivos, ni salida HTTP', function (): void {
    $violaciones = [];
    foreach (fuentesDeCapa('Dominio') as $archivo => $codigo) {
        if (preg_match('/\$_(SERVER|GET|POST|REQUEST)|file_(put|get)_contents|\bheader\s*\(|\becho\b|http_response_code/', sinComentarios($codigo)) === 1) {
            $violaciones[] = $archivo;
        }
    }
    afirmar($violaciones === [], 'E/S en el dominio: ' . implode(', ', $violaciones));
});

/** @return list<class-string> clases e interfaces definidas en src/, cargadas por el autoload */
function clasesDelProyecto(): array
{
    $clases = [];
    foreach (['Dominio', 'Aplicacion', 'Persistencia', 'Presentacion'] as $capa) {
        foreach (array_keys(fuentesDeCapa($capa)) as $archivo) {
            $clase = 'Rbac\\' . str_replace('/', '\\', substr($archivo, 0, -4));
            if (class_exists($clase) || interface_exists($clase)) {
                $clases[] = $clase;
            }
        }
    }

    return $clases;
}

prueba('ARQ-05', 'Controladores MVC: solo reciben casos de uso, no crean objetos, sin SQL ni códigos de reglas de negocio', function (): void {
    $violaciones = [];
    foreach (fuentesDeCapa('Presentacion') as $archivo => $codigo) {
        if (!str_contains($archivo, '/Controladores/')) {
            continue;
        }
        $limpio = sinComentarios($codigo);
        if (preg_match('/\bnew\s+[\\\\A-Za-z]/', $limpio) === 1) {
            $violaciones[] = "$archivo crea objetos con new";
        }
        if (preg_match("/'(active|inactive|revoked|admin_hospital|medico|enfermeria|laboratorio|roles\\.asignar|PERMISO_NO_ASIGNADO|ASIGNACION_DUPLICADA|AUTOASIGNACION)'/", $limpio) === 1) {
            $violaciones[] = "$archivo contiene códigos de reglas de negocio";
        }
        $clase = 'Rbac\\' . str_replace('/', '\\', substr($archivo, 0, -4));
        foreach ((new ReflectionClass($clase))->getConstructor()?->getParameters() ?? [] as $parametro) {
            $tipo = (string) $parametro->getType();
            if (!str_starts_with($tipo, 'Rbac\\Aplicacion\\CasosDeUso\\')) {
                $violaciones[] = "$archivo recibe $tipo (solo se admiten casos de uso)";
            }
        }
    }
    afirmar($violaciones === [], implode('; ', $violaciones));
});

prueba('ARQ-06', 'Patrón Repository: cada puerto tiene un adaptador PDO y uno InMemory en Persistencia', function (): void {
    $clases = clasesDelProyecto();
    $puertos = array_filter($clases, static fn (string $c): bool => interface_exists($c)
        && (str_starts_with($c, 'Rbac\\Dominio\\Puertos\\') || str_starts_with($c, 'Rbac\\Aplicacion\\Puertos\\')));
    afirmar(count($puertos) === 5, 'se esperaban 5 puertos, hay ' . count($puertos));

    $faltantes = [];
    foreach ($puertos as $puerto) {
        $adaptadores = array_filter($clases, static fn (string $c): bool => class_exists($c) && is_subclass_of($c, $puerto)
            && str_starts_with($c, 'Rbac\\Persistencia\\'));
        foreach (['Pdo', 'EnMemoria'] as $tecnologia) {
            $hay = array_filter($adaptadores, static fn (string $c): bool => str_starts_with($c, "Rbac\\Persistencia\\$tecnologia\\"));
            if ($hay === []) {
                $faltantes[] = "$puerto sin adaptador $tecnologia";
            }
        }
    }
    afirmar($faltantes === [], implode('; ', $faltantes));
});

prueba('ARQ-07', 'MVC: solo las Vistas dan formato a la respuesta (Respuesta::json fuera de Vistas está prohibido)', function (): void {
    $violaciones = [];
    foreach (fuentesDeCapa('Presentacion') as $archivo => $codigo) {
        if (!str_contains($archivo, '/Vistas/') && !str_ends_with($archivo, '/Respuesta.php')
            && str_contains(sinComentarios($codigo), 'Respuesta::json(')) {
            $violaciones[] = $archivo;
        }
    }
    afirmar($violaciones === [], 'formato de respuesta fuera de las vistas: ' . implode(', ', $violaciones));
});

prueba('ARQ-08', 'Toda ruta exige un permiso, salvo el endpoint de decisión (que ES la autorización)', function (): void {
    $e = new \Rbac\Pruebas\Dobles\Escenario();
    $rutas = \Rbac\Presentacion\Http\Rutas::definir(
        new \Rbac\Presentacion\Http\Controladores\AsignacionRolControlador($e->asignarRol),
        new \Rbac\Presentacion\Http\Controladores\AuditoriaControlador($e->consultar),
        new \Rbac\Presentacion\Http\Controladores\OperacionClinicaControlador(),
        new \Rbac\Presentacion\Http\Controladores\AutorizacionControlador($e->autorizar),
    );
    $sinPermiso = array_values(array_map(
        static fn (\Rbac\Presentacion\Http\Ruta $r): string => $r->metodo . ' ' . $r->patron,
        array_filter($rutas, static fn (\Rbac\Presentacion\Http\Ruta $r): bool => $r->permiso === null),
    ));
    igual(['POST #^/api/v1/autorizaciones/decisiones$#'], $sinPermiso, 'rutas sin permiso');
});
