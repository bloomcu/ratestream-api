<?php

namespace Tests\Unit\Domain\Organizations;

use Tests\TestCase;

// Models
use DDD\Domain\Base\Organizations\Organization;
use DDD\Domain\Base\Users\User;

class OrganizationTest extends TestCase
{
    /** @test */
    public function it_has_a_slug()
    {
        $organization = Organization::factory()->create();

        $this->assertNotNull($organization->slug);
    }

    /** @test */
    public function it_has_many_users()
    {
        $organization = Organization::factory()
            ->has(User::factory())
            ->create();

        $this->assertInstanceOf(User::class, $organization->users->first());
    }
}
