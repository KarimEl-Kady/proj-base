<?php

namespace App\Modules\Core\Repositories;

use App\Modules\Core\Repositories\Search\LikeSearch;
use App\Modules\Core\Repositories\Search\SearchStrategy;
use App\Modules\Core\Requests\FetchRequest;
use App\Modules\Core\Traits\HasUuid;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Str;

/**
 * Base for module repositories. Deliberately NOT generic over the model:
 * Eloquent's `static`-returning builders don't resolve through a PHPStan
 * template, so `@template TModel` would only produce unverifiable
 * annotations. Repositories that want a narrower type than Model simply
 * query their own model class directly (see UserRepository::findByEmail).
 */
abstract class BaseRepository
{
    public function __construct(
        protected Model $model
    ) {}

    public function query(): Builder
    {
        return $this->model->newQuery();
    }

    /**
     * Find by primary key, or by uuid when the model uses HasUuid and a
     * uuid string is given (API resources expose the uuid as "id").
     */
    public function find(int|string $id): ?Model
    {
        if ($this->shouldLookupByUuid($id)) {
            return $this->query()->where('uuid', $id)->first();
        }

        return $this->query()->find($id);
    }

    public function findOrFail(int|string $id): Model
    {
        if ($this->shouldLookupByUuid($id)) {
            return $this->query()->where('uuid', $id)->firstOrFail();
        }

        return $this->query()->findOrFail($id);
    }

    protected function shouldLookupByUuid(int|string $id): bool
    {
        return is_string($id)
            && Str::isUuid($id)
            && in_array(HasUuid::class, class_uses_recursive($this->model));
    }

    public function all(): Collection
    {
        return $this->query()->get();
    }

    /**
     * Run a listing query driven by a FetchRequest: free-text search over
     * the model's $searchable columns, whitelisted sorting, and optional
     * pagination (`pagination=false` returns the full collection).
     */
    public function fetch(FetchRequest $request): LengthAwarePaginator|Collection
    {
        $query = $this->baseQuery($request);

        $word = $request->searchWord();
        $columns = $this->searchableColumns();

        if ($word !== null && $columns !== []) {
            $this->searchStrategy()->apply($query, $word, $columns);
        }

        $sortBy = $request->sortBy();

        if ($sortBy !== null && in_array($sortBy, $this->sortableColumns())) {
            $query->orderBy($sortBy, $request->sortDir());
        } else {
            $query->latest($this->model->getKeyName());
        }

        if (! $request->wantsPagination()) {
            // pagination=false is a client-controlled toggle — cap it so it
            // can never load an unbounded table into memory (see
            // project.pagination.unpaginated_cap).
            $cap = max(1, min(
                (int) config('project.pagination.unpaginated_cap', 1000),
                10000,
            ));

            return $query->limit($cap)->get();
        }

        return $query->paginate($request->perPage())->appends($request->query());
    }

    /**
     * Starting point for fetch()'s query — override to scope listings to
     * extra request-driven filters (e.g. "cities in this country") before
     * word search / sorting / pagination are applied on top.
     */
    protected function baseQuery(FetchRequest $request): Builder
    {
        return $this->query();
    }

    /**
     * How fetch() applies `?word=` — LikeSearch by default (see that
     * class's docblock for the tradeoff). Override in a repository whose
     * table has grown large enough to need FullTextSearch instead.
     */
    protected function searchStrategy(): SearchStrategy
    {
        return new LikeSearch;
    }

    /**
     * @return array<int, string>
     */
    protected function searchableColumns(): array
    {
        return method_exists($this->model, 'searchableColumns')
            ? $this->model->searchableColumns()
            : [];
    }

    /**
     * @return array<int, string>
     */
    protected function sortableColumns(): array
    {
        return method_exists($this->model, 'sortableColumns')
            ? $this->model->sortableColumns()
            : ['id', 'created_at', 'updated_at'];
    }

    public function paginate(?int $perPage = null): LengthAwarePaginator
    {
        $perPage ??= (int) config('project.pagination.per_page', 15);
        $perPage = min($perPage, (int) config('project.pagination.max_per_page', 100));

        return $this->query()->paginate($perPage);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Model
    {
        return $this->query()->create($data);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Model $model, array $data): bool
    {
        return $model->update($data);
    }

    public function delete(Model $model): ?bool
    {
        return $model->delete();
    }

    public function count(): int
    {
        return $this->query()->count();
    }

    public function exists(): bool
    {
        return $this->query()->exists();
    }
}
