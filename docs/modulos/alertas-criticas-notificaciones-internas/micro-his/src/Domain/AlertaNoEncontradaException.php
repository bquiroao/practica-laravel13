<?php
declare(strict_types=1);

namespace AlertasCriticas\Domain;

/** La alerta no existe en el tenant del usuario (RNF-01: otro tenant se trata como inexistente). */
final class AlertaNoEncontradaException extends \RuntimeException {}
