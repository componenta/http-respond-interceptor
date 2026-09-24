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
    #[Respond(status: 200, contentType: 'application/json')]
    public function __invoke(): array
    {
        return ['status' => 'ok'];
    }
}
```

`#[Respond]` наследует `Componenta\Interceptor\Attribute\Intercept`. `AttributeInterceptor` создает `RespondInterceptor` через контейнер, а сам `RespondInterceptor` вызывает `Responder::respond($status, $result, $contentType)`.

## Callback ответа

Первый аргумент `#[Respond]` — необязательный callback ответа. Он принимает полностью сформированный `ResponseInterface` и обязан вернуть `ResponseInterface`.

Настроенные HTTP-заголовки применяются до callback, поэтому callback является финальным преобразованием ответа: он может заменить заголовки, изменить статус или выполнить любое другое иммутабельное PSR-7 преобразование.

PHP 8.5 позволяет передать static closure непосредственно в атрибут:

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

Также поддерживаются first-class callables:

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

`#[Created]` поддерживает такой же первый callback-аргумент.

Если callback возвращает значение, не реализующее `ResponseInterface`, `RespondInterceptor` выбрасывает `UnexpectedValueException`.

## Статусы

```php
use Componenta\Interceptor\Http\Attribute\Created;
use Componenta\Interceptor\Http\Attribute\Respond;

#[Created]
public function create(): array {}

#[Respond(status: 204)]
public function delete(): null {}
```

`#[Respond(status: 204)]` вернёт пустой ответ, потому что это поведение задает `Componenta\Http\Responder`.

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

Версия 2.0 требует PHP 8.5+. Первым позиционным аргументом `#[Respond]` теперь является необязательный callback, поэтому статус и Content-Type следует передавать по имени:

```php
// 1.x
#[Respond(201, 'application/json')]

// 2.x
#[Respond(status: 201, contentType: 'application/json')]
```

Первым позиционным аргументом `#[Created]` теперь также является callback. Старые позиционные вызовы Content-Type следует заменить именованным аргументом:

```php
// 1.x
#[Created('application/problem+json')]

// 2.x
#[Created(contentType: 'application/problem+json')]
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
