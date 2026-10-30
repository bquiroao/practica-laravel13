<?php
declare(strict_types=1);

namespace Catalogo\Domain\Errores;

/**
 * El almacenamiento no respondió. Lo lanzan los adaptadores; la presentación lo traduce a 503
 * sin mostrar el detalle técnico (RNF-08). La causa queda en getPrevious() para el registro.
 */
final class ErrorPersistencia extends ErrorDominio
{
    public function __construct(?\Throwable $previa = null)
    {
        parent::__construct('PERSISTENCIA', 'No se pudo acceder al catálogo. Intente de nuevo.', [], $previa);
    }
}
