<?php

declare(strict_types=1);

namespace Qa\Dominio\Modelo;

use Qa\Dominio\Excepciones\ReglaRechazada;

/**
 * Caso de prueba manual, versionado (R-01, R-02). Inmutable: aprobar o editar devuelven OTRA instancia;
 * la base lo refuerza con un trigger que no deja cambiar una versión aprobada.
 */
final readonly class CasoPrueba
{
    public const PRIORIDADES = ['ALTA', 'MEDIA', 'BAJA'];
    public const BORRADOR = 'BORRADOR';
    public const APROBADO = 'APROBADO';

    /**
     * @param list<array{accion: string, esperado: string}> $pasos
     * @param list<string>                                  $requisitos códigos RF-xx
     */
    public function __construct(
        public string $tenant,
        public string $id,
        public int $version,
        public string $autor,
        public string $titulo,
        public string $precondiciones,
        public array $pasos,
        public string $prioridad,
        public array $requisitos,
        public string $estado,
        public ?string $aprobador = null,
    ) {
    }

    /**
     * R-01: título, al menos un paso con acción y resultado esperado, prioridad válida y al menos un requisito RF-xx.
     * @param list<mixed>  $pasos
     * @param list<mixed>  $requisitos
     */
    public static function disenar(string $tenant, string $id, string $autor, string $titulo, string $precondiciones, array $pasos,
        string $prioridad, array $requisitos): self
    {
        $campos = [];
        if (!preg_match('/^TC-\d{3,6}$/', $id)) {
            $campos[] = 'id';
        }
        if (trim($titulo) === '' || mb_strlen($titulo) > 200) {
            $campos[] = 'titulo';
        }
        if ($pasos === []) {
            $campos[] = 'pasos';
        }
        foreach (array_values($pasos) as $i => $p) {
            if (!is_array($p) || !is_string($p['accion'] ?? null) || trim($p['accion']) === '') {
                $campos[] = "pasos[$i].accion";
            }
            if (!is_array($p) || !is_string($p['esperado'] ?? null) || trim($p['esperado']) === '') {
                $campos[] = "pasos[$i].esperado";
            }
        }
        if (!in_array($prioridad, self::PRIORIDADES, true)) {
            $campos[] = 'prioridad';
        }
        $validos = array_filter($requisitos, static fn ($r): bool => is_string($r) && preg_match('/^RF-\d{2,3}$/', $r) === 1);
        if ($validos === [] || count($validos) !== count($requisitos)) {
            $campos[] = 'requisitos';
        }
        if ($campos !== []) {
            throw new ReglaRechazada('DATOS_INVALIDOS', $campos);
        }
        // Orden canónico: dos adaptadores (o dos lecturas) devuelven los mismos requisitos en el mismo orden (CON-01).
        $validos = array_values(array_unique($validos));
        sort($validos);
        $pasosLimpios = array_map(static fn (array $p): array => ['accion' => trim($p['accion']), 'esperado' => trim($p['esperado'])], array_values($pasos));

        return new self($tenant, $id, 1, $autor, trim($titulo), trim($precondiciones), $pasosLimpios, $prioridad, $validos, self::BORRADOR);
    }

    /** R-02: lo aprueba alguien distinto del autor, y solo un borrador. */
    public function aprobar(string $aprobador): self
    {
        if ($this->estado !== self::BORRADOR) {
            throw new ReglaRechazada('ESTADO_NO_PERMITE_APROBAR');
        }
        if ($aprobador === $this->autor) {
            throw new ReglaRechazada('APROBACION_POR_AUTOR');
        }

        return new self($this->tenant, $this->id, $this->version, $this->autor, $this->titulo, $this->precondiciones, $this->pasos,
            $this->prioridad, $this->requisitos, self::APROBADO, $aprobador);
    }
}
