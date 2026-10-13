<?php

declare(strict_types=1);

namespace NotasSoap\Presentacion\Controladores;

use NotasSoap\Aplicacion\CasosDeUso\ConsultarNota;
use NotasSoap\Aplicacion\CasosDeUso\FirmarNota;
use NotasSoap\Aplicacion\CasosDeUso\ListarNotasExpediente;
use NotasSoap\Aplicacion\CasosDeUso\RegistrarNotaConDiagnostico;
use NotasSoap\Aplicacion\Comandos\ConsultarNotaComando;
use NotasSoap\Aplicacion\Comandos\FirmarNotaComando;
use NotasSoap\Presentacion\Entrada\FiltroNotasExpediente;
use NotasSoap\Presentacion\Entrada\FormularioRegistroNota;
use NotasSoap\Presentacion\Http\Identidad;
use NotasSoap\Presentacion\Http\Respuesta;
use NotasSoap\Presentacion\Http\Solicitud;
use NotasSoap\Presentacion\Vistas\SelectorVista;

/**
 * La C del MVC. Cada acción hace tres cosas y nada más:
 *   1) arma el comando (con FormularioRegistroNota cuando hay cuerpo),
 *   2) llama al caso de uso (el Modelo),
 *   3) entrega el resultado a la Vista que pidió el cliente.
 * Sin SQL, sin PDO y sin reglas de negocio (lo comprueba la prueba ARQ-09).
 */
final class NotasSoapController
{
    public function __construct(
        private readonly RegistrarNotaConDiagnostico $registrar,
        private readonly FirmarNota $firmar,
        private readonly ConsultarNota $consultar,
        private readonly ListarNotasExpediente $listar,
        private readonly SelectorVista $vistas,
    ) {
    }

    public function registrar(Solicitud $s, Identidad $quien, string $hospital): Respuesta
    {
        $resultado = $this->registrar->ejecutar(FormularioRegistroNota::comando($s, $quien, $hospital));

        return $this->vistas->para($s)->nota($resultado, 201);
    }

    public function firmar(Solicitud $s, Identidad $quien, string $hospital, string $notaId): Respuesta
    {
        $resultado = $this->firmar->ejecutar(new FirmarNotaComando($quien->usuarioId, $quien->hospitalId, $hospital, $notaId));

        return $this->vistas->para($s)->nota($resultado, 200);
    }

    public function consultar(Solicitud $s, Identidad $quien, string $hospital, string $notaId): Respuesta
    {
        $resultado = $this->consultar->ejecutar(new ConsultarNotaComando($quien->hospitalId, $hospital, $notaId));

        return $this->vistas->para($s)->nota($resultado, 200);
    }

    public function listarDeExpediente(Solicitud $s, Identidad $quien, string $hospital, string $expedienteId): Respuesta
    {
        $resumenes = $this->listar->ejecutar(FiltroNotasExpediente::comando($s, $quien, $hospital, $expedienteId));

        return $this->vistas->para($s)->lista($expedienteId, $resumenes, 200);
    }
}
