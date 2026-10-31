<?php

namespace App\Console\Commands;

use App\Models\Central\Hospital;
use App\Services\Alerts\CriticalAlertService;
use App\Services\Hospital\HospitalDatabase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

/**
 * Módulo alertas críticas y notificaciones internas (CU-05): revisa en la base de cada hospital las alertas sin acuse
 * con el plazo vencido y las escala. Lo corre el planificador cada minuto (routes/console.php).
 */
class EscalateCriticalAlerts extends Command
{
    protected $signature = 'nido:alertas-escalar {--hospital= : Solo el hospital con este código}';

    protected $description = 'Escala las alertas críticas sin acuse cuyo plazo venció, en cada hospital activo';

    public function handle(HospitalDatabase $databases, CriticalAlertService $alerts): int
    {
        $hospitals = Hospital::query()->active()->when($this->option('hospital'), fn ($q, $code) => $q->where('code', $code))->orderBy('code')->get();
        foreach ($hospitals as $hospital) {
            try {
                $databases->use($hospital);
                $changed = $alerts->escalateDue($hospital);
                if ($changed > 0) {
                    $this->line("{$hospital->code}: {$changed} alerta(s) escalada(s)");
                }
            } catch (\Throwable $e) {
                // Un hospital sin base disponible no detiene la revisión de los demás.
                Log::error('alertas.escalamiento', ['hospital' => $hospital->code, 'error' => $e->getMessage()]);
                $this->error("{$hospital->code}: no se pudo revisar ({$e->getMessage()})");
            }
        }
        $databases->reset();

        return self::SUCCESS;
    }
}
