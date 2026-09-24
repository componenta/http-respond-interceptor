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
