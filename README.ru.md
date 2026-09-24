# Componenta HTTP Respond Interceptor

HTTP-перехватчик для `componenta/interceptor`, который превращает результат обработчика маршрута в PSR-7 ответ через `Componenta\Http\Responder`.

**[English documentation](README.md)**

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

## Статусы

```php
use Componenta\Interceptor\Http\Attribute\Created;
use Componenta\Interceptor\Http\Attribute\Respond;

#[Created]
public function create(): array {}

#[Respond(204)]
public function delete(): null {}
```

`#[Respond(204)]` вернет пустой ответ, потому что это поведение задает `Componenta\Http\Responder`.

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

Перехватчик применяет настроенные заголовки после `Responder::respond()`. Поэтому они добавляются и в случае, когда обработчик уже вернул `ResponseInterface`; если заголовок с таким именем уже существует, настроенное значение заменяет его.

Таким же образом заголовки можно передать непосредственно в перехватчик:

```php
new RespondInterceptor(
    $responder,
    status: 200,
    contentType: 'application/json',
    headers: ['Cache-Control' => 'no-store'],
);
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
