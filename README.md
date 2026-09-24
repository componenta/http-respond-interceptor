# Componenta HTTP Respond Interceptor

HTTP interceptor for `componenta/interceptor` that turns a route handler result into a PSR-7 response through `Componenta\Http\Responder`.

**[Русская документация](README.ru.md)**

## Requirements

- PHP 8.5+

PHP 8.5 is required because `#[Respond]` accepts closures and first-class callables directly in attribute arguments.

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
    #[Respond(status: 200, contentType: 'application/json')]
    public function __invoke(): array
    {
        return ['status' => 'ok'];
    }
}
```

`#[Respond]` extends `Componenta\Interceptor\Attribute\Intercept`. `AttributeInterceptor` creates `RespondInterceptor` through the container, and `RespondInterceptor` calls `Responder::respond($status, $result, $contentType)`.

## Response Callback

The first argument of `#[Respond]` is an optional response callback. It receives the fully built `ResponseInterface` and must return a `ResponseInterface`.

Configured headers are applied before the callback, so the callback is the final response transformation and may replace headers, change the status, or perform any other immutable PSR-7 modification.

PHP 8.5 allows a static closure directly in an attribute:

```php
use Psr\Http\Message\ResponseInterface;

#[Respond(
    static function (ResponseInterface $response): ResponseInterface {
        return $response
            ->withStatus(202)
            ->withHeader('X-Response-Source', 'callback');
    },
    status: 200,
    contentType: 'application/json',
)]
public function show(): array {}
```

First-class callables are supported as well:

```php
final class UserController
{
    #[Respond(self::decorate(...), status: 200)]
    public function show(): array
    {
        return [];
    }

    private static function decorate(ResponseInterface $response): ResponseInterface
    {
        return $response->withHeader('Cache-Control', 'no-store');
    }
}
```

`#[Created]` supports the same first callback argument.

If a callback returns anything other than `ResponseInterface`, `RespondInterceptor` throws `UnexpectedValueException`.

## Status Codes

```php
use Componenta\Interceptor\Http\Attribute\Created;
use Componenta\Interceptor\Http\Attribute\Respond;

#[Created]
public function create(): array {}

#[Respond(status: 204)]
public function delete(): null {}
```

`#[Respond(status: 204)]` returns an empty response because this behavior belongs to `Componenta\Http\Responder`.

## HTTP Headers

Pass response headers through `#[Respond]` or `#[Created]` with the `headers` argument. Header values may be a string or an array of strings.

```php
#[Respond(
    200,
    'application/json',
    headers: [
        'Cache-Control' => 'no-store',
        'Vary' => ['Accept', 'Authorization'],
    ],
)]
public function show(): array {}

#[Created(headers: ['Location' => '/users/42'])]
public function create(): array {}
```

The interceptor applies configured headers after `Responder::respond()`. They are therefore also applied when the handler already returns a `ResponseInterface`, and a configured header replaces an existing header with the same name. The response callback, when configured, runs after these headers.

The interceptor can be configured directly in the same way:

```php
new RespondInterceptor(
    $responder,
    status: 200,
    contentType: 'application/json',
    headers: ['Cache-Control' => 'no-store'],
    callback: static function (ResponseInterface $response): ResponseInterface {
        return $response->withHeader('X-Response-Source', 'callback');
    },
);
```

## Migration From 1.x

Version 2.0 requires PHP 8.5+. The first positional argument of `#[Respond]` is now the optional callback, so status and content type should be passed by name:

```php
// 1.x
#[Respond(201, 'application/json')]

// 2.x
#[Respond(status: 201, contentType: 'application/json')]
```

The first positional argument of `#[Created]` is now the optional callback. Existing positional content-type calls should use the named argument:

```php
// 1.x
#[Created('application/problem+json')]

// 2.x
#[Created(contentType: 'application/problem+json')]
```

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
