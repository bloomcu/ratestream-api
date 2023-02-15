<?php

namespace DDD\Domain\Rates;

use Illuminate\Database\Eloquent\Factories\HasFactory;
// use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Model;

// Casts
use DDD\Domain\Rates\Casts\CustomFields;

// Traits
use DDD\App\Traits\BelongsToOrganization;
use DDD\App\Traits\BelongsToUser;

class Rate extends Model
{
    use HasFactory,
        // SoftDeletes,
        BelongsToOrganization,
        BelongsToUser;

    protected $guarded = [
        'id',
    ];

    protected $casts = [
        'custom_fields' => CustomFields::class,
    ];
}
