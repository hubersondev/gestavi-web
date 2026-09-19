<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class OrganisationPermission extends Model
{
    protected $fillable = [
        'name',
        'slug',
        'group',
        'description',
    ];

    // ──────────────────────────────────────────────
    //  Relations
    // ──────────────────────────────────────────────

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(
            OrganisationRole::class,
            'organisation_role_permission',
            'organisation_permission_id',
            'organisation_role_id',
        );
    }
}
