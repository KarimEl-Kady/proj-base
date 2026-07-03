<?php

namespace Tests\Unit;

use App\Modules\Core\Requests\FetchRequest;
use Tests\TestCase;

class FetchRequestTest extends TestCase
{
    protected function makeRequest(array $query): FetchRequest
    {
        return FetchRequest::create('/?'.http_build_query($query), 'GET');
    }

    public function test_defaults(): void
    {
        $request = $this->makeRequest([]);

        $this->assertTrue($request->wantsPagination());
        $this->assertSame((int) config('project.pagination.per_page', 15), $request->perPage());
        $this->assertNull($request->searchWord());
        $this->assertNull($request->sortBy());
        $this->assertSame('desc', $request->sortDir());
    }

    public function test_per_page_is_capped_and_floored(): void
    {
        config(['project.pagination.max_per_page' => 100]);

        $this->assertSame(100, $this->makeRequest(['per_page' => 500])->perPage());
        $this->assertSame(1, $this->makeRequest(['per_page' => -3])->perPage());
        $this->assertSame(25, $this->makeRequest(['per_page' => 25])->perPage());
    }

    public function test_search_word_is_trimmed_and_null_when_blank(): void
    {
        $this->assertSame('hello', $this->makeRequest(['word' => '  hello '])->searchWord());
        $this->assertNull($this->makeRequest(['word' => '   '])->searchWord());
    }

    public function test_pagination_accepts_query_string_booleans(): void
    {
        $this->assertFalse($this->makeRequest(['pagination' => 'false'])->wantsPagination());
        $this->assertFalse($this->makeRequest(['pagination' => '0'])->wantsPagination());
        $this->assertTrue($this->makeRequest(['pagination' => 'true'])->wantsPagination());
    }

    public function test_sort_dir_falls_back_to_desc(): void
    {
        $this->assertSame('asc', $this->makeRequest(['sort_dir' => 'ASC'])->sortDir());
        $this->assertSame('desc', $this->makeRequest(['sort_dir' => 'sideways'])->sortDir());
    }
}
