<?php

namespace Module\ApiResponse\Tests\Unit;

use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Response;
use Module\ApiResponse\Tests\TestCase;

class ResponseMacrosTest extends TestCase
{
    public function test_api_wraps_data_in_the_success_envelope(): void
    {
        $response = Response::api(['id' => 1], 'Done');

        $this->assertSame(200, $response->status());
        $this->assertSame(['status' => 'success', 'message' => 'Done', 'data' => ['id' => 1]], $response->getData(true));
    }

    public function test_message_is_optional_and_extras_go_at_the_top_level(): void
    {
        $body = Response::api([], extra: ['priorities' => ['low']])->getData(true);

        $this->assertSame(['status' => 'success', 'data' => [], 'priorities' => ['low']], $body);
    }

    public function test_api_takes_a_custom_status(): void
    {
        $this->assertSame(201, Response::api(['id' => 1], 'Created', status: 201)->status());
    }

    public function test_api_spreads_a_paginator_next_to_status(): void
    {
        $page = new LengthAwarePaginator([['id' => 1], ['id' => 2]], 5, 2, 1);

        $body = Response::api($page, 'Listed')->getData(true);

        $this->assertSame('success', $body['status']);
        $this->assertSame([['id' => 1], ['id' => 2]], $body['data']);
        $this->assertSame(1, $body['current_page']);
        $this->assertSame(3, $body['last_page']);
        $this->assertSame(2, $body['per_page']);
        $this->assertSame(5, $body['total']);
    }

    public function test_success_and_error_envelopes(): void
    {
        $this->assertSame(['status' => 'success', 'message' => 'Ok'], Response::success('Ok')->getData(true));

        $error = Response::error('Nope', 409, ['code' => 'x']);

        $this->assertSame(409, $error->status());
        $this->assertSame(['status' => 'error', 'message' => 'Nope', 'code' => 'x'], $error->getData(true));
    }
}
