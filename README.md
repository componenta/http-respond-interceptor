# Componenta HTTP Respond Interceptor

HTTP interceptor for `componenta/interceptor` that turns a route handler result into a PSR-7 response through `Componenta\Http\Responder`.

**[Русская документация](README.ru.md)**

## Boundary

This package contains:

- `RespondInterceptor`
- `#[Respond]`
- `#[Created]` as a shortcut for status `201`

It does not create the HTTP application, router, or PSR-17 factories. Those parts are provided by `componenta/app-http`, `componenta/router-app`, `componenta/http-psr-*`, and `componenta/http-responder`.

## Installation

```bash
composer require componenta/http-respond-interceptor
```

## Quick Start

```php
use Componenta\Interceptor\Http\Attribute\Respond;

final class HealthController
{
    #[Respond(200, 'application/json')]
    public function __invoke(): array
    {
        return ['status' => 'ok'];
    }
}
```

`#[Respond]` extends `Componenta\Interceptor\Attribute\Intercept`. `AttributeInterceptor` creates `RespondInterceptor` through the container, and `RespondInterceptor` calls `Responder::respond($status, $result, $contentType)`.

## Status Codes

```php
use Componenta\Interceptor\Http\Attribute\Created;
use Componenta\Interceptor\Http\Attribute\Respond;

#[Created]
public function create(): array {}

#[Respond(204)]
public function delete(): null {}
```

`#[Respond(204)]` returns an empty response because this behavior belongs to `Componenta\Http\Responder`.

## Ordering With Serialization

The response interceptor must be the outer layer when serialization is below it:

```php
#[Respond(200, 'application/json')]
#[Serialize]
public function show(): User {}
```

The method result flows through `#[Serialize]` first, then the serialized string is wrapped by `#[Respond]`.

## Scope

`RespondInterceptor` and `#[Respond]` are restricted to `Scope::HTTP`. They run only when the router integration sets the HTTP scope on `CallableContextInterface`.

## License

MIT
