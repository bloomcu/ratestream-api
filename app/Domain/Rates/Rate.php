<?php

namespace DDD\Domain\Rates;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Casts
use DDD\Domain\Rates\Casts\RateColumns;

// Traits
use DDD\App\Traits\BelongsToOrganization;
use DDD\App\Traits\BelongsToUser;

class Rate extends Model
{
    use HasFactory,
        BelongsToOrganization,
        BelongsToUser;

    protected $guarded = [
        'id',
    ];

    protected $with = ['group'];

    protected $casts = [
        'columns' => RateColumns::class,
    ];

    /**
     * Rate group this model belongs to.
     *
     * @return belongsTo
     */
    public function group()
    {
        return $this->belongsTo('DDD\Domain\Rates\RateGroup', 'rate_group_id');
    }
}
