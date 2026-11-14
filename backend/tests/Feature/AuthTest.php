<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

describe('inloggen', function () {
    test('lukt met de juiste gegevens en geeft een token', function () {
        $user = User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'geheim-wachtwoord-123',
        ]);

        $response = $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'geheim-wachtwoord-123',
        ]);

        $response->assertOk()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']])
            ->assertJsonPath('user.email', 'test@example.com');

        expect($user->tokens()->count())->toBe(1);
    });

    test('werkt ook met hoofdletters en spaties in het e-mailadres', function () {
        User::factory()->create([
            'email' => 'test@example.com',
            'password' => 'geheim-wachtwoord-123',
        ]);

        $this->postJson('/api/login', [
            'email' => '  Test@Example.COM ',
            'password' => 'geheim-wachtwoord-123',
        ])->assertOk();
    });

    test('geeft 422 bij een fout wachtwoord', function () {
        $user = User::factory()->create(['email' => 'test@example.com']);

        $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'fout-wachtwoord',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Deze gegevens kloppen niet.');

        expect($user->tokens()->count())->toBe(0);
    });

    test('geeft dezelfde melding bij een onbekend e-mailadres', function () {
        $this->postJson('/api/login', [
            'email' => 'bestaat-niet@example.com',
            'password' => 'wat-dan-ook-123',
        ])
            ->assertStatus(422)
            ->assertJsonPath('errors.email.0', 'Deze gegevens kloppen niet.');
    });

    test('geeft 422 als velden leeg zijn', function () {
        $this->postJson('/api/login', [])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email', 'password']);
    });

    test('geeft 429 na 5 pogingen binnen een minuut', function () {
        User::factory()->create(['email' => 'test@example.com']);

        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/api/login', [
                'email' => 'test@example.com',
                'password' => 'fout-wachtwoord',
            ])->assertStatus(422);
        }

        $this->postJson('/api/login', [
            'email' => 'test@example.com',
            'password' => 'fout-wachtwoord',
        ])->assertStatus(429);
    });
});

describe('registreren', function () {
    test('staat standaard uit en geeft 403', function () {
        config(['auth.registration_enabled' => false]);

        $this->postJson('/api/register', [
            'name' => 'Test',
            'email' => 'nieuw@example.com',
            'password' => 'sterk-wachtwoord-123',
            'password_confirmation' => 'sterk-wachtwoord-123',
        ])->assertForbidden();

        expect(User::count())->toBe(0);
    });

    test('maakt een account aan als de schakelaar aan staat', function () {
        config(['auth.registration_enabled' => true]);

        $response = $this->postJson('/api/register', [
            'name' => 'Test',
            'email' => 'Nieuw@Example.com',
            'password' => 'sterk-wachtwoord-123',
            'password_confirmation' => 'sterk-wachtwoord-123',
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'user' => ['id', 'name', 'email']]);

        $user = User::firstWhere('email', 'nieuw@example.com');

        expect($user)->not->toBeNull()
            ->and(Hash::check('sterk-wachtwoord-123', $user->password))->toBeTrue()
            ->and($user->password)->not->toBe('sterk-wachtwoord-123');
    });

    test('weigert een e-mailadres dat al bestaat', function () {
        config(['auth.registration_enabled' => true]);
        User::factory()->create(['email' => 'bestaat@example.com']);

        $this->postJson('/api/register', [
            'name' => 'Test',
            'email' => 'bestaat@example.com',
            'password' => 'sterk-wachtwoord-123',
            'password_confirmation' => 'sterk-wachtwoord-123',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['email']);
    });

    test('weigert een te kort wachtwoord of een herhaalveld dat niet klopt', function () {
        config(['auth.registration_enabled' => true]);

        $this->postJson('/api/register', [
            'name' => 'Test',
            'email' => 'nieuw@example.com',
            'password' => 'kort1',
            'password_confirmation' => 'iets-anders',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['password']);
    });
});

describe('ingelogde gebruiker ophalen', function () {
    test('geeft alleen id, naam en e-mail terug', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $response = $this->withToken($token)->getJson('/api/user');

        $response->assertOk()->assertJsonPath('data.id', $user->id);

        expect(array_keys($response->json('data')))->toBe(['id', 'name', 'email']);
    });

    test('geeft 401 zonder token', function () {
        $this->getJson('/api/user')->assertUnauthorized();
    });

    test('geeft 401 met een verlopen token (ouder dan 7 dagen)', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->travel(8)->days();

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    });
});

describe('uitloggen', function () {
    test('verwijdert het token en geeft 204', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertNoContent();

        expect($user->tokens()->count())->toBe(0);
    });

    test('daarna werkt het oude token niet meer', function () {
        $user = User::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withToken($token)->postJson('/api/logout')->assertNoContent();

        $this->app['auth']->forgetGuards();

        $this->withToken($token)->getJson('/api/user')->assertUnauthorized();
    });

    test('verwijdert alleen het token van dit apparaat', function () {
        $user = User::factory()->create();
        $laptopToken = $user->createToken('laptop')->plainTextToken;
        $user->createToken('telefoon');

        $this->withToken($laptopToken)->postJson('/api/logout')->assertNoContent();

        expect($user->tokens()->pluck('name')->all())->toBe(['telefoon']);
    });

    test('geeft 401 zonder token', function () {
        $this->postJson('/api/logout')->assertUnauthorized();
    });
});
