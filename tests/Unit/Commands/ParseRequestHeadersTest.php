<?php

namespace Tests\Unit\Commands;

use Tests\TestCase;

class ParseRequestHeadersTest extends TestCase
{
    /** @test */
    public function it_parses_valid_header()
    {
        $result = $this->parse(['Host: myapp.test']);

        $this->assertEquals(['Host' => 'myapp.test'], $result);
    }

    /** @test */
    public function it_parses_multiple_headers()
    {
        $result = $this->parse([
            'Host: myapp.test',
            'X-Forwarded-Proto: https',
        ]);

        $this->assertEquals([
            'Host' => 'myapp.test',
            'X-Forwarded-Proto' => 'https',
        ], $result);
    }

    /** @test */
    public function it_trims_whitespace_around_name_and_value()
    {
        $result = $this->parse(['  Host  :  myapp.test  ']);

        $this->assertEquals(['Host' => 'myapp.test'], $result);
    }

    /** @test */
    public function it_preserves_colons_in_value()
    {
        $result = $this->parse(['Authorization: Bearer token:with:colons']);

        $this->assertEquals(['Authorization' => 'Bearer token:with:colons'], $result);
    }

    /** @test */
    public function it_skips_input_without_colon()
    {
        $result = $this->parse(['InvalidHeader']);

        $this->assertEmpty($result);
    }

    /** @test */
    public function it_skips_empty_string()
    {
        $result = $this->parse(['']);

        $this->assertEmpty($result);
    }

    /** @test */
    public function it_skips_colon_only_input()
    {
        $result = $this->parse([':']);

        $this->assertEmpty($result);
    }

    /** @test */
    public function it_skips_empty_header_name()
    {
        $result = $this->parse([': some-value']);

        $this->assertEmpty($result);
    }

    /** @test */
    public function it_rejects_crlf_in_value()
    {
        $result = $this->parse(["Host: evil\r\nInjected: header"]);

        $this->assertEmpty($result);
    }

    /** @test */
    public function it_rejects_newline_in_value()
    {
        $result = $this->parse(["Host: evil\nInjected: header"]);

        $this->assertEmpty($result);
    }

    /** @test */
    public function it_rejects_null_byte_in_value()
    {
        $result = $this->parse(["Host: evil\x00value"]);

        $this->assertEmpty($result);
    }

    /** @test */
    public function it_rejects_invalid_header_name_with_spaces()
    {
        $result = $this->parse(['Invalid Name: value']);

        $this->assertEmpty($result);
    }

    /** @test */
    public function it_rejects_header_name_with_special_characters()
    {
        $result = $this->parse(['Host@Name: value']);

        $this->assertEmpty($result);
    }

    /** @test */
    public function it_allows_valid_rfc7230_token_characters_in_name()
    {
        $result = $this->parse(["X-Custom_Header.v1~test!#$%&'*+^`|: value"]);

        $this->assertCount(1, $result);
    }

    /** @test */
    public function it_allows_empty_value()
    {
        $result = $this->parse(['X-Empty:']);

        $this->assertEquals(['X-Empty' => ''], $result);
    }

    /** @test */
    public function it_handles_mixed_valid_and_invalid_inputs()
    {
        $result = $this->parse([
            'Valid: header',
            'InvalidNoColon',
            ': empty-name',
            "Injected: evil\r\nstuff",
            'Also-Valid: works',
        ]);

        $this->assertEquals([
            'Valid' => 'header',
            'Also-Valid' => 'works',
        ], $result);
    }

    /**
     * Invoke the protected parseRequestHeaders method via a test subclass.
     *
     * @param  array  $rawHeaders  Simulated --request-header-add values
     * @return array
     */
    protected function parse(array $rawHeaders): array
    {
        $command = new class extends \Expose\Client\Commands\ShareCommand
        {
            protected $testHeaders = [];

            public function setTestHeaders(array $headers): void
            {
                $this->testHeaders = $headers;
            }

            public function option($key = null)
            {
                if ($key === 'request-header-add') {
                    return $this->testHeaders;
                }
                return parent::option($key);
            }

            public function exposedParseRequestHeaders(): array
            {
                return $this->parseRequestHeaders();
            }
        };

        // ShareCommand extends ServerAwareCommand which extends Command — we need
        // to set up enough state so that option() doesn't crash.
        $command->setTestHeaders($rawHeaders);

        return $command->exposedParseRequestHeaders();
    }
}
