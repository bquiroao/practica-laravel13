<?php

declare(strict_types=1);

/*
 * Aplicación de la semana 2. Los casos de uso trabajan con EvidenciaVerificable y NUNCA preguntan por el subtipo
 * (ningún instanceof Captura / RegistroTexto / ResultadoJUnit): eso es lo que LSP hace posible (LSP-06 lo verifica).
 */

namespace Qa\Aplicacion;

use Qa\Dominio\Campana;
use Qa\Dominio\Defecto;
use Qa\Dominio\Evidencia\Clasificacion;
use Qa\Dominio\Evidencia\EvidenciaInvalida;
use Qa\Dominio\Evidencia\EvidenciaVerificable;
use Qa\Dominio\Evidencia\ReferenciaExterna;
use Qa\Dominio\Evidencia\RegistroTexto;
use Qa\Dominio\Intento;
use Qa\Dominio\ReglaRechazada;
use Qa\Dominio\Resultado;

// ---------- Puertos ----------

final class AlmacenNoDisponible extends \RuntimeException
{
}

/** Almacén de evidencias. guardar() lanza AlmacenNoDisponible si no confirma; nunca devuelve una ubicación falsa. */
interface AlmacenEvidencias
{
    /** @throws AlmacenNoDisponible */
    public function guardar(EvidenciaVerificable $evidencia): string;

    /** @throws AlmacenNoDisponible */
    public function leer(string $ubicacion): string;
}

interface PoliticaAcceso
{
    public function permite(string $usuario, string $permiso, string $tenant): bool;
}

/** Descarga un archivo remoto (el log de un pipeline de CI). */
interface DescargadorRemoto
{
    public function descargar(string $url): string;
}

final class Respuesta
{
    /** @param array<string, mixed> $datos */
    public function __construct(public readonly int $estado, public readonly string $codigo, public readonly array $datos = [])
    {
    }
}

// ---------- Casos de uso ----------

/** CU-05/06/07: registrar el resultado de un caso con su evidencia y su defecto (R-04..R-06, R-11, R-12). */
final class RegistrarResultado
{
    public const TAMANO_MAXIMO = 5 * 1024 * 1024;

    /** @var array<string, array{huella: string, respuesta: Respuesta}> clave de idempotencia => primera respuesta */
    private array $claves = [];

    public function __construct(private readonly AlmacenEvidencias $almacen, private readonly PoliticaAcceso $acceso)
    {
    }

    /**
     * @param list<EvidenciaVerificable> $evidencias las que prueban el resultado
     * @param list<ReferenciaExterna>    $referencias contexto: NO cuentan como evidencia
     */
    public function ejecutar(string $tester, Campana $campana, string $casoId, Resultado $resultado, array $evidencias,
        array $referencias, ?Defecto $defecto, ?string $motivo, string $clave): Respuesta
    {
        if (!$this->acceso->permite($tester, 'qa.ejecutar', $campana->tenant)) {
            return new Respuesta(403, 'PERMISO_DENEGADO');
        }
        $huella = hash('sha256', serialize([$campana->id, $casoId, $resultado->value, array_map(static fn ($e) => $e->huella(), $evidencias), $defecto?->id, $motivo]));
        if (isset($this->claves[$clave])) {
            return $this->claves[$clave]['huella'] === $huella ? $this->claves[$clave]['respuesta'] : new Respuesta(409, 'CLAVE_REUTILIZADA');
        }
        if (!$campana->admite($casoId)) {
            return new Respuesta(409, 'CAMPANA_NO_ADMITE_RESULTADOS');
        }
        if ($resultado === Resultado::NO_APLICA && trim((string) $motivo) === '') {
            return new Respuesta(422, 'MOTIVO_REQUERIDO');
        }
        if ($resultado->exigeEvidencia() && $evidencias === []) {
            return new Respuesta(422, 'EVIDENCIA_REQUERIDA');
        }
        foreach ($evidencias as $e) {
            if ($e->tamano() > self::TAMANO_MAXIMO) {
                return new Respuesta(413, 'EVIDENCIA_DEMASIADO_GRANDE');
            }
        }
        if ($resultado === Resultado::FALLA && $defecto === null) {
            return new Respuesta(422, 'DEFECTO_REQUERIDO');
        }
        $guardadas = [];
        try {
            foreach ($evidencias as $e) {
                $guardadas[] = ['tipo' => $e->tipo(), 'huella' => $e->huella(), 'tamano' => $e->tamano(),
                    'clasificacion' => $e->clasificacion()->value, 'ubicacion' => $this->almacen->guardar($e)];
            }
        } catch (AlmacenNoDisponible) {
            return new Respuesta(503, 'EVIDENCIA_NO_DISPONIBLE');   // sin evidencia confirmada no hay resultado
        }
        $intento = new Intento($casoId, $campana->siguienteNumero($casoId), $resultado, $tester, $guardadas, $referencias, $defecto?->id, $motivo);
        $campana->agregar($intento);
        $respuesta = new Respuesta(201, 'RESULTADO_REGISTRADO', ['intento' => $intento->numero]);
        $this->claves[$clave] = ['huella' => $huella, 'respuesta' => $respuesta];

        return $respuesta;
    }
}

/** ¿Las evidencias de un intento son las que se registraron? Relee del almacén y compara la huella (R-11). */
final class VerificarIntegridad
{
    public function __construct(private readonly AlmacenEvidencias $almacen)
    {
    }

    public function ejecutar(Intento $intento): string
    {
        foreach ($intento->evidencias as $e) {
            if (hash('sha256', $this->almacen->leer($e['ubicacion'])) !== $e['huella']) {
                return 'ALTERADA';
            }
        }

        return 'INTEGRA';
    }
}

/** Informe de la campaña: tipo, huella y tamaño de cada evidencia. Nunca lee el contenido (puede tener datos clínicos). */
final class GenerarInforme
{
    public function ejecutar(Campana $campana): string
    {
        $lineas = [];
        foreach ($campana->intentos() as $i) {
            $lineas[] = sprintf('%s #%d %s %s', $i->casoId, $i->numero, $i->resultado->value, implode(', ', array_map(
                static fn (array $e): string => "{$e['tipo']} {$e['tamano']} B " . substr($e['huella'], 0, 12) . ($e['clasificacion'] === 'DATOS_CLINICOS' ? ' [clínica]' : ''),
                $i->evidencias)));
        }

        return implode("\n", $lineas);
    }
}

/** Ver el contenido de una evidencia: la protección de los datos clínicos vive AQUÍ, no en la evidencia (R-13). */
final class ConsultarEvidencia
{
    public function __construct(private readonly PoliticaAcceso $acceso)
    {
    }

    public function ejecutar(string $usuario, string $tenant, EvidenciaVerificable $evidencia): Respuesta
    {
        $permiso = $evidencia->clasificacion() === Clasificacion::DATOS_CLINICOS ? 'qa.ver_evidencia_clinica' : 'qa.consultar';
        if (!$this->acceso->permite($usuario, $permiso, $tenant)) {
            return new Respuesta(403, 'PERMISO_DENEGADO');
        }

        return new Respuesta(200, 'EVIDENCIA', ['contenido' => $evidencia->contenido()]);
    }
}

/**
 * Lo que en el ANTES era EnlaceExterno: el log del pipeline se DESCARGA una vez y se guarda como RegistroTexto.
 * La URL queda como ReferenciaExterna, para contexto. Si el log remoto rota después, la evidencia no cambia.
 */
final class ImportarLogRemoto
{
    public function __construct(private readonly DescargadorRemoto $descargador)
    {
    }

    /** @return array{0: RegistroTexto, 1: ReferenciaExterna} */
    public function ejecutar(string $url): array
    {
        $texto = $this->descargador->descargar($url);
        try {
            return [new RegistroTexto($texto), new ReferenciaExterna($url, 'log del pipeline (copia descargada en el registro)')];
        } catch (EvidenciaInvalida $e) {
            throw new ReglaRechazada($e->codigo);
        }
    }
}
