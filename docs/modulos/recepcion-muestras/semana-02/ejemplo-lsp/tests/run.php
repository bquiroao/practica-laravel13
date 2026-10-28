<?php

declare(strict_types=1);

require __DIR__ . '/../src/SpecimenAcceptance.php';

$assertions = 0;

$assert = static function (bool $condition, string $message) use (&$assertions): void {
    $assertions++;

    if (!$condition) {
        throw new RuntimeException("FAIL: {$message}");
    }

    echo "PASS: {$message}\n";
};

$general = new GeneralAcceptancePolicy();
$blood = new BloodTubeAcceptancePolicy();
$policies = [$general, $blood];
$urine = new Specimen('S-URINE-001', SpecimenType::URINE, true, true, false);
$sealedBlood = new Specimen('S-BLOOD-001', SpecimenType::BLOOD, true, true, true);
$unsealedBlood = new Specimen('S-BLOOD-002', SpecimenType::BLOOD, true, true, false);
$unlabelledBlood = new Specimen('S-BLOOD-003', SpecimenType::BLOOD, false, true, true);
$unlabelled = new Specimen('S-SWAB-001', SpecimenType::SWAB, false, true, false);
$damaged = new Specimen('S-SWAB-002', SpecimenType::SWAB, true, false, false);

foreach ($policies as $policy) {
    $decision = $policy->evaluate($urine);
    $assert(
        ($decision->accepted && $decision->reasons === [])
            || (!$decision->accepted && (!$decision->applicable || $decision->reasons !== [])),
        $policy::class . ' devuelve un resultado conforme al contrato para orina',
    );
}

$assert(
    !$blood->evaluate($urine)->applicable,
    'la política especializada indica explícitamente que no aplica a orina',
);
$generalService = new ReceptionDecisionService([$general]);
$combinedService = new ReceptionDecisionService([$general, $blood]);
$assert(
    $combinedService->decide($urine) == $generalService->decide($urine),
    'agregar la política de sangre conserva la decisión general para orina',
);
$assert($combinedService->decide($sealedBlood)->accepted, 'acepta sangre identificada, íntegra y sellada');
$missingSeal = $combinedService->decide($unsealedBlood);
$assert(!$missingSeal->accepted && str_contains(implode(' ', $missingSeal->reasons), 'sello'), 'rechaza sangre con sello no íntegro y explica la razón');
$missingLabel = $combinedService->decide($unlabelledBlood);
$assert(!$missingLabel->accepted && str_contains(implode(' ', $missingLabel->reasons), 'identificación'), 'la regla general sigue rechazando sangre sin etiqueta');
$assert(!$general->evaluate($unlabelled)->accepted, 'rechaza una muestra sin etiqueta con la regla general');
$assert(!$general->evaluate($damaged)->accepted, 'rechaza un recipiente dañado con la política general');

$serviceDecision = $combinedService->decide($unsealedBlood);
$assert(!$serviceDecision->accepted, 'el servicio consolida el rechazo de una política');
$unsupported = (new ReceptionDecisionService([$blood]))->decide($urine);
$assert(!$unsupported->accepted, 'sin regla aplicable el servicio rechaza por defecto');

echo "OK: {$assertions} aserciones.\n";
