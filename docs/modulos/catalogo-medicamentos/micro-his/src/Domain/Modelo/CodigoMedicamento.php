<?php
declare(strict_types=1);

namespace Catalogo\Domain\Modelo;

use Catalogo\Domain\Errores\ErrorValidacion;

/** Código interno del hospital (RN-06): MED-NNNNNN. No cambia entre versiones. */
final class CodigoMedicamento
{
    private function __construct(public readonly string $valor)
    {
    }

    public static function desdeTexto(mixed $valor): self
    {
        $texto = is_string($valor) ? strtoupper(trim($valor)) : '';
        if (preg_match('/^MED-\d{6}$/', $texto) !== 1) {
            throw new ErrorValidacion(['codigo' => 'El código debe tener el formato MED-000000']);
        }
        return new self($texto);
    }

    public static function desdeNumero(int $numero): self
    {
        if ($numero < 1 || $numero > 999999) {
            throw new \RangeException('Se agotó la numeración de códigos del hospital');
        }
        return new self(sprintf('MED-%06d', $numero));
    }

    public function numero(): int
    {
        return (int) substr($this->valor, 4);
    }

    public function igualA(self $otro): bool
    {
        return $this->valor === $otro->valor;
    }
}
