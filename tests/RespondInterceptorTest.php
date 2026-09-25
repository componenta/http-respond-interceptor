<?php

declare(strict_types=1);

use Componenta\Http\Responder;
use Componenta\Interceptor\CallableContext;
use Componenta\Interceptor\CallableContextInterface;
use Componenta\Interceptor\ContextHandlerInterface;
use Componenta\Interceptor\Http\Attribute\Created;
use Componenta\Interceptor\Http\Attribute\Respond;
use Componenta\Interceptor\Http\RespondInterceptor;
use Componenta\Interceptor\Scope;
use Nyholm\Psr7\Factory\Psr17Factory;
use Psr\Http\Message\ResponseInterface;

final readonly class RespondFixedResultHandler implements ContextHandlerInterface
{
    public function __construct(private mixed $result)
    {
    }

    public function handle(CallableContextInterface $context): mixed
    {
        return $this->result;
    }
}

final class RespondAttributeFactoryFixture
{
    #[Respond(
        202,
        factory: static function (ResponseInterface $response, mixed $result): ResponseInterface {
            return $response
                ->withHeader('X-Factory', 'closure')
                ->withHeader('X-Factory-Result', is_array($result) ? 'array' : get_debug_type($result));
        },
    )]
    public function closure(): array
    {
        return [];
    }

    #[Respond(203, factory: self::modify(...))]
    public function firstClassCallable(): array
    {
        return [];
    }

    private static function modify(ResponseInterface $response, mixed $result): ResponseInterface
    {
        return $response
            ->withHeader('X-Factory', 'first-class')
            ->withHeader('X-Factory-Result', is_array($result) ? 'array' : get_debug_type($result));
    }
}

it('wraps handler result into a response', function () {
    $factory = new Psr17Factory();
    $responder = new Responder($factory, $factory);
    $interceptor = new RespondInterceptor($responder, status: 201, contentType: 'application/json');
    $context = new CallableContext(static fn () => null);

    $response = $interceptor->intercept($context, new RespondFixedResultHandler(['id' => 1]));

    expect($response->getStatusCode())->toBe(201)
        ->and($response->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and((string) $response->getBody())->toBe('{"id":1}');
});

it('applies configured headers to a wrapped response', function () {
    $factory = new Psr17Factory();
    $responder = new Responder($factory, $factory);
    $interceptor = new RespondInterceptor(
        $responder,
        headers: [
            'Cache-Control' => 'no-store',
            'Vary' => ['Accept', 'Authorization'],
        ],
    );
    $context = new CallableContext(static fn () => null);

    $response = $interceptor->intercept($context, new RespondFixedResultHandler(['id' => 1]));

    expect($response->getHeaderLine('Cache-Control'))->toBe('no-store')
        ->and($response->getHeader('Vary'))->toBe(['Accept', 'Authorization']);
});

it('applies configured headers when the handler already returns a response', function () {
    $factory = new Psr17Factory();
    $responder = new Responder($factory, $factory);
    $interceptor = new RespondInterceptor(
        $responder,
        status: 201,
        contentType: 'application/json',
        headers: [
            'Cache-Control' => 'no-store',
            'X-Request-Id' => 'request-1',
        ],
    );
    $context = new CallableContext(static fn () => null);
    $result = $factory->createResponse(202)
        ->withHeader('Cache-Control', 'private')
        ->withHeader('X-Origin', 'handler');

    $response = $interceptor->intercept($context, new RespondFixedResultHandler($result));

    expect($response->getStatusCode())->toBe(202)
        ->and($response->getHeaderLine('Cache-Control'))->toBe('no-store')
        ->and($response->getHeaderLine('X-Request-Id'))->toBe('request-1')
        ->and($response->getHeaderLine('X-Origin'))->toBe('handler');
});

it('applies the response factory after configured headers', function () {
    $factory = new Psr17Factory();
    $responder = new Responder($factory, $factory);
    $interceptor = new RespondInterceptor(
        $responder,
        status: 201,
        headers: ['X-Stage' => 'headers'],
        factory: static function (ResponseInterface $response, mixed $result): ResponseInterface {
            return $response
                ->withStatus(is_array($result) && $result['id'] === 1 ? 202 : 500)
                ->withHeader('X-Stage', $response->getHeaderLine('X-Stage') . ', factory');
        },
    );
    $context = new CallableContext(static fn () => null);

    $response = $interceptor->intercept($context, new RespondFixedResultHandler(['id' => 1]));

    expect($response->getStatusCode())->toBe(202)
        ->and($response->getHeaderLine('X-Stage'))->toBe('headers, factory');
});

it('rejects a factory result that is not a response', function () {
    $factory = new Psr17Factory();
    $responder = new Responder($factory, $factory);
    $interceptor = new RespondInterceptor(
        $responder,
        factory: static function (ResponseInterface $response, mixed $result): string {
            return 'invalid';
        },
    );
    $context = new CallableContext(static fn () => null);

    expect(
        fn () => $interceptor->intercept($context, new RespondFixedResultHandler(['id' => 1])),
    )->toThrow(
        UnexpectedValueException::class,
        'Response factory must return Psr\Http\Message\ResponseInterface, string returned.',
    );
});

it('omits handler content when the response status prohibits it', function (int $status): void {
    $factory = new Psr17Factory();
    $responder = new Responder($factory, $factory);
    $interceptor = new RespondInterceptor($responder, status: $status);
    $context = new CallableContext(static fn () => null);

    $response = $interceptor->intercept($context, new RespondFixedResultHandler(['id' => 1]));

    expect($response->getStatusCode())->toBe($status)
        ->and((string) $response->getBody())->toBe('')
        ->and($response->getHeaderLine('Content-Length'))->toBe('');
})->with([103, 204, 205, 304]);

it('declares HTTP scope through response attributes', function () {
    $respond = new Respond(204);
    $created = new Created();

    expect($respond->scopes->contains(Scope::HTTP))->toBeTrue()
        ->and($created->scopes->contains(Scope::HTTP))->toBeTrue()
        ->and($created->interceptor)->toBe(RespondInterceptor::class)
        ->and($created->params)->toBe(['status' => 201, 'contentType' => 'application/json']);
});

it('passes response headers through attributes', function () {
    $headers = [
        'Cache-Control' => 'no-store',
        'Vary' => ['Accept', 'Authorization'],
    ];
    $respond = new Respond(202, 'application/json', $headers);
    $created = new Created(headers: $headers);

    expect($respond->params)->toBe([
        'status' => 202,
        'contentType' => 'application/json',
        'headers' => $headers,
    ])->and($created->params)->toBe([
        'status' => 201,
        'contentType' => 'application/json',
        'headers' => $headers,
    ]);
});

it('accepts a factory as the last response attribute argument', function () {
    $factory = static function (ResponseInterface $response, mixed $result): ResponseInterface {
        return $response->withHeader(
            'X-Factory',
            is_array($result) && $result['id'] === 1 ? 'direct' : 'unexpected',
        );
    };
    $respond = new Respond(202, 'application/json', [], $factory);
    $created = new Created('application/json', [], $factory);

    expect($respond->params)->toBe([
        'status' => 202,
        'contentType' => 'application/json',
        'factory' => $factory,
    ])->and($created->params)->toBe([
        'status' => 201,
        'contentType' => 'application/json',
        'factory' => $factory,
    ]);
});

it('materializes PHP 8.5 closure and first-class callable attribute arguments', function () {
    $factory = new Psr17Factory();

    $closure = (new ReflectionMethod(RespondAttributeFactoryFixture::class, 'closure'))
        ->getAttributes(Respond::class)[0]
        ->newInstance();
    $firstClass = (new ReflectionMethod(RespondAttributeFactoryFixture::class, 'firstClassCallable'))
        ->getAttributes(Respond::class)[0]
        ->newInstance();

    $closureResponse = ($closure->params['factory'])($factory->createResponse(), ['id' => 1]);
    $firstClassResponse = ($firstClass->params['factory'])($factory->createResponse(), ['id' => 1]);

    expect($closure->params['status'])->toBe(202)
        ->and($closureResponse->getHeaderLine('X-Factory'))->toBe('closure')
        ->and($closureResponse->getHeaderLine('X-Factory-Result'))->toBe('array')
        ->and($firstClass->params['status'])->toBe(203)
        ->and($firstClassResponse->getHeaderLine('X-Factory'))->toBe('first-class')
        ->and($firstClassResponse->getHeaderLine('X-Factory-Result'))->toBe('array');
});
