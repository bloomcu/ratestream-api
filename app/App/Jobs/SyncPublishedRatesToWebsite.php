<?php

namespace DDD\App\Jobs;

use DDD\Domain\Organizations\Organization;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class SyncPublishedRatesToWebsite implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private const SECRET = 'test-secret';

    public int $tries = 3;

    public int $timeout = 10;

    public function __construct(
        public int $organizationId,
        public int $publishedRateGroupId
    ) {
    }

    public function backoff(): array
    {
        return [30, 120, 300];
    }

    public function handle(): void
    {
        $organization = Organization::find($this->organizationId);

        if (! $organization) {
            Log::warning('Published rates webhook skipped: organization not found.', [
                'organization_id' => $this->organizationId,
                'published_rate_group_id' => $this->publishedRateGroupId,
            ]);

            return;
        }

        $url = $this->webhookUrl($organization->rates_domain);

        if (! $url) {
            Log::warning('Published rates webhook skipped: missing or invalid rates domain.', [
                'organization_id' => $organization->id,
                'published_rate_group_id' => $this->publishedRateGroupId,
                'rates_domain' => $organization->rates_domain,
            ]);

            return;
        }

        try {
            $response = Http::asJson()
                ->timeout(5)
                ->withHeaders([
                    'X-RateStream-Secret' => self::SECRET,
                ])
                ->post($url, []);
        } catch (Throwable $exception) {
            Log::warning('Published rates webhook request failed.', [
                'organization_id' => $organization->id,
                'published_rate_group_id' => $this->publishedRateGroupId,
                'url' => $url,
                'message' => $exception->getMessage(),
            ]);

            throw $exception;
        }

        if ($response->failed()) {
            Log::warning('Published rates webhook returned an unsuccessful response.', [
                'organization_id' => $organization->id,
                'published_rate_group_id' => $this->publishedRateGroupId,
                'url' => $url,
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            $response->throw();
        }
    }

    private function webhookUrl(?string $ratesDomain): ?string
    {
        $baseUrl = trim((string) $ratesDomain);

        if ($baseUrl === '') {
            return null;
        }

        if (! preg_match('/^https?:\/\//i', $baseUrl)) {
            $baseUrl = 'https://' . $baseUrl;
        }

        $host = parse_url($baseUrl, PHP_URL_HOST);

        if (! $host) {
            return null;
        }

        return rtrim($baseUrl, '/') . '/wp-json/ratestream/v1/sync';
    }
}
