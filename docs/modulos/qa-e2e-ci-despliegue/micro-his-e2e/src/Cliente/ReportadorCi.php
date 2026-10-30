<?php

declare(strict_types=1);

namespace E2e\Cliente;

/**
 * El lado del pipeline: reporta resultados al servidor con resiliencia.
 *   · La clave de idempotencia se fija UNA vez por resultado y se repite en cada reintento (R-12): reintentar no duplica.
 *   · Se reintenta solo lo que puede cambiar al reintentar: red caída, 5xx y 429. Un 4xx no (el servidor ya decidió).
 *   · Tras los intentos, el resultado queda PENDIENTE en la bandeja del job: no se pierde y no frena la ejecución.
 *   · Cada petición lleva un X-Request-ID propio para seguirla en el registro de acceso del servidor.
 */
final class ReportadorCi
{
    public const CONFIRMADO = 'CONFIRMADO';
    public const RECHAZADO = 'RECHAZADO';
    public const PENDIENTE = 'PENDIENTE';

    /** @var callable(int): void */
    private $dormir;

    public function __construct(
        private readonly Transporte $transporte,
        private readonly string $token,
        private readonly string $tenant,
        private readonly BandejaPendientes $bandeja,
        private readonly int $intentos = 3,
        ?callable $dormir = null,
    ) {
        $this->dormir = $dormir ?? static fn (int $ms) => usleep($ms * 1000);
    }

    /**
     * @param list<array{tipo: string, bytes: string}> $artefactos
     * @return array{estado: string, http: ?int, cuerpo: array<string, mixed>}
     */
    public function reportar(int $ejecucion, string $escenario, string $resultado, int $intentos, array $artefactos = [], ?string $clave = null): array
    {
        $pedido = ['ruta' => "/api/v1/e2e/ejecuciones/$ejecucion/resultados", 'clave' => $clave ?? self::clave($ejecucion, $escenario),
            'cuerpo' => ['escenario' => $escenario, 'resultado' => $resultado, 'intentos' => $intentos,
                'evidencias' => array_map(static fn (array $a): array => ['tipo' => $a['tipo'], 'contenido_base64' => base64_encode($a['bytes'])], $artefactos)]];
        $r = $this->enviar($pedido);
        if ($r['estado'] === self::PENDIENTE) {
            $this->bandeja->guardar($pedido);
        }

        return $r;
    }

    /** El paso final del job: reenvía lo pendiente con SU clave. Devuelve cuántos se confirmaron, se rechazaron y siguen pendientes. */
    public function reenviarPendientes(): array
    {
        $conteo = [self::CONFIRMADO => 0, self::RECHAZADO => 0, self::PENDIENTE => 0];
        $quedan = [];
        foreach ($this->bandeja->todos() as $pedido) {
            $estado = $this->enviar($pedido)['estado'];
            $conteo[$estado]++;
            if ($estado === self::PENDIENTE) {
                $quedan[] = $pedido;
            }
        }
        $this->bandeja->reemplazar($quedan);

        return $conteo;
    }

    /**
     * Determinística: el mismo escenario en la misma ejecución da la misma clave, también si el job se reinicia.
     * Un reintento del escenario dentro del runner no es otro resultado: va en «intentos».
     */
    public static function clave(int $ejecucion, string $escenario): string
    {
        return 'ci-' . substr(hash('sha256', "$ejecucion|$escenario"), 0, 32);
    }

    /** @param array{ruta: string, clave: string, cuerpo: array<string, mixed>} $pedido */
    private function enviar(array $pedido): array
    {
        $ultimo = null;
        for ($i = 1; $i <= $this->intentos; $i++) {
            try {
                $r = $this->transporte->enviar('POST', $pedido['ruta'], ['Authorization' => "Bearer {$this->token}", 'X-Tenant-ID' => $this->tenant,
                    'Idempotency-Key' => $pedido['clave'], 'X-Request-ID' => bin2hex(random_bytes(8))], json_encode($pedido['cuerpo'], JSON_THROW_ON_ERROR));
                $ultimo = $r['estado'];
                if ($r['estado'] < 300) {
                    return ['estado' => self::CONFIRMADO, 'http' => $r['estado'], 'cuerpo' => $r['cuerpo']];
                }
                if ($r['estado'] < 500 && $r['estado'] !== 429) {
                    return ['estado' => self::RECHAZADO, 'http' => $r['estado'], 'cuerpo' => $r['cuerpo']];
                }
            } catch (FallaTransporte) {
                $ultimo = null;
            }
            if ($i < $this->intentos) {
                ($this->dormir)(200 * 2 ** ($i - 1));                       // 200 ms, 400 ms, …
            }
        }

        return ['estado' => self::PENDIENTE, 'http' => $ultimo, 'cuerpo' => []];
    }
}
