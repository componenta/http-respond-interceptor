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
    public function __construct(private mixed $result) {}

    public function handle(CallableContextInterface $context): mixed
    {
        return $this->result;
    }
}

it('wraps handler result into a response', function () {
    $factory = new Psr17Factory();
    $responder = new Responder($factory, $factory);
    $interceptor = new RespondInterceptor($responder, status: 201, contentType: 'application/json');
    $context = new CallableContext(static fn() => null);

    $response = $interceptor->intercept($context, new RespondFixedResultHandler(['id' => 1]));

    expect($response->getStatusCode())->toBe(201)
        ->and($response->getHeaderLine('Content-Type'))->toBe('application/json')
        ->and((string) $response->getBody())->toBe('{"id":1}');
});

it('declares HTTP scope through response attributes', function () {
    $respond = new Respond(204);
    $created = new Created();

    expect($respond->scopes->contains(Scope::HTTP))->toBeTrue()
        ->and($created->scopes->contains(Scope::HTTP))->toBeTrue()
        ->and($created->interceptor)->toBe(RespondInterceptor::class)
        ->and($created->params)->toBe(['status' => 201, 'contentType' => 'application/json']);
});
