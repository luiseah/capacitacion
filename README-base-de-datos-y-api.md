# Base de datos y API REST en Laravel

**Autor:** Luis Eduardo Álvarez Henao · Senior Full Stack Developer
**LinkedIn:** [linkedin.com/in/luis-eah](https://www.linkedin.com/in/luis-eah)

> Más de 10 años de experiencia en desarrollo de software, especializado en arquitectura de aplicaciones, soluciones empresariales e integración de sistemas.
> Tecnologías: Laravel, PHP, Node.js, TypeScript, Docker y AWS.

Dos guías en un solo documento, con ejemplos del repositorio:

1. [Cómo nombrar tablas y columnas siguiendo el estándar de Laravel](#parte-1--base-de-datos)
2. [Cómo implementar una API REST y separarla de las vistas web](#parte-2--api-rest-y-vistas-web)

---

# Parte 1 · Base de datos

## 1.1 La regla de oro

Laravel adivina los nombres a partir del modelo. Si se siguen las convenciones no hay que configurar nada.

| Elemento | Convención | Ejemplo en el proyecto |
|---|---|---|
| Modelo | Singular, `StudlyCase` | `Student`, `ExternalParticipant` |
| Tabla | Plural, `snake_case` | `students`, `external_participants`, `request_types` |
| Clave primaria | `id` | `$table->id()` |
| Clave foránea | `{modelo_singular}_id` | `request_type_id`, `requester_id` |
| Tabla pivote | Los dos modelos en orden alfabético, singular | `role_user`; en convenios, `agreement_institution` |
| Marcas de tiempo | `created_at`, `updated_at` | `$table->timestamps()` |
| Borrado lógico | `deleted_at` | `$table->softDeletes()` |
| Booleanos | Prefijo `is_` o `has_` | `is_active`, `is_local` |
| Fechas con hora | Sufijo `_at` | `submitted_at`, `resolved_at` |
| Fechas sin hora | Sufijo `_on` | `starts_on`, `ends_on`, `signed_on` |
| Estados y tipos | Columna `string` con índice, no `enum` de la base | `status`, `priority`, `direction` |
| Datos libres | `json`/`jsonb` | `metadata` |

> Una palabra no contable no se pluraliza: `AdministrativeStaff` usa la tabla `administrative_staff`.

## 1.2 Tablas del proyecto

| Tabla | Para qué sirve |
|---|---|
| `users`, `password_reset_tokens`, `sessions`, `passkeys` | Autenticación |
| `roles`, `permissions`, `model_has_roles`, `model_has_permissions`, `role_has_permissions` | ACL de Spatie |
| `request_types`, `requests` | Solicitudes de servicio |
| `students`, `professors`, `administrative_staff` | Personas internas (cada una con su `user_id`) |
| `external_participants` | Visitantes de otras instituciones |
| `mobilities`, `mobility_requirements` | Movilidades y los requisitos copiados a cada una |
| `institutions`, `requirements` | Instituciones y catálogo de requisitos |
| `agreements`, `agreement_institution` | Convenios y sus instituciones (multilaterales) |
| `jobs`, `failed_jobs`, `job_batches`, `cache`, `cache_locks`, `audits`, `personal_access_tokens` | Infraestructura |

> **Prefijo en los paquetes:** los paquetes locales anteponen `academic_` a sus tablas (configurable con `table_prefix` en `config/academic-mobility.php`) para no chocar con las de la aplicación. En esta guía se nombran sin el prefijo.

## 1.3 Dónde el proyecto se aparta del estándar, y por qué

| Caso | Qué pasa | Motivo |
|---|---|---|
| `ServiceRequest` usa la tabla `requests` | El modelo declara `protected $table = 'requests'` | La entidad de negocio se llama "solicitud" y la ruta es `/requests`; el nombre `ServiceRequest` evita chocar con la clase `Request` de Laravel |
| `requests.uuid` además de `id` | La clave primaria sigue siendo `id`; la ruta usa `uuid` (`getRouteKeyName`) | No exponer ids secuenciales en las URLs |
| Claves `ulid` en los paquetes, `bigint` en la aplicación | Las tablas de los paquetes usan `ulid` | Los paquetes no dependen de una secuencia de la base de la aplicación y los ids se pueden generar antes de guardar |
| `participant_type` + `participant_id` en las movilidades | Referencia sin clave foránea | El participante puede ser un estudiante, un profesor, personal o un visitante, de tablas distintas |

Si se rompe una convención, se documenta el motivo en el modelo o en la migración.

## 1.4 Claves foráneas e índices

```php
$table->foreignId('request_type_id')->constrained()->restrictOnDelete();
$table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
$table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();

$table->index(['request_type_id', 'status']);
$table->index(['requester_id', 'status']);
```

- `constrained()` deduce la tabla del nombre de la columna; si el nombre no coincide (`requester_id` → `users`), se indica.
- Se elige siempre la acción al borrar: `restrictOnDelete()` (proteger), `cascadeOnDelete()` (los hijos mueren con el padre), `nullOnDelete()` (opcional).
- Se indexa lo que se filtra y ordena: llaves foráneas, `status`, y los pares que se consultan juntos (primero la columna más selectiva).
- Una regla que la base puede garantizar se deja a la base: en `institutions` solo puede haber **una** institución local, y lo impone un índice único parcial.

## 1.5 Migraciones

### Nombres

| Para | Nombre | Ejemplo |
|---|---|---|
| Crear una tabla | `create_{tabla}_table` | `create_agreements_table` |
| Agregar columnas | `add_{columna}_to_{tabla}_table` | `add_type_to_agreements_table` |
| Cambiar datos | verbo + qué | `rename_agreement_activities` |

Se generan con `docker compose exec backend php artisan make:migration add_status_to_requests_table`: el prefijo de fecha lo pone Artisan con la fecha real de creación; no se escribe a mano.

### Buenas prácticas

- **Una migración, un propósito.**
- **Nunca se edita una migración que ya corrió en un entorno compartido:** se crea una nueva.
- **Repetibles**, para paquetes y reinstalaciones:

```php
if ($schema->hasColumn($prefix.'mobilities', 'agreement_id')) {
    return;
}
```

- **Siempre con `down()`** cuando sea posible revertirla.
- **Sin lógica de aplicación** dentro: no se usan modelos de Eloquent (cambian con el tiempo); se usa `DB::table()`.
- **Una columna nueva con datos existentes** necesita `nullable()` o un `default()`.
- **Los seeders** también son repetibles: se saltan lo que ya existe y `migrate --seed` llena todo en una instalación nueva.

## 1.6 Esquema de pruebas

La base se llama `app`. El script de inicio crea el esquema `public` (la aplicación) y `testing` (las pruebas). `RefreshDatabase` solo borra tablas del esquema `testing`; los datos de desarrollo no se tocan. La extensión `unaccent` se instala una vez en `public` y sirve a los dos.

## 1.7 Buenas y malas prácticas

Cada práctica se contrasta igual: el **antipatrón** (código de ejemplo de lo que hay que evitar), el **patrón del proyecto** (código real, abreviado) y cuándo usarlo o evitarlo.

### 1.7.1 Nombres que Laravel no adivina, o el estándar

**Antipatrón**

```php
Schema::create('tbl_User', function (Blueprint $table) {
    $table->increments('UserID');
    $table->string('firstName');
    $table->integer('RoleID');
});

class User extends Model { protected $table = 'tbl_User'; protected $primaryKey = 'UserID'; }
```

**Patrón del proyecto**

```php
Schema::create('students', function (Blueprint $table) {
    $table->id();
    $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();
    // ...
});

class Student extends Model {}   // tabla, clave y llaves foráneas se deducen del nombre
```

- **Úsalo cuando:** siempre que el nombre sea el natural del modelo: no hay que configurar nada.
- **Evítalo cuando:** un nombre estándar chocaría con algo (como `Request` de Laravel): ahí se declara `protected $table` y se documenta el motivo, como en `ServiceRequest` → `requests`.

### 1.7.2 `enum` de la base, o `string` más enum de PHP

**Antipatrón**

```php
$table->enum('status', ['draft', 'active', 'expired']);
// agregar 'suspended' exige una migración que altera la columna, y es distinto en cada motor
```

**Patrón del proyecto**

```php
$table->string('status', 20)->default('active')->index();

enum AgreementStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Expired = 'expired';
}
// modelo: 'status' => AgreementStatus::class
```

- **Úsalo cuando:** el conjunto de valores puede crecer o cambiar con el negocio: se agrega un caso al enum, sin migración.
- **Evítalo cuando:** el dato lo crea o edita el usuario (programas, categorías): es una tabla con su propia fila, no un enum.

### 1.7.3 Llave foránea sin restricción, o con acción al borrar

**Antipatrón**

```php
$table->unsignedBigInteger('requester_id');   // nada impide un id que no existe
// borrar el usuario deja solicitudes huérfanas
```

**Patrón del proyecto**

```php
$table->foreignId('request_type_id')->constrained()->restrictOnDelete();
$table->foreignId('requester_id')->constrained('users')->restrictOnDelete();
$table->foreignId('assignee_id')->nullable()->constrained('users')->nullOnDelete();
```

- **Úsalo cuando:** siempre: la base garantiza la integridad y la acción al borrar queda escrita (`restrict` para proteger, `cascade` si los hijos no existen sin el padre, `null` si es opcional).
- **Evítalo cuando:** la referencia apunta a varias tablas (como `participant_type` + `participant_id`, que puede ser estudiante, profesor o visitante): ahí no se puede imponer una sola llave y se valida en el código.

### 1.7.4 Pivote con nombre libre, o en orden alfabético

**Antipatrón**

```php
Schema::create('institution_agreement', ...);                         // el orden no es alfabético
public function institutions() { return $this->belongsToMany(Institution::class, 'links'); }
```

**Patrón del proyecto**

```php
Schema::create('agreement_institution', function (Blueprint $table) {
    $table->foreignUlid('agreement_id')->constrained('agreements')->cascadeOnDelete();
    $table->foreignUlid('institution_id')->constrained('institutions')->restrictOnDelete();
});
```

- **Úsalo cuando:** una relación muchos a muchos: dos nombres en singular, orden alfabético.
- **Evítalo cuando:** la relación tiene datos propios (fecha, estado, rol): ya no es un pivote simple, sino una tabla con su modelo.

### 1.7.5 Booleanos y fechas sin una regla

**Antipatrón**

```php
$table->string('active');            // 'yes' / 'no' / '1' / 'Y'
$table->date('created');
$table->timestamp('end');            // ¿fecha o fecha y hora?
```

**Patrón del proyecto**

```php
$table->boolean('is_active')->default(true)->index();
$table->timestamp('submitted_at')->nullable();   // _at: momento exacto
$table->date('starts_on');                       // _on: solo el día
$table->date('ends_on')->nullable();
```

- **Úsalo cuando:** siempre: el sufijo dice qué es (`_at` con hora, `_on` sin hora) y `is_`/`has_` indica un booleano.
- **Evítalo cuando:** nunca guardes una fecha como texto ni un booleano como `'yes'/'no'`.

### 1.7.6 Filtrar sin índice, o indexar lo que se consulta

**Antipatrón**

```php
$table->string('status');
$table->foreignId('requester_id')->constrained('users');
// la lista "mis solicitudes por estado" recorre toda la tabla
```

**Patrón del proyecto**

```php
$table->index(['request_type_id', 'status']);
$table->index(['requester_id', 'status']);
$table->index(['institution_id', 'starts_on']);
```

- **Úsalo cuando:** una columna o un par se usa para filtrar y ordenar con frecuencia, y en las llaves foráneas.
- **Evítalo cuando:** la tabla es pequeña o la columna casi nunca se consulta: cada índice encarece las escrituras.

### 1.7.7 Editar una migración ya ejecutada, o crear otra repetible

**Antipatrón**

```php
// se edita la migración create_agreements_table, ya corrida en otros entornos
$table->string('status');   // agregado "a escondidas": a esos entornos nunca llega
```

**Patrón del proyecto**

```php
// una migración nueva, repetible
if ($schema->hasColumn('agreements', 'status')) {
    return;
}

$schema->table('agreements', function (Blueprint $table) {
    $table->string('status', 20)->default('active')->after('parties')->index();
});
```

- **Úsalo cuando:** el cambio ya está en un entorno compartido, o la migración viaja en un paquete que se reinstala.
- **Evítalo cuando:** nunca se edita una migración compartida; solo una que aún no salió de tu máquina.

### 1.7.8 Una regla que la base puede garantizar, o solo en PHP

**Antipatrón**

```php
// "solo una institución local", validado solo en PHP
if (Institution::where('is_local', true)->exists()) {
    throw new Exception('Ya hay una institución local');
}
Institution::create([...]);   // dos peticiones a la vez pasan la comprobación
```

**Patrón del proyecto**

```php
// la base lo impone: a lo sumo una fila con is_local = true
$connection->statement("create unique index {$name}_single_local on {$name} (is_local) where is_local");
```

- **Úsalo cuando:** la regla es "como máximo uno" o "nunca repetido" y la base lo soporta (índices únicos, parciales o restricciones).
- **Evítalo cuando:** la regla depende de la lógica de la aplicación (permisos, estados); eso se queda en el modelo o la policy. Y si el motor no soporta el índice (SQLite en las pruebas), la validación en PHP queda como respaldo.

### 1.7.9 Migraciones con modelos, o con la base

**Antipatrón**

```php
public function up(): void
{
    Agreement::all()->each(fn ($a) => $a->update(['activities' => ...]));
    // si mañana el modelo cambia (casts, columnas, eventos), esta migración deja de funcionar
}
```

**Patrón del proyecto**

```php
public function up(): void
{
    DB::table($table)->orderBy('id')->each(function ($row) use ($table) {
        DB::table($table)->where('id', $row->id)->update(['activities' => json_encode(...)]);
    });
}
```

- **Úsalo cuando:** la migración transforma datos: depende solo de la estructura de ese momento.
- **Evítalo cuando:** nunca importes un modelo de Eloquent en una migración: el modelo evoluciona y la migración vieja se rompe.

### Resumen

| Práctica | Úsala cuando | Evítala cuando |
|---|---|---|
| Nombres estándar | Siempre que sea posible | Choca con una clase del framework: declara `$table` y explica |
| `string` + enum de PHP | Los valores pueden crecer | El usuario crea los valores: es una tabla |
| `constrained()` con acción al borrar | Siempre | La referencia apunta a varias tablas |
| Pivote alfabético y singular | Muchos a muchos simple | La relación tiene datos propios |
| `is_`, `_at`, `_on` | Siempre | Nunca fechas ni booleanos como texto |
| Índices | Se filtra u ordena a menudo | Tabla pequeña o columna casi no consultada |
| Migración nueva y repetible | El cambio ya salió de tu máquina | Solo una que nadie más ejecutó |
| Regla en la base | "Como máximo uno" o "sin repetir" | Depende de lógica de la aplicación |
| `DB::table()` en migraciones | Transformar datos | Nunca modelos de Eloquent |

---

# Parte 2 · API REST y vistas web

## 2.1 Dos mundos separados

Una aplicación Inertia tiene dos tipos de ruta, y no se mezclan:

| | Vistas web | API |
|---|---|---|
| Archivo | `routes/web.php` | `routes/api.php` |
| Prefijo | ninguno (`/users`) | `/api/v1` (`/api/v1/users`) |
| Responde | Una página de Inertia | JSON con el envoltorio compartido |
| Controlador | `App\Http\Controllers\Web\…` | `App\Http\Controllers\Api\V1\…` |
| Contiene | Autoriza y entrega el id | Valida, consulta y responde |
| Middleware | `web`, `auth`, `verified` | `auth:sanctum` |

### El controlador web solo sirve la página

```php
/** Serves the user pages only; they receive the user id and load their data through the API. */
class UserController extends Controller
{
    public function edit(User $user): Response
    {
        Gate::authorize('update', $user);

        return Inertia::render('users/form', ['userId' => $user->id]);
    }
}
```

La página recibe **solo el id** y pide sus datos a la API. Así hay una única fuente de datos, la misma para la web, para una aplicación móvil o para otro sistema.

### El controlador de la API trae los datos

```php
public function index(UserIndexRequest $request): JsonResponse
{
    $users = User::query()
        ->applyFilters($request)
        ->paginated(15)
        ->through(fn (User $user) => (new UserResource($user))->resolve());

    return Response::api($users, __('Successful'));
}
```

## 2.2 Diseñar las URL

- **Sustantivos en plural**, nunca verbos: `/users`, no `/getUsers`.
- **El verbo HTTP es la acción.**
- **`kebab-case`** para varias palabras: `/request-types`.
- **Versionada**: `/api/v1`, para poder romper compatibilidad en `v2`.
- **Anidar poco**: `/requests/{serviceRequest}`, no cinco niveles.

| Acción | Verbo y ruta | Controlador |
|---|---|---|
| Listar | `GET /api/v1/users` | `index` |
| Ver uno | `GET /api/v1/users/{user}` | `show` |
| Crear | `POST /api/v1/requests/{type}` | `store` |
| Reemplazar o editar | `PUT /api/v1/users/{user}` | `update` |
| Eliminar | `DELETE /api/v1/users/{user}` | `destroy` |

Los nombres de ruta también siguen un patrón: `api.v1.users.index`. En los paquetes, `agreements.api.index` y `academic-mobility.api.mobilities.index`.

## 2.3 Códigos de estado

| Código | Cuándo | En el proyecto |
|---|---|---|
| 200 | Lectura o actualización correcta | `Response::api($datos)` |
| 201 | Recurso creado | `Response::api($datos, 'Agreement created', status: 201)` |
| 401 | No hay sesión | Guardia `auth:sanctum` |
| 403 | Hay sesión pero no permiso | Policy, con mensaje |
| 404 | No existe, o no debe verse | Route model binding, `denyAsNotFound()` |
| 409 | El estado actual lo impide | Borrar una institución con movilidades |
| 422 | Datos inválidos | Errores del `FormRequest` |

> El proyecto responde `200` con mensaje al eliminar (`Response::success`); una API estricta usaría `204 No Content`. Se eligió el envoltorio uniforme para que el frontend lea siempre `status` y `message`.

## 2.4 Respuesta uniforme

Un macro compartido (paquete `api-response`) da el mismo formato a toda la API:

```json
{
  "status": "success",
  "message": "Successful",
  "data": [ ... ],
  "current_page": 1,
  "per_page": 15,
  "total": 40
}
```

```php
Response::api($datos, 'Mensaje');         // éxito con datos
Response::success('Institution deleted');  // éxito sin datos
Response::error('motivo', 409);            // error controlado
```

## 2.5 Entrada de una lista: filtros, relaciones, orden y paginación

```
GET /api/v1/users?search=ana&role=admin&with[]=roles&sort[name]=desc&page=2
```

| Parámetro | Qué hace | Cómo se valida |
|---|---|---|
| `search`, `role`… | Filtros | `FormRequest` con `Rule::enum` |
| `with[]` | Relaciones a incluir | `Rule::enum(UserRelation::class)` + `distinct` |
| `sort[columna]` | Orden | `Rule::enum(SortDirection::class)` |
| `page`, `per_page` | Paginación (máximo 100) | `PaginatedBuilder::paginated()` |

Todo se valida en el `FormRequest` del `index` y se aplica en el builder dentro de `applyFilters`. Lo que no esté permitido responde `422`.

## 2.6 Autenticación

- **SPA propia (Inertia/React):** Sanctum con sesión (`$middleware->statefulApi()`); la página llama a `/api` con su cookie y no maneja tokens.
- **Clientes externos o móviles:** tokens personales de Sanctum (`HasApiTokens`).
- En ambos casos las rutas se protegen con `auth:sanctum` y los permisos con policies (ver [README-acl.md](README-acl.md)).

## 2.7 Recursos de Eloquent

El modelo nunca sale tal cual: un `Resource` decide los campos.

```php
public function toArray(Request $request): array
{
    return [
        'id' => $this->id,
        'roles' => $this->roles->pluck('name')->values(),
        'abilities' => [
            'update' => $request->user()?->can('update', $this->resource) ?? false,
            'delete' => $request->user()?->can('delete', $this->resource) ?? false,
        ],
    ];
}
```

## 2.8 Cómo la consume el frontend

```
Página (Inertia)  →  hook (TanStack Query)  →  servicio (UserService)  →  HttpClient  →  /api/v1/users
```

- El servicio centraliza las URL y los parámetros (`UserService`, `RequestService`).
- Los hooks manejan caché, estados de carga y errores.
- Wayfinder genera funciones tipadas de las rutas del backend.

## 2.9 Paquetes: la misma separación

| | Web | API |
|---|---|---|
| `academic-mobility` | `/academic-mobility/mobilities` | `/api/academic-mobility/mobilities` |
| `agreements` | `/agreements` | `/api/agreements` |

Cada paquete registra sus dos grupos de rutas y la aplicación decide el middleware desde la configuración (`api_middleware => ['api', 'auth:sanctum']`).

## 2.10 Probar la API

```php
$this->getJson('/api/v1/users')->assertUnauthorized();                       // 401
$this->getJson('/api/v1/users?with[]=password')->assertUnprocessable();      // 422
$this->getJson('/api/v1/users?with[]=roles')->assertOk();                    // 200
```

```bash
docker compose exec backend php artisan route:list --path=api
docker compose exec backend php artisan test --filter=UserApiTest
```

## 2.11 Buenas y malas prácticas

Cada práctica se contrasta igual: el **antipatrón** (código de ejemplo de lo que hay que evitar), el **patrón del proyecto** (código real, abreviado) y cuándo usarlo o evitarlo.

### 2.11.1 Un controlador para web y JSON, o dos separados

**Antipatrón**

```php
public function index(Request $request)
{
    $users = User::paginate(15);

    return $request->wantsJson()
        ? response()->json($users)
        : Inertia::render('users/index', ['users' => $users]);
}
```

**Patrón del proyecto**

```php
// Web: autoriza y sirve la página
class UserController extends Controller   // App\Http\Controllers\Web
{
    public function index(): Response
    {
        Gate::authorize('viewAny', User::class);

        return Inertia::render('users/index');
    }
}

// API: valida, consulta y responde
class UserController extends Controller   // App\Http\Controllers\Api\V1
{
    public function index(UserIndexRequest $request): JsonResponse { /* ... */ }
}
```

- **Úsalo cuando:** la aplicación tiene páginas y también API. Cada controlador tiene una sola razón para cambiar.
- **Evítalo cuando:** no hay API ni la habrá (una página sencilla que trae sus datos en las props): ahí un solo controlador web es suficiente.

### 2.11.2 URL con verbos, o sustantivos y el verbo HTTP

**Antipatrón**

```php
Route::get('/getUsers', ...);
Route::get('/users/delete/{id}', ...);        // un GET que borra
Route::post('/updateUser', ...);
```

**Patrón del proyecto**

```php
Route::get('users', [UserController::class, 'index'])->name('users.index');
Route::get('users/{user}', [UserController::class, 'show'])->name('users.show');
Route::put('users/{user}', [UserController::class, 'update'])->name('users.update');
Route::delete('users/{user}', [UserController::class, 'destroy'])->name('users.destroy');
```

- **Úsalo cuando:** siempre: el recurso va en la URL y la acción en el verbo. `GET` nunca cambia datos.
- **Evítalo cuando:** hay una acción que no es CRUD (por ejemplo "cambiar de estado"): se modela como un sub-recurso o un `POST` con nombre claro, no como `/doSomething`.

### 2.11.3 Siempre `200`, o el código correcto

**Antipatrón**

```php
if (! $user->can('delete', $target)) {
    return response()->json(['error' => 'No permitido'], 200);   // el cliente tiene que adivinar
}
```

**Patrón del proyecto**

```php
Response::error(__('This institution has mobilities and cannot be deleted.'), 409);
Response::api($datos, __('Agreement created'), status: 201);
// 401 sin sesión · 403 sin permiso (policy) · 404 · 422 datos inválidos (FormRequest)
```

- **Úsalo cuando:** siempre: el código de estado le dice al cliente qué pasó antes de leer el cuerpo.
- **Evítalo cuando:** nunca devuelvas `200` con un error dentro. Y en lo que el proyecto se aparta (`200` con mensaje al eliminar en lugar de `204`), se debe a un envoltorio uniforme y está documentado.

### 2.11.4 Devolver el modelo, o un Resource

**Antipatrón**

```php
return response()->json($user);   // incluye password, remember_token y lo que se agregue mañana
```

**Patrón del proyecto**

```php
return Response::api(new UserResource($user->load('roles')), __('Successful'));

// UserResource: campos explícitos
'id' => $this->id,
'roles' => $this->roles->pluck('name')->values(),
'abilities' => ['update' => $request->user()?->can('update', $this->resource) ?? false],
```

- **Úsalo cuando:** siempre que un modelo salga por la API: el contrato es el resource, no la tabla.
- **Evítalo cuando:** la respuesta es un simple mensaje (`Response::success('Institution deleted')`): no hay modelo que presentar.

### 2.11.5 Sin versión, o `/api/v1`

**Antipatrón**

```php
Route::get('/api/users', ...);   // cambiar el formato rompe a todos los clientes a la vez
```

**Patrón del proyecto**

```php
Route::prefix('v1')->name('api.v1.')->middleware('auth:sanctum')->group(function () {
    Route::get('users', [UserController::class, 'index'])->name('users.index');
});
// controladores en App\Http\Controllers\Api\V1
```

- **Úsalo cuando:** la API la consumen clientes que no despliegas contigo (móviles, otros sistemas): `v2` convive con `v1`.
- **Evítalo cuando:** la API es de uso interno y se despliega junto al frontend: la versión aporta poco, aunque dejar `v1` desde el inicio cuesta casi nada.

### 2.11.6 Listas sin paginar y filtros en el controlador, o un builder

**Antipatrón**

```php
$query = User::query();
if ($request->search) { $query->where('name', 'like', '%'.$request->search.'%'); }
if ($request->role)   { $query->whereHas('roles', fn ($q) => $q->where('name', $request->role)); }

return $query->get();          // toda la tabla en una respuesta
```

**Patrón del proyecto**

```php
$users = User::query()
    ->applyFilters($request)   // filtros, with[] y sort[...]
    ->paginated(15)            // per_page acotado a 100
    ->through(fn (User $user) => (new UserResource($user))->resolve());
```

- **Úsalo cuando:** cualquier lista que pueda crecer: filtros reutilizables en el builder y paginación siempre.
- **Evítalo cuando:** la lista es fija y pequeña (un catálogo de tres elementos): ahí `get()` basta, sin paginar.

### 2.11.7 Relaciones con nombres sin validar, o un enum en el FormRequest

**Antipatrón**

```php
User::query()->with($request->input('with'))->paginate();
// ?with=tokens carga relaciones que nadie debería ver, y un nombre inventado da un error 500
```

**Patrón del proyecto**

```php
// UserIndexRequest
'with' => ['nullable', 'array'],
'with.*' => ['distinct', Rule::enum(UserRelation::class)],
'sort.*' => [Rule::enum(SortDirection::class)],
```

```
GET /api/v1/users?with[]=roles&sort[name]=desc     → 200
GET /api/v1/users?with[]=password                  → 422
```

- **Úsalo cuando:** el cliente decide qué relaciones y qué orden quiere: una pantalla pide lo que muestra.
- **Evítalo cuando:** la respuesta tiene siempre las mismas relaciones y nadie más las pide: cárgalas fijas y no abras el parámetro.

### 2.11.8 Datos en el controlador web, o la página que los pide

**Antipatrón**

```php
return Inertia::render('users/form', [
    'user' => new UserResource($user->load('roles')),   // otra forma de traer los mismos datos
]);
```

**Patrón del proyecto**

```php
Gate::authorize('update', $user);

return Inertia::render('users/form', ['userId' => $user->id]);   // la página pide a la API
```

- **Úsalo cuando:** hay una API: la web, el móvil y otros sistemas comparten una sola fuente de datos y un solo contrato.
- **Evítalo cuando:** la página es estática o no tiene API detrás: ahí sí puedes pasar los datos como props.

### 2.11.9 Validar a mano, o un FormRequest

**Antipatrón**

```php
public function store(Request $request)
{
    if (! $request->name || strlen($request->name) > 200) {
        return response()->json(['error' => 'Nombre inválido'], 400);
    }
    if (! $request->user()->can('create', Agreement::class)) { abort(403); }
    // ...
}
```

**Patrón del proyecto**

```php
class AgreementRequest extends FormRequest
{
    public function authorize(): bool { return $this->user()->can('create', Agreement::class); }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:200'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
        ];
    }
}
```

- **Úsalo cuando:** en toda ruta que recibe datos: responde `422` con los errores por campo y `403` si no hay permiso.
- **Evítalo cuando:** la ruta no recibe datos y no tiene reglas propias. Aun así, un `FormRequest` con `authorize()` y `rules(): []` mantiene la autorización en un solo lugar.

### Resumen

| Práctica | Úsala cuando | Evítala cuando |
|---|---|---|
| Controlador web y de API separados | Hay páginas y API | No habrá API |
| Sustantivos y verbo HTTP | Siempre | Acciones fuera de CRUD: sub-recurso o `POST` con nombre claro |
| Código de estado correcto | Siempre | Nunca `200` con un error dentro |
| `Resource` | Sale un modelo | Solo se devuelve un mensaje |
| `/api/v1` | Clientes que no despliegas contigo | API interna que se despliega junto al frontend |
| Builder y paginación | La lista puede crecer | Catálogo fijo y pequeño |
| `with[]` validado con enum | El cliente decide qué relaciones | Siempre las mismas relaciones |
| La página pide a la API | Hay API | Página estática |
| `FormRequest` | Toda ruta que recibe datos | Rutas sin datos ni reglas |

## 2.12 Checklist para un endpoint nuevo

1. ¿Está en `routes/api.php`, bajo `/api/v1`, con sustantivo en plural y el verbo correcto?
2. ¿Tiene un `FormRequest` que valida y autoriza?
3. ¿El controlador solo delega y responde con `Response::api`?
4. ¿La salida pasa por un `Resource`?
5. ¿Las listas filtran, ordenan, incluyen relaciones y paginan desde el builder?
6. ¿Hay pruebas de 401, 403, 422 y del caso correcto?
7. ¿La vista web solo sirve la página y entrega el id?
