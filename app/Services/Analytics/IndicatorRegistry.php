<?php

namespace App\Services\Analytics;

use App\Exceptions\DomainRuleException;
use App\Services\Analytics\Indicators\AdmissionIndicator;
use App\Services\Analytics\Indicators\AllergyIndicator;
use App\Services\Analytics\Indicators\AppointmentIndicator;
use App\Services\Analytics\Indicators\CancellationIndicator;
use App\Services\Analytics\Indicators\CriticalLabResultIndicator;
use App\Services\Analytics\Indicators\DiagnosisIndicator;
use App\Services\Analytics\Indicators\DischargeIndicator;
use App\Services\Analytics\Indicators\DoctorProductivityIndicator;
use App\Services\Analytics\Indicators\HighRiskAllergyIndicator;
use App\Services\Analytics\Indicators\LabSectionIndicator;
use App\Services\Analytics\Indicators\LabTestIndicator;
use App\Services\Analytics\Indicators\LengthOfStayIndicator;
use App\Services\Analytics\Indicators\MortalityIndicator;
use App\Services\Analytics\Indicators\OccupancyIndicator;
use App\Services\Analytics\Indicators\PatientIndicator;
use App\Services\Analytics\Indicators\PrescriptionGroupIndicator;
use App\Services\Analytics\Indicators\PrescriptionIndicator;
use App\Services\Analytics\Indicators\VitalSignIndicator;
use Illuminate\Contracts\Container\Container;

/** Módulo reportes y analytics básicos: entrega el indicador según su clave, sin conocer las clases concretas. */
class IndicatorRegistry
{
    /** Un indicador nuevo es una línea aquí y su clase (RNF-05). El orden es el de las tarjetas. */
    private const INDICATORS = [
        OccupancyIndicator::class,
        LengthOfStayIndicator::class,
        AdmissionIndicator::class,
        DischargeIndicator::class,
        MortalityIndicator::class,
        AppointmentIndicator::class,
        CancellationIndicator::class,
        DoctorProductivityIndicator::class,
        PatientIndicator::class,
        DiagnosisIndicator::class,
        VitalSignIndicator::class,
        AllergyIndicator::class,
        HighRiskAllergyIndicator::class,
        LabTestIndicator::class,
        LabSectionIndicator::class,
        CriticalLabResultIndicator::class,
        PrescriptionIndicator::class,
        PrescriptionGroupIndicator::class,
    ];

    /**
     * Indicadores que necesitan datos que todavía no genera el sistema. Se muestran en la pantalla para que se sepa
     * qué módulo los habilita; al integrarse, se agregan como clase en INDICATORS.
     */
    public const PENDING = [
        ['nombre' => 'Alertas críticas reconocidas a tiempo', 'area' => 'Clínico', 'modulo' => 'alertas-criticas-notificaciones-internas',
            'descripcion' => 'Porcentaje de alertas críticas que el personal reconoció dentro del tiempo de escalamiento.'],
        ['nombre' => 'Tiempo de espera de cama', 'area' => 'Hospitalización', 'modulo' => 'admision-hospitalaria-asignacion-cama',
            'descripcion' => 'Horas entre la orden de ingreso y la asignación de una cama disponible.'],
    ];

    /** @var array<string, Indicator> */
    private array $indicators = [];

    public function __construct(Container $container)
    {
        foreach (self::INDICATORS as $class) {
            $indicator = $container->make($class);
            $this->indicators[$indicator->key()] = $indicator;
        }
    }

    /** @return array<string, Indicator> */
    public function all(): array
    {
        return $this->indicators;
    }

    public function get(string $key): Indicator
    {
        return $this->indicators[$key] ?? throw new DomainRuleException('REPORTE_NO_EXISTE', 'Ese indicador no existe. Elija uno de la lista.', 404);
    }
}
