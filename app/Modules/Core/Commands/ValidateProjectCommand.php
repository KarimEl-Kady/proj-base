<?php

namespace App\Modules\Core\Commands;

use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Model;

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
        $this->enum($errors, 'database.default', ['mysql', 'pgsql', 'sqlite']);

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
        $this->integer($errors, 'project.health.queue_heartbeat_ttl', 30, 3600);
        $this->integer($errors, 'project.health.queue_backlog_warning', 1, 10000000);

        $this->identifier($errors, 'project.tenancy.tenant_column');
        $this->pathSegment($errors, 'project.api.prefix');
        $this->pathSegment($errors, 'project.api.version');

        $defaultSlug = config('project.tenancy.default_tenant.slug');
        if (! is_string($defaultSlug) || preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $defaultSlug) !== 1) {
            $errors[] = 'project.tenancy.default_tenant.slug must be a lowercase URL slug.';
        }

        $tenantModel = config('project.tenancy.tenant_model');
        if (! is_string($tenantModel) || ! class_exists($tenantModel)
            || ! is_subclass_of($tenantModel, Model::class)) {
            $errors[] = 'project.tenancy.tenant_model must be an Eloquent model class.';
        }

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

    /** @param array<int, string> $errors */
    protected function identifier(array &$errors, string $key): void
    {
        $value = config($key);

        if (! is_string($value) || preg_match('/^[A-Za-z_][A-Za-z0-9_]*$/', $value) !== 1) {
            $errors[] = "{$key} must be a valid SQL identifier.";
        }
    }

    /** @param array<int, string> $errors */
    protected function pathSegment(array &$errors, string $key): void
    {
        $value = config($key);

        if (! is_string($value) || preg_match('/^[A-Za-z0-9][A-Za-z0-9_-]*$/', $value) !== 1) {
            $errors[] = "{$key} must be one URL path segment.";
        }
    }
}
