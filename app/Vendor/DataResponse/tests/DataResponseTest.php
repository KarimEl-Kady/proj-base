<?php

namespace Local\DataResponse\Tests;

use Illuminate\Testing\TestResponse;
use Local\DataResponse\DataResponse;
use Tests\TestCase;

class DataResponseTest extends TestCase
{
    protected function wrap($jsonResponse): TestResponse
    {
        return TestResponse::fromBaseResponse($jsonResponse);
    }

    public function test_success_builds_the_default_envelope(): void
    {
        $response = $this->wrap(DataResponse::success(['id' => 1], 'Created.', 201));

        $response->assertStatus(201);
        $response->assertJson([
            'success' => true,
            'message' => 'Created.',
            'data' => ['id' => 1],
        ]);
    }

    public function test_success_falls_back_to_configured_default_message(): void
    {
        $response = $this->wrap(DataResponse::success(['id' => 1]));

        $response->assertJsonPath('message', 'Success');
    }

    public function test_success_marks_non_2xx_status_as_unsuccessful(): void
    {
        $response = $this->wrap(DataResponse::success(null, 'Odd but allowed', 404));

        $response->assertJsonPath('success', false);
    }

    public function test_error_builds_envelope_with_errors_key_only_when_given(): void
    {
        $withErrors = $this->wrap(DataResponse::error('Invalid.', 422, ['email' => ['required']]));
        $withErrors->assertStatus(422);
        $withErrors->assertJson([
            'success' => false,
            'message' => 'Invalid.',
            'errors' => ['email' => ['required']],
        ]);

        $withoutErrors = DataResponse::error('Nope.', 400);
        $this->assertArrayNotHasKey('errors', $withoutErrors->getData(true));
    }

    public function test_error_falls_back_to_configured_default_message(): void
    {
        $response = $this->wrap(DataResponse::error());

        $response->assertJsonPath('message', 'Error');
    }

    public function test_keys_are_renameable_via_config(): void
    {
        config([
            'data_response.keys.success' => 'ok',
            'data_response.keys.message' => 'msg',
            'data_response.keys.data' => 'payload',
        ]);

        $response = $this->wrap(DataResponse::success(['x' => 1], 'Hi'));

        $response->assertJson([
            'ok' => true,
            'msg' => 'Hi',
            'payload' => ['x' => 1],
        ]);
    }

    public function test_raw_builds_a_response_with_exactly_the_given_payload(): void
    {
        $response = $this->wrap(DataResponse::raw(['status' => 'healthy', 'checks' => []], 200));

        $response->assertStatus(200);
        $response->assertExactJson(['status' => 'healthy', 'checks' => []]);
    }

    public function test_raw_respects_the_given_status_and_headers(): void
    {
        $response = $this->wrap(DataResponse::raw(['status' => 'degraded'], 503, ['X-Probe' => 'health']));

        $response->assertStatus(503);
        $response->assertHeader('X-Probe', 'health');
    }
}
