<?php

declare(strict_types=1);

/*
 * Diseño ANTES de aplicar LSP (semana 2). Una sola jerarquía «Evidencia» para todo lo que se adjunta a un resultado.
 * Contrato documentado de la clase base (lo que los clientes asumen):
 *   - contenido(): los bytes que el tester adjuntó; no lanza y no cambia entre llamadas;
 *   - hash():      sha256 de ese contenido (sirve para probar que la evidencia no cambió);
 *   - tamano():    bytes del contenido.
 * Dos subclases rompen ese contrato, y los clientes (ServicioCampanaAntes) no lo saben.
 */

namespace QaAntes;

abstract class Evidencia
{
    abstract public function tipo(): string;

    /** Los bytes que el tester adjuntó. */
    abstract public function contenido(): string;

    /** sha256 del contenido: si cambia el contenido, cambia el hash. */
    public function hash(): string
    {
        return hash('sha256', $this->contenido());
    }

    public function tamano(): int
    {
        return strlen($this->contenido());
    }
}

final class Captura extends Evidencia
{
    public function __construct(private readonly string $bytes)
    {
    }

    public function tipo(): string
    {
        return 'captura';
    }

    public function contenido(): string
    {
        return $this->bytes;
    }
}

final class RegistroTexto extends Evidencia
{
    public function __construct(private readonly string $texto)
    {
    }

    public function tipo(): string
    {
        return 'registro';
    }

    public function contenido(): string
    {
        return $this->texto;
    }
}

/**
 * Enlace al log del pipeline de CI. «Descargar es lento», así que hash() se SOBRESCRIBE con el hash de la URL.
 * Viola LSP: debilita la postcondición de hash() (ya no identifica el contenido) y contenido() cambia entre llamadas
 * cuando el log remoto rota.
 */
final class EnlaceExterno extends Evidencia
{
    /** @param \Closure(string): string $descargar */
    public function __construct(private readonly string $url, private readonly \Closure $descargar)
    {
    }

    public function tipo(): string
    {
        return 'enlace';
    }

    public function contenido(): string
    {
        return ($this->descargar)($this->url);
    }

    public function hash(): string
    {
        return hash('sha256', $this->url);
    }
}

final class AccesoDenegado extends \RuntimeException
{
}

/**
 * Captura que muestra datos de un paciente. Para protegerlos, contenido() LANZA si el usuario no tiene permiso.
 * Viola LSP: agrega una excepción que la base no declara (refuerza la precondición): todo cliente que llama a
 * contenido(), hash() o tamano() sin saber de esta subclase se rompe.
 */
final class CapturaConDatosClinicos extends Evidencia
{
    public static bool $usuarioConPermiso = false;

    public function __construct(private readonly string $bytes)
    {
    }

    public function tipo(): string
    {
        return 'captura';
    }

    public function contenido(): string
    {
        if (!self::$usuarioConPermiso) {
            throw new AccesoDenegado('La captura contiene datos clínicos.');
        }

        return $this->bytes;
    }
}

/** Cliente del ANTES: registra resultados, verifica la integridad y arma el informe de la campaña. */
final class ServicioCampanaAntes
{
    /** @var list<array{caso: string, resultado: string, evidencias: list<array{evidencia: Evidencia, hash: string}>}> */
    public array $intentos = [];

    /** @param list<Evidencia> $evidencias */
    public function registrar(string $caso, string $resultado, array $evidencias): void
    {
        if (in_array($resultado, ['FALLA', 'BLOQUEADO'], true) && $evidencias === []) {
            throw new \DomainException('EVIDENCIA_REQUERIDA');
        }
        $this->intentos[] = ['caso' => $caso, 'resultado' => $resultado,
            'evidencias' => array_map(static fn (Evidencia $e): array => ['evidencia' => $e, 'hash' => $e->hash()], $evidencias)];
    }

    /** ¿La evidencia sigue siendo la que se registró? Recalcula el hash y lo compara con el guardado. */
    public function verificarIntegridad(int $intento): string
    {
        foreach ($this->intentos[$intento]['evidencias'] as ['evidencia' => $e, 'hash' => $guardado]) {
            if ($e->hash() !== $guardado) {
                return 'ALTERADA';
            }
        }

        return 'INTEGRA';
    }

    /** Informe de la campaña: una línea por intento con el tipo y el tamaño de cada evidencia. */
    public function informe(): string
    {
        $lineas = [];
        foreach ($this->intentos as $i) {
            $lineas[] = $i['caso'] . ' ' . $i['resultado'] . ' ' . implode(', ', array_map(
                static fn (array $x): string => $x['evidencia']->tipo() . ' ' . $x['evidencia']->tamano() . ' B', $i['evidencias']));
        }

        return implode("\n", $lineas);
    }
}
