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
/** @return list<string> capas de Prescripciones\… que el archivo importa o nombra */
$capasUsadas = static function (string $codigo): array {
    preg_match_all('/Prescripciones\\\\(Dominio|Aplicacion|Persistencia|Presentacion)\\\\/', $codigo, $m);

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
            $codigo = $codigoDe($archivo);
            if (preg_match('/\\\\PDO\b|\bSELECT\s|\bINSERT\s|\bUPDATE\s|\bDELETE\s+FROM/', $codigo)) {
                $violaciones[] = basename($archivo);
            }
        }
    }
    igual([], $violaciones, 'archivos');
});

prueba('ARQ-07', 'Controlador MVC: sin SQL, sin new, sin códigos de negocio ni HTTP escritos a mano, sin armar respuestas',
    function () use ($codigoDe): void {
        $codigo = $codigoDe(dirname(__DIR__, 2) . '/src/Presentacion/Http/Controladores/PrescripcionesControlador.php');
        $prohibido = [
            'SQL' => '/\b(SELECT|INSERT|UPDATE|DELETE)\b/',
            'new' => '/\bnew\s+[A-Z\\\\]/',
            'código de negocio literal' => '/[\'"][A-Z][A-Z_]{3,}[\'"]/',
            'Respuesta::json' => '/Respuesta::json/',
            'número de estado HTTP' => '/\b[1-5][0-9]{2}\b/',
            'acceso a reglas o puertos' => '/Prescripciones\\\\(Dominio\\\\Reglas|Aplicacion\\\\Puertos|Persistencia)/',
        ];
        $hallazgos = array_keys(array_filter($prohibido, static fn (string $re): bool => (bool) preg_match($re, $codigo)));
        igual([], $hallazgos, 'el controlador contiene');
    });

prueba('ARQ-08', 'Solo las Vistas (y el Kernel para sus propios 4xx/500) arman respuestas HTTP', function () use ($archivosDe, $codigoDe): void {
    $arman = [];
    foreach ($archivosDe('Presentacion') as $archivo) {
        if (str_contains($codigoDe($archivo), 'Respuesta::json(')) {
            $arman[] = basename($archivo);
        }
    }
    sort($arman);
    igual(['ErrorVista.php', 'Kernel.php', 'PrescripcionVista.php'], $arman, 'archivos que arman respuestas');
});

prueba('ARQ-09', 'Cada puerto de datos tiene adaptador PDO e InMemory', function (): void {
    $puertos = [
        'Prescripciones\\Dominio\\Puertos\\RepositorioPrescripciones', 'Prescripciones\\Aplicacion\\Puertos\\FuenteAlergias',
        'Prescripciones\\Aplicacion\\Puertos\\CatalogoMedicamentos', 'Prescripciones\\Aplicacion\\Puertos\\Pacientes',
        'Prescripciones\\Aplicacion\\Puertos\\RegistroAuditoria', 'Prescripciones\\Aplicacion\\Puertos\\Transaccion',
    ];
    $faltan = [];
    foreach ($puertos as $puerto) {
        foreach (['Pdo', 'EnMemoria'] as $tipo) {
            $hay = false;
            foreach (glob(dirname(__DIR__, 2) . "/src/Persistencia/$tipo/*.php") as $archivo) {
                $clase = "Prescripciones\\Persistencia\\$tipo\\" . basename($archivo, '.php');
                $hay = $hay || (class_exists($clase) && in_array($puerto, class_implements($clase), true));
            }
            $hay || $faltan[] = substr(strrchr($puerto, '\\'), 1) . " sin $tipo";
        }
    }
    igual([], $faltan, 'faltan');
});

prueba('ARQ-06','Sin framework: ningún archivo usa Composer ni namespaces de terceros', function () use ($archivosDe, $codigoDe): void {
    $violaciones = [];
    foreach (['Dominio', 'Aplicacion', 'Persistencia', 'Presentacion'] as $capa) {
        foreach ($archivosDe($capa) as $archivo) {
            if (preg_match('/^use\s+(?!Prescripciones\\\\)[A-Z][A-Za-z]+\\\\/m', $codigoDe($archivo))) {
                $violaciones[] = basename($archivo);
            }
        }
    }
    afirmar(!is_file(dirname(__DIR__, 2) . '/composer.json'), 'no debe haber composer.json');
    igual([], $violaciones, 'archivos con dependencias de terceros');
});

prueba('ARQ-10', 'El Cliente se programa contra el contrato: no importa ninguna capa del servidor, y ninguna capa lo importa a él',
    function () use ($archivosDe, $codigoDe): void {
        $violaciones = [];
        foreach ($archivosDe('Cliente') as $archivo) {
            if (preg_match('/Prescripciones\\\\(Dominio|Aplicacion|Persistencia|Presentacion)\\\\/', $codigoDe($archivo), $m)) {
                $violaciones[] = basename($archivo) . " → {$m[1]}";
            }
        }
        foreach (['Dominio', 'Aplicacion', 'Persistencia', 'Presentacion'] as $capa) {
            foreach ($archivosDe($capa) as $archivo) {
                if (str_contains($codigoDe($archivo), 'Prescripciones\\Cliente\\')) {
                    $violaciones[] = basename($archivo) . ' → Cliente';
                }
            }
        }
        igual([], $violaciones, 'violaciones');
    });
