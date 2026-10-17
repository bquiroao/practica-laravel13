<?php

declare(strict_types=1);

namespace NotasSoap\Aplicacion\CasosDeUso;

use NotasSoap\Aplicacion\Comandos\CorregirNotaComando;
use NotasSoap\Aplicacion\Puertos\GeneradorId;
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
use NotasSoap\Dominio\Puertos\RepositorioNotas;
use NotasSoap\Dominio\Servicios\ReglasDeEscritura;

/**
 * CU-06 (semana 6): corregir una nota SOAP firmada.
 * La nota firmada no se modifica (R-07) ni se borra (R-08): se guarda una VERSIÓN NUEVA con su
 * diagnóstico, que apunta a la anterior (supersedes_id) y explica el motivo. Usa las mismas reglas
 * y los mismos puertos que el registro; no cambia RegistrarNotaConDiagnostico ni FirmarNota (SRP).
 * Semana 7: las reglas están en ReglasDeEscritura y el guardado con su evento en RegistradorClinico.
 */
final class CorregirNota
{
    private const SOLO_EL_AUTOR = 'Solo el autor, todavía asignado al episodio, puede corregir la nota.';

    public function __construct(
        private readonly ReglasDeEscritura $reglas,
        private readonly RegistradorClinico $registrador,
        private readonly RepositorioNotas $notas,
        private readonly GeneradorId $ids,
        private readonly Reloj $reloj,
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
            $this->registrador->guardar(new NotaConDiagnostico($nueva, $diagnostico));                         // R-06
        } catch (ErrorPersistencia $e) {
            // Dos correcciones de la misma versión al mismo tiempo: la base (UNIQUE supersedes_id) deja pasar una sola.
            if (str_starts_with((string) $e->sqlState, '23') && $this->notas->correccionDe($anterior->id) !== null) {
                throw new NotaYaCorregida('Esta versión ya tiene una corrección: corrija la versión más reciente.');
            }
            throw $e;
        }

        return NotaResultado::desde($nueva, [$diagnostico]);
    }
}
