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
            if ($this->isRestricted($name)) {
                continue;
            }

            $request = $request->withHeader($name, $value);
        }

        return $request;
    }

    protected function isRestricted(string $name): bool
    {
        return in_array(strtolower($name), self::RESTRICTED_HEADERS, true);
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
