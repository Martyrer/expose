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

    protected function createModifier(array $requestHeaders = []): RewriteRequestHeaders
    {
        $configuration = new Configuration('localhost', 443, null, null, false, null, $requestHeaders);

        return new RewriteRequestHeaders($configuration);
    }
}
