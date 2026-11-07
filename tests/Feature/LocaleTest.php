<?php

namespace Tests\Feature;

use App\Enums\RoleName;
use App\Models\User;
use Database\Seeders\RolesAndPermissionsSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Laravel\Sanctum\Sanctum;
use Module\AcademicMobility\Core\Exceptions\InvalidRoute;
use Tests\TestCase;

class LocaleTest extends TestCase
{
    use RefreshDatabase;

    private function admin(): User
    {
        $this->seed(RolesAndPermissionsSeeder::class);

        return Sanctum::actingAs(User::factory()->create()->assignRole(RoleName::Admin->value));
    }

    public function test_the_default_language_is_english(): void
    {
        $this->admin();

        $this->getJson('/api/v1/users')->assertOk()->assertJsonPath('message', 'Successful');
    }

    public function test_the_locale_cookie_picks_the_language_of_the_api_and_the_pages(): void
    {
        $this->admin();

        $this->withCredentials()->withUnencryptedCookie('locale', 'es')
            ->getJson('/api/v1/users')
            ->assertOk()
            ->assertJsonPath('message', 'Operación exitosa');

        $this->withUnencryptedCookie('locale', 'es')->get('/')->assertInertia(fn (Assert $page) => $page
            ->where('locale', 'es')
            ->where('locales', ['en', 'es'])
            ->where('translations.Users', 'Usuarios')
            // The package's own texts travel with the app's.
            ->where('translations.New mobility', 'Nueva movilidad'));
    }

    public function test_the_browser_language_is_used_without_a_cookie_and_unsupported_ones_fall_back(): void
    {
        $this->admin();

        $this->getJson('/api/v1/users', ['Accept-Language' => 'es-CO,es;q=0.9,en;q=0.5'])->assertJsonPath('message', 'Operación exitosa');
        $this->getJson('/api/v1/users', ['Accept-Language' => 'fr-FR,fr;q=0.9'])->assertJsonPath('message', 'Successful');
        $this->withCredentials()->withUnencryptedCookie('locale', 'fr')->getJson('/api/v1/users')->assertJsonPath('message', 'Successful');
    }

    public function test_the_cookie_wins_over_the_browser_language(): void
    {
        $this->admin();

        $this->withCredentials()->withUnencryptedCookie('locale', 'en')
            ->getJson('/api/v1/users', ['Accept-Language' => 'es'])
            ->assertJsonPath('message', 'Successful');
    }

    public function test_the_switcher_remembers_the_language(): void
    {
        $this->post('/locale', ['locale' => 'es'])->assertRedirect()->assertCookie('locale', 'es', encrypted: false);
    }

    public function test_the_switcher_only_accepts_supported_languages(): void
    {
        $this->post('/locale', ['locale' => 'fr'])->assertSessionHasErrors('locale')->assertCookieMissing('locale');
        $this->post('/locale', [])->assertSessionHasErrors('locale');
    }

    public function test_validation_messages_follow_the_language(): void
    {
        $this->admin();
        $target = User::factory()->create();

        $this->withCredentials()->withUnencryptedCookie('locale', 'es')
            ->putJson("/api/v1/users/{$target->id}", ['roles' => []])
            ->assertUnprocessable()
            ->assertJsonPath('errors.name.0', 'El campo nombre es obligatorio.');
    }

    public function test_package_messages_are_translated(): void
    {
        app()->setLocale('es');

        $this->assertSame('Institución creada', __('Institution created'));
        $this->assertStringContainsString('institución local', InvalidRoute::counterpartIsLocal()->getMessage());

        app()->setLocale('en');
        $this->assertSame('The other institution cannot be the local institution.', InvalidRoute::counterpartIsLocal()->getMessage());
    }
}
