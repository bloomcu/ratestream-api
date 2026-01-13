<?php

namespace DDD\App\Jobs;

use DDD\App\Services\RateGroups\RateGroupPublisher;
use DDD\Domain\Organizations\Organization;
use DDD\Domain\Rates\RateGroup;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class PublishScheduledRateGroups implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * Execute the job.
     *
     * @param RateGroupPublisher $publisher
     * @return void
     */
    public function handle(RateGroupPublisher $publisher)
    {
        RateGroup::whereNotNull('revision_of')
            ->whereNull('archived_at')
            ->whereNotNull('published_at')
            ->where('published_at', '<=', now())
            ->orderBy('published_at')
            ->chunkById(100, function ($revisions) use ($publisher) {
                foreach ($revisions as $revision) {
                    $organization = Organization::find($revision->organization_id);
                    if (! $organization) {
                        continue;
                    }

                    $publisher->publish($organization, $revision);
                }
            });
    }
}
