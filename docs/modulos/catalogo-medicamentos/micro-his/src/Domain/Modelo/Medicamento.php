<?php
declare(strict_types=1);

namespace Catalogo\Domain\Modelo;

use Catalogo\Domain\Errores\ErrorReglaNegocio;
use Catalogo\Domain\Errores\ErrorValidacion;

/**
 * Una VERSIÓN de un medicamento del catálogo de un hospital. Es inmutable: los cambios
 * producen objetos nuevos (versión cerrada + versión nueva), nunca se edita una versión.
 */
final class Medicamento
{
    public function __construct(
        public readonly string $id,
        public readonly string $hospitalId,
        public readonly CodigoMedicamento $codigo,
        public readonly int $version,
        public readonly FichaMedicamento $ficha,
        public readonly Vigencia $vigencia,
        public readonly string $creadoPor,
        public readonly ?string $motivoCierre = null,
    ) {
    }

    /** CU-01: versión 1 con vigencia abierta desde la fecha indicada. */
    public static function registrar(string $id, string $hospitalId, CodigoMedicamento $codigo, FichaMedicamento $ficha, \DateTimeImmutable $desde, string $usuario): self
    {
        return new self($id, $hospitalId, $codigo, 1, $ficha, new Vigencia($desde), self::usuario($usuario));
    }

    /**
     * CU-03 / RN-01: cierra esta versión el día en que empieza la nueva.
     * @return array{0: self, 1: self} [esta versión cerrada, versión nueva]
     */
    public function nuevaVersion(string $id, FichaMedicamento $ficha, \DateTimeImmutable $desde, string $usuario): array
    {
        $this->exigirAbierta();
        if ($desde <= $this->vigencia->desde) {
            throw new ErrorReglaNegocio('SOLAPE_VIGENCIA', sprintf(
                'La nueva versión debe empezar después del %s, inicio de la versión %d',
                Fecha::texto($this->vigencia->desde),
                $this->version,
            ), ['vigente_desde' => Fecha::texto($this->vigencia->desde)]);
        }
        if ($this->version >= Limites::VERSION_MAX) {
            throw new ErrorReglaNegocio('INTEGRIDAD', 'Se alcanzó el máximo de versiones para este código');
        }
        $cerrada = $this->conVigencia($this->vigencia->cerrarEn($desde), 'Reemplazada por la versión ' . ($this->version + 1));
        $nueva = new self($id, $this->hospitalId, $this->codigo, $this->version + 1, $ficha, new Vigencia($desde), self::usuario($usuario));
        return [$cerrada, $nueva];
    }

    /** CU-04 / RN-02: no se borra; se cierra la vigencia con fecha y motivo. */
    public function darDeBaja(\DateTimeImmutable $fecha, mixed $motivo): self
    {
        $this->exigirAbierta();
        $errores = [];
        if ($fecha <= $this->vigencia->desde) {
            $errores['fecha_baja'] = 'La baja debe ser posterior al ' . Fecha::texto($this->vigencia->desde);
        }
        $texto = is_string($motivo) ? trim($motivo) : '';
        $largo = mb_strlen($texto, 'UTF-8');
        if ($largo < Limites::MOTIVO_MIN || $largo > Limites::MOTIVO_MAX) {
            $errores['motivo'] = sprintf('Use entre %d y %d caracteres', Limites::MOTIVO_MIN, Limites::MOTIVO_MAX);
        }
        if ($errores !== []) {
            throw new ErrorValidacion($errores);
        }
        return $this->conVigencia($this->vigencia->cerrarEn($fecha), $texto);
    }

    public function clave(): ClaveEquivalencia
    {
        return $this->ficha->clave();
    }

    public function vigenteEn(\DateTimeImmutable $fecha): bool
    {
        return $this->vigencia->vigenteEn($fecha);
    }

    private function exigirAbierta(): void
    {
        if (!$this->vigencia->abierta()) {
            throw new ErrorReglaNegocio('YA_DADO_DE_BAJA', sprintf(
                'El medicamento %s ya no tiene una versión abierta (cerrada el %s)',
                $this->codigo->valor,
                Fecha::texto($this->vigencia->hasta),
            ));
        }
    }

    private function conVigencia(Vigencia $vigencia, string $motivo): self
    {
        return new self($this->id, $this->hospitalId, $this->codigo, $this->version, $this->ficha, $vigencia, $this->creadoPor, $motivo);
    }

    private static function usuario(string $usuario): string
    {
        $u = trim($usuario);
        if (mb_strlen($u, 'UTF-8') < 3 || mb_strlen($u, 'UTF-8') > Limites::USUARIO_MAX) {
            throw new ErrorValidacion(['usuario' => 'Usuario inválido']);
        }
        return $u;
    }
}
