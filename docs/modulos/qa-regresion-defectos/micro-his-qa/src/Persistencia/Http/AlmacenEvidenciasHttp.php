<?php

declare(strict_types=1);

namespace Qa\Persistencia\Http;

use Qa\Aplicacion\Puertos\AlmacenEvidencias;
use Qa\Aplicacion\Puertos\AlmacenNoDisponible;
use Qa\Dominio\Evidencia\EvidenciaVerificable;

/**
 * El almacén de evidencias como SERVICIO aparte (semana 5). Es la única dependencia remota del módulo en el camino de
 * «registrar resultado», así que aquí se aplican comunicación, seguridad y resiliencia:
 *   PUT {base}/almacen/v1/{tenant}/{sha256}  (bytes)  → 201/200 {"huella": "<sha256>"}
 *   GET {base}/almacen/v1/{tenant}/{sha256}           → 200 bytes
 * Fail-closed (R-05): tiempo agotado (3 s), red caída, estado inesperado, o una huella que no coincide → AlmacenNoDisponible.
 * El PUT es idempotente por construcción: la ruta ES la huella del contenido, así que reintentarlo no duplica nada.
 */
final class AlmacenEvidenciasHttp implements AlmacenEvidencias
{
    public const LIMITE_SEGUNDOS = 3.0;

    /** @var \Closure(string, string, float, array<string, string>, ?string): array{0: int, 1: string} */
    private \Closure $http;

    /**
     * @param \Closure(string, string, float, array<string, string>, ?string): array{0: int, 1: string}|null $http
     *        (método, url, límite, cabeceras, cuerpo) → [estado, cuerpo]; por defecto, streams de PHP. Las pruebas inyectan fallas.
     */
    public function __construct(
        private readonly string $base,
        private readonly ?CircuitoInterruptor $circuito = null,
        ?\Closure $http = null,
        private readonly float $limite = self::LIMITE_SEGUNDOS,
        private readonly ?string $token = null,
    ) {
        $this->http = $http ?? self::httpStream(...);
    }

    public function guardar(string $tenant, EvidenciaVerificable $evidencia): string
    {
        if (!preg_match('/^[a-z0-9-]{1,40}$/', $tenant)) {
            throw new AlmacenNoDisponible('tenant no válido para una ruta');
        }
        $ubicacion = $tenant . '/' . $evidencia->huella();
        [$estado, $cuerpo] = $this->llamar('PUT', $ubicacion, $evidencia->contenido());
        $json = json_decode($cuerpo, true);
        if (!in_array($estado, [200, 201], true) || !is_array($json) || ($json['huella'] ?? null) !== $evidencia->huella()) {
            $this->circuito?->falla();
            throw new AlmacenNoDisponible("el almacén no confirmó la evidencia (estado $estado)");
        }
        $this->circuito?->exito();

        return $ubicacion;
    }

    public function leer(string $ubicacion): string
    {
        if (!preg_match('#^[a-z0-9-]{1,40}/([0-9a-f]{64})$#', $ubicacion, $m)) {
            throw new AlmacenNoDisponible('ubicación no válida');
        }
        [$estado, $cuerpo] = $this->llamar('GET', $ubicacion, null);
        if ($estado !== 200 || hash('sha256', $cuerpo) !== $m[1]) {
            $this->circuito?->falla();
            throw new AlmacenNoDisponible("el almacén devolvió otra cosa (estado $estado)");
        }
        $this->circuito?->exito();

        return $cuerpo;
    }

    /** @return array{0: int, 1: string} */
    private function llamar(string $metodo, string $ubicacion, ?string $cuerpo): array
    {
        if ($this->circuito !== null && !$this->circuito->permiteIntentar()) {
            throw new AlmacenNoDisponible('circuito abierto: el almacén falló hace poco');
        }
        $cabeceras = ['Content-Type' => 'application/octet-stream'] + ($this->token === null ? [] : ['Authorization' => "Bearer {$this->token}"]);
        try {
            return ($this->http)($metodo, rtrim($this->base, '/') . '/almacen/v1/' . $ubicacion, $this->limite, $cabeceras, $cuerpo);
        } catch (\Throwable $e) {
            $this->circuito?->falla();
            throw new AlmacenNoDisponible('sin respuesta del almacén', 0, $e);
        }
    }

    /**
     * @param array<string, string> $cabeceras
     * @return array{0: int, 1: string}
     */
    private static function httpStream(string $metodo, string $url, float $limite, array $cabeceras, ?string $cuerpo): array
    {
        $contexto = stream_context_create(['http' => [
            'method' => $metodo,
            'header' => implode("\r\n", array_map(static fn (string $k, string $v): string => "$k: $v", array_keys($cabeceras), $cabeceras)),
            'content' => $cuerpo ?? '',
            'timeout' => $limite,
            'ignore_errors' => true,
        ]]);
        $texto = @file_get_contents($url, false, $contexto);
        if ($texto === false || !isset($http_response_header[0])) {
            throw new \RuntimeException('sin respuesta');
        }
        preg_match('#^HTTP/\S+\s+(\d{3})#', $http_response_header[0], $m);

        return [(int) ($m[1] ?? 0), $texto];
    }
}
