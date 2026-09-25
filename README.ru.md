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

Последний аргумент `#[Respond]` — необязательный factory ответа. Первым аргументом он принимает полностью сформированный `ResponseInterface`, вторым — `mixed` результат, возвращённый вложенной цепочкой обработчика/interceptor-ов. Factory обязан вернуть `ResponseInterface`.

Настроенные HTTP-заголовки применяются до factory, поэтому factory является финальным преобразованием ответа: он может заменить заголовки, изменить статус или выполнить любое другое иммутабельное PSR-7 преобразование.

PHP 8.5 позволяет передать static closure непосредственно в атрибут:

```php
use Psr\Http\Message\ResponseInterface;

#[Respond(
    200,
    'application/json',
    factory: static function (ResponseInterface $response, mixed $result): ResponseInterface {
        return $response
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

    private static function decorate(ResponseInterface $response, mixed $result): ResponseInterface
    {
        return $response->withHeader('Cache-Control', 'no-store');
    }
}
```

`#[Created]` поддерживает такой же последний factory-аргумент.

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

Перехватчик применяет настроенные заголовки после `Responder::respond()`. Поэтому они добавляются и в случае, когда обработчик уже вернул `ResponseInterface`; если заголовок с таким именем уже существует, настроенное значение заменяет его. Если настроен factory, он выполняется после применения этих заголовков и получает тот же результат вложенной цепочки, который был передан в `Responder::respond()`.

Таким же образом заголовки можно передать непосредственно в перехватчик:

```php
new RespondInterceptor(
    $responder,
    status: 200,
    contentType: 'application/json',
    headers: ['Cache-Control' => 'no-store'],
    factory: static function (ResponseInterface $response, mixed $result): ResponseInterface {
        return $response->withHeader('X-Response-Source', 'factory');
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
#[Created(factory: static fn (ResponseInterface $response, mixed $result): ResponseInterface =>
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
