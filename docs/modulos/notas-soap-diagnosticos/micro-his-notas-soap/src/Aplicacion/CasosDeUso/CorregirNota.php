<?php

declare(strict_types=1);

namespace NotasSoap\Aplicacion\CasosDeUso;

use NotasSoap\Aplicacion\Comandos\CorregirNotaComando;
use NotasSoap\Aplicacion\Excepciones\ClaveIdempotenciaReutilizada;
use NotasSoap\Aplicacion\Puertos\GeneradorId;
use NotasSoap\Aplicacion\Puertos\RegistroIdempotencia;
use NotasSoap\Aplicacion\Puertos\Reloj;
use NotasSoap\Aplicacion\Resultados\NotaResultado;
use NotasSoap\Aplicacion\Servicios\RegistradorClinico;
use NotasSoap\Dominio\Cambios\NotaConDiagnostico;
use NotasSoap\Dominio\Excepciones\AccesoDenegado;
use NotasSoap\Dominio\Excepciones\ErrorPersistencia;
use NotasSoap\Dominio\Excepciones\NotaNoEncontrada;
use NotasSoap\Dominio\Excepciones\NotaYaCorregida;
use NotasSoap\Dominio\Modelo\Diagnostico;
use NotasSoap\Dominio\Modelo\NotaSoap;
use NotasSoap\Dominio\Modelo\SeccionesSoap;
use NotasSoap\Dominio\Modelo\UsuarioClinico;
use NotasSoap\Dominio\Puertos\RepositorioDiagnosticos;
use NotasSoap\Dominio\Puertos\RepositorioNotas;
use NotasSoap\Dominio\Servicios\ReglasDeEscritura;

/**
 * CU-06 (semana 6): corregir una nota SOAP firmada.
 * La nota firmada no se modifica (R-07) ni se borra (R-08): se guarda una VERSIÓN NUEVA con su
 * diagnóstico, que apunta a la anterior (supersedes_id) y explica el motivo. Usa las mismas reglas
 * y los mismos puertos que el registro; no cambia RegistrarNotaConDiagnostico ni FirmarNota (SRP).
 * Semana 7: las reglas están en ReglasDeEscritura y el guardado con su evento en RegistradorClinico.
 * Semana 12 (B-3): con Idempotency-Key, un reintento devuelve la corrección ya creada en vez de 409.
 */
final class CorregirNota
{
    private const SOLO_EL_AUTOR = 'Solo el autor, todavía asignado al episodio, puede corregir la nota.';

    public function __construct(
        private readonly ReglasDeEscritura $reglas,
        private readonly RegistradorClinico $registrador,
        private readonly RepositorioNotas $notas,
        private readonly RepositorioDiagnosticos $diagnosticos,
        private readonly GeneradorId $ids,
        private readonly Reloj $reloj,
        private readonly RegistroIdempotencia $idempotencia,
    ) {
    }

    public function ejecutar(CorregirNotaComando $c): NotaResultado
    {
        $usuario = new UsuarioClinico($c->usuarioId, $c->hospitalUsuario, $c->roles);
        $this->reglas->exigirMismoHospital($usuario, $c->hospitalSolicitud);                                   // R-01
        $anterior = $this->notas->buscar($c->notaId) ?? throw new NotaNoEncontrada('La nota no existe.');
        if ($anterior->autorId !== $usuario->id) {
            throw new AccesoDenegado(self::SOLO_EL_AUTOR);
        }
        $this->reglas->exigirPuedeEscribir($usuario, $c->hospitalSolicitud, $anterior->episodioId, self::SOLO_EL_AUTOR);   // R-02
        // Semana 12: ¿reintento de una corrección ya atendida? Después de verificar acceso, como en el registro.
        $previa = $c->claveIdempotencia === null ? null : $this->idempotencia->buscar($c->hospitalSolicitud, $usuario->id, $c->claveIdempotencia);
        if ($previa !== null) {
            return $this->repetir($previa, $c);
        }
        if ($this->notas->correccionDe($anterior->id) !== null) {
            throw new NotaYaCorregida('Esta versión ya tiene una corrección: corrija la versión más reciente.');
        }
        $this->reglas->exigirExpedienteDisponible($anterior->expedienteId, $anterior->episodioId,
            'El expediente está cerrado o ya no corresponde al episodio.');                                       // R-03

        // R-04, R-05 y las reglas nuevas (firmada, motivo): el dominio valida todo ANTES de guardar.
        $nueva = NotaSoap::corregir($anterior, $this->ids->nuevo(),
            new SeccionesSoap($c->subjetivo, $c->objetivo, $c->analisis, $c->plan), $c->motivo, $this->reloj->ahora());
        $diagnostico = Diagnostico::registrar($this->ids->nuevo(), $nueva, $c->codigoCie10, $c->descripcionDiagnostico, $c->tipoDiagnostico);

        try {
            $this->registrador->guardar(new NotaConDiagnostico($nueva, $diagnostico), $c->claveIdempotencia === null ? null    // R-06
                : fn () => $this->idempotencia->guardar($c->hospitalSolicitud, $usuario->id, $c->claveIdempotencia, $c->huella(), $nueva->id));
        } catch (ErrorPersistencia $e) {
            // Dos correcciones de la misma versión al mismo tiempo: la base (UNIQUE supersedes_id) deja pasar una sola.
            if (str_starts_with((string) $e->sqlState, '23') && $this->notas->correccionDe($anterior->id) !== null) {
                throw new NotaYaCorregida('Esta versión ya tiene una corrección: corrija la versión más reciente.');
            }
            throw $e;
        }

        return NotaResultado::desde($nueva, [$diagnostico]);
    }

    /** @param array{huella: string, nota_id: string} $previa */
    private function repetir(array $previa, CorregirNotaComando $c): NotaResultado
    {
        if (!hash_equals($previa['huella'], $c->huella())) {
            throw new ClaveIdempotenciaReutilizada('La Idempotency-Key ya se usó con otro contenido. Genere una clave nueva para otra corrección.');
        }
        $nota = $this->notas->buscar($previa['nota_id']) ?? throw new \LogicException('La clave apunta a una nota inexistente.');

        return NotaResultado::desde($nota, $this->diagnosticos->deNota($nota->id))->comoRepetida();
    }
}
