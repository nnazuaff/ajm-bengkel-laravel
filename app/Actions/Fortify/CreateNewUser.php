<?php

namespace App\Actions\Fortify;

use App\Actions\CustomerResolver;
use App\Concerns\PasswordValidationRules;
use App\Concerns\ProfileValidationRules;
use App\Models\User;
use App\Support\WorkshopInput;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Laravel\Fortify\Contracts\CreatesNewUsers;

class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules, ProfileValidationRules;

    /**
     * Validate and create a newly registered user.
     *
     * @param  array<string, string>  $input
     */
    public function create(array $input): User
    {
        $input['phone'] = is_string($input['phone'] ?? null) ? WorkshopInput::phone($input['phone']) : '';
        Validator::make($input, [
            'phone' => ['required', 'string', 'regex:/^62[1-9][0-9]{7,12}$/D'],
            ...$this->profileRules(),
            'password' => $this->passwordRules(),
        ])->validate();

        return DB::transaction(function () use ($input): User {
            $user = User::create(array_intersect_key($input, array_flip(['name', 'email', 'password', 'phone'])));
            app(CustomerResolver::class)->forUser($user, $input);

            return $user;
        });
    }
}
