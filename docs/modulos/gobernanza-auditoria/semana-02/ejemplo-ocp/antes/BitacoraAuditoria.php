<?php

declare(strict_types=1);

namespace Auditoria\Antes;

/**
 * Diseño ANTES de aplicar OCP: una sola clase registra y consulta.
 * - Las validaciones del registro son una cadena de if/elseif dentro de registrar().
 * - Los filtros de la consulta son if sueltos dentro del bucle de consultar().
 * - El catálogo y los permisos están escritos dentro de la clase.
 * Pedir un filtro nuevo («solo los denegados») o una regla nueva obliga a EDITAR esta clase.
 */
final class BitacoraAuditoria
{
    /** @var list<array<string, mixed>> */
    private array $movimientos = [];

    /** Simula que la base de datos no responde. */
    public bool $fallar = false;

    /**
     * @param array<string, list<string>> $permisos "actor|hospital" => permisos ("actor|*" = red)
     */
    public function __construct(private readonly array $permisos, private readonly \DateTimeImmutable $ahora)
    {
    }

    /** @param array<string, mixed> $d */
    public function registrar(string $emisor, ?string $hospitalToken, array $d): array
    {
        if (!in_array('auditoria.registrar', $this->permisos[$emisor . '|' . $hospitalToken] ?? [], true)) {
            return ['estado' => 'prohibido', 'codigo' => 'ACCESO_DENEGADO', 'datos' => []];
        }
        $catalogo = [
            'pacientes' => ['PACIENTE_REGISTRADO', 'PACIENTE_ACTUALIZADO', 'PACIENTE_CONSULTADO', 'PACIENTE_BUSCADO'],
            'rbac' => ['ROL_ASIGNADO', 'ROL_REVOCADO', 'ACCESO_DENEGADO'],
            'citas' => ['CITA_CREADA', 'CITA_CANCELADA'],
            'auditoria' => ['AUDITORIA_CONSULTADA'],
        ];
        $faltan = [];
        foreach (['event_id', 'usuario', 'modulo', 'accion', 'ocurrido_en', 'resultado'] as $campo) {
            if (!isset($d[$campo]) || !is_string($d[$campo]) || trim($d[$campo]) === '') {
                $faltan[$campo] = 'Obligatorio.';
            }
        }
        $ocurrido = isset($d['ocurrido_en']) && is_string($d['ocurrido_en'])
            ? \DateTimeImmutable::createFromFormat(\DATE_ATOM, $d['ocurrido_en']) : false;

        if (($d['hospital'] ?? null) !== $hospitalToken) {
            return ['estado' => 'prohibido', 'codigo' => 'TENANT_NO_COINCIDE', 'datos' => []];
        } elseif ($faltan !== []) {
            return ['estado' => 'invalido', 'codigo' => 'DATOS_INVALIDOS', 'datos' => ['campos' => $faltan]];
        } elseif (!in_array($d['accion'], $catalogo[$d['modulo']] ?? [], true)) {
            return ['estado' => 'invalido', 'codigo' => 'DATOS_INVALIDOS', 'datos' => ['campos' => ['accion' => 'No está en el catálogo del módulo.']]];
        } elseif (!in_array($d['resultado'], ['exito', 'denegado', 'error'], true)) {
            return ['estado' => 'invalido', 'codigo' => 'DATOS_INVALIDOS', 'datos' => ['campos' => ['resultado' => 'Debe ser exito, denegado o error.']]];
        } elseif ($ocurrido === false) {
            return ['estado' => 'invalido', 'codigo' => 'DATOS_INVALIDOS', 'datos' => ['campos' => ['ocurrido_en' => 'Fecha ISO 8601 con zona.']]];
        } elseif ($ocurrido > $this->ahora->modify('+5 minutes')) {
            return ['estado' => 'invalido', 'codigo' => 'DATOS_INVALIDOS', 'datos' => ['campos' => ['ocurrido_en' => 'No puede ser futura.']]];
        } elseif (preg_match('/"(password|contrasena|token|secreto|clave|diagnostico)"\s*:/i', (string) json_encode($d['detalle'] ?? [])) === 1) {
            return ['estado' => 'invalido', 'codigo' => 'DATOS_INVALIDOS', 'datos' => ['campos' => ['detalle' => 'Contiene datos prohibidos.']]];
        }
        // Una regla nueva sería otro elseif aquí.

        $contenido = [
            'emisor' => $emisor, 'event_id' => $d['event_id'], 'hospital' => $d['hospital'] ?? null, 'usuario' => $d['usuario'],
            'modulo' => $d['modulo'], 'accion' => $d['accion'],
            'ocurrido_en' => $ocurrido->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'),
            'resultado' => $d['resultado'], 'recurso' => $d['recurso'] ?? null, 'detalle' => $d['detalle'] ?? [],
        ];
        foreach ($this->movimientos as $m) {
            if ($m['emisor'] === $emisor && $m['event_id'] === $d['event_id']) {
                return json_encode($m['contenido']) === json_encode($contenido)
                    ? ['estado' => 'ok', 'codigo' => 'YA_REGISTRADO', 'datos' => ['id' => $m['id']]]
                    : ['estado' => 'conflicto', 'codigo' => 'EVENTO_EN_CONFLICTO', 'datos' => []];
            }
        }
        if ($this->fallar) {
            return ['estado' => 'no_disponible', 'codigo' => 'SERVICIO_NO_DISPONIBLE', 'datos' => []];
        }

        return ['estado' => 'creado', 'codigo' => null, 'datos' => $this->guardar($emisor, $contenido)];
    }

    /** @param array<string, string> $p */
    public function consultar(string $actor, ?string $hospitalToken, string $alcance, array $p): array
    {
        $permiso = $alcance === 'red' ? 'auditoria.consultar_red' : 'auditoria.consultar';
        $llave = $actor . '|' . ($alcance === 'red' ? '*' : $hospitalToken);
        if (!in_array($permiso, $this->permisos[$llave] ?? [], true)) {
            return ['estado' => 'prohibido', 'codigo' => 'ACCESO_DENEGADO', 'datos' => []];
        }
        foreach (array_keys($p) as $nombre) {
            if (!in_array($nombre, ['desde', 'hasta', 'usuario', 'modulo', 'accion'], true)) {
                return ['estado' => 'invalido', 'codigo' => 'FILTRO_NO_SOPORTADO', 'datos' => ['campos' => [$nombre => 'Filtro no soportado.']]];
            }
        }
        $zona = new \DateTimeZone('America/Guatemala');
        $desde = \DateTimeImmutable::createFromFormat('!Y-m-d', $p['desde'] ?? '', $zona);
        $hasta = \DateTimeImmutable::createFromFormat('!Y-m-d', $p['hasta'] ?? '', $zona);
        if ($desde === false || $hasta === false || $desde->format('Y-m-d') !== $p['desde'] || $hasta->format('Y-m-d') !== $p['hasta']) {
            return ['estado' => 'invalido', 'codigo' => 'DATOS_INVALIDOS', 'datos' => ['campos' => ['desde' => 'Indique desde y hasta (AAAA-MM-DD).']]];
        }
        if ($desde > $hasta) {
            return ['estado' => 'invalido', 'codigo' => 'DATOS_INVALIDOS', 'datos' => ['campos' => ['desde' => 'Desde no puede ser posterior a hasta.']]];
        }
        $hastaExclusivo = $hasta->modify('+1 day');

        $encontrados = [];
        foreach ($this->movimientos as $m) {
            $c = $m['contenido'];
            $fecha = new \DateTimeImmutable($c['ocurrido_en']);
            if ($alcance !== 'red' && $c['hospital'] !== $hospitalToken) {
                continue;
            }
            if ($fecha < $desde || $fecha >= $hastaExclusivo) {
                continue;
            }
            if (isset($p['usuario']) && $c['usuario'] !== $p['usuario']) {
                continue;
            }
            if (isset($p['modulo']) && $c['modulo'] !== $p['modulo']) {
                continue;
            }
            if (isset($p['accion']) && $c['accion'] !== $p['accion']) {
                continue;
            }
            // Un filtro nuevo sería otro if aquí (y otro nombre en la lista de arriba).
            $encontrados[] = $m;
        }
        usort($encontrados, static fn (array $a, array $b): int => [$b['contenido']['ocurrido_en'], $b['id']] <=> [$a['contenido']['ocurrido_en'], $a['id']]);
        $pagina = array_slice($encontrados, 0, 50);

        if ($this->fallar) {
            return ['estado' => 'no_disponible', 'codigo' => 'SERVICIO_NO_DISPONIBLE', 'datos' => []];
        }
        $this->guardar('auditoria', [
            'emisor' => 'auditoria', 'event_id' => 'consulta-' . (count($this->movimientos) + 1), 'hospital' => $alcance === 'red' ? null : $hospitalToken,
            'usuario' => $actor, 'modulo' => 'auditoria', 'accion' => 'AUDITORIA_CONSULTADA',
            'ocurrido_en' => $this->ahora->setTimezone(new \DateTimeZone('UTC'))->format('Y-m-d\TH:i:s\Z'), 'resultado' => 'exito',
            'recurso' => null, 'detalle' => ['alcance' => $alcance, 'filtros' => $p, 'resultados' => count($pagina)],
        ]);

        return ['estado' => 'ok', 'codigo' => null, 'datos' => [
            'ids' => array_map(static fn (array $m): int => $m['id'], $pagina),
            'hay_mas' => count($encontrados) > 50,
        ]];
    }

    /** @return list<array<string, mixed>> */
    public function todos(): array
    {
        return $this->movimientos;
    }

    private function guardar(string $emisor, array $contenido): array
    {
        $anterior = null;
        foreach ($this->movimientos as $m) {
            if ($m['contenido']['hospital'] === $contenido['hospital']) {
                $anterior = $m;
            }
        }
        $secuencia = ($anterior['secuencia'] ?? 0) + 1;
        $sello = hash('sha256', ($anterior['sello'] ?? str_repeat('0', 64)) . $secuencia . json_encode($contenido));
        $this->movimientos[] = ['id' => count($this->movimientos) + 1, 'emisor' => $emisor, 'event_id' => $contenido['event_id'],
            'contenido' => $contenido, 'secuencia' => $secuencia, 'sello' => $sello];

        return ['id' => count($this->movimientos), 'secuencia' => $secuencia, 'sello' => $sello];
    }
}
