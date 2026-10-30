<?php

declare(strict_types=1);

/**
 * Regla de dependencias: Presentación → Aplicación → Dominio ← Persistencia.
 * Se analiza el código (sin comentarios) de cada archivo de src/.
 */
$codigoDe = static function (string $archivo): string {
    return implode('', array_map(static fn ($t): string => is_array($t) ? (in_array($t[0], [T_COMMENT, T_DOC_COMMENT], true) ? ' ' : $t[1]) : $t,
        token_get_all((string) file_get_contents($archivo))));
};
$archivosDe = static function (string $capa): array {
    $it = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(dirname(__DIR__, 2) . "/src/$capa", \FilesystemIterator::SKIP_DOTS));

    return array_map('strval', iterator_to_array($it, false));
};
/** @return list<string> capas de Qa\… que el archivo importa o nombra */
$capasUsadas = static function (string $codigo): array {
    preg_match_all('/Qa\\\\(Dominio|Aplicacion|Persistencia|Presentacion)\\\\/', $codigo, $m);

    return array_values(array_unique($m[1]));
};

$prohibidas = [
    'Dominio' => ['Aplicacion', 'Persistencia', 'Presentacion'],
    'Aplicacion' => ['Persistencia', 'Presentacion'],
    'Persistencia' => ['Presentacion'],
    'Presentacion' => ['Persistencia'],
];
$n = 1;
foreach ($prohibidas as $capa => $noPuede) {
    prueba(sprintf('ARQ-%02d', $n++), "$capa no depende de " . implode(', ', $noPuede), function () use ($capa, $noPuede, $archivosDe, $codigoDe, $capasUsadas): void {
        $violaciones = [];
        foreach ($archivosDe($capa) as $archivo) {
            foreach (array_intersect($capasUsadas($codigoDe($archivo)), $noPuede) as $otra) {
                $violaciones[] = basename($archivo) . " → $otra";
            }
        }
        igual([], $violaciones, 'violaciones');
    });
}

prueba('ARQ-05', 'PDO y SQL solo existen en Persistencia', function () use ($archivosDe, $codigoDe): void {
    $violaciones = [];
    foreach (['Dominio', 'Aplicacion', 'Presentacion'] as $capa) {
        foreach ($archivosDe($capa) as $archivo) {
            if (preg_match('/\\\\PDO\b|\bSELECT\s|\bINSERT\s|\bUPDATE\s|\bDELETE\s+FROM/', $codigoDe($archivo))) {
                $violaciones[] = basename($archivo);
            }
        }
    }
    igual([], $violaciones, 'archivos');
});

prueba('ARQ-06', 'Sin framework: ningún archivo usa Composer ni namespaces de terceros', function () use ($archivosDe, $codigoDe): void {
    $violaciones = [];
    foreach (['Dominio', 'Aplicacion', 'Persistencia', 'Presentacion'] as $capa) {
        foreach ($archivosDe($capa) as $archivo) {
            if (preg_match('/^use\s+(?!Qa\\\\)[A-Z][A-Za-z]+\\\\/m', $codigoDe($archivo))) {
                $violaciones[] = basename($archivo);
            }
        }
    }
    afirmar(!is_file(dirname(__DIR__, 2) . '/composer.json'), 'no debe haber composer.json');
    igual([], $violaciones, 'archivos con dependencias de terceros');
});

prueba('ARQ-08', 'MVC: los controladores no tienen SQL ni reglas; solo usan formularios, casos de uso y vistas', function () use ($archivosDe, $codigoDe): void {
    $violaciones = [];
    foreach ($archivosDe('Presentacion/Http/Controladores') as $archivo) {
        $codigo = $codigoDe($archivo);
        preg_match_all('/^use\s+Qa\\\\([A-Za-z\\\\]+);/m', $codigo, $m);
        foreach ($m[1] as $usado) {
            if (!preg_match('/^(Aplicacion\\\\CasosDeUso|Presentacion\\\\Http)\\\\/', $usado)) {
                $violaciones[] = basename($archivo) . " usa $usado";
            }
        }
        // Ni SQL, ni decisiones con el dominio: un controlador no lanza reglas ni compara estados.
        if (preg_match('/\bSELECT\s|\bINSERT\s|\bthrow\s+new\s+ReglaRechazada|\bSalida::|->estado\(\)|\bResultado::|\bSeveridad::/', $codigo)) {
            $violaciones[] = basename($archivo) . ' decide algo';
        }
    }
    igual([], $violaciones, 'controladores');
});

prueba('ARQ-09', 'Repository: los adaptadores EnMemoria y Pdo implementan exactamente los mismos puertos', function (): void {
    $puertos = static function (string $clase): array {
        $p = array_values(array_filter(class_implements($clase), static fn (string $i): bool => str_starts_with($i, 'Qa\\Dominio\\Puertos') || str_starts_with($i, 'Qa\\Aplicacion\\Puertos')));
        sort($p);

        return $p;
    };
    $pares = [['RepositorioCasos', 'Casos'], ['RepositorioCampanas', 'Campanas'], ['RepositorioDefectos', 'Defectos']];
    foreach ($pares as [$puerto, $sufijo]) {
        igual($puertos("Qa\\Persistencia\\Pdo\\PdoRepositorio$sufijo"), $puertos("Qa\\Persistencia\\EnMemoria\\Repositorio{$sufijo}EnMemoria"), $puerto);
    }
});

prueba('ARQ-10', 'Cliente-servidor: el Cliente se programa contra el contrato (no importa ninguna capa) y ninguna capa importa el Cliente', function () use ($archivosDe, $codigoDe, $capasUsadas): void {
    $violaciones = [];
    foreach ($archivosDe('Cliente') as $archivo) {
        foreach ($capasUsadas($codigoDe($archivo)) as $capa) {
            $violaciones[] = basename($archivo) . " → $capa";
        }
    }
    foreach (['Dominio', 'Aplicacion', 'Persistencia', 'Presentacion'] as $capa) {
        foreach ($archivosDe($capa) as $archivo) {
            if (str_contains($codigoDe($archivo), 'Qa\\Cliente\\')) {
                $violaciones[] = basename($archivo) . ' → Cliente';
            }
        }
    }
    igual([], $violaciones, 'violaciones');
});

prueba('ARQ-07', 'LSP (semana 2): fuera de la fábrica, nadie pregunta por el subtipo de evidencia (instanceof Captura/RegistroTexto/ResultadoJUnit)', function () use ($archivosDe, $codigoDe): void {
    $violaciones = [];
    foreach (['Dominio', 'Aplicacion', 'Persistencia', 'Presentacion'] as $capa) {
        foreach ($archivosDe($capa) as $archivo) {
            if (basename($archivo) !== 'FabricaEvidencias.php' && preg_match('/instanceof\s+\\\\?(\w+\\\\)*(Captura|RegistroTexto|ResultadoJUnit)\b/', $codigoDe($archivo))) {
                $violaciones[] = basename($archivo);
            }
        }
    }
    igual([], $violaciones, 'archivos');
});
