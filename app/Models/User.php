<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;
use Spatie\Permission\Traits\HasRoles;
use App\Notifications\DtcResetPasswordNotification;

class User extends Authenticatable
{
    use HasApiTokens, HasFactory, Notifiable, HasRoles;

    protected $fillable = [
        'name',
        'email',
        'password',
        'status',
        'avatar',
    ];

    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];
    /**
     * Student-specific institutional identity.
     * Present only for users with the 'student' or 'alumni' role.
     */
    public function studentProfile()
    {
        return $this->hasOne(\App\Models\StudentProfile::class);
    }

    public function enrollments()
    {
        return $this->hasMany(\App\Models\Enrollment::class);
    }

    /**
    * Get all applications submitted by this user.
    */
    public function applications()
    {
        return $this->hasMany(Application::class, 'user_id');
    }
    /**
     * Get custom in-app notifications.
     * Named differently to avoid conflict with Notifiable trait.
     */
    public function userNotifications()
    {
        return $this->hasMany(UserNotification::class, 'user_id')->latest();
    }

    public function unreadNotificationsCount(): int
    {
        return UserNotification::where('user_id', $this->id)
            ->whereNull('read_at')
            ->count();
    }

    public function sendPasswordResetNotification($token): void
    {
        $expiresInMinutes = config('auth.passwords.users.expire', 60);
        $this->notify(new DtcResetPasswordNotification($token, $expiresInMinutes));
    }
}
