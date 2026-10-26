<?php
declare(strict_types=1);

// DESPUÉS (ISP): una interfaz pequeña por tipo de cliente; nadie depende de métodos que no usa.

/** Para quien consulta (CU-03). */
interface LectorDocumentos { public function publicada(string $slug, ?string $version): ?array; }
/** Para quien envía versiones (CU-01). */
interface EscritorVersiones
{
    public function vigente(string $slug): ?string;
    public function guardarEnRevision(string $slug, string $version, array $audiencia, string $autor): int;
}
/** Para quien revisa (CU-02, CU-04). */
interface RevisionPublicaciones
{
    public function enRevision(int $id): ?array;
    public function publicar(int $id): void;
    public function rechazar(int $id, string $motivo): void;
    public function marcarObsoleta(int $id): void;
}

/** El almacén sí sirve a los tres clientes, pero cada cliente solo ve su interfaz. */
final class RepositorioDocumentos implements LectorDocumentos, EscritorVersiones, RevisionPublicaciones
{
    private array $v = [];
    public function publicada(string $slug, ?string $version): ?array
    {
        $c = array_filter($this->v, fn ($d) => $d['slug'] === $slug && in_array($d['estado'], ['publicada', 'obsoleta'], true)
            && ($version === null ? $d['estado'] === 'publicada' : $d['version'] === $version));
        usort($c, fn ($a, $b) => version_compare($b['version'], $a['version']));
        return $c ? ['version' => $c[0]['version'], 'audiencia' => $c[0]['audiencia'], 'obsoleta' => $c[0]['estado'] === 'obsoleta'] : null;
    }
    public function vigente(string $slug): ?string { return $this->publicada($slug, null)['version'] ?? null; }
    public function guardarEnRevision(string $slug, string $version, array $audiencia, string $autor): int
    {
        $this->v[] = compact('slug', 'version', 'audiencia', 'autor') + ['estado' => 'en_revision'];
        return count($this->v);
    }
    public function enRevision(int $id): ?array { return ($this->v[$id - 1]['estado'] ?? '') === 'en_revision' ? $this->v[$id - 1] : null; }
    public function publicar(int $id): void { $this->v[$id - 1]['estado'] = 'publicada'; }
    public function rechazar(int $id, string $motivo): void { $this->v[$id - 1]['estado'] = 'rechazada'; }
    public function marcarObsoleta(int $id): void { $this->v[$id - 1]['estado'] = 'obsoleta'; }
}

/** Caché de solo lectura: implementa 1 método, el único que tiene sentido. */
final class CacheLectura implements LectorDocumentos
{
    private array $memo = [];
    public function __construct(private LectorDocumentos $origen) {}
    public function publicada(string $slug, ?string $version): ?array
    {
        return $this->memo["$slug@$version"] ??= $this->origen->publicada($slug, $version);
    }
}

/** CU-03: solo lo que la audiencia permite; lo ajeno responde 404 (R-06, R-07, R-08). */
final class ServicioConsulta
{
    public function __construct(private LectorDocumentos $docs) {}
    public function consultar(string $slug, ?string $version, array $roles): array
    {
        $d = $this->docs->publicada($slug, $version);
        if ($d === null || !array_intersect($roles, $d['audiencia'])) return ['status' => 404];
        return ['status' => 200, 'version' => $d['version'], 'aviso_obsoleta' => $d['obsoleta']];
    }
}

/** CU-01: metadatos completos y versión mayor que la vigente (R-01, R-02). */
final class ServicioPublicacion
{
    public function __construct(private EscritorVersiones $docs) {}
    public function enviar(string $slug, string $version, array $audiencia, string $autor): array
    {
        if (!preg_match('/^\d+\.\d+\.\d+$/', $version) || $audiencia === []) return ['status' => 422, 'error' => 'E2'];
        $vigente = $this->docs->vigente($slug);
        if ($vigente !== null && version_compare($version, $vigente, '<=')) return ['status' => 409, 'error' => 'E3'];
        return ['status' => 201, 'id' => $this->docs->guardarEnRevision($slug, $version, $audiencia, $autor)];
    }
}

/** CU-02 y CU-04: nadie aprueba lo propio (R-05); obsoleta, nunca borrada (R-08). */
final class ServicioRevision
{
    public function __construct(private RevisionPublicaciones $docs) {}
    public function aprobar(int $id, string $revisor): array
    {
        $v = $this->docs->enRevision($id);
        if ($v === null) return ['status' => 404];
        if ($v['autor'] === $revisor) return ['status' => 403, 'error' => 'E5'];
        $this->docs->publicar($id);
        return ['status' => 200];
    }
    public function marcarObsoleta(int $id): array { $this->docs->marcarObsoleta($id); return ['status' => 200]; }
}
