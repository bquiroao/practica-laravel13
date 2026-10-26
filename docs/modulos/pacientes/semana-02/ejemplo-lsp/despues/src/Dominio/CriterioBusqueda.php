<?php

declare(strict_types=1);

namespace Pacientes\Dominio;

/**
 * Criterio de búsqueda ya validado (R-10): código, documento completo o nombre (3 letras
 * o más) con fecha de nacimiento opcional. La coincidencia vive aquí para que TODAS
 * las implementaciones de ConsultaPacientes busquen igual.
 */
final class CriterioBusqueda
{
    public const LIMITE = 20;

    private function __construct(
        private readonly ?string $codigo,
        private readonly ?string $claveIdentificador,
        private readonly ?string $nombre,
        private readonly ?string $nacimiento,
    ) {
    }

    /** @throws ErrorPaciente 422 CRITERIO_INSUFICIENTE o DATOS_INVALIDOS */
    public static function desde(?string $codigo = null, ?string $tipoDocumento = null, ?string $numeroDocumento = null,
        ?string $paisDocumento = null, ?string $nombre = null, ?string $nacimiento = null): self
    {
        $codigo = strtoupper(trim((string) $codigo));
        if ($codigo !== '') {
            return new self($codigo, null, null, null);
        }

        try {
            $identificador = FabricaIdentificador::crear($tipoDocumento, $numeroDocumento, $paisDocumento);
        } catch (\InvalidArgumentException $e) {
            throw new ErrorPaciente(422, 'DATOS_INVALIDOS', ['campos' => ['documento' => $e->getMessage()]]);
        }
        if ($identificador !== null) {
            return new self(null, $identificador->claveBusqueda(), null, null);
        }

        $nombre = Texto::normalizar((string) $nombre);
        $nacimiento = trim((string) $nacimiento);
        if (Texto::contarLetras($nombre) < 3) {
            throw new ErrorPaciente(422, 'CRITERIO_INSUFICIENTE', ['ayuda' => 'Use el código, el documento completo o al menos 3 letras del nombre.']);
        }
        if ($nacimiento !== '' && preg_match('/^\d{4}(-\d{2}(-\d{2})?)?$/', $nacimiento) !== 1) {
            throw new ErrorPaciente(422, 'DATOS_INVALIDOS', ['campos' => ['nacimiento' => 'Use AAAA, AAAA-MM o AAAA-MM-DD.']]);
        }

        return new self(null, null, $nombre, $nacimiento === '' ? null : $nacimiento);
    }

    public function coincideCon(Paciente $paciente): bool
    {
        if ($this->codigo !== null) {
            return $paciente->codigo === $this->codigo;
        }
        if ($this->claveIdentificador !== null) {
            return $paciente->identificador?->claveBusqueda() === $this->claveIdentificador;
        }

        return str_contains($paciente->nombreNormalizado(), (string) $this->nombre)
            && ($this->nacimiento === null || str_starts_with($paciente->nacimiento->format('Y-m-d'), $this->nacimiento));
    }

    /** Resumen para la auditoría: QUÉ tipo de criterio se usó, sin datos personales en claro. */
    public function resumenAuditable(): string
    {
        return match (true) {
            $this->codigo !== null => 'codigo',
            $this->claveIdentificador !== null => 'documento',
            default => sprintf('nombre(%d letras)%s', Texto::contarLetras((string) $this->nombre), $this->nacimiento !== null ? '+nacimiento' : ''),
        };
    }
}
