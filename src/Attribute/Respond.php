<?php

declare(strict_types=1);

namespace Componenta\Interceptor\Http\Attribute;

use Attribute;
use Componenta\Interceptor\Attribute\Intercept;
use Componenta\Interceptor\Http\RespondInterceptor;
use Componenta\Interceptor\Scope;
use Componenta\Scope\ScopedInterface;
use Componenta\Scope\Scopes;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
class Respond extends Intercept implements ScopedInterface
{
    public Scopes $scopes {
        get => Scopes::of(Scope::HTTP);
    }

    public function __construct(
        int $status = 200,
        ?string $contentType = null,
    ) {
        parent::__construct(RespondInterceptor::class, [
            'status' => $status,
            'contentType' => $contentType,
        ]);
    }
}
