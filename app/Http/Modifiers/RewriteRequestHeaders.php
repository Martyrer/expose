<?php

namespace Expose\Client\Http\Modifiers;

use Expose\Client\Configuration;
use Psr\Http\Message\RequestInterface;
use Ratchet\Client\WebSocket;

class RewriteRequestHeaders
{
    /** Headers used internally by Expose that must not be overwritten. */
    protected const RESTRICTED_HEADERS = [
        'x-expose-request-id',
        'x-exposed-by',
        'x-original-host',
    ];

    /** @var array Precomputed merged headers (config + CLI, restricted removed) */
    protected $headers = [];

    public function __construct(Configuration $configuration)
    {
        $this->headers = $this->buildHeaders($configuration);
    }

    public function handle(RequestInterface $request, ?WebSocket $proxyConnection): ?RequestInterface
    {
        foreach ($this->headers as $name => $value) {
            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    protected function isRestricted(string $name): bool
    {
        return in_array(strtolower($name), self::RESTRICTED_HEADERS, true);
    }

    protected function buildHeaders(Configuration $configuration): array
    {
        $headers = [];

        // Config file headers (lower priority)
        $configHeaders = config('expose.request_headers', []);
        if (is_array($configHeaders)) {
            $headers = $configHeaders;
        }

        // CLI --request-header-add takes precedence
        $cliHeaders = $configuration->requestHeaders();
        foreach ($cliHeaders as $name => $value) {
            $headers[$name] = $value;
        }

        // Remove restricted headers
        foreach ($headers as $name => $value) {
            if ($this->isRestricted($name)) {
                unset($headers[$name]);
            }
        }

        return $headers;
    }
}
