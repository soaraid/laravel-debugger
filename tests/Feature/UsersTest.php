<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Schema;
use Soara\Debugger\Debugger;
use Soara\Debugger\Tests\Fixtures\AppUser;
use Soara\Debugger\Tests\Fixtures\User;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->migrateTestTables();
    Debugger::auth(fn () => true);
    config()->set('auth.providers.users.model', User::class);

    $this->dev = User::create(['name' => 'Dev Admin', 'email' => 'dev@team.example.com', 'password' => bcrypt('x')]);
    $this->andi = User::create(['name' => 'Andi Pratama', 'email' => 'andi@example.com', 'password' => bcrypt('x')]);
    $this->rizal = User::create(['name' => 'Rizal Hakim', 'email' => 'rizal@team.example.com', 'password' => bcrypt('x')]);

    Route::middleware('web')->get('/home', fn () => '<!doctype html><html><body><h1>Home</h1></body></html>');
    Route::middleware('web')->get('/api-like', fn () => response()->json(['ok' => true]));
});

it('lists users with log in as, that is you and protected', function () {
    Gate::define('beImpersonated', fn ($actor, $target) => $target->email !== 'rizal@team.example.com');

    $users = collect($this->actingAs($this->dev)->getJson('/debugger/api/users')->assertOk()->json('users'))->keyBy('name');

    expect($users['Andi Pratama']['refusal'])->toBeNull()
        ->and($users['Dev Admin']['refusal'])->toBe("That's you")
        ->and($users['Rizal Hakim']['refusal'])->toBe('Protected');
});

it('searches users', function () {
    $names = collect($this->actingAs($this->dev)->getJson('/debugger/api/users?q=andi')->json('users'))->pluck('name')->all();

    expect($names)->toBe(['Andi Pratama']);
});

it('logs in as another user, shows the bar on html pages and switches back', function () {
    config()->set('debugger.impersonation.redirect', '/home');

    $this->actingAs($this->dev)
        ->postJson("/debugger/api/users/{$this->andi->id}/impersonate")
        ->assertJsonPath('redirect', url('/home'));

    $this->assertAuthenticatedAs($this->andi);

    $this->get('/home')
        ->assertSee("You're signed in as", false)
        ->assertSee('Andi Pratama')
        ->assertSee('Back to Dev Admin');

    $this->get('/api-like')->assertExactJson(['ok' => true]);

    $this->post('/debugger/impersonation/stop')->assertRedirect('/debugger/users');

    $this->assertAuthenticatedAs($this->dev);
    $this->get('/home')->assertDontSee("You're signed in as", false);

    expect(DB::table('debugger_audit_logs')->pluck('action')->all())->toBe(['impersonation.start', 'impersonation.stop']);
});

it('lets the impersonated user switch back without debugger access', function () {
    $this->actingAs($this->dev)->postJson("/debugger/api/users/{$this->andi->id}/impersonate");

    Debugger::auth(fn ($request) => $request->user()?->is($this->dev));

    $this->post('/debugger/impersonation/stop')->assertRedirect();
    $this->assertAuthenticatedAs($this->dev);
});

it('refuses yourself, protected accounts, nesting and read-only mode', function () {
    Gate::define('beImpersonated', fn ($actor, $target) => $target->email !== 'rizal@team.example.com');
    $this->actingAs($this->dev);

    $this->postJson("/debugger/api/users/{$this->dev->id}/impersonate")->assertJsonPath('notice.tone', 'danger');
    $this->postJson("/debugger/api/users/{$this->rizal->id}/impersonate")->assertJsonPath('notice.text', 'This account is protected and cannot be impersonated.');
    $this->assertAuthenticatedAs($this->dev);

    $this->postJson("/debugger/api/users/{$this->andi->id}/impersonate");
    $this->postJson("/debugger/api/users/{$this->dev->id}/impersonate")->assertJsonPath('notice.tone', 'danger');
    $this->assertAuthenticatedAs($this->andi);

    $this->post('/debugger/impersonation/stop');
    config()->set('debugger.read_only', true);
    $this->postJson("/debugger/api/users/{$this->andi->id}/impersonate")->assertForbidden();
});

it('respects the impersonate gate', function () {
    Gate::define('impersonate', fn ($user) => false);

    $this->actingAs($this->dev)
        ->postJson("/debugger/api/users/{$this->andi->id}/impersonate")
        ->assertJsonPath('notice.text', "You aren't allowed to log in as other users.");
});

it('works with a custom users table, uuid keys and other column names', function () {
    Schema::create('app_users', function ($table) {
        $table->uuid('id')->primary();
        $table->string('full_name');
        $table->string('login_email');
        $table->string('password');
        $table->string('role');
        $table->timestamp('last_login_at')->nullable();
        $table->rememberToken();
        $table->timestamps();
    });

    config()->set('auth.providers.users.model', AppUser::class);
    config()->set('debugger.users.columns', ['name' => 'full_name', 'email' => 'login_email', 'last_active' => 'last_login_at']);
    config()->set('debugger.users.search', ['full_name', 'login_email']);
    Debugger::userRole(fn ($user) => ucfirst($user->role));

    $me = AppUser::create(['full_name' => 'Dev Admin', 'login_email' => 'dev@example.com', 'password' => 'x', 'role' => 'developer']);
    $budi = AppUser::create(['full_name' => 'Budi Santoso', 'login_email' => 'budi@example.com', 'password' => 'x', 'role' => 'admin', 'last_login_at' => now()->subMinutes(5)]);

    $users = $this->actingAs($me)->getJson('/debugger/api/users?q=budi')->json('users');

    expect($users)->toHaveCount(1)
        ->and($users[0])->toMatchArray(['name' => 'Budi Santoso', 'email' => 'budi@example.com', 'role' => 'Admin', 'last_active' => '5 minutes ago']);

    $this->postJson("/debugger/api/users/{$budi->id}/impersonate")->assertJsonPath('redirect', url('/'));
    $this->assertAuthenticatedAs($budi);
});

it('keeps the config cacheable: no closures in it', function () {
    $closures = collect(Arr::dot(require __DIR__.'/../../config/debugger.php'))
        ->filter(fn ($value) => $value instanceof Closure);

    expect($closures)->toBeEmpty();
});

it('limits the list with Debugger::userQuery', function () {
    Debugger::userQuery(fn ($query) => $query->where('email', 'like', '%@team.example.com'));

    $names = collect($this->actingAs($this->dev)->getJson('/debugger/api/users')->json('users'))->pluck('name')->all();

    expect($names)->toBe(['Dev Admin', 'Rizal Hakim']);
});
