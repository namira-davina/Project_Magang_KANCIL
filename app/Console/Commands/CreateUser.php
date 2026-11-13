<?php

namespace App\Console\Commands;

use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Validator;

class CreateUser extends Command
{
    protected $signature = 'kancil:buat-user
        {username : Username login}
        {--role=petugas : admin | petugas | pimpinan}
        {--name= : Nama lengkap}
        {--email= : Email (opsional)}
        {--password= : Kata sandi (minimal 8 karakter; ditanyakan bila kosong)}';

    protected $description = 'Buat atau perbarui akun dashboard (Admin, Petugas, Pimpinan)';

    public function handle(): int
    {
        $data = [
            'username' => $this->argument('username'),
            'role' => $this->option('role'),
            'name' => $this->option('name') ?: $this->argument('username'),
            'email' => $this->option('email'),
            'password' => $this->option('password') ?: $this->secret('Kata sandi'),
        ];

        $validator = Validator::make($data, [
            'username' => ['required', 'string', 'max:50'],
            'role' => ['required', 'in:'.implode(',', User::ROLES)],
            'name' => ['required', 'string', 'max:100'],
            'email' => ['nullable', 'email', 'max:100'],
            'password' => ['required', 'string', 'min:8'],
        ]);

        if ($validator->fails()) {
            foreach ($validator->errors()->all() as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $user = User::updateOrCreate(['username' => $data['username']], [...$data, 'is_active' => true]);

        $this->info("Akun {$user->username} ({$user->role}) tersimpan.");

        return self::SUCCESS;
    }
}
