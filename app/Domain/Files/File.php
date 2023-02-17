<?php

namespace DDD\Domain\Files;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

// Traits
use DDD\App\Traits\BelongsToOrganization;
use DDD\App\Traits\BelongsToUser;

class File extends Model
{
    use HasFactory,
        BelongsToOrganization,
        BelongsToUser;

    protected $guarded = [
        'id',
    ];

    public $casts = [
        'completed_at' => 'datetime'
    ];

    public function scopeNotCompleted(Builder $query)
    {
        $query->whereNull('completed_at');
    }

    public function scopeForModel(Builder $query, string $model)
    {
        $query->where('model', $model);
    }

    public function percentageComplete(): int
    {
        return floor(($this->processed_rows / $this->total_rows) * 100);
    }
}
