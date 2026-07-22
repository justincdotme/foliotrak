<?php

declare(strict_types=1);

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

/**
 * @property int                             $id
 * @property string                          $name
 * @property string                          $email
 * @property string|null                     $pushover_user_key
 * @property \Illuminate\Support\Carbon|null $email_verified_at
 * @property string                          $password
 * @property string|null                     $remember_token
 * @property \Illuminate\Support\Carbon      $created_at
 * @property \Illuminate\Support\Carbon      $updated_at
 */
#[Fillable(['name', 'email', 'password', 'pushover_user_key'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    use HasApiTokens;

    /** @use HasFactory<UserFactory> */
    use HasFactory;

    use Notifiable;

    /**
     * The recipient key for Pushover reminders; null users are skipped by the fan-out.
     *
     * @return string|null
     */
    public function routeNotificationForPushover(): ?string
    {
        return $this->pushover_user_key;
    }

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password'          => 'hashed',
        ];
    }
}
