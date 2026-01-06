<?php

namespace DDD\Http\Rates\Resources;

use Carbon\Carbon;
use Illuminate\Http\Resources\Json\JsonResource;

class RateGroupResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @param  \Illuminate\Http\Request  $request
     * @return array
     */
    public function toArray($request)
    {
        return [
            'id' => $this->id,
            'organization_id' => $this->organization_id,
            'title' => $this->title,
            'published_at' => $this->published_at
                ? Carbon::parse($this->published_at)->toIso8601String()
                : null,
            'revision_of' => $this->revision_of,
            'position' => $this->position,
            'created_at' => $this->created_at,
            'updated_at' => $this->updated_at,
            'has_revisions' => $this->revisions()->exists(),
            
        ];
    }
}
