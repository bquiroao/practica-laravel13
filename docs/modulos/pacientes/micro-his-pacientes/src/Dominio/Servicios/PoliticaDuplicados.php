<?php

declare(strict_types=1);

namespace Pacientes\Dominio\Servicios;

use Pacientes\Dominio\Excepciones\ErrorDominio;
use Pacientes\Dominio\Modelo\Paciente;
use Pacientes\Dominio\Puertos\IndiceDuplicados;

/** R-02 y R-03: el mismo documento bloquea; nombre y fecha iguales advierten. */
final class PoliticaDuplicados
{
    public function __construct(private readonly IndiceDuplicados $indice)
    {
    }

    /**
     * @param bool $puedeConfirmar el actor tiene pacientes.confirmar_duplicado
     * @return list<string> coincidencias que el supervisor confirmó como otra persona
     * @throws ErrorDominio
     */
    public function verificarRegistro(Paciente $paciente, bool $confirma, bool $puedeConfirmar, string $motivo): array
    {
        if ($paciente->identificador !== null) {
            $titular = $this->indice->titularDe($paciente->identificador);
            if ($titular !== null) {
                throw new ErrorDominio(ErrorDominio::CONFLICTO, 'PACIENTE_EXISTENTE', [
                    'codigo' => $titular,
                    'identificador' => $paciente->identificador->enmascarado(),
                ]);
            }
        }

        $coincidencias = $this->indice->coincidenciasProbables($paciente->nombreNormalizado(), $paciente->nacimiento);
        if ($coincidencias === []) {
            return [];
        }
        if (!$confirma) {
            throw new ErrorDominio(ErrorDominio::CONFLICTO, 'POSIBLE_DUPLICADO', ['candidatos' => $coincidencias]);
        }
        if (!$puedeConfirmar) {
            throw new ErrorDominio(ErrorDominio::PROHIBIDO, 'ACCESO_DENEGADO');
        }
        if (trim($motivo) === '') {
            throw new ErrorDominio(ErrorDominio::INVALIDO, 'MOTIVO_REQUERIDO');
        }

        return $coincidencias;
    }

    /** R-02 al editar: el documento nuevo no puede pertenecer a otro paciente. */
    public function verificarDocumentoNuevo(Paciente $actual, Paciente $nuevo): void
    {
        if ($nuevo->identificador === null) {
            return;
        }
        $titular = $this->indice->titularDe($nuevo->identificador);
        if ($titular !== null && $titular !== $actual->codigo) {
            throw new ErrorDominio(ErrorDominio::CONFLICTO, 'IDENTIFICADOR_EN_USO', ['identificador' => $nuevo->identificador->enmascarado()]);
        }
    }
}
