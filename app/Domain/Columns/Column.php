<?php

namespace DDD\Domain\Columns;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Traits
use DDD\App\Traits\BelongsToOrganization;
use DDD\App\Traits\BelongsToUser;
use DDD\App\Traits\IsSortable;

class Rate extends Model
{
    use HasFactory,
        BelongsToOrganization,
        BelongsToUser,
        IsSortable;

    protected $guarded = ['id'];

    // /**
    //  * Rate group this model belongs to.
    //  *
    //  * @return belongsTo
    //  */
    // public function group()
    // {
    //     return $this->belongsTo('DDD\Domain\Rates\RateGroup', 'rate_group_id');
    // }
}
