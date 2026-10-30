<?php
declare(strict_types=1);

namespace Catalogo\Persistence;

use Catalogo\Domain\Modelo\CodigoMedicamento;
use Catalogo\Domain\Modelo\Fecha;
use Catalogo\Domain\Modelo\FichaMedicamento;
use Catalogo\Domain\Modelo\Medicamento;
use Catalogo\Domain\Puertos\GeneradorId;
use Catalogo\Domain\Repositorio\EscrituraCatalogo;

/**
 * Datos de ejemplo FICTICIOS: hospitales, códigos, registros sanitarios, fechas y referencias de
 * prescripción son inventados. Los nombres son genéricos conocidos; no es información clínica.
 * Se cargan pasando por el dominio, así que cumplen las mismas reglas que un alta real.
 */
final class DatosEjemplo
{
    public const NORTE = '11111111-1111-4111-8111-111111111111';
    public const SUR = '22222222-2222-4222-8222-222222222222';
    /** Códigos usados en prescripciones ficticias (para RN-02). */
    public const REFERENCIAS = [[self::NORTE, 'MED-000101', 'PRE-000231']];

    public static function cargar(EscrituraCatalogo $repo, GeneradorId $ids): void
    {
        $alta = function (string $hospital, int $numero, array $d, string $desde) use ($repo, $ids): Medicamento {
            $m = Medicamento::registrar($ids->nuevo(), $hospital, CodigoMedicamento::desdeNumero($numero), self::ficha($d), Fecha::desdeTexto('d', $desde), 'carga.ejemplo');
            $repo->agregar($m);
            return $m;
        };

        $alta(self::NORTE, 101, ['Paracetamol 500 mg tableta', 'Paracetamol', '500', 'mg', 'TABLETA', 'ORAL', 'Caja con 100 tabletas', 'RS-FIC-0101'], '2026-01-01');
        $alta(self::NORTE, 102, ['Amoxicilina 500 mg cápsula', 'Amoxicilina', '500', 'mg', 'CAPSULA', 'ORAL', 'Frasco con 21 cápsulas', 'RS-FIC-0102'], '2026-01-01');
        $alta(self::NORTE, 103, ['Amoxicilina 250 mg/5 mL suspensión', 'Amoxicilina', '250', 'mg/5mL', 'SUSPENSION', 'ORAL', 'Frasco de 100 mL', 'RS-FIC-0103'], '2026-01-01');

        $ibu = $alta(self::NORTE, 104, ['Ibuprofeno 400 mg tableta', 'Ibuprofeno', '400', 'mg', 'TABLETA', 'ORAL', 'Caja con 20 tabletas', 'RS-FIC-0104'], '2026-01-01');
        [$cerrada, $v2] = $ibu->nuevaVersion($ids->nuevo(), self::ficha(['Ibuprofeno 400 mg tableta', 'Ibuprofeno', '400', 'mg', 'TABLETA', 'ORAL', 'Caja con 30 tabletas', 'RS-FIC-0104']), Fecha::desdeTexto('d', '2026-07-01'), 'carga.ejemplo');
        $repo->reemplazarVigente($cerrada, $v2);

        $ome = $alta(self::NORTE, 105, ['Omeprazol 20 mg cápsula', 'Omeprazol', '20', 'mg', 'CAPSULA', 'ORAL', 'Caja con 14 cápsulas', 'RS-FIC-0105'], '2026-01-01');
        $repo->cerrar($ome->darDeBaja(Fecha::desdeTexto('d', '2026-09-01'), 'Sustituido en el formulario (dato ficticio)'));

        $alta(self::NORTE, 106, ['Cloruro de sodio 0,9 % solución inyectable', 'Cloruro de sodio', '0.9', '%', 'SOLUCION_INYECTABLE', 'INTRAVENOSA', 'Bolsa de 500 mL', 'RS-FIC-0106'], '2026-01-01');

        $alta(self::SUR, 101, ['Paracetamol 500 mg tableta', 'Paracetamol', '500', 'mg', 'TABLETA', 'ORAL', 'Caja con 50 tabletas', 'RS-FIC-2101'], '2026-02-01');
    }

    /** @param list<string> $d nombre, principio, valor, unidad, forma, vía, presentación, registro */
    private static function ficha(array $d): FichaMedicamento
    {
        [$ficha, $errores] = FichaMedicamento::validar(array_combine(
            ['nombre', 'principio_activo', 'concentracion_valor', 'concentracion_unidad', 'forma_farmaceutica', 'via_administracion', 'presentacion', 'registro_sanitario'],
            $d,
        ));
        return $ficha ?? throw new \LogicException('Dato de ejemplo inválido: ' . json_encode($errores));
    }
}
