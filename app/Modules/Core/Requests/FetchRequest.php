<?php

namespace App\Modules\Core\Requests;

/**
 * Standard request for listing/index endpoints. Validates the global
 * fetch keys and exposes typed accessors for them:
 *
 *   pagination  bool   default true — false returns the full result set
 *   per_page    int    default project.pagination.per_page, capped at max_per_page
 *   page        int    standard paginator page
 *   word        string free-text search word (matched against the model's $searchable columns)
 *   sort_by     string column to sort by (must be in the model's $sortable list)
 *   sort_dir    string asc|desc, default desc
 *
 * Extend it per module and merge extra filters:
 *
 *   public function rules(): array
 *   {
 *       return parent::rules() + [
 *           'status' => ['sometimes', 'in:draft,published'],
 *       ];
 *   }
 */
class FetchRequest extends BaseRequest
{
    /**
     * Query strings deliver booleans as "true"/"false" — normalize them so
     * the `boolean` validation rule accepts them.
     */
    protected function prepareForValidation(): void
    {
        if ($this->has('pagination')) {
            $normalized = filter_var($this->input('pagination'), FILTER_VALIDATE_BOOLEAN, FILTER_NULL_ON_FAILURE);

            if ($normalized !== null) {
                $this->merge(['pagination' => $normalized]);
            }
        }
    }

    public function rules(): array
    {
        return [
            'pagination' => ['sometimes', 'boolean'],
            'per_page' => ['sometimes', 'integer', 'min:1', 'max:'.$this->maxPerPage()],
            'page' => ['sometimes', 'integer', 'min:1'],
            'word' => ['sometimes', 'nullable', 'string', 'max:255'],
            'sort_by' => ['sometimes', 'string', 'max:64'],
            'sort_dir' => ['sometimes', 'in:asc,desc'],
        ];
    }

    public function wantsPagination(): bool
    {
        return $this->boolean('pagination', true);
    }

    public function perPage(): int
    {
        $perPage = (int) $this->input('per_page', config('project.pagination.per_page', 15));

        return min(max($perPage, 1), $this->maxPerPage());
    }

    public function searchWord(): ?string
    {
        $word = trim((string) $this->input('word', ''));

        return $word === '' ? null : $word;
    }

    public function sortBy(): ?string
    {
        return $this->input('sort_by');
    }

    public function sortDir(): string
    {
        return strtolower((string) $this->input('sort_dir', 'desc')) === 'asc' ? 'asc' : 'desc';
    }

    protected function maxPerPage(): int
    {
        return (int) config('project.pagination.max_per_page', 100);
    }
}
