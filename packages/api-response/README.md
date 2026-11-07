# module/api-response

One JSON envelope for every Laravel API of the family (the host app and the `module/*` packages).

```json
{ "status": "success", "message": "Done", "data": { } }
{ "status": "success", "data": [ ], "current_page": 1, "last_page": 3, "per_page": 15, "total": 40 }
{ "status": "error", "message": "Nope" }
```

## Install

```bash
composer require module/api-response
```

The service provider is auto-discovered.

## Use

Three macros on the `Response` facade:

```php
use Illuminate\Support\Facades\Response;

Response::api($resource, __('Successful'));                 // 200; also takes a LengthAwarePaginator
Response::api($resource, __('Created'), status: 201);       // custom status
Response::api($data, extra: ['priorities' => ['low']]);     // extra top-level fields
Response::success(__('Deleted'));                           // message only
Response::error(__('Not allowed'), 403);
```

A paginator is spread next to `status` (`data`, `current_page`, `last_page`, `per_page`, `total`, links),
which is what the front end's `HttpClient::paginated()` reads.

Packages that use the macros should `require` this package so they exist wherever it is installed.

## Tests

```bash
composer install && vendor/bin/phpunit
```
