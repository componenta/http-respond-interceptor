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
    #[Respond(200, 'application/json')]
    public function __invoke(): array
    {
        return ['status' => 'ok'];
    }
}
```

`#[Respond]` extends `Componenta\Interceptor\Attribute\Intercept`. `AttributeInterceptor` creates `RespondInterceptor` through the container, and `RespondInterceptor` calls `Responder::respond($status, $result, $contentType)`.

## Response Factory

The last argument of `#[Respond]` is an optional response factory. It receives a configured `Closure $respond` as its first argument and the result returned by the downstream handler/interceptor chain as its second `mixed` argument. Any parameters after those two are resolved through Componenta DI's normal callable-invocation pipeline. The factory must return a `ResponseInterface`.

The `$respond` closure is already bound to the attribute's status, content type and headers. Calling `$respond()` uses the original downstream result. Calling `$respond($replacement)` passes the replacement to `Responder::respond()` instead; an explicit `null` is distinct from omitting the argument. Configured headers are applied by `$respond` before it returns, so the factory may still replace them afterwards.

PHP 8.5 allows a static closure directly in an attribute:

```php
use Closure;
use Psr\Http\Message\ResponseInterface;

#[Respond(
    200,
    'application/json',
    factory: static function (Closure $respond, mixed $result): ResponseInterface {
        return $respond()
            ->withStatus(202)
            ->withHeader('X-Response-Source', 'factory');
    },
)]
public function show(): array {}
```

First-class callables are supported as well:

```php
final class UserController
{
    #[Respond(200, factory: self::decorate(...))]
    public function show(): array
    {
        return [];
    }

    private static function decorate(Closure $respond, mixed $result): ResponseInterface
    {
        return $respond()->withHeader('Cache-Control', 'no-store');
    }
}
```

`#[Created]` supports the same final factory argument.

Additional services can be injected after the first two reserved arguments. For example, when the application binds Symfony's `SerializerInterface`, it can be requested directly by type:

```php
use Closure;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Serializer\SerializerInterface;

#[Respond(
    200,
    factory: static function (
        Closure $respond,
        mixed $result,
        SerializerInterface $serializer,
    ): ResponseInterface {
        return $respond($serializer->serialize($result, 'json'))
            ->withHeader('X-Response-Source', 'serialized-factory');
    },
)]
public function show(): object {}
```

The third and later parameters are not limited to serializers; any service resolvable by Componenta DI can be requested there. The first two positions always remain the configured respond closure and downstream result. This lets a factory serialize or project an unsupported DTO first and then hand the transformed value back to the normal Responder without injecting Responder itself.


If a factory returns anything other than `ResponseInterface`, `RespondInterceptor` throws `UnexpectedValueException`.

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

The interceptor applies configured headers after `Responder::respond()`. They are therefore also applied when the handler already returns a `ResponseInterface`, and a configured header replaces an existing header with the same name. The configured `$respond` closure applies these headers to every response it builds. A factory receives the original downstream result separately and can call `$respond()` or `$respond($replacement)`; after the closure returns, the factory remains free to override those headers.

The interceptor can be configured directly in the same way. Direct construction also requires a DI-aware `CallableInvokerInterface`; normal attribute-based construction receives it from the container automatically:

```php
new RespondInterceptor(
    $responder,
    $invoker,
    status: 200,
    contentType: 'application/json',
    headers: ['Cache-Control' => 'no-store'],
    factory: static function (Closure $respond, mixed $result): ResponseInterface {
        return $respond()->withHeader('X-Response-Source', 'factory');
    },
);
```

## Migration From 1.x

Version 2.0 requires PHP 8.5+, but the existing argument order is preserved. The factory is appended after `headers`, so existing positional calls remain valid:

```php
#[Respond(201, 'application/json')]
#[Respond(200, 'application/json', ['Cache-Control' => 'no-store'])]

#[Created('application/problem+json')]
```

The factory can be supplied by name without filling unused optional arguments:

```php
#[Respond(200, factory: self::decorate(...))]
#[Created(factory: static fn (Closure $respond, mixed $result): ResponseInterface =>
    $respond()->withHeader('Location', '/users/42')
)]
```

## Ordering With Serialization

A factory that needs the original DTO should normally serialize inside the factory instead of placing `#[Serialize]` below `#[Respond]`:

```php
#[Respond(200, 'application/json', factory: UserResponseFactory::create(...))]
public function show(): User {}
```

The factory then receives the original `User`, may inject `SerializerInterface`, and can call `$respond($serializer->serialize($result, 'json'))`. If `#[Serialize]` is still placed below `#[Respond]`, the factory's second argument is the serialized inner-chain result rather than the original DTO.

## Scope

`RespondInterceptor` and `#[Respond]` are restricted to `Scope::HTTP`. They run only when the router integration sets the HTTP scope on `CallableContextInterface`.

## License

MIT
