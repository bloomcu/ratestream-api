<?php

namespace DDD\Http\Rates\Resources;

use Illuminate\Http\Resources\Json\JsonResource;

class RateResource extends JsonResource
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
            // 'id' => $this->id,
            'uid' => $this->uid,
            // 'group' => $this->whenLoaded('group', fn() => $this->group->title),
            // 'group' => $this->whenLoaded('group'),
            // 'rate' => $this->rate,
                // 'rate_low' => $this->rate_low,
                // 'rate_high' => $this->rate_high,
            // 'term' => $this->term,
                // 'term_low' => $this->term_low,
                // 'term_high' => $this->term_high,
                // 'term_frequency' => $this->term_frequency,
            // 'year' => $this->year,
                // 'year_low' => $this->year_low,
                // 'year_high' => $this->year_high,
            'data' => $this->data,
        ];
    }
}
