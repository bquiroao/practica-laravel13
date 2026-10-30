<?php

declare(strict_types=1);

namespace Qa\Cliente;

/**
 * Cliente de la API para la interfaz (semanas 8–11) u otros módulos. Se programa contra el CONTRATO (api/openapi.yaml),
 * no contra las clases del servidor (ARQ-10).
 *
 * Política de resiliencia:
 *  - registrarResultado: una Idempotency-Key por resultado, la MISMA en cada reintento y en cada reenvío; reintenta si no
 *    hubo respuesta o fue 5xx; si no se confirma, queda PENDIENTE en la bandeja y reenviarPendientes() lo vuelve a mandar.
 *    Es seguro (R-12) y es lo correcto aquí: el resultado ya OCURRIÓ (el tester ejecutó el caso), perderlo rompe la
 *    trazabilidad y demorarlo no daña a nadie. (Prescripciones hace lo contrario: una prescripción no puede quedar pendiente.)
 *  - trazabilidad (GET): reintenta; es segura.
 *  - el resto (planificar, iniciar, cerrar, clasificar, corregir, diseñar, aprobar): UN intento. Sin respuesta → INCIERTO.
 *  - un 4xx nunca se reintenta: es una decisión del servidor sobre los datos.
 */
final class ClienteQa
{
    private const BASE = '/api/v1/qa';

    /** @var \Closure(int): void */
    private \Closure $esperar;

    /** @param \Closure(int): void|null $esperar espera entre intentos (ms); las pruebas la anulan */
    public function __construct(
        private readonly Transporte $transporte,
        private readonly string $token,
        private readonly string $tenant,
        private readonly BandejaPendientes $bandeja = new BandejaEnMemoria(),
        private readonly int $reintentos = 2,
        ?\Closure $esperar = null,
    ) {
        $this->esperar = $esperar ?? static function (int $ms): void {
            usleep($ms * 1000);
        };
    }

    /**
     * @param list<array{tipo: string, bytes: string, clasificacion?: string}> $evidencias
     * @param array{titulo: string, pasos: list<string>, severidad: string}|null $defecto
     */
    public function registrarResultado(int $campana, string $caso, string $resultado, array $evidencias = [], ?array $defecto = null,
        ?int $verificaDefecto = null, ?string $motivo = null): ResultadoCliente
    {
        $cuerpo = array_filter([
            'caso' => $caso,
            'resultado' => $resultado,
            'evidencias' => array_map(static fn (array $e): array => ['tipo' => $e['tipo'], 'contenido_base64' => base64_encode($e['bytes']),
                'clasificacion' => $e['clasificacion'] ?? 'GENERAL'], $evidencias),
            'defecto' => $defecto,
            'verifica_defecto' => $verificaDefecto,
            'motivo' => $motivo,
        ], static fn ($v): bool => $v !== null && $v !== []);

        return $this->enviarResultado('res-' . bin2hex(random_bytes(12)), self::BASE . "/campanas/$campana/resultados", $cuerpo);
    }

    /** Reenvía cada resultado pendiente con SU clave. @return array<string, ResultadoCliente> clave => resultado */
    public function reenviarPendientes(): array
    {
        $r = [];
        foreach ($this->bandeja->todos() as $clave => $p) {
            $r[$clave] = $this->enviarResultado($clave, $p['ruta'], $p['cuerpo']);
        }

        return $r;
    }

    /** @return array<string, array{ruta: string, cuerpo: array<string, mixed>}> */
    public function pendientes(): array
    {
        return $this->bandeja->todos();
    }

    public function trazabilidad(int $campana): ResultadoCliente
    {
        return $this->llamar('GET', self::BASE . "/campanas/$campana/trazabilidad", null, null, 1 + $this->reintentos);
    }

    /** @param list<string> $funcionalidades @param list<string> $casos */
    public function planificar(string $nombre, array $funcionalidades, array $casos, ?float $umbral = null): ResultadoCliente
    {
        return $this->llamar('POST', self::BASE . '/campanas', array_filter(['nombre' => $nombre, 'funcionalidades' => $funcionalidades,
            'casos' => $casos, 'umbral' => $umbral], static fn ($v): bool => $v !== null), null, 1);
    }

    public function iniciar(int $campana, string $build): ResultadoCliente
    {
        return $this->llamar('POST', self::BASE . "/campanas/$campana/inicio", ['build' => $build], null, 1);
    }

    public function cerrar(int $campana): ResultadoCliente
    {
        return $this->llamar('POST', self::BASE . "/campanas/$campana/cierre", null, null, 1);
    }

    public function clasificar(int $defecto, string $severidad, string $prioridad, ?string $motivo = null): ResultadoCliente
    {
        return $this->llamar('POST', self::BASE . "/defectos/$defecto/clasificacion", array_filter(['severidad' => $severidad,
            'prioridad' => $prioridad, 'motivo' => $motivo], static fn ($v): bool => $v !== null), null, 1);
    }

    public function corregir(int $defecto, string $build): ResultadoCliente
    {
        return $this->llamar('POST', self::BASE . "/defectos/$defecto/correccion", ['build' => $build], null, 1);
    }

    /** @param array<string, mixed> $cuerpo */
    private function enviarResultado(string $clave, string $ruta, array $cuerpo): ResultadoCliente
    {
        $r = $this->llamar('POST', $ruta, $cuerpo, $clave, 1 + $this->reintentos);
        if ($r->tipo === ResultadoCliente::INCIERTO || ($r->estado ?? 0) >= 500) {
            $this->bandeja->guardar($clave, $ruta, $cuerpo);

            return new ResultadoCliente(ResultadoCliente::PENDIENTE, $r->estado, $r->cuerpo, $clave, $r->requestId, $r->intentos);
        }
        $this->bandeja->quitar($clave);

        return $r;
    }

    /** @param array<string, mixed>|null $cuerpo */
    private function llamar(string $metodo, string $ruta, ?array $cuerpo, ?string $clave, int $maximo): ResultadoCliente
    {
        $requestId = 'cli-' . bin2hex(random_bytes(8));
        $cabeceras = ['Authorization' => "Bearer {$this->token}", 'X-Tenant-ID' => $this->tenant, 'X-Request-ID' => $requestId]
            + ($clave !== null ? ['Idempotency-Key' => $clave] : []);
        $ultimo = null;
        for ($intento = 1; $intento <= $maximo; $intento++) {
            if ($intento > 1) {
                ($this->esperar)(200 * 2 ** ($intento - 2));
            }
            try {
                $r = $this->transporte->enviar($metodo, $ruta, $cabeceras, $cuerpo);
            } catch (FallaTransporte) {
                continue;
            }
            $ultimo = $r;
            if ($r['estado'] < 500) {
                return new ResultadoCliente($r['estado'] < 400 ? ResultadoCliente::CONFIRMADO : ResultadoCliente::RECHAZADO, $r['estado'], $r['cuerpo'],
                    $clave, $requestId, $intento);
            }
        }

        return new ResultadoCliente(ResultadoCliente::INCIERTO, $ultimo['estado'] ?? null, $ultimo['cuerpo'] ?? [], $clave, $requestId, $maximo);
    }
}
