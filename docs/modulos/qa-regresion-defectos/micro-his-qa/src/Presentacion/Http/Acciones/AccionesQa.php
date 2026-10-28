<?php

declare(strict_types=1);

namespace Qa\Presentacion\Http\Acciones;

use Qa\Aplicacion\CasosDeUso\AprobarCaso;
use Qa\Aplicacion\CasosDeUso\CerrarCampana;
use Qa\Aplicacion\CasosDeUso\ClasificarDefecto;
use Qa\Aplicacion\CasosDeUso\ConsultarTrazabilidad;
use Qa\Aplicacion\CasosDeUso\DisenarCaso;
use Qa\Aplicacion\CasosDeUso\IniciarCampana;
use Qa\Aplicacion\CasosDeUso\MarcarCorregido;
use Qa\Aplicacion\CasosDeUso\PlanificarCampana;
use Qa\Aplicacion\CasosDeUso\RegistrarResultado;
use Qa\Presentacion\Http\Identidad;
use Qa\Presentacion\Http\Respuesta;
use Qa\Presentacion\Http\Ruta;
use Qa\Presentacion\Http\Solicitud;
use Qa\Presentacion\Http\TraductorSalida;

/**
 * Acciones HTTP: leen la solicitud, llaman al caso de uso y traducen la salida. Sin SQL y sin reglas de QA
 * (el tenant y el usuario salen de la Identidad, nunca del cuerpo). Semana 3: todo en una clase; la semana 4 la
 * separa en controlador, formularios y vistas (MVC).
 */
final class AccionesQa
{
    private const BASE = '/api/v1/qa';

    /** @param array{disenar: DisenarCaso, aprobar: AprobarCaso, planificar: PlanificarCampana, iniciar: IniciarCampana,
     *               registrar: RegistrarResultado, clasificar: ClasificarDefecto, corregir: MarcarCorregido, cerrar: CerrarCampana,
     *               trazabilidad: ConsultarTrazabilidad} $casos */
    public function __construct(private readonly array $casos, private readonly TraductorSalida $traductor = new TraductorSalida())
    {
    }

    /** @return list<Ruta> */
    public function rutas(): array
    {
        $b = preg_quote(self::BASE, '#');

        return [
            new Ruta('POST', "#^{$b}/casos$#", $this->disenar(...)),
            new Ruta('POST', "#^{$b}/casos/(?<id>TC-\d{3,6})/aprobacion$#", $this->aprobar(...)),
            new Ruta('POST', "#^{$b}/campanas$#", $this->planificar(...)),
            new Ruta('POST', "#^{$b}/campanas/(?<id>\d{1,12})/inicio$#", $this->iniciar(...)),
            new Ruta('POST', "#^{$b}/campanas/(?<id>\d{1,12})/resultados$#", $this->registrar(...)),
            new Ruta('POST', "#^{$b}/campanas/(?<id>\d{1,12})/cierre$#", $this->cerrar(...)),
            new Ruta('GET', "#^{$b}/campanas/(?<id>\d{1,12})/trazabilidad$#", $this->trazabilidad(...)),
            new Ruta('POST', "#^{$b}/defectos/(?<id>\d{1,12})/clasificacion$#", $this->clasificar(...)),
            new Ruta('POST', "#^{$b}/defectos/(?<id>\d{1,12})/correccion$#", $this->corregir(...)),
        ];
    }

    public function disenar(Solicitud $s, Identidad $i): Respuesta
    {
        $cuerpo = $this->cuerpo($s);
        if ($cuerpo instanceof Respuesta) {
            return $cuerpo;
        }
        $salida = $this->casos['disenar']->ejecutar($i->usuario, $i->tenant, $cuerpo);

        return $this->traductor->traducir($salida, isset($salida->datos['id']) ? self::BASE . '/casos/' . $salida->datos['id'] : null);
    }

    /** @param array<string, string> $p */
    public function aprobar(Solicitud $s, Identidad $i, array $p): Respuesta
    {
        return $this->traductor->traducir($this->casos['aprobar']->ejecutar($i->usuario, $i->tenant, $p['id']));
    }

    public function planificar(Solicitud $s, Identidad $i): Respuesta
    {
        $cuerpo = $this->cuerpo($s);
        if ($cuerpo instanceof Respuesta) {
            return $cuerpo;
        }
        $salida = $this->casos['planificar']->ejecutar($i->usuario, $i->tenant, $cuerpo);

        return $this->traductor->traducir($salida, isset($salida->datos['id']) ? self::BASE . '/campanas/' . $salida->datos['id'] . '/trazabilidad' : null);
    }

    /** @param array<string, string> $p */
    public function iniciar(Solicitud $s, Identidad $i, array $p): Respuesta
    {
        $cuerpo = $this->cuerpo($s);
        if ($cuerpo instanceof Respuesta) {
            return $cuerpo;
        }
        if (!is_string($cuerpo['build'] ?? null)) {
            return Respuesta::json(400, ['error' => 'CUERPO_INVALIDO', 'detalle' => ['build']]);
        }

        return $this->traductor->traducir($this->casos['iniciar']->ejecutar($i->usuario, $i->tenant, (int) $p['id'], $cuerpo['build']));
    }

    /** POST .../resultados con Idempotency-Key; cada evidencia viaja en base64 y aquí se decodifica (formato, no regla). */
    public function registrar(Solicitud $s, Identidad $i, array $p): Respuesta
    {
        $clave = $s->cabecera('idempotency-key');
        if ($clave === null || !preg_match('/^[A-Za-z0-9-]{8,64}$/', $clave)) {
            return Respuesta::json(400, ['error' => 'IDEMPOTENCY_KEY_REQUERIDA']);
        }
        $cuerpo = $this->cuerpo($s);
        if ($cuerpo instanceof Respuesta) {
            return $cuerpo;
        }
        $evidencias = [];
        foreach (is_array($cuerpo['evidencias'] ?? null) ? $cuerpo['evidencias'] : [] as $n => $e) {
            $bytes = is_array($e) && is_string($e['contenido_base64'] ?? null) ? base64_decode($e['contenido_base64'], true) : false;
            if ($bytes === false) {
                return Respuesta::json(400, ['error' => 'EVIDENCIA_BASE64_INVALIDA', 'detalle' => ["evidencias[$n]"]]);
            }
            $evidencias[] = ['tipo' => (string) ($e['tipo'] ?? ''), 'bytes' => $bytes, 'clasificacion' => (string) ($e['clasificacion'] ?? 'GENERAL')];
        }
        $cuerpo['evidencias'] = $evidencias;

        return $this->traductor->traducir($this->casos['registrar']->ejecutar($i->usuario, $i->tenant, (int) $p['id'], $clave, $cuerpo));
    }

    /** @param array<string, string> $p */
    public function cerrar(Solicitud $s, Identidad $i, array $p): Respuesta
    {
        return $this->traductor->traducir($this->casos['cerrar']->ejecutar($i->usuario, $i->tenant, (int) $p['id']));
    }

    /** @param array<string, string> $p */
    public function trazabilidad(Solicitud $s, Identidad $i, array $p): Respuesta
    {
        return $this->traductor->traducir($this->casos['trazabilidad']->ejecutar($i->usuario, $i->tenant, (int) $p['id']));
    }

    /** @param array<string, string> $p */
    public function clasificar(Solicitud $s, Identidad $i, array $p): Respuesta
    {
        $cuerpo = $this->cuerpo($s);
        if ($cuerpo instanceof Respuesta) {
            return $cuerpo;
        }

        return $this->traductor->traducir($this->casos['clasificar']->ejecutar($i->usuario, $i->tenant, (int) $p['id'], $cuerpo));
    }

    /** @param array<string, string> $p */
    public function corregir(Solicitud $s, Identidad $i, array $p): Respuesta
    {
        $cuerpo = $this->cuerpo($s);
        if ($cuerpo instanceof Respuesta) {
            return $cuerpo;
        }
        if (!is_string($cuerpo['build'] ?? null)) {
            return Respuesta::json(400, ['error' => 'CUERPO_INVALIDO', 'detalle' => ['build']]);
        }

        return $this->traductor->traducir($this->casos['corregir']->ejecutar($i->usuario, $i->tenant, (int) $p['id'], $cuerpo['build']));
    }

    /** @return array<string, mixed>|Respuesta */
    private function cuerpo(Solicitud $s): array|Respuesta
    {
        try {
            $datos = json_decode($s->cuerpo === '' ? '{}' : $s->cuerpo, true, 16, JSON_THROW_ON_ERROR);
        } catch (\JsonException) {
            return Respuesta::json(400, ['error' => 'JSON_INVALIDO']);
        }

        return is_array($datos) && !array_is_list($datos) || $datos === [] ? $datos : Respuesta::json(400, ['error' => 'CUERPO_INVALIDO']);
    }
}
