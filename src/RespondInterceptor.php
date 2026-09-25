<?php

declare(strict_types=1);

namespace Componenta\Interceptor\Http;

use Closure;
use Componenta\DI\CallableInvokerInterface;
use Componenta\Http\Responder;
use Componenta\Interceptor\CallableContextInterface;
use Componenta\Interceptor\ContextHandlerInterface;
use Componenta\Interceptor\InterceptorInterface;
use Componenta\Interceptor\Scope;
use Componenta\Scope\ScopedInterface;
use Componenta\Scope\Scopes;
use Psr\Http\Message\ResponseInterface;
use UnexpectedValueException;

final readonly class RespondInterceptor implements InterceptorInterface, ScopedInterface
{
    public Scopes $scopes;

    /**
     * @param array<string, string|string[]> $headers
     * @param Closure|null $factory Receives the response and result first; trailing parameters are resolved by DI.
     */
    public function __construct(
        private Responder $responder,
        private CallableInvokerInterface $invoker,
        private int $status = 200,
        private ?string $contentType = null,
        private array $headers = [],
        private ?Closure $factory = null,
    ) {
        $this->scopes = Scopes::of(Scope::HTTP);
    }

    public function intercept(
        CallableContextInterface $context,
        ContextHandlerInterface $handler,
    ): ResponseInterface {
        $result = $handler->handle($context);
        $response = $this->responder->respond($this->status, $result, $this->contentType);

        foreach ($this->headers as $name => $value) {
            $response = $response->withHeader($name, $value);
        }

        if ($this->factory === null) {
            return $response;
        }

        $modified = $this->invoker->call($this->factory, [$response, $result]);
        if (!$modified instanceof ResponseInterface) {
            throw new UnexpectedValueException(sprintf(
                'Response factory must return %s, %s returned.',
                ResponseInterface::class,
                get_debug_type($modified),
            ));
        }

        return $modified;
    }
}
