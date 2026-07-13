<?php

namespace App\Modules\Core\Commands;

use Illuminate\Console\Command;

class ValidateProjectCommand extends Command
{
    protected $signature = 'project:validate';

    protected $description = 'Validate project configuration and production safety bounds';

    public function handle(): int
    {
        $errors = [];

        $this->enum($errors, 'project.tenancy.mode', ['none', 'single', 'multi']);
        $this->enum($errors, 'project.tenancy.tenant_identification', ['subdomain', 'header', 'path']);
        $this->enum($errors, 'project.platform', ['web', 'api', 'hybrid']);
        $this->enum($errors, 'project.auth.driver', ['sanctum', 'token', 'session']);

        $this->integer($errors, 'project.api.rate_limit', 1, 10000);
        $this->integer($errors, 'project.pagination.per_page', 1, 1000);
        $this->integer($errors, 'project.pagination.max_per_page', 1, 1000);
        $this->integer($errors, 'project.pagination.unpaginated_cap', 1, 10000);
        $this->integer($errors, 'project.auth.token_expiration', 1, 525600);
        $this->integer($errors, 'project.auth.personal_token_expiration', 1, 525600);
        $this->integer($errors, 'project.events.tries', 1, 100);
        $this->integer($errors, 'project.outbox.max_attempts', 1, 100);
        $this->integer($errors, 'project.outbox.claim_ttl_seconds', 30, 86400);
        $this->integer($errors, 'project.outbox.retention.published_hours', 1, 87600);
        $this->integer($errors, 'project.outbox.retention.failed_hours', 1, 87600);
        $this->integer($errors, 'project.outbox.retention.processed_hours', 1, 87600);
        $this->integer($errors, 'project.tenancy.cache.ttl_seconds', 1, 86400);

        $backoff = config('project.outbox.backoff');
        if (! is_array($backoff) || $backoff === [] || collect($backoff)->contains(
            fn (mixed $seconds): bool => ! is_numeric($seconds) || (int) $seconds < 1 || (int) $seconds > 86400
        )) {
            $errors[] = 'project.outbox.backoff must be a non-empty list of seconds between 1 and 86400.';
        }

        if ((int) config('project.pagination.per_page') > (int) config('project.pagination.max_per_page')) {
            $errors[] = 'project.pagination.per_page must not exceed max_per_page.';
        }

        if (app()->isProduction() && config('app.debug')) {
            $errors[] = 'APP_DEBUG must be false in production.';
        }

        if ($errors !== []) {
            foreach ($errors as $error) {
                $this->error($error);
            }

            return self::FAILURE;
        }

        $this->info('Project configuration is valid.');

        return self::SUCCESS;
    }

    /** @param array<int, string> $errors */
    protected function enum(array &$errors, string $key, array $allowed): void
    {
        if (! in_array(config($key), $allowed, true)) {
            $errors[] = "{$key} must be one of: ".implode(', ', $allowed).'.';
        }
    }

    /** @param array<int, string> $errors */
    protected function integer(array &$errors, string $key, int $min, int $max): void
    {
        $value = config($key);

        if (! is_numeric($value) || (int) $value < $min || (int) $value > $max) {
            $errors[] = "{$key} must be an integer between {$min} and {$max}.";
        }
    }
}
