<?php

namespace DDD\Domain\Rates;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Traits
use DDD\App\Traits\BelongsToOrganization;
use DDD\App\Traits\BelongsToUser;

class RateGroup extends Model
{
    use HasFactory,
        BelongsToOrganization,
        BelongsToUser;

    protected $guarded = [
        'id',
    ];

    /**
     * Rates associated with the group.
     *
     * @return hasMany
     */
    public function rates()
    {
        return $this->hasMany('DDD\Domain\Rates\Rate');
    }
}
