<?php

namespace Database\Seeders;

use App\Models\Central\CentralUser;
use App\Models\Central\Hospital;
use App\Models\Central\HospitalMembership;
use App\Models\Central\Network;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

/** Red de demostración: cuatro hospitales y un usuario por rol. Contraseña de todos: NidoDemo2026. */
class DemoNetworkSeeder extends Seeder
{
    public const PASSWORD = 'NidoDemo2026';

    public const HOSPITALS = [
        'HN-CEN' => 'Hospital Nido Central',
        'HN-NOR' => 'Hospital Nido Norte',
        'HN-ORI' => 'Hospital Nido Oriente',
        'HN-OCC' => 'Hospital Nido Occidente',
    ];

    public function run(): void
    {
        $network = Network::query()->firstOrCreate(['name' => 'Red Hospital Nido'], ['status' => 'active', 'timezone' => 'America/Guatemala', 'metadata' => []]);
        $hospitals = collect(self::HOSPITALS)->map(fn (string $name, string $code) => Hospital::query()->firstOrCreate(
            ['network_id' => $network->network_id, 'code' => $code],
            ['name' => $name, 'status' => 'active', 'timezone' => 'America/Guatemala', 'metadata' => []],
        ));

        $admin = $this->user('admin@nido.test', 'Administración de la red');
        foreach ($hospitals as $hospital) {
            $this->member($admin, $hospital, 'admin_red');
        }

        $central = $hospitals['HN-CEN'];
        foreach (array_keys(config('nido.roles')) as $role) {
            if ($role !== 'admin_red') {
                $this->member($this->user(str_replace('_', '.', $role).'@nido.test', config("nido.roles.{$role}.0").' (demo)'), $central, $role);
            }
        }
        // Un médico con acceso a dos hospitales, para probar el cambio de hospital.
        $this->member(CentralUser::query()->where('email', 'medico@nido.test')->firstOrFail(), $hospitals['HN-NOR'], 'medico');
    }

    private function user(string $email, string $name): CentralUser
    {
        $user = CentralUser::query()->firstOrCreate(['email' => $email], ['subject' => 'local|'.$email, 'display_name' => $name, 'status' => 'active', 'metadata' => []]);
        $user->credential()->firstOrCreate([], ['password_hash' => Hash::make(self::PASSWORD)]);

        return $user;
    }

    private function member(CentralUser $user, Hospital $hospital, string $role): void
    {
        HospitalMembership::query()->firstOrCreate(
            ['user_id' => $user->user_id, 'hospital_id' => $hospital->hospital_id, 'role_code' => $role, 'status' => 'active'],
            ['valid_from' => now()->subDay(), 'metadata' => []],
        );
    }
}
