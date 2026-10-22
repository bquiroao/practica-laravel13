<?php

declare(strict_types=1);

namespace Pacientes\Dominio\Servicios;

use Pacientes\Dominio\Excepciones\ErrorDominio;
use Pacientes\Dominio\Modelo\Paciente;
use Pacientes\Dominio\Puertos\IndiceDuplicados;

/** R-02 y R-03: el mismo documento bloquea; nombre y fecha iguales advierten. */
final class PoliticaDuplicados
{
    /** Sustituye el código de un paciente protegido para quien no puede verlo (R-11). */
    public const CODIGO_RESERVADO = 'RESERVADO';

    public function __construct(private readonly IndiceDuplicados $indice)
    {
    }

    /**
     * @param bool $puedeConfirmar el actor tiene pacientes.confirmar_duplicado
     * @param bool $puedeVerProtegidos el actor tiene pacientes.leer_protegido
     * @return list<string> coincidencias que el supervisor confirmó como otra persona
     * @throws ErrorDominio
     */
    public function verificarRegistro(Paciente $paciente, bool $confirma, bool $puedeConfirmar, string $motivo, bool $puedeVerProtegidos): array
    {
        if ($paciente->identificador !== null) {
            $titular = $this->indice->titularDe($paciente->identificador);
            if ($titular !== null) {
                $codigo = $this->visibles([$titular], $puedeVerProtegidos)[0];
                // De un protegido no se confirma ningún dato, ni siquiera el documento enmascarado.
                throw new ErrorDominio(ErrorDominio::CONFLICTO, 'PACIENTE_EXISTENTE', $codigo === self::CODIGO_RESERVADO
                    ? ['codigo' => $codigo]
                    : ['codigo' => $codigo, 'identificador' => $paciente->identificador->enmascarado()]);
            }
        }

        $coincidencias = $this->indice->coincidenciasProbables($paciente->nombreNormalizado(), $paciente->nacimiento);
        if ($coincidencias === []) {
            return [];
        }
        if (!$confirma) {
            throw new ErrorDominio(ErrorDominio::CONFLICTO, 'POSIBLE_DUPLICADO', ['candidatos' => $this->visibles($coincidencias, $puedeVerProtegidos)]);
        }
        if (!$puedeConfirmar) {
            throw new ErrorDominio(ErrorDominio::PROHIBIDO, 'ACCESO_DENEGADO');
        }
        if (trim($motivo) === '') {
            throw new ErrorDominio(ErrorDominio::INVALIDO, 'MOTIVO_REQUERIDO');
        }

        return $coincidencias;
    }

    /** R-02 al editar: el documento nuevo no puede pertenecer a otro paciente (no revela cuál). */
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

    /**
     * @param list<string> $codigos
     * @return list<string>
     */
    private function visibles(array $codigos, bool $puedeVerProtegidos): array
    {
        if ($puedeVerProtegidos) {
            return $codigos;
        }
        $protegidos = $this->indice->protegidos($codigos);

        return array_map(static fn (string $c): string => in_array($c, $protegidos, true) ? self::CODIGO_RESERVADO : $c, $codigos);
    }
}
