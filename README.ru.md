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

## Callback ответа

Последний аргумент `#[Respond]` — необязательный callback ответа. Он принимает полностью сформированный `ResponseInterface` и обязан вернуть `ResponseInterface`.

Настроенные HTTP-заголовки применяются до callback, поэтому callback является финальным преобразованием ответа: он может заменить заголовки, изменить статус или выполнить любое другое иммутабельное PSR-7 преобразование.

PHP 8.5 позволяет передать static closure непосредственно в атрибут:

```php
use Psr\Http\Message\ResponseInterface;

#[Respond(
    200,
    'application/json',
    callback: static function (ResponseInterface $response): ResponseInterface {
        return $response
            ->withStatus(202)
            ->withHeader('X-Response-Source', 'callback');
    },
)]
public function show(): array {}
```

Также поддерживаются first-class callables:

```php
final class UserController
{
    #[Respond(200, callback: self::decorate(...))]
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

`#[Created]` поддерживает такой же последний callback-аргумент.

Если callback возвращает значение, не реализующее `ResponseInterface`, `RespondInterceptor` выбрасывает `UnexpectedValueException`.

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

Перехватчик применяет настроенные заголовки после `Responder::respond()`. Поэтому они добавляются и в случае, когда обработчик уже вернул `ResponseInterface`; если заголовок с таким именем уже существует, настроенное значение заменяет его. Если настроен callback, он выполняется после применения этих заголовков.

Таким же образом заголовки можно передать непосредственно в перехватчик:

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

## Миграция с 1.x

Версия 2.0 требует PHP 8.5+, но существующий порядок аргументов сохранён. Callback добавлен после `headers`, поэтому старые позиционные вызовы остаются корректными:

```php
#[Respond(201, 'application/json')]
#[Respond(200, 'application/json', ['Cache-Control' => 'no-store'])]

#[Created('application/problem+json')]
```

Callback можно передать именованным аргументом без заполнения неиспользуемых необязательных параметров:

```php
#[Respond(200, callback: self::decorate(...))]
#[Created(callback: static fn (ResponseInterface $response): ResponseInterface =>
    $response->withHeader('Location', '/users/42')
)]
```

## Порядок с сериализацией

Response-перехватчик должен быть внешним слоем, если ниже есть сериализация:

```php
#[Respond(200, 'application/json')]
#[Serialize]
public function show(): User {}
```

Возврат метода сначала пройдет через `#[Serialize]`, а затем сериализованная строка попадет в `#[Respond]`.

## Область выполнения

`RespondInterceptor` и `#[Respond]` ограничены областью `Scope::HTTP`. Они выполняются только когда интеграция маршрутизатора выставляет HTTP-область в `CallableContextInterface`.

## Лицензия

MIT
