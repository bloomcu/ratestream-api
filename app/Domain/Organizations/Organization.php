<?php

namespace DDD\Domain\Organizations;

// Domains
use DDD\Domain\Base\Organizations\Organization as BaseOrganization;

class Organization extends BaseOrganization {
    /**
     * columns associated with the organization.
     *
     * @return hasMany
     */
    public function columns()
    {
        return $this->hasMany('DDD\Domain\Columns\Column');
    }

    /**
     * Pages associated with the organization.
     *
     * @return hasMany
     */
    public function rates()
    {
        return $this->hasMany('DDD\Domain\Rates\Rate');
    }

    /**
     * Rate groups associated with the organization.
     *
     * @return hasMany
     */
    public function groups()
    {
        return $this->hasMany('DDD\Domain\Rates\RateGroup');
    }

    /**
     * Files associated with the organization.
     *
     * @return hasMany
     */
    public function files()
    {
        return $this->hasMany('DDD\Domain\Files\File');
    }
}
