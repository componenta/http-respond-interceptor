<?php

declare(strict_types=1);

namespace Componenta\Interceptor\Http\Attribute;

use Attribute;
use Closure;
use Psr\Http\Message\ResponseInterface;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
final class Created extends Respond
{
    /**
     * @param array<string, string|string[]> $headers
     * @param Closure|null $factory Receives a configured respond closure and the handler result first; trailing parameters are resolved by DI.
     */
    public function __construct(
        ?string $contentType = 'application/json',
        array $headers = [],
        ?Closure $factory = null,
    ) {
        parent::__construct(201, $contentType, $headers, $factory);
    }
}
