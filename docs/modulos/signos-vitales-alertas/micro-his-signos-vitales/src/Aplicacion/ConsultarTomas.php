<?php
declare(strict_types=1);

// CU-05: enfermería y médico consultan las tomas del episodio (solo lectura).
namespace SignosVitales\Aplicacion;

use SignosVitales\Dominio\ErrorDominio;

final class ConsultarTomas {
    public function __construct(private RepositorioTomas $tomas, private FuenteClinica $clinica) {}

    public function ejecutar(string $pacienteId, string $episodioId, Usuario $usuario): array {
        if (!in_array($usuario->rol, ['enfermeria', 'medico'], true)) { throw new ErrorDominio('acceso_denegado', 'Rol sin acceso a signos vitales'); }
        exigirEpisodio($this->clinica, $pacienteId, $episodioId, $usuario);
        return $this->tomas->deEpisodio($episodioId);
    }
}
