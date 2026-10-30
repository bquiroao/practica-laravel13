<?php
declare(strict_types=1);

namespace AlertasCriticas\Domain;

/** Contrato de error de persistencia: la aplicación no conoce PDO ni SQL. */
final class ErrorPersistenciaException extends \RuntimeException {}
