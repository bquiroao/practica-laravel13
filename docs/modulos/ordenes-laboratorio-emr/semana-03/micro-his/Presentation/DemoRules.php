<?php

declare(strict_types=1);
namespace OrdersWeek3\Presentation;
use OrdersWeek3\Domain\ClinicalRules;
/** Doble de composición: no sustituye autenticación ni autorización real. */
final class DemoRules implements ClinicalRules
{
    public function authorized(string $actor,string $patient,string $encounter): bool {return $actor==='MED-DEMO'&&$patient==='PAC-DEMO'&&in_array($encounter,['EPI-DEMO','EPI-INVALID'],true);}
    public function contextValid(string $patient,string $encounter): bool {return $patient==='PAC-DEMO'&&$encounter==='EPI-DEMO';}
    public function testsAvailable(array $codes): bool {foreach($codes as $c)if(!in_array($c,['TEST-A','TEST-B'],true))return false;return true;}
    public function initialStatus(): string {return 'TEST_INITIAL';}
}