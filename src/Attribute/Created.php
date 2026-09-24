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
     * @param null|Closure(ResponseInterface): ResponseInterface $callback
     * @param array<string, string|string[]> $headers
     */
    public function __construct(
        ?Closure $callback = null,
        ?string $contentType = 'application/json',
        array $headers = [],
    ) {
        parent::__construct($callback, 201, $contentType, $headers);
    }
}
