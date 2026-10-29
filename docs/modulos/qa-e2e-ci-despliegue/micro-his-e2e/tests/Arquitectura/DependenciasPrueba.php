<?php

declare(strict_types=1);

/*
 * La regla de dependencias, verificada sobre el código: cada capa solo importa las de adentro.
 *   Presentación → Aplicación → Dominio ← Persistencia
 */

$src = dirname(__DIR__, 2) . '/src';
$archivosDe = static fn (string $capa): array => iterator_to_array(new RecursiveIteratorIterator(new RecursiveDirectoryIterator("$src/$capa", FilesystemIterator::SKIP_DOTS)), false);
$capasUsadas = static function (SplFileInfo|string $archivo): array {
    preg_match_all('/^use\s+E2e\\\\(\w+)\\\\/m', (string) file_get_contents((string) $archivo), $m);

    return array_values(array_unique($m[1]));
};

$reglas = ['Dominio' => ['Aplicacion', 'Persistencia', 'Presentacion'], 'Aplicacion' => ['Persistencia', 'Presentacion'], 'Persistencia' => ['Presentacion'],
    'Presentacion' => ['Persistencia', 'Dominio']];
$n = 1;
foreach ($reglas as $capa => $noPuede) {
    prueba(sprintf('ARQ-%02d', $n++), "$capa no depende de " . implode(', ', $noPuede), function () use ($capa, $noPuede, $archivosDe, $capasUsadas): void {
        $violaciones = [];
        foreach ($archivosDe($capa) as $archivo) {
            foreach (array_intersect($capasUsadas($archivo), $noPuede) as $otra) {
                $violaciones[] = basename((string) $archivo) . " → $otra";
            }
        }
        igual([], $violaciones, 'importaciones prohibidas');
    });
}

prueba('ARQ-05', 'PDO y SQL solo existen en Persistencia; sin framework ni Composer', function () use ($archivosDe): void {
    $fuera = [];
    foreach (['Dominio', 'Aplicacion', 'Presentacion'] as $capa) {
        foreach ($archivosDe($capa) as $archivo) {
            $codigo = (string) file_get_contents((string) $archivo);
            if (preg_match('/\\\\?PDO\b|\b(SELECT|INSERT|UPDATE|DELETE)\s+(\*|INTO|FROM|\w+\s+SET)/', $codigo) === 1) {
                $fuera[] = basename((string) $archivo);
            }
        }
    }
    igual([], $fuera, 'PDO o SQL fuera de Persistencia');
    afirmar(!is_file(dirname(__DIR__, 2) . '/composer.json') && !is_dir(dirname(__DIR__, 2) . '/vendor'), 'hay Composer');
});

prueba('ARQ-06', 'MVC: los controladores no tienen SQL, no importan el Dominio y no deciden reglas; solo usan formularios, casos de uso y vistas', function () use ($archivosDe): void {
    $problemas = [];
    foreach (array_filter($archivosDe('Presentacion/Http/Controladores'), static fn ($a): bool => str_ends_with((string) $a, 'Controlador.php')) as $archivo) {
        $codigo = (string) file_get_contents((string) $archivo);
        preg_match_all('/^use\s+E2e\\\\([\w\\\\]+);/m', $codigo, $m);
        afirmar($m[1] !== [], basename((string) $archivo) . ': no se leyó ningún import (la expresión no sirve)');
        foreach ($m[1] as $usado) {
            if (!preg_match('/^(Aplicacion\\\\CasosDeUso|Presentacion\\\\Http)\\\\/', $usado)) {
                $problemas[] = basename((string) $archivo) . " usa $usado";
            }
        }
        if (preg_match('/\b(SELECT|INSERT|UPDATE|DELETE)\b|PDO|ReglaRechazada|Salida::/', $codigo) === 1) {
            $problemas[] = basename((string) $archivo) . ' tiene SQL, reglas o traduce salidas';
        }
    }
    igual([], $problemas, 'controladores');
});

prueba('ARQ-07', 'Cliente-servidor: el Cliente se programa contra el contrato (no importa ninguna capa) y ninguna capa importa el Cliente', function () use ($archivosDe, $capasUsadas): void {
    $problemas = [];
    foreach ($archivosDe('Cliente') as $archivo) {
        foreach (array_diff($capasUsadas($archivo), ['Cliente']) as $capa) {
            $problemas[] = basename((string) $archivo) . " → $capa";
        }
    }
    foreach (['Dominio', 'Aplicacion', 'Persistencia', 'Presentacion'] as $capa) {
        foreach ($archivosDe($capa) as $archivo) {
            if (in_array('Cliente', $capasUsadas($archivo), true)) {
                $problemas[] = basename((string) $archivo) . ' → Cliente';
            }
        }
    }
    igual([], $problemas, 'dependencias del cliente');
});
