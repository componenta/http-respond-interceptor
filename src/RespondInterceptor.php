<?php

declare(strict_types=1);

namespace Componenta\Interceptor\Http;

use Componenta\Http\Responder;
use Componenta\Interceptor\CallableContextInterface;
use Componenta\Interceptor\ContextHandlerInterface;
use Componenta\Interceptor\InterceptorInterface;
use Componenta\Interceptor\Scope;
use Componenta\Scope\ScopedInterface;
use Componenta\Scope\Scopes;
use Psr\Http\Message\ResponseInterface;

final readonly class RespondInterceptor implements InterceptorInterface, ScopedInterface
{
    public Scopes $scopes;

    /**
     * @param array<string, string|string[]> $headers
     */
    public function __construct(
        private Responder $responder,
        private int $status = 200,
        private ?string $contentType = null,
        private array $headers = [],
    ) {
        $this->scopes = Scopes::of(Scope::HTTP);
    }

    public function intercept(
        CallableContextInterface $context,
        ContextHandlerInterface $handler,
    ): ResponseInterface {
        $response = $this->responder->respond($this->status, $handler->handle($context), $this->contentType);

        foreach ($this->headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        return $response;
    }
}
