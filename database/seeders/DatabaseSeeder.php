<?php

namespace Database\Seeders;

use App\Models\BotSetting;
use App\Models\ComplaintCategory;
use App\Models\FaqCategory;
use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Reference data taken from `kancil_sapa.sql`. Idempotent.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        User::updateOrCreate(
            ['username' => 'admin'],
            [
                'name' => 'Administrator',
                'email' => 'admin@kancilsapa.local',
                'password' => 'password',
                'role' => 'admin',
                'is_active' => true,
            ]
        );

        foreach ([
            'Paspor' => 'Informasi layanan paspor WNI',
            'Visa' => 'Informasi visa',
            'Izin Tinggal' => 'Informasi izin tinggal keimigrasian',
            'Persyaratan Layanan' => 'Persyaratan berbagai layanan keimigrasian',
            'Biaya dan Pembayaran' => 'Informasi tarif dan metode pembayaran',
            'Jadwal Pelayanan' => 'Informasi jadwal dan waktu pelayanan',
            'Pengaduan' => 'Informasi dan bantuan pengaduan',
            'Informasi Umum' => 'Informasi umum Kantor Imigrasi',
        ] as $name => $description) {
            FaqCategory::firstOrCreate(['name' => $name], ['description' => $description, 'is_active' => true]);
        }

        foreach ([
            'Pelayanan' => 'Keluhan terkait pelayanan keimigrasian',
            'Persyaratan' => 'Kendala terkait persyaratan',
            'Sistem atau Aplikasi' => 'Kendala pada sistem atau aplikasi',
            'Informasi' => 'Informasi yang tidak sesuai atau belum jelas',
            'Lainnya' => 'Pengaduan di luar kategori yang tersedia',
        ] as $name => $description) {
            ComplaintCategory::firstOrCreate(['name' => $name], ['description' => $description, 'is_active' => true]);
        }

        foreach ([
            'bot_name' => ['KANCIL SAPA', 'Nama chatbot'],
            'working_hours_start' => ['08:00', 'Jam mulai layanan petugas - perlu konfirmasi'],
            'working_hours_end' => ['16:00', 'Jam selesai layanan petugas - perlu konfirmasi'],
            'working_days' => ['Senin-Jumat', 'Hari layanan petugas - perlu konfirmasi'],
        ] as $key => [$value, $description]) {
            BotSetting::firstOrCreate(['setting_key' => $key], ['setting_value' => $value, 'description' => $description]);
        }
    }
}
