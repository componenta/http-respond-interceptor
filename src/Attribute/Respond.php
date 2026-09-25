<?php

declare(strict_types=1);

namespace Componenta\Interceptor\Http\Attribute;

use Attribute;
use Closure;
use Componenta\Interceptor\Attribute\Intercept;
use Componenta\Interceptor\Http\RespondInterceptor;
use Componenta\Interceptor\Scope;
use Componenta\Scope\ScopedInterface;
use Componenta\Scope\Scopes;
use Psr\Http\Message\ResponseInterface;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class Respond extends Intercept implements ScopedInterface
{
    public Scopes $scopes {
        get => Scopes::of(Scope::HTTP);
    }

    /**
     * @param array<string, string|string[]> $headers
     * @param Closure|null $factory Receives a configured respond closure and the handler result first; trailing parameters are resolved by DI.
     */
    public function __construct(
        int $status = 200,
        ?string $contentType = null,
        array $headers = [],
        ?Closure $factory = null,
    ) {
        $params = [
            'status' => $status,
            'contentType' => $contentType,
        ];

        if ($headers !== []) {
            $params['headers'] = $headers;
        }

        if ($factory !== null) {
            $params['factory'] = $factory;
        }

        parent::__construct(RespondInterceptor::class, $params);
    }
}
