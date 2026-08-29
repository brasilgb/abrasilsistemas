<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Minimal model for the `accounts` table.
 *
 * The account_id columns on users/leads/lead_activities were introduced by
 * migrations that ran on this database (batch 10-11) but were never
 * committed to the repository, and no application code ever consumed them —
 * this model and the default-account fill in Lead::booted() are the minimal
 * fix to keep lead creation working today. A real multi-tenancy feature
 * (account switching, scoping queries by account, etc.) appears to have been
 * started but not finished; this does not attempt to complete that.
 */
class Account extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'prospect_api_token',
        'trial_ends_at',
        'paid_until',
    ];

    protected function casts(): array
    {
        return [
            'trial_ends_at' => 'datetime',
            'paid_until' => 'datetime',
        ];
    }

    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    public function leads(): HasMany
    {
        return $this->hasMany(Lead::class);
    }
}
