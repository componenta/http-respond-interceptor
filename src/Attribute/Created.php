<?php

declare(strict_types=1);

namespace Componenta\Interceptor\Http\Attribute;

use Attribute;

#[Attribute(Attribute::TARGET_METHOD | Attribute::TARGET_FUNCTION)]
final class Created extends Respond
{
    public function __construct(?string $contentType = 'application/json')
    {
        parent::__construct(201, $contentType);
    }
}
