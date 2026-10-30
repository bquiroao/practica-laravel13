<?php
declare(strict_types=1);

namespace Catalogo\Domain\Modelo;

/**
 * Límites de RN-05 en un solo lugar. El script de esquema usa estas mismas constantes para las
 * columnas, y una prueba compara la base real contra ellas: un dato inválido da 422, no 500.
 */
final class Limites
{
    public const CODIGO = 10;            // MED-NNNNNN
    public const NOMBRE_MIN = 3;
    public const NOMBRE_MAX = 150;
    public const PRINCIPIO_MIN = 3;
    public const PRINCIPIO_MAX = 120;
    public const CONCENTRACION_ENTEROS = 6;   // numeric(9,3)
    public const CONCENTRACION_DECIMALES = 3;
    public const UNIDAD_MAX = 10;
    public const FORMA_MAX = 30;
    public const VIA_MAX = 20;
    public const PRESENTACION_MIN = 3;
    public const PRESENTACION_MAX = 120;
    public const REGISTRO_SANITARIO_MAX = 30;
    public const MOTIVO_MIN = 5;
    public const MOTIVO_MAX = 200;
    public const USUARIO_MAX = 60;
    public const CLAVE_MAX = 200;
    public const VERSION_MAX = 999;
    public const FECHA_MIN = '2000-01-01';
    public const FECHA_MAX = '2099-12-31';
    public const BUSQUEDA_MIN = 2;
    public const BUSQUEDA_MAX = 60;
    public const RESULTADOS_MAX = 50;
}
