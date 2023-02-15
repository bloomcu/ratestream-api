<?php

namespace DDD\Domain\Organizations;

// Domains
use DDD\Domain\Base\Organizations\Organization as BaseOrganization;

class Organization extends BaseOrganization {
    /**
     * Pages associated with the organization.
     *
     * @return hasMany
     */
    public function rates()
    {
        return $this->hasMany('DDD\Domain\Rates\Rate');
    }
}
