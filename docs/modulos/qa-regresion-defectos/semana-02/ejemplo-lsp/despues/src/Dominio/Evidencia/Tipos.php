<?php

declare(strict_types=1);

namespace Qa\Dominio\Evidencia;

enum Clasificacion: string
{
    case GENERAL = 'GENERAL';
    case DATOS_CLINICOS = 'DATOS_CLINICOS';
}

final class EvidenciaInvalida extends \InvalidArgumentException
{
    public function __construct(public readonly string $codigo)
    {
        parent::__construct($codigo);
    }
}

/** Captura de pantalla: PNG o JPEG, reconocidos por su firma. */
final class Captura extends EvidenciaInmutable
{
    public function __construct(string $bytes, Clasificacion $clasificacion = Clasificacion::GENERAL)
    {
        if (!str_starts_with($bytes, "\x89PNG\r\n\x1a\n") && !str_starts_with($bytes, "\xFF\xD8\xFF")) {
            throw new EvidenciaInvalida('TIPO_NO_PERMITIDO');
        }
        parent::__construct($bytes, $clasificacion);
    }

    public function tipo(): string
    {
        return 'captura';
    }
}

/** Registro de texto (log, salida de consola): UTF-8 válido y sin bytes nulos. */
final class RegistroTexto extends EvidenciaInmutable
{
    public function __construct(string $texto, Clasificacion $clasificacion = Clasificacion::GENERAL)
    {
        if (!mb_check_encoding($texto, 'UTF-8') || str_contains($texto, "\0")) {
            throw new EvidenciaInvalida('TIPO_NO_PERMITIDO');
        }
        parent::__construct($texto, $clasificacion);
    }

    public function tipo(): string
    {
        return 'registro';
    }
}

/**
 * Resultado de una suite automática en formato JUnit XML (PHPUnit lo genera con --log-junit). Agrega resumen():
 * es una EXTENSIÓN del contrato, no un cambio; quien no la conoce lo usa como cualquier otra evidencia.
 */
final class ResultadoJUnit extends EvidenciaInmutable
{
    /** @var array{pruebas: int, fallas: int, errores: int} */
    private readonly array $resumen;

    public function __construct(string $xml, Clasificacion $clasificacion = Clasificacion::GENERAL)
    {
        $anterior = libxml_use_internal_errors(true);
        $doc = simplexml_load_string($xml);
        libxml_use_internal_errors($anterior);
        $raiz = $doc === false ? null : ($doc->getName() === 'testsuites' ? ($doc->testsuite[0] ?? $doc) : $doc);
        if ($raiz === null || !isset($raiz['tests'])) {
            throw new EvidenciaInvalida('TIPO_NO_PERMITIDO');
        }
        $this->resumen = ['pruebas' => (int) $raiz['tests'], 'fallas' => (int) ($raiz['failures'] ?? 0), 'errores' => (int) ($raiz['errors'] ?? 0)];
        parent::__construct($xml, $clasificacion);
    }

    public function tipo(): string
    {
        return 'junit';
    }

    /** @return array{pruebas: int, fallas: int, errores: int} */
    public function resumen(): array
    {
        return $this->resumen;
    }
}

/**
 * Enlace a algo que vive en otro sistema (un build de CI, un ticket). NO es una evidencia: su contenido puede cambiar
 * y nadie lo puede volver a comprobar. Se adjunta como contexto y nunca cuenta para exigir evidencia (R-05).
 */
final class ReferenciaExterna
{
    public function __construct(public readonly string $url, public readonly string $descripcion)
    {
        if (!preg_match('#^https://\S+$#', $url)) {
            throw new EvidenciaInvalida('URL_INVALIDA');
        }
    }
}
