<?php

namespace App\Models;

use App\Notifications\ResetPasswordNotification;
use App\Notifications\VerifyEmailNotification;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    protected $fillable = [
        'name',
        'email',
        'password',
    ];

    /**
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function restaurant(): BelongsTo
    {
        return $this->belongsTo(Restaurant::class);
    }

    public function branches(): BelongsToMany
    {
        return $this->belongsToMany(Branch::class);
    }

    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    public function isOperator(): bool
    {
        return $this->role === 'operator';
    }

    /**
     * Whether the user can view cost/profit/margin data — sensitive business info.
     * Used for production_cost, net_profit and margin-derived data across orders/POS.
     * Alias of canViewProfitMetrics().
     */
    public function canViewFinancials(): bool
    {
        return $this->canViewProfitMetrics();
    }

    /**
     * Whether the user can view cash/revenue/payment-breakdown metrics.
     * Needed by operators to reconcile register and know how much money moved today.
     * NOT sensitive — does NOT reveal production costs nor profit.
     */
    public function canViewCashMetrics(): bool
    {
        return $this->isAdmin() || $this->isOperator();
    }

    /**
     * Whether the user can view profitability metrics (net_profit, margin, cost).
     * Restaurant-level sensitive data — admin only.
     */
    public function canViewProfitMetrics(): bool
    {
        return $this->isAdmin();
    }

    /**
     * Get the branch IDs this user can access.
     * Admins can access all branches; operators only their assigned ones.
     *
     * @return list<int>|null null = all branches (admin), array = specific IDs (operator)
     */
    public function allowedBranchIds(): ?array
    {
        if ($this->isAdmin()) {
            return null; // null = no restriction
        }

        return $this->branches()->pluck('branches.id')->all();
    }

    /**
     * Send the password reset notification with custom branding and Spanish text.
     */
    public function sendPasswordResetNotification($token): void
    {
        $this->notify(new ResetPasswordNotification($token));
    }

    /**
     * Send the email verification notification with custom branding and Spanish text.
     */
    public function sendEmailVerificationNotification(): void
    {
        $this->notify(new VerifyEmailNotification);
    }
}
