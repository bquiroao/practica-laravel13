<?php

declare(strict_types=1);

namespace Prescripciones\Persistencia\Http;

use Prescripciones\Aplicacion\Puertos\FuenteAlergias;
use Prescripciones\Aplicacion\Puertos\FuenteAlergiasNoDisponible;
use Prescripciones\Dominio\Modelo\Alergia;
use Prescripciones\Dominio\Modelo\ExpedienteAlergias;

/**
 * Adaptador para cuando el Expediente sea un SERVICIO aparte (evaluación de la frontera, semana 5):
 *   GET {base}/expediente/v1/pacientes/{paciente}/alergias  →  {"evaluado": bool, "alergias": [{codigo, nombre, tipo, severidad, estado, reaccion}]}
 * Contrato fail-closed (R-07): tiempo agotado (2 s, RNF-08), red caída, estado ≠ 200, JSON inválido o un dato fuera del
 * esquema → FuenteAlergiasNoDisponible. Nunca un expediente vacío.
 */
final class HttpFuenteAlergias implements FuenteAlergias
{
    public const LIMITE_SEGUNDOS = 2.0;

    /** @var \Closure(string, float, array<string, string>): array{0: int, 1: string} */
    private \Closure $obtener;

    /**
     * @param \Closure(string, float, array<string, string>): array{0: int, 1: string}|null $obtener GET (url, límite, cabeceras) → [estado, cuerpo];
     *        por defecto, HTTP con streams de PHP. Las pruebas inyectan uno que simula fallas.
     */
    public function __construct(
        private readonly string $base,
        private readonly ?CircuitoInterruptor $circuito = null,
        ?\Closure $obtener = null,
        private readonly float $limite = self::LIMITE_SEGUNDOS,
        private readonly ?string $token = null,
    ) {
        $this->obtener = $obtener ?? self::getHttp(...);
    }

    public function alergiasDe(string $paciente, string $hospital): ExpedienteAlergias
    {
        if ($this->circuito !== null && !$this->circuito->permiteIntentar()) {
            throw new FuenteAlergiasNoDisponible('Circuito abierto: el Expediente falló varias veces seguidas.');
        }
        try {
            $url = rtrim($this->base, '/') . '/expediente/v1/pacientes/' . rawurlencode($paciente) . '/alergias';
            [$estado, $cuerpo] = ($this->obtener)($url, $this->limite, ['X-Tenant-ID' => $hospital] + ($this->token !== null ? ['Authorization' => "Bearer {$this->token}"] : []));
            if ($estado !== 200) {
                throw new \RuntimeException("estado $estado");
            }
            $expediente = self::traducir(json_decode($cuerpo, true, 8, JSON_THROW_ON_ERROR));
        } catch (\Throwable $e) {
            $this->circuito?->falla();
            throw new FuenteAlergiasNoDisponible('El Expediente no respondió correctamente.', 0, $e);
        }
        $this->circuito?->exito();

        return $expediente;
    }

    private static function traducir(mixed $datos): ExpedienteAlergias
    {
        if (!is_array($datos) || !is_bool($datos['evaluado'] ?? null) || !is_array($datos['alergias'] ?? null)) {
            throw new \UnexpectedValueException('respuesta fuera del contrato');
        }
        $alergias = array_map(static function (mixed $a): Alergia {
            if (!is_array($a) || !is_string($a['tipo'] ?? null) || !is_string($a['severidad'] ?? null) || !is_string($a['estado'] ?? null)) {
                throw new \UnexpectedValueException('alergia fuera del contrato');
            }

            return new Alergia($a['codigo'] ?? null, $a['nombre'] ?? null, $a['tipo'], $a['severidad'], $a['estado'], $a['reaccion'] ?? null);
        }, $datos['alergias']);
        // Coherencia: «evaluado = false» con alergias es una contradicción; se trata como falla, no como dato.
        if (!$datos['evaluado'] && $alergias !== []) {
            throw new \UnexpectedValueException('evaluado=false con alergias');
        }

        return new ExpedienteAlergias($datos['evaluado'], $alergias);
    }

    /** @param array<string, string> $cabeceras @return array{0: int, 1: string} */
    private static function getHttp(string $url, float $limite, array $cabeceras): array
    {
        $lineas = array_map(static fn (string $k, string $v): string => "$k: $v", array_keys($cabeceras), $cabeceras);
        $contexto = stream_context_create(['http' => ['method' => 'GET', 'header' => implode("\r\n", $lineas), 'timeout' => $limite,
            'ignore_errors' => true]]);
        $inicio = microtime(true);
        $cuerpo = @file_get_contents($url, false, $contexto);
        if ($cuerpo === false || microtime(true) - $inicio > $limite) {
            throw new \RuntimeException('sin respuesta dentro del límite');
        }
        $estado = isset($http_response_header[0]) && preg_match('#^HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m) ? (int) $m[1] : 0;

        return [$estado, $cuerpo];
    }
}
