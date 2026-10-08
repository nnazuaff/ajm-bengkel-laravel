<?php

namespace App\Console\Commands;

use App\Enums\Role;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class CreateWorkshopUser extends Command
{
    protected $signature = 'workshop:create-user {email} {--name=} {--role=owner}';

    protected $description = 'Buat akun staff melalui console tepercaya, tanpa password default';

    public function handle(): int
    {
        $data = [
            'email' => strtolower(trim((string) $this->argument('email'))),
            'name' => trim((string) $this->option('name')),
            'role' => (string) $this->option('role'),
        ];

        $validator = Validator::make($data, [
            'email' => ['required', 'email:rfc', 'max:254', Rule::unique('users')],
            'name' => ['required', 'string', 'max:120'],
            'role' => ['required', Rule::in([Role::Owner->value, Role::Admin->value, Role::Mechanic->value])],
        ]);

        if ($validator->fails()) {
            $this->error(implode(' ', $validator->errors()->all()));

            return self::FAILURE;
        }

        $password = (string) $this->secret('Kata sandi (minimal 12 karakter)');
        $confirmation = (string) $this->secret('Ulangi kata sandi');

        if (mb_strlen($password) < 12 || $password !== $confirmation) {
            $this->error('Kata sandi minimal 12 karakter dan konfirmasi harus sama.');

            return self::FAILURE;
        }

        $user = DB::transaction(function () use ($data, $password): User {
            $user = new User;
            $user->name = $data['name'];
            $user->email = $data['email'];
            $user->password = $password;
            $user->role = Role::from($data['role']);
            $user->email_verified_at = now();
            $user->save();

            AuditLog::create([
                'action' => 'user.created',
                'entity_type' => User::class,
                'entity_id' => $user->id,
                'context' => ['role' => $user->role->value, 'source' => 'trusted_console'],
            ]);

            return $user;
        });

        $this->info('Akun staff dibuat. ID: '.$user->id.'; role: '.$user->role->label());

        return self::SUCCESS;
    }
}
