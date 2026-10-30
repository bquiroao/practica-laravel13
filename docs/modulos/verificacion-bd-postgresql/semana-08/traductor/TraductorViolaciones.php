<?php
declare(strict_types=1);

// Semana 8: traduce un error de PostgreSQL (PDOException) a la respuesta HTTP para el usuario.
// Devuelve ['http' => int, 'codigo' => string, 'mensaje' => string, 'campo' => ?string]. El mensaje nunca contiene SQL, tablas ni nombres de restricciones.
// Lee el nombre de la restricción del texto del error en inglés: la conexión debe usar lc_messages = 'C' (ver el documento, §4).
namespace Shi\Persistencia;

final class TraductorViolaciones {
    private array $catalogo;
    public function __construct(?array $catalogo = null) { $this->catalogo = $catalogo ?? require __DIR__ . '/catalogo.php'; }

    public function traducir(\Throwable $e): array {
        $estado = ($e instanceof \PDOException ? ($e->errorInfo[0] ?? null) : null) ?? (string) $e->getCode();
        $m = $e->getMessage();
        preg_match('/constraint "([^"]+)"/', $m, $c);
        preg_match('/(?:relation|table) "([^"]+)"/', $m, $t);
        $nombre = $c[1] ?? ''; $campo = fn (string $sufijo) => $nombre !== '' && isset($t[1]) && preg_match('/^' . preg_quote($t[1], '/') . '_(.+)_' . $sufijo . '$/', $nombre, $x) ? $x[1] : null;
        return match (true) {
            in_array($estado, ['23505', '23P01'], true) => isset($this->catalogo['unicas'][$nombre])
                ? $this->r(409, ...$this->catalogo['unicas'][$nombre])
                : $this->r(409, 'registro_duplicado', 'Ya existe un registro igual o que se cruza con este'),
            $estado === '23503' => str_contains($m, 'update or delete on table')
                ? $this->r(409, 'registro_en_uso', 'No se puede eliminar o cambiar: otros registros dependen de él')
                : $this->r(422, 'referencia_inexistente', 'Un dato hace referencia a un registro que no existe', $campo('fkey')),
            $estado === '23502' => $this->r(422, 'campo_obligatorio', 'Falta un dato obligatorio', preg_match('/column "([^"]+)"/', $m, $k) ? $k[1] : null),
            $estado === '23514' => $this->check($nombre, $campo('check')),
            str_starts_with($estado, '22') => $this->r(422, 'formato_invalido', 'Un dato no tiene el formato esperado'),
            $estado === 'P0001' => $this->disparador($m),
            in_array($estado, ['40001', '40P01'], true) => $this->r(409, 'conflicto_concurrencia', 'Otra operación cambió los mismos datos; vuelva a intentar'),
            str_starts_with($estado, '08') || in_array($estado, ['57P01', '57014', '53300'], true) => $this->r(503, 'base_no_disponible', 'El servicio no está disponible; intente en unos minutos'),
            default => $this->r(500, 'error_interno', 'No se pudo completar la operación; intente de nuevo'),
        };
    }
    private function disparador(string $m): array {
        foreach ($this->catalogo['disparadores'] as [$patron, $http, $codigo, $mensaje]) { if (preg_match($patron, $m)) { return $this->r($http, $codigo, $mensaje); } }
        return $this->r(422, 'regla_no_cumplida', 'La operación no cumple una regla del sistema');
    }
    private function check(string $nombre, ?string $campo): array {
        foreach ($this->catalogo['checks'] as [$patron, $codigo, $mensaje]) { if (preg_match($patron, $nombre)) { return $this->r(422, $codigo, $mensaje, $campo); } }
        return $this->r(422, 'valor_invalido', 'Hay un valor que no es válido', $campo);
    }
    private function r(int $http, string $codigo, string $mensaje, ?string $campo = null): array { return ['http' => $http, 'codigo' => $codigo, 'mensaje' => $mensaje, 'campo' => $campo]; }
}
