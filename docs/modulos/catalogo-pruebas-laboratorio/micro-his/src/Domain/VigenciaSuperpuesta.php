<?php
declare(strict_types=1);

namespace Lab\Domain;

/** RN-05: la nueva versión no empieza después de la vigente (409). */
final class VigenciaSuperpuesta extends \DomainException
{
}
