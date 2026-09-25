# Componenta HTTP Respond Interceptor

HTTP-перехватчик для `componenta/interceptor`, который превращает результат обработчика маршрута в PSR-7 ответ через `Componenta\Http\Responder`.

**[English documentation](README.md)**

## Требования

- PHP 8.5+

PHP 8.5 требуется потому, что `#[Respond]` принимает closures и first-class callables непосредственно в аргументах атрибута.

## Граница пакета

Пакет содержит:

- `RespondInterceptor`
- атрибут `#[Respond]`
- сокращенный атрибут `#[Created]` для статуса `201`

Пакет не создает HTTP-приложение, маршрутизатор или PSR-17 фабрики. Эти части подключают `componenta/app-http`, `componenta/router-app`, `componenta/http-psr-*` и `componenta/http-responder`.

## Установка

```bash
composer require componenta/http-respond-interceptor
```

## Быстрый старт

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

`#[Respond]` наследует `Componenta\Interceptor\Attribute\Intercept`. `AttributeInterceptor` создает `RespondInterceptor` через контейнер, а сам `RespondInterceptor` вызывает `Responder::respond($status, $result, $contentType)`.

## Factory ответа

Последний аргумент `#[Respond]` — необязательный factory ответа. Первым аргументом он принимает настроенную `Closure $respond`, вторым — `mixed` результат, возвращённый вложенной цепочкой обработчика/interceptor-ов. Все параметры после этих двух разрешаются через обычный механизм вызова Componenta DI. Factory обязан вернуть `ResponseInterface`.

Closure `$respond` уже связана со status, content type и headers атрибута. Вызов `$respond()` использует исходный downstream result. Вызов `$respond($replacement)` передаёт в `Responder::respond()` заменяющее значение; явный `null` отличается от отсутствующего аргумента. Настроенные headers применяются внутри `$respond`, поэтому после её возврата factory всё ещё может их переопределить.

PHP 8.5 позволяет передать static closure непосредственно в атрибут:

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

Также поддерживаются first-class callables:

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

`#[Created]` поддерживает такой же последний factory-аргумент.

После первых двух зарезервированных аргументов можно инъектировать дополнительные сервисы. Например, если в приложении зарегистрирован Symfony `SerializerInterface`, его можно запросить непосредственно по типу:

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

Третий и последующие параметры не ограничены сериализатором: в них можно запросить любой сервис, который способен разрешить Componenta DI. Первые две позиции всегда остаются настроенной respond-closure и downstream result. Это позволяет factory сначала сериализовать или спроецировать неподдерживаемый DTO, а затем передать преобразованное значение обычному Responder без инъекции самого Responder.


Если factory возвращает значение, не реализующее `ResponseInterface`, `RespondInterceptor` выбрасывает `UnexpectedValueException`.

## Статусы

```php
use Componenta\Interceptor\Http\Attribute\Created;
use Componenta\Interceptor\Http\Attribute\Respond;

#[Created]
public function create(): array {}

#[Respond(204)]
public function delete(): null {}
```

`#[Respond(204)]` вернёт пустой ответ, потому что это поведение задает `Componenta\Http\Responder`.

## HTTP-заголовки

Заголовки ответа можно передать в `#[Respond]` или `#[Created]` через аргумент `headers`. Значением заголовка может быть строка или массив строк.

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

Перехватчик применяет настроенные заголовки после `Responder::respond()`. Поэтому они добавляются и в случае, когда обработчик уже вернул `ResponseInterface`; если заголовок с таким именем уже существует, настроенное значение заменяет его. Настроенная `$respond` closure применяет эти headers к каждому создаваемому response. Factory отдельно получает исходный downstream result и может вызвать `$respond()` или `$respond($replacement)`; после возврата closure factory может переопределить эти headers.

Таким же образом заголовки можно передать непосредственно в перехватчик. При прямом создании также требуется DI-aware `CallableInvokerInterface`; при обычном использовании атрибута он автоматически поступает из контейнера:

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

## Миграция с 1.x

Версия 2.0 требует PHP 8.5+, но существующий порядок аргументов сохранён. Factory добавлен после `headers`, поэтому старые позиционные вызовы остаются корректными:

```php
#[Respond(201, 'application/json')]
#[Respond(200, 'application/json', ['Cache-Control' => 'no-store'])]

#[Created('application/problem+json')]
```

Factory можно передать именованным аргументом без заполнения неиспользуемых необязательных параметров:

```php
#[Respond(200, factory: self::decorate(...))]
#[Created(factory: static fn (Closure $respond, mixed $result): ResponseInterface =>
    $respond()->withHeader('Location', '/users/42')
)]
```

## Порядок с сериализацией

Если factory нужен исходный DTO, обычно следует сериализовать внутри factory, а не размещать `#[Serialize]` ниже `#[Respond]`:

```php
#[Respond(200, 'application/json', factory: UserResponseFactory::create(...))]
public function show(): User {}
```

Тогда factory получает исходный `User`, может инъектировать `SerializerInterface` и вызвать `$respond($serializer->serialize($result, 'json'))`. Если `#[Serialize]` всё же расположен ниже `#[Respond]`, вторым аргументом factory будет сериализованный результат внутренней цепочки, а не исходный DTO.

## Область выполнения

`RespondInterceptor` и `#[Respond]` ограничены областью `Scope::HTTP`. Они выполняются только когда интеграция маршрутизатора выставляет HTTP-область в `CallableContextInterface`.

## Лицензия

MIT
