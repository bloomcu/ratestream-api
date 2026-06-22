# Laravel Base

A Laravel SaaS starter.

## Install Locally

**Step 1:** Clone this repository

```
git clone https://github.com/heyharmon/laravel-starter.git
```

<br>

**Step 2:** Change directory into application

```
cd 'app-name'
```

<br>

**Step 3:** Install dependencies

```
composer install
```

<br>

**Step 4:** Copy **env.example** to **.env** and setup environment
> Example database connection:
```
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=app-name
DB_USERNAME=root
DB_PASSWORD=
```

<br>

**Step 5:** Generate unique app key

```php
php artisan key:generate
```

<br>

**Step 6:** Migrate and seed database

```php
php artisan migrate --seed
```

<br>

**Step 7:** Serve application

> Using Artisan CLI, run:
```
php artisan serve
```
Then visit: http://127.0.0.1:8000


> Using Valet, run:
```
valet link app-name
```
Then visit: http://app-name.test

## Get started

[WIP] - API usage instructions coming soon.

### Token Authentication
The Token Authentication allows you to issue API tokens / personal access tokens that may be used to authenticate API requests to your application. When making requests using API tokens, the token should be included in the Authorization header as a Bearer token. [Read More](https://laravel.com/docs/8.x/sanctum#issuing-api-tokens)

After you install, migrate and seed your database, open Tinker and generate a personal access token:
```
php artisan tinker
$user = DDD\Domain\Base\Users\User::find(1);
$user->createToken('test');
```

Use the plainTextToken returned in request header:
```
Header Key: Authorization
Header Value: Bearer YOUR_PLAINTEXT_TOKEN
```

### Rate Publication and Website Sync

Website syncs are triggered by publication events. When a rate group revision is published, the API queues `SyncPublishedRatesToWebsite`, which posts to the configured organization's rates website webhook.

Publication can happen in three ways:

- **Immediate publication:** publishing a revision immediately triggers a website sync after the revision becomes the organization's published/default rate group.
- **Scheduled publication:** scheduled revisions are checked once per minute by Laravel's scheduler. When a revision's `published_at` time is due, `PublishScheduledRateGroups` publishes it, then the same publication flow queues the website sync. Scheduled changes should therefore sync within about one minute of the scheduled publish time, plus normal queue/runtime delay.
- **Edits to the current published group:** batch edits to the organization's current published/default rate group queue a website sync immediately after the save.

For scheduled publication and website syncs to run in an environment, both the Laravel scheduler and queue worker must be active:

```
php artisan schedule:work
php artisan queue:work
```

### API Endpoints

[WIP] - API endpoints.
