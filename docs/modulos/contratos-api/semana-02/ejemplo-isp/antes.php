<?php
declare(strict_types=1);

// ANTES: una interfaz «gorda» que sirve a todos los clientes. Quien solo lee
// (la consulta por rol, una caché) depende igual de los 7 métodos y debe implementarlos.
interface RepositorioDocumentosAntes
{
    public function publicada(string $slug, ?string $version): ?array;
    public function vigente(string $slug): ?string;
    public function guardarEnRevision(string $slug, string $version, array $audiencia, string $autor): int;
    public function enRevision(int $id): ?array;
    public function publicar(int $id): void;
    public function rechazar(int $id, string $motivo): void;
    public function marcarObsoleta(int $id): void;
}

/** Caché de solo lectura: está obligada a «implementar» 6 métodos que no tiene sentido ofrecer. */
final class CacheLecturaAntes implements RepositorioDocumentosAntes
{
    private array $memo = [];
    public function __construct(private RepositorioDocumentosAntes $origen) {}
    public function publicada(string $slug, ?string $version): ?array
    {
        return $this->memo["$slug@$version"] ??= $this->origen->publicada($slug, $version);
    }
    public function vigente(string $slug): ?string { throw new LogicException('no soportado'); }
    public function guardarEnRevision(string $slug, string $version, array $audiencia, string $autor): int { throw new LogicException('no soportado'); }
    public function enRevision(int $id): ?array { throw new LogicException('no soportado'); }
    public function publicar(int $id): void { throw new LogicException('no soportado'); }
    public function rechazar(int $id, string $motivo): void { throw new LogicException('no soportado'); }
    public function marcarObsoleta(int $id): void { throw new LogicException('no soportado'); }
}

/** La consulta usa 1 método, pero depende de los 7. */
final class ServicioConsultaAntes
{
    public function __construct(private RepositorioDocumentosAntes $docs) {}
}
