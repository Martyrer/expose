<?php

namespace Tests\Unit\Modifiers;

use Expose\Client\Configuration;
use Expose\Client\Http\Modifiers\RewriteRequestHeaders;
use GuzzleHttp\Psr7\Request;
use Tests\TestCase;

class RewriteRequestHeadersTest extends TestCase
{
    /** @test */
    public function it_passes_request_through_unchanged_when_no_headers_configured()
    {
        $modifier = $this->createModifier();

        $request = new Request('GET', '/example', ['Host' => 'original.test']);
        $result = $modifier->handle($request, null);

        $this->assertNotNull($result);
        $this->assertEquals('original.test', $result->getHeaderLine('Host'));
    }

    /** @test */
    public function it_rewrites_host_header_from_cli_option()
    {
        $modifier = $this->createModifier(requestHeaders: ['Host' => 'myapp.test']);

        $request = new Request('GET', '/example', ['Host' => 'original.test']);
        $result = $modifier->handle($request, null);

        $this->assertNotNull($result);
        $this->assertEquals('myapp.test', $result->getHeaderLine('Host'));
    }

    /** @test */
    public function it_adds_new_headers_from_cli_option()
    {
        $modifier = $this->createModifier(requestHeaders: ['X-Custom' => 'value']);

        $request = new Request('GET', '/example', ['Host' => 'original.test']);
        $result = $modifier->handle($request, null);

        $this->assertNotNull($result);
        $this->assertEquals('value', $result->getHeaderLine('X-Custom'));
        $this->assertEquals('original.test', $result->getHeaderLine('Host'));
    }

    /** @test */
    public function it_adds_multiple_headers_from_cli_option()
    {
        $modifier = $this->createModifier(requestHeaders: [
            'Host' => 'myapp.test',
            'X-Forwarded-Proto' => 'https',
            'X-Custom' => 'value',
        ]);

        $request = new Request('GET', '/example', ['Host' => 'original.test']);
        $result = $modifier->handle($request, null);

        $this->assertNotNull($result);
        $this->assertEquals('myapp.test', $result->getHeaderLine('Host'));
        $this->assertEquals('https', $result->getHeaderLine('X-Forwarded-Proto'));
        $this->assertEquals('value', $result->getHeaderLine('X-Custom'));
    }

    /** @test */
    public function it_reads_headers_from_config()
    {
        config(['expose.request_headers' => ['Host' => 'from-config.test']]);

        $modifier = $this->createModifier();

        $request = new Request('GET', '/example', ['Host' => 'original.test']);
        $result = $modifier->handle($request, null);

        $this->assertNotNull($result);
        $this->assertEquals('from-config.test', $result->getHeaderLine('Host'));
    }

    /** @test */
    public function cli_headers_take_precedence_over_config_headers()
    {
        config(['expose.request_headers' => [
            'Host' => 'from-config.test',
            'X-Config-Only' => 'config-value',
        ]]);

        $modifier = $this->createModifier(requestHeaders: ['Host' => 'from-cli.test']);

        $request = new Request('GET', '/example', ['Host' => 'original.test']);
        $result = $modifier->handle($request, null);

        $this->assertNotNull($result);
        $this->assertEquals('from-cli.test', $result->getHeaderLine('Host'));
        $this->assertEquals('config-value', $result->getHeaderLine('X-Config-Only'));
    }

    /** @test */
    public function it_handles_empty_config_headers_gracefully()
    {
        config(['expose.request_headers' => []]);

        $modifier = $this->createModifier();

        $request = new Request('GET', '/example', ['Host' => 'original.test']);
        $result = $modifier->handle($request, null);

        $this->assertNotNull($result);
        $this->assertEquals('original.test', $result->getHeaderLine('Host'));
    }

    /** @test */
    public function it_handles_null_config_headers_gracefully()
    {
        config(['expose.request_headers' => null]);

        $modifier = $this->createModifier();

        $request = new Request('GET', '/example', ['Host' => 'original.test']);
        $result = $modifier->handle($request, null);

        $this->assertNotNull($result);
        $this->assertEquals('original.test', $result->getHeaderLine('Host'));
    }

    /** @test */
    public function it_preserves_existing_headers_not_being_overwritten()
    {
        $modifier = $this->createModifier(requestHeaders: ['X-New' => 'new-value']);

        $request = new Request('GET', '/example', [
            'Host' => 'original.test',
            'Accept' => 'text/html',
            'User-Agent' => 'TestBrowser/1.0',
        ]);
        $result = $modifier->handle($request, null);

        $this->assertNotNull($result);
        $this->assertEquals('original.test', $result->getHeaderLine('Host'));
        $this->assertEquals('text/html', $result->getHeaderLine('Accept'));
        $this->assertEquals('TestBrowser/1.0', $result->getHeaderLine('User-Agent'));
        $this->assertEquals('new-value', $result->getHeaderLine('X-New'));
    }

    /** @test */
    public function it_always_returns_a_request_and_never_blocks()
    {
        $modifier = $this->createModifier(requestHeaders: ['Host' => 'test.test']);

        $request = new Request('GET', '/example');
        $result = $modifier->handle($request, null);

        $this->assertNotNull($result, 'RewriteRequestHeaders should never block a request');
    }

    /** @test */
    public function it_handles_header_names_case_insensitively()
    {
        $modifier = $this->createModifier(requestHeaders: ['host' => 'lowercase.test']);

        $request = new Request('GET', '/example', ['Host' => 'original.test']);
        $result = $modifier->handle($request, null);

        $this->assertNotNull($result);
        $this->assertEquals('lowercase.test', $result->getHeaderLine('Host'));
    }

    /** @test */
    public function it_applies_cli_headers_when_config_is_empty()
    {
        config(['expose.request_headers' => []]);

        $modifier = $this->createModifier(requestHeaders: ['Host' => 'from-cli.test']);

        $request = new Request('GET', '/example', ['Host' => 'original.test']);
        $result = $modifier->handle($request, null);

        $this->assertNotNull($result);
        $this->assertEquals('from-cli.test', $result->getHeaderLine('Host'));
    }

    /** @test */
    public function it_does_not_overwrite_x_expose_request_id()
    {
        $modifier = $this->createModifier(requestHeaders: ['X-Expose-Request-Id' => 'fake-id']);

        $request = new Request('GET', '/example', ['X-Expose-Request-Id' => 'real-id']);
        $result = $modifier->handle($request, null);

        $this->assertNotNull($result);
        $this->assertEquals('real-id', $result->getHeaderLine('X-Expose-Request-Id'));
    }

    /** @test */
    public function it_does_not_overwrite_x_original_host()
    {
        $modifier = $this->createModifier(requestHeaders: ['X-Original-Host' => 'fake.test']);

        $request = new Request('GET', '/example', ['X-Original-Host' => 'real.sharedwithexpose.com']);
        $result = $modifier->handle($request, null);

        $this->assertNotNull($result);
        $this->assertEquals('real.sharedwithexpose.com', $result->getHeaderLine('X-Original-Host'));
    }

    /** @test */
    public function it_does_not_overwrite_x_exposed_by()
    {
        $modifier = $this->createModifier(requestHeaders: ['X-Exposed-By' => 'impersonator']);

        $request = new Request('GET', '/example', ['X-Exposed-By' => 'Expose-server']);
        $result = $modifier->handle($request, null);

        $this->assertNotNull($result);
        $this->assertEquals('Expose-server', $result->getHeaderLine('X-Exposed-By'));
    }

    /** @test */
    public function it_blocks_restricted_headers_regardless_of_case()
    {
        $modifier = $this->createModifier(requestHeaders: ['x-expose-request-id' => 'fake']);

        $request = new Request('GET', '/example', ['X-Expose-Request-Id' => 'real-id']);
        $result = $modifier->handle($request, null);

        $this->assertNotNull($result);
        $this->assertEquals('real-id', $result->getHeaderLine('X-Expose-Request-Id'));
    }

    /** @test */
    public function it_blocks_restricted_headers_from_config_too()
    {
        config(['expose.request_headers' => ['X-Expose-Request-Id' => 'from-config']]);

        $modifier = $this->createModifier();

        $request = new Request('GET', '/example', ['X-Expose-Request-Id' => 'real-id']);
        $result = $modifier->handle($request, null);

        $this->assertNotNull($result);
        $this->assertEquals('real-id', $result->getHeaderLine('X-Expose-Request-Id'));
    }

    /** @test */
    public function it_allows_non_restricted_x_headers()
    {
        $modifier = $this->createModifier(requestHeaders: [
            'X-Custom-Header' => 'allowed',
            'X-Forwarded-For' => '1.2.3.4',
        ]);

        $request = new Request('GET', '/example');
        $result = $modifier->handle($request, null);

        $this->assertNotNull($result);
        $this->assertEquals('allowed', $result->getHeaderLine('X-Custom-Header'));
        $this->assertEquals('1.2.3.4', $result->getHeaderLine('X-Forwarded-For'));
    }

    protected function createModifier(array $requestHeaders = []): RewriteRequestHeaders
    {
        $configuration = new Configuration('localhost', 443, null, null, false, null, $requestHeaders);

        return new RewriteRequestHeaders($configuration);
    }
}
