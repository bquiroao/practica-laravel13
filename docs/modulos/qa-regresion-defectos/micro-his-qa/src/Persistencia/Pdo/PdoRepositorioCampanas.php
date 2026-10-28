<?php

declare(strict_types=1);

namespace Qa\Persistencia\Pdo;

use Qa\Dominio\Evidencia\ReferenciaExterna;
use Qa\Dominio\Excepciones\ClaveEnUso;
use Qa\Dominio\Excepciones\ReglaRechazada;
use Qa\Dominio\Modelo\Campana;
use Qa\Dominio\Modelo\Decision;
use Qa\Dominio\Modelo\EstadoCampana;
use Qa\Dominio\Modelo\EvidenciaRegistrada;
use Qa\Dominio\Modelo\Intento;
use Qa\Dominio\Modelo\Resultado;
use Qa\Dominio\Puertos\RepositorioCampanas;

final class PdoRepositorioCampanas extends ConsultasPdo implements RepositorioCampanas
{
    /** Estado del que viene cada estado: la escritura solo avanza si nadie la cambió antes (doble cierre = 409). */
    private const ANTERIOR = ['EN_CURSO' => 'PLANIFICADA', 'CERRADA' => 'EN_CURSO'];

    public function crear(Campana $campana): Campana
    {
        // Las funcionalidades ya cumplen ^[a-z][a-z0-9_-]+$ (dominio), y aun así viajan como parámetro.
        $id = (int) $this->consulta('INSERT INTO campanas (tenant, nombre, funcionalidades, umbral, estado, creada_por)
            VALUES (?, ?, ?::text[], ?, ?, ?) RETURNING id', [$campana->tenant, $campana->nombre, '{' . implode(',', $campana->funcionalidades) . '}',
            $campana->umbral, $campana->estado()->value, $campana->creadaPor])->fetchColumn();
        foreach ($campana->alcance as $caso => $version) {
            $this->consulta('INSERT INTO campana_alcance (tenant, campana_id, caso_id, version) VALUES (?, ?, ?, ?)', [$campana->tenant, $id, $caso, $version]);
        }

        return $campana->conId($id);
    }

    public function actualizarEstado(Campana $campana): void
    {
        $estado = $campana->estado()->value;
        $decision = $campana->decision();
        $filas = $this->consulta('UPDATE campanas SET estado = ?, build = ?, decision = ?, tasa_aprobados = ?, causas = ?::jsonb
            WHERE tenant = ? AND id = ? AND estado = ?', [$estado, $campana->build(), $decision?->valor, $decision?->tasa,
            json_encode($decision?->causas ?? [], JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR), $campana->tenant, $campana->id, self::ANTERIOR[$estado] ?? $estado])->rowCount();
        if ($filas !== 1) {
            throw new ReglaRechazada('ESTADO_CAMBIO_CONCURRENTE');
        }
    }

    public function porId(string $tenant, int $id): ?Campana
    {
        $f = $this->consulta('SELECT id, tenant, nombre, array_to_json(funcionalidades)::text AS funcionalidades, umbral, estado, build, decision,
            tasa_aprobados, causas::text AS causas, creada_por FROM campanas WHERE tenant = ? AND id = ?', [$tenant, $id])->fetch();
        if ($f === false) {
            return null;
        }
        $alcance = [];
        foreach ($this->consulta('SELECT caso_id, version FROM campana_alcance WHERE campana_id = ? ORDER BY caso_id', [$id]) as $a) {
            $alcance[$a['caso_id']] = (int) $a['version'];
        }
        $filas = $this->consulta('SELECT * FROM intentos WHERE tenant = ? AND campana_id = ? ORDER BY id', [$tenant, $id])->fetchAll();
        $decision = $f['decision'] === null ? null : new Decision($f['decision'], (float) $f['tasa_aprobados'], self::json($f['causas']));

        return new Campana((int) $f['id'], $f['tenant'], $f['nombre'], self::json($f['funcionalidades']), $alcance, (float) $f['umbral'],
            $f['creada_por'], EstadoCampana::from($f['estado']), $f['build'], $decision, $this->intentos($filas));
    }

    public function agregarIntento(string $tenant, Intento $intento): Intento
    {
        try {
            $sentencia = $this->pdo->prepare('INSERT INTO intentos (tenant, campana_id, caso_id, numero, resultado, tester, defecto_id,
                verifica_defecto, motivo, idempotency_key, huella_solicitud, registrado_en) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?) RETURNING id');
            $sentencia->execute([$tenant, $intento->campanaId, $intento->casoId, $intento->numero, $intento->resultado->value, $intento->tester,
                $intento->defectoId, $intento->verificaDefecto, $intento->motivo, $intento->clave, $intento->huellaSolicitud,
                $intento->registradoEn->format('Y-m-d H:i:s.uP')]);
            $id = (int) $sentencia->fetchColumn();
        } catch (\PDOException $e) {
            throw match (true) {
                str_contains($e->getMessage(), 'intentos_clave_unica') => new ClaveEnUso('clave de idempotencia en uso', 0, $e),
                str_contains($e->getMessage(), 'intentos_numero_unico') => new ReglaRechazada('INTENTO_CONCURRENTE'),
                default => $this->traducir($e),
            };
        }
        foreach ($intento->evidencias as $e) {
            $this->consulta('INSERT INTO evidencias (intento_id, tipo, huella, tamano, clasificacion, ubicacion) VALUES (?, ?, ?, ?, ?, ?)',
                [$id, $e->tipo, $e->huella, $e->tamano, $e->clasificacion, $e->ubicacion]);
        }
        foreach ($intento->referencias as $r) {
            $this->consulta('INSERT INTO referencias_externas (intento_id, url, descripcion) VALUES (?, ?, ?)', [$id, $r->url, $r->descripcion]);
        }

        return $intento->conId($id);
    }

    public function intentoPorClave(string $tenant, string $clave): ?Intento
    {
        $filas = $this->consulta('SELECT * FROM intentos WHERE tenant = ? AND idempotency_key = ?', [$tenant, $clave])->fetchAll();

        return $this->intentos($filas)[0] ?? null;
    }

    /**
     * @param list<array<string, mixed>> $filas
     * @return list<Intento>
     */
    private function intentos(array $filas): array
    {
        if ($filas === []) {
            return [];
        }
        $ids = array_map(static fn (array $f): int => (int) $f['id'], $filas);
        $evidencias = [];
        foreach ($this->consulta('SELECT * FROM evidencias WHERE intento_id IN (' . self::enlaces($ids) . ') ORDER BY id', $ids) as $e) {
            $evidencias[(int) $e['intento_id']][] = new EvidenciaRegistrada($e['tipo'], trim($e['huella']), (int) $e['tamano'], $e['clasificacion'], $e['ubicacion']);
        }
        $referencias = [];
        foreach ($this->consulta('SELECT * FROM referencias_externas WHERE intento_id IN (' . self::enlaces($ids) . ') ORDER BY id', $ids) as $r) {
            $referencias[(int) $r['intento_id']][] = new ReferenciaExterna($r['url'], $r['descripcion']);
        }

        return array_map(static fn (array $f): Intento => new Intento((int) $f['id'], (int) $f['campana_id'], $f['caso_id'], (int) $f['numero'],
            Resultado::from($f['resultado']), $f['tester'], $evidencias[(int) $f['id']] ?? [], $referencias[(int) $f['id']] ?? [],
            $f['defecto_id'] === null ? null : (int) $f['defecto_id'], $f['verifica_defecto'] === null ? null : (int) $f['verifica_defecto'],
            $f['motivo'], $f['idempotency_key'], trim($f['huella_solicitud']), new \DateTimeImmutable($f['registrado_en'])), $filas);
    }
}
