<?php

use App\Http\Responses\PasskeyLoginResponse;
use App\Models\User;
use App\Support\AuthRedirect;
use Illuminate\Http\Request;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('login intended forwards redirect query to the login page', function () {
    $this->get(route('login.intended', ['redirect' => '/cooks/5']))
        ->assertRedirect(route('login', ['redirect' => '/cooks/5']));
});

test('login screen stores intended redirect from query string', function () {
    $user = User::factory()->create();

    $this->get(route('login', ['redirect' => '/cooks/1']))
        ->assertOk();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/cooks/1');

    $this->assertAuthenticated();
});

test('auth middleware sends guests to login and back after authentication', function () {
    $user = User::factory()->create();

    $this->get('/cooks/new')
        ->assertRedirect(route('login'));

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/cooks/new');

    $this->assertAuthenticated();
});

test('users are redirected back to the page they came from after login', function () {
    $user = User::factory()->create();

    $this->get(route('login.intended', ['redirect' => '/cooks']))
        ->assertRedirect(route('login', ['redirect' => '/cooks']));

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'redirect' => '/cooks',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/cooks');

    $this->assertAuthenticated();
});

test('login redirect survives without session using posted redirect field', function () {
    $user = User::factory()->create();

    $this->get(route('login', ['redirect' => '/cooks/9']))
        ->assertOk();

    session()->forget('url.intended');

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
        'redirect' => '/cooks/9',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect('/cooks/9');

    $this->assertAuthenticated();
});

test('login ignores unsafe redirect targets', function () {
    $user = User::factory()->create();

    $this->get(route('login.intended', ['redirect' => 'https://evil.test/phish']))
        ->assertRedirect(route('login'));

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home', absolute: false));

    $this->assertAuthenticated();
});

test('login ignores protocol-relative redirect targets', function () {
    $user = User::factory()->create();

    $this->get(route('login.intended', ['redirect' => '//evil.test/phish']))
        ->assertRedirect(route('login'));

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home', absolute: false));

    $this->assertAuthenticated();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response
        ->assertSessionHasNoErrors()
        ->assertRedirect(route('home', absolute: false));

    $this->assertAuthenticated();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertSessionHasErrorsIn('email');

    $this->assertGuest();
});

test('users with two factor enabled are redirected to two factor challenge', function () {
    $this->skipUnlessFortifyHas(Features::twoFactorAuthentication());

    Features::twoFactorAuthentication([
        'confirm' => true,
        'confirmPassword' => true,
    ]);

    $user = User::factory()->withTwoFactor()->create();

    $response = $this->post(route('login.store'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $response->assertRedirect(route('two-factor.login'));
    $this->assertGuest();
});

test('login screen exposes intended redirect for passkey sign in', function () {
    $this->get('/cooks/new')
        ->assertRedirect(route('login'));

    $this->get(route('login'))
        ->assertOk()
        ->assertSee('data-auth-redirect', false)
        ->assertSee('value="/cooks/new"', false);
});

test('passkey login response honors posted redirect', function () {
    $request = Request::create('/passkeys/login', 'POST', [
        'redirect' => '/cooks/7',
    ]);
    $request->headers->set('Accept', 'application/json');
    $request->setLaravelSession(app('session.store'));

    $response = (new PasskeyLoginResponse)->toResponse($request);

    expect($response->getData(true))
        ->redirect->toBe('/cooks/7');
});

test('passkey login response honors persistent redirect session key', function () {
    $request = Request::create('/passkeys/login', 'POST');
    $request->headers->set('Accept', 'application/json');
    $request->setLaravelSession(app('session.store'));
    $request->session()->put(AuthRedirect::PERSISTENT_SESSION_KEY, '/cooks/8');

    $response = (new PasskeyLoginResponse)->toResponse($request);

    expect($response->getData(true))
        ->redirect->toBe('/cooks/8');
});

test('passkey options request persists redirect from query string', function () {
    $this->get(route('passkey.login-options', ['redirect' => '/cooks/9']))
        ->assertSuccessful()
        ->assertJsonStructure(['options']);

    expect(session(AuthRedirect::PERSISTENT_SESSION_KEY))->toBe('/cooks/9');
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->post(route('logout'));

    $response->assertRedirect(route('home'));

    $this->assertGuest();
});
