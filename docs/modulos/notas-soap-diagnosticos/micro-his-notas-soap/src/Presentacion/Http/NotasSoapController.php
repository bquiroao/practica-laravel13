<?php

declare(strict_types=1);

namespace NotasSoap\Presentacion\Http;

use NotasSoap\Aplicacion\CasosDeUso\ConsultarNota;
use NotasSoap\Aplicacion\CasosDeUso\FirmarNota;
use NotasSoap\Aplicacion\CasosDeUso\RegistrarNotaConDiagnostico;
use NotasSoap\Aplicacion\Comandos\ConsultarNotaComando;
use NotasSoap\Aplicacion\Comandos\FirmarNotaComando;
use NotasSoap\Aplicacion\Comandos\RegistrarNotaComando;

/** Traduce HTTP ↔ casos de uso. Sin SQL ni reglas de negocio: solo valida el formato de entrada. */
final class NotasSoapController
{
    public function __construct(
        private readonly RegistrarNotaConDiagnostico $registrar,
        private readonly FirmarNota $firmar,
        private readonly ConsultarNota $consultar,
    ) {
    }

    public function registrar(Solicitud $s, Identidad $quien, string $hospital): Respuesta
    {
        $b = $s->cuerpo;
        if ($b === null || !is_array($b['diagnostico'] ?? null)) {
            return Respuesta::error(400, 'cuerpo_invalido', 'El cuerpo debe ser JSON con los datos de la nota y un objeto "diagnostico".');
        }
        $texto = static fn (mixed $v): string => is_scalar($v) ? (string) $v : '';

        $resultado = $this->registrar->ejecutar(new RegistrarNotaComando(
            $quien->usuarioId,
            $quien->hospitalId,
            $quien->roles,
            $hospital,
            $texto($b['medical_record_id'] ?? ''),
            $texto($b['care_episode_id'] ?? ''),
            $texto($b['subjetivo'] ?? ''),
            $texto($b['objetivo'] ?? ''),
            $texto($b['analisis'] ?? ''),
            $texto($b['plan'] ?? ''),
            $texto($b['diagnostico']['code'] ?? ''),
            $texto($b['diagnostico']['description'] ?? ''),
            $texto($b['diagnostico']['type'] ?? ''),
        ));

        return Respuesta::json(201, $resultado->comoArreglo());
    }

    public function firmar(Identidad $quien, string $hospital, string $notaId): Respuesta
    {
        return Respuesta::json(200, $this->firmar->ejecutar(new FirmarNotaComando($quien->usuarioId, $quien->hospitalId, $hospital, $notaId))->comoArreglo());
    }

    public function consultar(Identidad $quien, string $hospital, string $notaId): Respuesta
    {
        return Respuesta::json(200, $this->consultar->ejecutar(new ConsultarNotaComando($quien->hospitalId, $hospital, $notaId))->comoArreglo());
    }
}
