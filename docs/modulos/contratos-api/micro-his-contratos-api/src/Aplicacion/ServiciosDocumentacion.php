<?php
declare(strict_types=1);

namespace MicroHis\ContratosApi\Aplicacion;

use MicroHis\ContratosApi\Dominio\{Audiencia, EstadoVersion, ReglaDeDominio, VersionNueva};

// Puertos por cliente (ISP de la semana 2): los define la aplicación y los implementa la persistencia.
interface EscritorVersiones { public function versiones(string $slug): array; public function guardarEnRevision(VersionNueva $v, string $autor): int; }
/** datosRevision: ['autor', 'estado'] o null. cambiarEstado: solo si sigue siendo $de (concurrencia optimista); false = otra persona lo cambió antes. */
interface RevisionPublicaciones { public function datosRevision(int $id): ?array; public function cambiarEstado(int $id, string $de, string $a, string $revisor, ?string $motivo): bool; }
/** Versiones publicadas u obsoletas, cada una con 'version', 'tipo', 'estado' y 'audiencia' (lista de role_code). */
interface LectorDocumentos { public function consultables(string $slug): array; }

final class ErrorPersistencia extends \RuntimeException {}   // fallo técnico de la base: no es una regla de negocio

/** CU-01 con CU-05 incluido: valida los metadatos y deja la versión «en revisión». */
final class ServicioPublicacion
{
    public function __construct(private EscritorVersiones $repo) {}
    public function enviar(string $slug, string $tipo, string $version, array $audiencia, string $autor): int
    {
        return $this->repo->guardarEnRevision(VersionNueva::validar($slug, $tipo, $version, $audiencia, $this->repo->versiones($slug)), $autor);
    }
}

/** CU-02 y CU-04: nadie aprueba lo propio (R-05) y la obsoleta se marca, nunca se borra (R-08). */
final class ServicioRevision
{
    public function __construct(private RevisionPublicaciones $repo) {}
    public function aprobar(int $id, string $revisor): void { $this->cambiar($id, EstadoVersion::Publicada, $revisor, null); }
    public function marcarObsoleta(int $id, string $revisor): void { $this->cambiar($id, EstadoVersion::Obsoleta, $revisor, null); }
    public function rechazar(int $id, string $revisor, string $motivo): void
    {
        trim($motivo) !== '' ? $this->cambiar($id, EstadoVersion::Rechazada, $revisor, $motivo) : throw new ReglaDeDominio('E2', 'El motivo del rechazo es obligatorio.');
    }
    private function cambiar(int $id, EstadoVersion $nuevo, string $revisor, ?string $motivo): void
    {
        $v = $this->repo->datosRevision($id) ?? throw new ReglaDeDominio('NF', "La versión $id no existe.");
        EstadoVersion::from($v['estado'])->validarCambioA($nuevo);
        if ($nuevo === EstadoVersion::Publicada && $v['autor'] === $revisor) throw new ReglaDeDominio('E5', 'Nadie aprueba su propia publicación.');
        if (!$this->repo->cambiarEstado($id, $v['estado'], $nuevo->value, $revisor, $motivo)) throw new ReglaDeDominio('E7', 'La versión cambió mientras tanto: recarga.');
    }
}

/** CU-03: la vigente (mayor publicada) o la versión pedida, solo dentro de la audiencia del lector y con aviso si está obsoleta. */
final class ServicioConsulta
{
    public function __construct(private LectorDocumentos $repo) {}
    public function consultar(string $slug, ?string $version, array $roles): array
    {
        $visibles = array_filter($this->repo->consultables($slug), fn (array $v) => Audiencia::permite($v['audiencia'], $roles));
        $publicadas = array_filter($visibles, fn (array $v) => $v['estado'] === EstadoVersion::Publicada->value);
        usort($publicadas, fn (array $a, array $b) => version_compare($b['version'], $a['version']));
        $d = $version === null ? ($publicadas[0] ?? null) : (array_values(array_filter($visibles, fn (array $v) => $v['version'] === $version))[0] ?? null);
        if ($d === null) throw new ReglaDeDominio('E6', 'Documento no encontrado.');   // R-07: lo ajeno responde igual que lo inexistente
        return ['version' => $d['version'], 'tipo' => $d['tipo'], 'obsoleta' => $d['estado'] === EstadoVersion::Obsoleta->value, 'vigente' => $publicadas[0]['version'] ?? null];
    }
}
