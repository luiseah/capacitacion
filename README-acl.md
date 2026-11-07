# Control de acceso (ACL) con Spatie en Laravel y React

**Autor:** Luis Eduardo Álvarez Henao · Senior Full Stack Developer
**LinkedIn:** [linkedin.com/in/luis-eah](https://www.linkedin.com/in/luis-eah)

> Más de 10 años de experiencia en desarrollo de software, especializado en arquitectura de aplicaciones, soluciones empresariales e integración de sistemas.
> Tecnologías: Laravel, PHP, Node.js, TypeScript, Docker y AWS.

Guía práctica de cómo se implementa el control de acceso en este proyecto: qué hace cada pieza, cómo se usa en el backend y en el frontend, cómo se prueba y qué errores evitar. Todos los ejemplos salen del código del repositorio.

---

## 1. Idea central

```
Rol  ──tiene──▶  Permisos  ──se consultan como──▶  Habilidades (Gate / policy)
admin             users.index                       $user->can('users.index')
requester         requests.create                   Gate::authorize('delete', $user)
```

- Un **permiso** es un texto con el formato `recurso.acción` (`users.index`, `requests.create`). Se nombra igual que la ruta que protege.
- Un **rol** es un conjunto de permisos (`admin`, `requester`).
- Una **policy** decide una pregunta concreta sobre un recurso: "¿este usuario puede borrar a este otro?".
- **El backend es la autoridad.** El frontend solo usa los permisos para mostrar u ocultar la interfaz; nunca para proteger datos.

## 2. Las piezas del proyecto

| Pieza | Dónde | Qué hace |
|---|---|---|
| `PermissionName` | `app/Enums/PermissionName.php` | Lista cerrada de permisos (24). Única fuente de verdad. |
| `RoleName` | `app/Enums/RoleName.php` | Roles y los permisos que trae cada uno (`permissions()`). |
| `RolesAndPermissionsSeeder` | `database/seeders/` | Deja las tablas de Spatie iguales a los enums: crea lo que falta, sincroniza y borra lo que sobra. |
| `HasRoles` | `app/Models/User.php` | Trait de Spatie: `assignRole`, `givePermissionTo`, `can`, `hasRole`. |
| Policies | `app/Policies/` | `UserPolicy`, `ServiceRequestPolicy`. |
| `Gate::before` | `app/Providers/AuthServiceProvider.php` | El rol `admin` pasa todas las comprobaciones de permiso. |
| `FormRequest` | `app/Http/Requests/` | `authorize()` consulta la policy antes de validar. |
| `HandleInertiaRequests` | `app/Http/Middleware/` | Comparte `auth.roles` y `auth.permissions` con React. |
| `permissions:export-ts` | comando artisan | Genera `resources/js/generated/permissions.ts` desde los enums. |
| `usePermissions()` | `resources/js/hooks/` | `can()`, `hasRole()` para la interfaz. |
| Habilidades de los paquetes | `MobilityAbility`, `AgreementAbility` | Los paquetes deniegan todo por defecto; la aplicación concede con los mismos nombres. |

## 3. Backend

### 3.1 Definir permisos y roles en enums

```php
enum PermissionName: string
{
    case IndexRequests = 'requests.index';
    case CreateRequests = 'requests.create';
    case IndexUsers = 'users.index';
    case DestroyUsers = 'users.destroy';
    // ...
}

enum RoleName: string
{
    case Admin = 'admin';
    case Requester = 'requester';

    public function permissions(): array
    {
        return match ($this) {
            self::Admin => PermissionName::cases(),
            self::Requester => [PermissionName::CreateRequests],
        };
    }
}
```

Para agregar un permiso: se agrega el caso en el enum, se asigna al rol en `RoleName::permissions()` y se corre `php artisan db:seed --class=RolesAndPermissionsSeeder`. Después `php artisan permissions:export-ts` regenera el archivo de TypeScript.

### 3.2 Sembrar sin dejar basura

```php
app(PermissionRegistrar::class)->forgetCachedPermissions();

foreach (PermissionName::cases() as $permission) {
    Permission::findOrCreate($permission->value, 'web');
}

// Spatie guarda en caché la lista que cargó: hay que releerla o los roles no encuentran lo recién creado.
app(PermissionRegistrar::class)->forgetCachedPermissions();

foreach (RoleName::cases() as $role) {
    Role::findOrCreate($role->value, 'web')
        ->syncPermissions(array_map(fn (PermissionName $p) => $p->value, $role->permissions()));
}

Role::query()->whereNotIn('name', array_column(RoleName::cases(), 'value'))->delete();
Permission::query()->whereNotIn('name', array_column(PermissionName::cases(), 'value'))->delete();
```

El seeder es **idempotente**: se puede correr cuantas veces haga falta.

### 3.3 Policy: la pregunta sobre un recurso

```php
public function delete(User $user, User $target): Response|bool
{
    if (! $user->can(PermissionName::DestroyUsers->value)) {
        return Response::deny(__('You are not allowed to delete this user.'));
    }

    return $user->is($target)
        ? Response::deny(__('You cannot delete your own account here.'))
        : Response::allow();
}
```

- `Response::deny('mensaje')` lleva el texto que verá el cliente en el 403.
- La regla "nadie se elimina a sí mismo" es de dominio, así que vive en la policy.
- `Response::denyAsNotFound()` oculta un recurso que ese usuario no debe ni saber que existe (404 en vez de 403).

### 3.4 Dónde se consulta

**En el FormRequest** (rutas de la API que reciben datos o ejecutan acciones):

```php
class UserDestroyRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('delete', $this->route('user'));
    }

    public function rules(): array
    {
        return [];
    }
}
```

`Gate::inspect` devuelve la `Response` de la policy: el 403 de la API trae el mensaje de la regla.

**En el controlador web** (páginas):

```php
public function edit(User $user): Response
{
    Gate::authorize('update', $user);

    return Inertia::render('users/form', ['userId' => $user->id]);
}
```

### 3.5 `Gate::before` y sus límites

```php
Gate::before(function ($user, $ability, array $arguments) {
    return $arguments === [] && $user->hasRole(RoleName::Admin) ? true : null;
});
```

El admin pasa solo las comprobaciones **sin modelo** (`$user->can('users.index')`). Cuando se consulta contra un modelo (`Gate::authorize('delete', $user)`), manda la policy y sus reglas, como "nadie se elimina a sí mismo", valen también para el admin. Si el `before` devolviera `true` siempre, esa regla se saltaría.

### 3.6 Los paquetes: denegado por defecto

```php
class AgreementPolicy
{
    public function viewAny(Authorizable $user): Response
    {
        return $user->can(AgreementAbility::ViewAny->value)
            ? Response::allow()
            : Response::deny(__('You are not allowed to view agreements.'));
    }
}
```

El paquete no sabe cómo se conceden los permisos: la aplicación los crea con esos mismos nombres (`agreements.index`) y los asigna a los roles. Sin eso, nadie puede nada.

## 4. Frontend (React + Inertia)

### 4.1 Cómo llegan los permisos

`HandleInertiaRequests` comparte `auth.roles` y `auth.permissions` en cada página. El admin recibe todos los permisos, porque el backend le concede todo por el `Gate::before`.

### 4.2 Los nombres vienen del backend

```ts
// resources/js/generated/permissions.ts  (generado, no se edita)
export const Agreements = {
    create: 'agreements.create',
    index: 'agreements.index',
    // ...
} as const;
```

### 4.3 Usarlos

```tsx
const { can, hasRole } = usePermissions();

{can(Agreements.create) && <Button>Nuevo convenio</Button>}
```

- Los nombres autocompletan y un permiso que no existe es un error de TypeScript.
- Para decisiones **por fila** (¿puedo editar *este* usuario?) no se usan permisos globales: la API trae las habilidades de cada recurso.

```php
// UserResource
'abilities' => [
    'update' => $request->user()?->can('update', $this->resource) ?? false,
    'delete' => $request->user()?->can('delete', $this->resource) ?? false,
],
```

```tsx
{user.abilities.delete && <DeleteButton user={user} />}
```

Así la regla "no te puedes borrar a ti mismo" también oculta el botón, sin duplicarla en el frontend.

## 5. Pruebas prácticas

Ejecutar:

```bash
docker compose exec backend php artisan test --filter=ServiceRequestPolicyTest
docker compose exec backend php artisan test --filter=UserApiTest
docker compose exec backend bash -c 'cd packages/agreements && vendor/bin/phpunit'
```

### 5.1 Que la base sea igual a los enums

```php
public function test_seeder_creates_exactly_the_enum_roles_and_permissions(): void
{
    Role::create(['name' => 'legacy']);
    Permission::create(['name' => 'requests.manage']);

    $this->seed(RolesAndPermissionsSeeder::class);

    $this->assertEqualsCanonicalizing(array_column(RoleName::cases(), 'value'), Role::pluck('name')->all());
    $this->assertEqualsCanonicalizing(array_column(PermissionName::cases(), 'value'), Permission::pluck('name')->all());
}
```

### 5.2 Que cada rol tenga lo que debe

```php
$requester = User::factory()->create()->assignRole(RoleName::Requester->value);
$this->assertSame(['requests.create'], $requester->getAllPermissions()->pluck('name')->all());

foreach (PermissionName::cases() as $permission) {
    $this->assertTrue($admin->can($permission->value), $permission->value);
}
```

### 5.3 Los tres códigos de la API

```php
public function test_guests_get_401(): void
{
    $this->getJson('/api/v1/users')->assertUnauthorized();
}

public function test_users_without_permission_get_403(): void
{
    Sanctum::actingAs(User::factory()->create()->assignRole(RoleName::Requester->value));

    $this->getJson('/api/v1/users')->assertForbidden();
}
```

### 5.4 Probar la policy con solo el permiso que se prueba

```php
private function userWith(PermissionName ...$permissions): User
{
    return User::factory()->create()->givePermissionTo(array_map(fn ($p) => $p->value, $permissions));
}
```

Un usuario con **un** permiso demuestra que ese permiso basta y que los demás no se regalan. Probar siempre con un admin esconde errores.

### 5.5 En los paquetes: conceder una sola habilidad

```php
foreach ([...MobilityAbility::cases(), ...AgreementAbility::cases()] as $ability) {
    Gate::define($ability->value, fn ($user) => in_array($ability->value, $granted, true));
}
```

Cada prueba concede únicamente la habilidad que necesita; el resto queda denegado, igual que en producción.

### 5.6 Qué probar siempre

| Caso | Esperado |
|---|---|
| Sin sesión | 401 |
| Con sesión y sin permiso | 403 con el mensaje de la policy |
| Con el permiso justo | 200 / 201 |
| Regla de dominio (borrarse a sí mismo) | 403, incluso siendo admin |
| Recurso que no debe verse | 404 (`denyAsNotFound`) |

## 6. Buenas y malas prácticas

Cada práctica se contrasta igual: el **antipatrón** (código de ejemplo de lo que hay que evitar), el **patrón del proyecto** (código real, abreviado) y cuándo usarlo o evitarlo.

### 6.1 Preguntar por el rol, o por el permiso

**Antipatrón**

```php
// en el controlador A
if ($user->role === 'admin' || $user->role === 'coordinador') { ... }

// en la vista B, otra lista de roles
@if (in_array($user->role, ['admin', 'jefe'])) ... @endif

// mañana nace el rol "auditor": hay que editar todos los sitios
```

**Patrón del proyecto**

```php
$user->can(PermissionName::IndexUsers->value);   // el permiso, no el rol

// el rol es solo un conjunto de permisos
Role::findOrCreate('requester')->syncPermissions([PermissionName::CreateRequests->value]);
```

- **Úsalo cuando:** la decisión es "¿puede hacer esto?". Un rol nuevo se crea con otro conjunto de permisos, sin tocar el código.
- **Evítalo cuando:** de verdad importa quién eres, no qué puedes hacer (por ejemplo, mostrar un panel distinto según el rol). Ahí `hasRole()` es válido, pero no para proteger acciones.

### 6.2 Textos de permiso escritos a mano, o un enum

**Antipatrón**

```php
$user->can('users.indx');            // typo: devuelve false sin avisar
Permission::create(['name' => 'users.destroy']);   // creado a mano, sin seeder
```

```tsx
{permissions.includes('users.destroy') && <DeleteButton />}   // otra copia del texto
```

**Patrón del proyecto**

```php
$user->can(PermissionName::DestroyUsers->value);
```

```tsx
{can(Users.destroy) && <DeleteButton />}   // constante generada con permissions:export-ts
```

- **Úsalo cuando:** siempre. El permiso se escribe una vez, en el enum; el seeder y el archivo de TypeScript salen de ahí.
- **Evítalo cuando:** nunca hay motivo para escribir el texto a mano. Un permiso mal escrito no da error: simplemente niega.

### 6.3 Ocultar el botón, o autorizar en el servidor

**Antipatrón**

```tsx
// solo el frontend decide
{isAdmin && <DeleteButton />}
```

```php
public function destroy(User $user)      // la ruta no autoriza nada
{
    $user->delete();
}
```

**Patrón del proyecto**

```php
class UserDestroyRequest extends FormRequest
{
    public function authorize(): Response
    {
        return Gate::inspect('delete', $this->route('user'));
    }
}
```

```tsx
{user.abilities.delete && <DeleteButton user={user} />}   // solo para la interfaz
```

- **Úsalo cuando:** siempre: el backend decide y el frontend solo refleja esa decisión.
- **Evítalo cuando:** nunca se confía en el frontend para proteger datos. Cualquiera puede llamar a la API sin la pantalla.

### 6.4 `Gate::before` que lo permite todo, o limitado a comprobaciones sin modelo

**Antipatrón**

```php
Gate::before(function ($user) {
    return $user->hasRole('admin') ? true : null;
});
// el admin se salta TODAS las policies: también puede borrarse a sí mismo
```

**Patrón del proyecto**

```php
Gate::before(function ($user, $ability, array $arguments) {
    return $arguments === [] && $user->hasRole(RoleName::Admin) ? true : null;
});
// solo `$user->can('users.index')`; con un modelo manda la policy
```

- **Úsalo cuando:** quieres que el admin pase las comprobaciones de permiso simples y que las reglas de dominio (no borrarse a uno mismo) sigan valiendo para todos.
- **Evítalo cuando:** hay reglas que ni el admin debe saltarse. En ese caso no devuelvas `true` si la comprobación trae un modelo.

### 6.5 La regla copiada en cada sitio, o una policy

**Antipatrón**

```php
// en la vista
if ($user->id !== $target->id && $user->can('users.destroy')) { ... }

// en el controlador, la misma regla un poco distinta
if ($user->can('users.destroy')) { $target->delete(); }
```

**Patrón del proyecto**

```php
public function delete(User $user, User $target): Response|bool
{
    if (! $user->can(PermissionName::DestroyUsers->value)) {
        return Response::deny(__('You are not allowed to delete this user.'));
    }

    return $user->is($target)
        ? Response::deny(__('You cannot delete your own account here.'))
        : Response::allow();
}
```

- **Úsalo cuando:** la respuesta depende del usuario **y** del recurso (el dueño, el estado, "no a uno mismo").
- **Evítalo cuando:** solo importa un permiso global sin recurso (ver una lista): basta `Gate::authorize('viewAny', User::class)` o `can()`.

### 6.6 Olvidar la caché al sembrar

**Antipatrón**

```php
foreach (PermissionName::cases() as $permission) {
    Permission::findOrCreate($permission->value, 'web');
}
Role::findOrCreate('admin', 'web')->syncPermissions($permisos);
// Spatie guardó la lista vieja: "There is no permission named requests.index"
```

**Patrón del proyecto**

```php
app(PermissionRegistrar::class)->forgetCachedPermissions();
// ... crea los permisos ...
app(PermissionRegistrar::class)->forgetCachedPermissions();   // relee antes de asignar a los roles
// ... sincroniza los roles ...
```

- **Úsalo cuando:** el seeder crea permisos y luego los asigna en la misma ejecución, o cambia permisos con la aplicación en marcha.
- **Evítalo cuando:** nunca lo omitas en una instalación nueva: la base empieza vacía y es donde más falla.

### 6.7 Un permiso global para cada fila, o habilidades por recurso

**Antipatrón**

```tsx
// "puede editar usuarios" es verdad para todos, incluido uno mismo
{can(Users.update) && <EditButton user={user} />}
```

**Patrón del proyecto**

```php
// UserResource
'abilities' => [
    'update' => $request->user()?->can('update', $this->resource) ?? false,
    'delete' => $request->user()?->can('delete', $this->resource) ?? false,
],
```

```tsx
{user.abilities.update && <EditButton user={user} />}
```

- **Úsalo cuando:** la decisión cambia de una fila a otra (el dueño, el estado, "no a uno mismo").
- **Evítalo cuando:** la decisión es la misma para toda la pantalla (mostrar el menú "Usuarios"): ahí basta un permiso global con `can()`.

### 6.8 Probar solo con el admin, o con el permiso exacto

**Antipatrón**

```php
public function test_lists_users(): void
{
    $this->actingAs($this->admin());          // el admin pasa todo: no se prueba el permiso

    $this->getJson('/api/v1/users')->assertOk();
}
```

**Patrón del proyecto**

```php
$this->getJson('/api/v1/users')->assertUnauthorized();                 // sin sesión: 401

Sanctum::actingAs(User::factory()->create()->assignRole(RoleName::Requester->value));
$this->getJson('/api/v1/users')->assertForbidden();                    // sin permiso: 403

Sanctum::actingAs($this->userWith(PermissionName::IndexUsers));        // el permiso justo
$this->getJson('/api/v1/users')->assertOk();
```

- **Úsalo cuando:** siempre: sin sesión, sin permiso y con el permiso exacto, más el caso de dominio (borrarse a sí mismo).
- **Evítalo cuando:** nunca pruebes solo con el admin: un permiso mal nombrado pasaría desapercibido.

### 6.9 Un 403 mudo, o con el motivo

**Antipatrón**

```php
public function update(User $user, ServiceRequest $request): bool
{
    return $user->can('requests.update') || $request->requester_id === $user->id;
}
// el cliente solo ve "This action is unauthorized."
```

**Patrón del proyecto**

```php
return Response::deny($this->isOwner($user, $request)
    ? __('Only draft requests can be edited.')
    : __('You are not allowed to edit this request.'));
```

- **Úsalo cuando:** el motivo ayuda a quien recibe el rechazo (el dueño sabe que debe ser borrador).
- **Evítalo cuando:** revelar el motivo expondría que el recurso existe: ahí usa `Response::denyAsNotFound()`.

### Resumen

| Práctica | Úsala cuando | Evítala cuando |
|---|---|---|
| Permisos en vez de roles | Se decide qué puede hacer | Se cambia la pantalla según el rol |
| Enum de permisos | Siempre | Nunca |
| Autorizar en el servidor | Siempre | Nunca confiar solo en el frontend |
| `Gate::before` acotado | El admin pasa permisos simples | Hay reglas que el admin no debe saltarse |
| Policy | Depende del usuario y del recurso | Es un permiso global sin recurso |
| `forgetCachedPermissions()` | El seeder crea y asigna | Nunca omitirlo en una instalación nueva |
| `abilities` por fila | La decisión cambia por recurso | Es igual para toda la pantalla |
| Prueba con el permiso exacto | Siempre | Nunca solo con el admin |
| `Response::deny('motivo')` | El motivo ayuda | Revelaría que el recurso existe |

## 7. Checklist al crear una función nueva

1. ¿Existe el permiso en `PermissionName` y está asignado en `RoleName::permissions()`?
2. ¿La policy responde con `Response::allow()/deny('motivo')`?
3. ¿El FormRequest o el controlador la consultan?
4. ¿Corrí `permissions:export-ts` y el frontend usa la constante?
5. ¿Hay pruebas de 401, 403 y del caso permitido?
6. ¿La API trae `abilities` si la pantalla decide por fila?
