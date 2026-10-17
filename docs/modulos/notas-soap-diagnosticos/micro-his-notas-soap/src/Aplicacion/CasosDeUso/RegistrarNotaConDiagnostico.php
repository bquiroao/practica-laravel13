<?php

declare(strict_types=1);

namespace NotasSoap\Aplicacion\CasosDeUso;

use NotasSoap\Aplicacion\Comandos\RegistrarNotaComando;
use NotasSoap\Aplicacion\Excepciones\ClaveIdempotenciaReutilizada;
use NotasSoap\Aplicacion\Puertos\GeneradorId;
use NotasSoap\Aplicacion\Puertos\RegistroIdempotencia;
use NotasSoap\Aplicacion\Puertos\Reloj;
use NotasSoap\Aplicacion\Resultados\NotaResultado;
use NotasSoap\Aplicacion\Servicios\RegistradorClinico;
use NotasSoap\Dominio\Cambios\NotaConDiagnostico;
use NotasSoap\Dominio\Modelo\Diagnostico;
use NotasSoap\Dominio\Modelo\NotaSoap;
use NotasSoap\Dominio\Modelo\SeccionesSoap;
use NotasSoap\Dominio\Modelo\UsuarioClinico;
use NotasSoap\Dominio\Puertos\RepositorioDiagnosticos;
use NotasSoap\Dominio\Puertos\RepositorioNotas;
use NotasSoap\Dominio\Servicios\ReglasDeEscritura;

/**
 * CU-01 + CU-02: registrar la nota SOAP con su diagnóstico.
 * Semana 5: con Idempotency-Key, un reintento del cliente devuelve la nota ya creada en vez de duplicarla.
 * Semana 7: solo orquesta; las reglas están en ReglasDeEscritura y el guardado con su evento en RegistradorClinico.
 */
final class RegistrarNotaConDiagnostico
{
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

    public function ejecutar(RegistrarNotaComando $c): NotaResultado
    {
        $usuario = new UsuarioClinico($c->usuarioId, $c->hospitalUsuario, $c->roles);

        // CU-05 / R-01 / R-02
        $this->reglas->exigirPuedeEscribir($usuario, $c->hospitalSolicitud, $c->episodioId);
        // Semana 5: ¿es el reintento de una petición que ya se atendió? (después de verificar acceso,
        // para que nadie sin permiso pueda averiguar qué claves existen)
        if ($c->claveIdempotencia !== null) {
            $previa = $this->idempotencia->buscar($c->hospitalSolicitud, $usuario->id, $c->claveIdempotencia);
            if ($previa !== null) {
                return $this->repetir($previa, $c);
            }
        }

        // R-03
        $this->reglas->exigirExpedienteDisponible($c->expedienteId, $c->episodioId);

        // R-04 y R-05: el dominio valida todo ANTES de guardar.
        $nota = NotaSoap::registrar(
            $this->ids->nuevo(),
            $c->expedienteId,
            $c->episodioId,
            $usuario->id,
            new SeccionesSoap($c->subjetivo, $c->objetivo, $c->analisis, $c->plan),
            $this->reloj->ahora(),
        );
        $diagnostico = Diagnostico::registrar($this->ids->nuevo(), $nota, $c->codigoCie10, $c->descripcionDiagnostico, $c->tipoDiagnostico);

        // R-06: nota, diagnóstico, evento y clave de idempotencia juntos o nada.
        $this->registrador->guardar(new NotaConDiagnostico($nota, $diagnostico), $c->claveIdempotencia === null ? null
            : fn () => $this->idempotencia->guardar($c->hospitalSolicitud, $usuario->id, $c->claveIdempotencia, $c->huella(), $nota->id));

        return NotaResultado::desde($nota, [$diagnostico]);
    }

    /** @param array{huella: string, nota_id: string} $previa */
    private function repetir(array $previa, RegistrarNotaComando $c): NotaResultado
    {
        if (!hash_equals($previa['huella'], $c->huella())) {
            throw new ClaveIdempotenciaReutilizada('La Idempotency-Key ya se usó con otro contenido. Genere una clave nueva para una nota nueva.');
        }
        $nota = $this->notas->buscar($previa['nota_id']) ?? throw new \LogicException('La clave apunta a una nota inexistente.');

        return NotaResultado::desde($nota, $this->diagnosticos->deNota($nota->id))->comoRepetida();
    }
}
