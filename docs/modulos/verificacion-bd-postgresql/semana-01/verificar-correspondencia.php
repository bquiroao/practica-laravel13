<?php
declare(strict_types=1);

// Semana 1 — Verifica que la tabla de correspondencia (clase → tabla) y las relaciones (→ llave foránea) del documento
// coincidan con las migraciones reales. Uso desde la raíz del repo:
//   php docs/modulos/verificacion-bd-postgresql/semana-01/verificar-correspondencia.php [carpeta de migraciones]
$raiz = $argv[1] ?? dirname(__DIR__, 4) . '/database/migrations';
$documento = (string) file_get_contents(__DIR__ . '/semana-01-clases-his-postgresql.md');

// 1. Cuerpo de cada CREATE TABLE de las bases central y hospital.
$tablas = [];
foreach (array_merge(glob("$raiz/central/*.php") ?: [], glob("$raiz/hospital/*.php") ?: []) as $archivo) {
    preg_match_all('/CREATE TABLE (\w+) \((.*?)\);/s', (string) file_get_contents($archivo), $m, PREG_SET_ORDER);
    foreach ($m as [, $nombre, $cuerpo]) { $tablas[$nombre] = $cuerpo; }
}
if ($tablas === []) { fwrite(STDERR, "No encontré migraciones en $raiz\n"); exit(2); }

$fallas = [];
$columnaExiste = fn (string $tabla, string $col) => isset($tablas[$tabla])
    && preg_match('/(^|[(,])\s*' . $col . '\s+(uuid|text|date|timestamptz|boolean|integer|numeric|jsonb)\b/', $tablas[$tabla]) === 1;

// 2. Sección 2: | Clase | Base | `tabla` | `col`, `col`, ... |
$seccion = fn (string $desde, string $hasta) => substr($documento, strpos($documento, $desde), strpos($documento, $hasta) - strpos($documento, $desde));
[$nTablas, $nTablasOk, $nCols, $nColsOk] = [0, 0, 0, 0];
foreach (explode("\n", $seccion('## 2.', '## 3.')) as $fila) {
    $celdas = array_map('trim', explode('|', $fila));
    if (count($celdas) !== 6 || !preg_match('/^`(\w+)`$/', $celdas[3], $t)) { continue; }
    $nTablas++;
    if (!isset($tablas[$t[1]])) { $fallas[] = "Tabla $t[1] ({$celdas[1]}) no existe en las migraciones"; continue; }
    $nTablasOk++;
    preg_match_all('/`(\w+)`/', $celdas[4], $cols);
    foreach ($cols[1] as $col) {
        $nCols++;
        if ($columnaExiste($t[1], $col)) { $nColsOk++; } else { $fallas[] = "Columna $t[1].$col no existe"; }
    }
}

// 3. Sección 3: `tabla.columna` → `tabla_referida` (las marcadas «lógica» no tienen FK física: solo se valida la columna).
[$nFk, $nFkOk, $nLogicas] = [0, 0, 0];
foreach (explode("\n", $seccion('## 3.', '## 4.')) as $fila) {
    if (!preg_match('/`(\w+)\.(\w+)` → `(\w+)`( \(lógica\))?/u', $fila, $r)) { continue; }
    [, $tabla, $col, $ref] = $r;
    if (!empty($r[4])) {
        $nLogicas++;
        if (!$columnaExiste($tabla, $col) || !isset($tablas[$ref])) { $fallas[] = "Referencia lógica $tabla.$col → $ref incompleta"; }
        continue;
    }
    $nFk++;
    if (isset($tablas[$tabla]) && preg_match('/\b' . $col . '\s+uuid[^,]*REFERENCES\s+' . $ref . '\(/', $tablas[$tabla])) { $nFkOk++; }
    else { $fallas[] = "Llave foránea $tabla.$col → $ref no existe"; }
}

printf("Tablas: %d/%d · Columnas: %d/%d · Llaves foráneas: %d/%d (+%d lógica)\n", $nTablasOk, $nTablas, $nColsOk, $nCols, $nFkOk, $nFk, $nLogicas);
foreach ($fallas as $f) { echo "  FALLA: $f\n"; }
echo $fallas === [] && $nTablas > 0 ? "Resultado: correspondencia verificada\n" : "Resultado: hay diferencias entre el documento y las migraciones\n";
exit($fallas === [] && $nTablas > 0 ? 0 : 1);
