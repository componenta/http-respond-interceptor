<?php

declare(strict_types=1);

namespace Componenta\Interceptor\Http\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
final class Created extends Respond
{
    /**
     * @param array<string, string|string[]> $headers
     */
    public function __construct(?string $contentType = 'application/json', array $headers = [])
    {
        parent::__construct(201, $contentType, $headers);
    }
}
