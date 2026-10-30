<?php
declare(strict_types=1);

use Catalogo\Domain\Modelo\CodigoMedicamento;
use Catalogo\Domain\Modelo\Fecha;
use Catalogo\Domain\Modelo\FichaMedicamento;
use Catalogo\Domain\Modelo\Medicamento;

/** Datos ficticios para pruebas. Mismos hospitales y medicamentos que la semana 1 (sección 9). */
final class Fabrica
{
    public const NORTE = '11111111-1111-4111-8111-111111111111';
    public const SUR = '22222222-2222-4222-8222-222222222222';
    public const HOY = '2026-10-15';

    /** @return array<string, mixed> datos válidos de Amoxicilina 500 mg cápsula oral */
    public static function datos(array $cambios = []): array
    {
        return $cambios + [
            'nombre' => 'Amoxicilina 500 mg cápsula',
            'principio_activo' => 'Amoxicilina',
            'concentracion_valor' => '500',
            'concentracion_unidad' => 'mg',
            'forma_farmaceutica' => 'CAPSULA',
            'via_administracion' => 'ORAL',
            'presentacion' => 'Frasco con 21 cápsulas',
            'registro_sanitario' => 'RS-FIC-0102',
            'vigente_desde' => '2026-01-01',
        ];
    }

    public static function ficha(array $cambios = []): FichaMedicamento
    {
        [$ficha, $errores] = FichaMedicamento::validar(self::datos($cambios));
        if ($ficha === null) {
            throw new LogicException('Datos de fábrica inválidos: ' . json_encode($errores));
        }
        return $ficha;
    }

    public static function medicamento(string $codigo = 'MED-000102', string $desde = '2026-01-01', array $cambios = [], string $hospital = self::NORTE): Medicamento
    {
        return Medicamento::registrar(self::uuid(), $hospital, CodigoMedicamento::desdeTexto($codigo), self::ficha($cambios), Fecha::desdeTexto('d', $desde), 'qf.norte');
    }

    public static function fecha(string $texto): DateTimeImmutable
    {
        return Fecha::desdeTexto('fecha', $texto);
    }

    public static function uuid(): string
    {
        $b = random_bytes(16);
        $b[6] = chr((ord($b[6]) & 0x0f) | 0x40);
        $b[8] = chr((ord($b[8]) & 0x3f) | 0x80);
        return vsprintf('%s%s-%s-%s-%s-%s%s%s', str_split(bin2hex($b), 4));
    }
}
