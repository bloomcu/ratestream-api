<?php

namespace DDD\Domain\Columns;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

// Traits
use DDD\App\Traits\BelongsToOrganization;
use DDD\App\Traits\BelongsToUser;
use DDD\App\Traits\IsSortable;

class Column extends Model
{
    use HasFactory,
        BelongsToOrganization,
        BelongsToUser,
        IsSortable;

    protected $guarded = ['id'];
}
