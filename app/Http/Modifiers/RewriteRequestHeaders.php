<?php

namespace Expose\Client\Http\Modifiers;

use Expose\Client\Configuration;
use Psr\Http\Message\RequestInterface;
use Ratchet\Client\WebSocket;

class RewriteRequestHeaders
{
    /** @var Configuration */
    protected $configuration;

    public function __construct(Configuration $configuration)
    {
        $this->configuration = $configuration;
    }

    public function handle(RequestInterface $request, ?WebSocket $proxyConnection): ?RequestInterface
    {
        $headers = $this->getHeaders();

        foreach ($headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    protected function getHeaders(): array
    {
        $headers = [];

        // Config file headers (lower priority)
        $configHeaders = config('expose.request_headers', []);
        if (is_array($configHeaders)) {
            $headers = $configHeaders;
        }

        // CLI --request-header-add takes precedence
        $cliHeaders = $this->configuration->requestHeaders();
        foreach ($cliHeaders as $name => $value) {
            $headers[$name] = $value;
        }

        return $headers;
    }
}
