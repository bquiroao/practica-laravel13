<?php

declare(strict_types=1);

require_once __DIR__ . '/src/Domain/ReceptionDecision.php';
require_once __DIR__ . '/src/Application/ReceptionPorts.php';
require_once __DIR__ . '/src/Application/ReceiveSpecimen.php';
require_once __DIR__ . '/src/Infrastructure/Identity/RandomSpecimenIdentifierGenerator.php';
require_once __DIR__ . '/src/Persistence/PdoSpecimenRepository.php';
require_once __DIR__ . '/src/Presentation/Cli/ReceiveSpecimenCommand.php';
