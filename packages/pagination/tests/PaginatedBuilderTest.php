<?php

namespace Module\Pagination\Tests;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Http\Request as HttpRequest;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Schema;
use Module\Pagination\PaginatedBuilder;
use Module\Pagination\PaginationServiceProvider;
use Orchestra\Testbench\TestCase;

class Item extends Model
{
    public $timestamps = false;

    protected $guarded = [];

    public function newEloquentBuilder($query): PaginatedBuilder
    {
        return new ItemBuilder($query);
    }

    public function children()
    {
        return $this->hasMany(self::class, 'id');
    }
}

enum ItemRelation: string
{
    case Children = 'children';
}

class ItemBuilder extends PaginatedBuilder
{
    protected function relationEnum(): ?string
    {
        return ItemRelation::class;
    }

    protected function sortable(): array
    {
        return ['name' => 'name', 'code' => fn ($query, string $direction) => $query->orderBy('code', $direction)];
    }

    protected function defaultOrder(): void
    {
        $this->orderBy('name');
    }
}

class PaginatedBuilderTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [PaginationServiceProvider::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('database.default', 'testing');
        $app['config']->set('database.connections.testing', ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    }

    protected function setUp(): void
    {
        parent::setUp();

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('name')->nullable();
            $table->string('code')->nullable();
        });
        Item::insert(array_fill(0, 30, ['name' => 'x']));
    }

    public function test_returns_every_row_when_the_request_does_not_ask_for_pages(): void
    {
        $this->assertCount(30, Item::query()->pagination());
    }

    public function test_paginates_when_the_request_has_per_page_or_page(): void
    {
        $this->app['request']->merge(['per_page' => 10]);
        $this->app['request']->query->set('per_page', 10);

        $page = Item::query()->pagination();

        $this->assertInstanceOf(LengthAwarePaginator::class, $page);
        $this->assertCount(10, $page);
        $this->assertSame(30, $page->total());
    }

    public function test_bounds_the_page_size(): void
    {
        $this->app['request']->merge(['per_page' => 100000]);
        $this->assertSame(PaginatedBuilder::MAX_PER_PAGE, Item::query()->paginated()->perPage());

        $this->app['request']->merge(['per_page' => 0]);
        $this->assertSame(1, Item::query()->paginated()->perPage());
    }

    public function test_paginated_uses_the_default_without_per_page(): void
    {
        $this->assertSame(15, Item::query()->paginated()->perPage());
        $this->assertSame(7, Item::query()->paginated(7)->perPage());
    }

    public function test_searches_every_word_in_any_column_ignoring_case_and_accents(): void
    {
        Item::query()->delete();
        Item::insert([['name' => 'Universidad Católica', 'code' => 'UC'], ['name' => 'Sorbonne', 'code' => 'FR1']]);

        $names = fn (?string $text, array $columns = ['name', 'code']) => Item::query()->whereSearch($columns, $text)->pluck('name')->all();

        $this->assertSame(['Universidad Católica'], $names('catolica'));
        $this->assertSame(['Universidad Católica'], $names('UNIVERSIDAD uc'));
        $this->assertSame(['Sorbonne'], $names('fr1'));
        $this->assertSame([], $names('catolica', ['code']));
        $this->assertCount(2, $names('   '));
    }

    public function test_loads_only_the_allowed_relations(): void
    {
        $load = fn (array $relations) => array_keys(Item::query()->loadRelations($relations)->getEagerLoads());

        $this->assertSame(['children'], $load(['children']));
        $this->assertSame(['children'], $load(['children', 'secrets', 'children']));
        $this->assertSame([], $load(['secrets']));
        $this->assertSame([], $load([]));
        // A builder without an enum accepts no relation.
        $this->assertSame([], array_keys((new PaginatedBuilder(Item::query()->getQuery()))->setModel(new Item)->loadRelations(['children'])->getEagerLoads()));
    }

    public function test_sorts_by_the_known_names_and_falls_back_to_the_default_order(): void
    {
        Item::query()->delete();
        Item::insert([['name' => 'b', 'code' => '1'], ['name' => 'a', 'code' => '2']]);

        $names = fn (array $query) => Item::query()->sort(HttpRequest::create('/', 'GET', $query))->pluck('name')->all();

        $this->assertSame(['a', 'b'], $names([]));
        $this->assertSame(['b', 'a'], $names(['sort' => ['name' => 'desc']]));
        $this->assertSame(['b', 'a'], $names(['sort' => ['code' => 'asc']]));
        $this->assertSame(['a', 'b'], $names(['sort' => ['password' => 'desc']]));
        $this->assertSame(['name', 'code'], Item::query()->sortableNames());
    }
}
