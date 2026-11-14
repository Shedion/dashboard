<?php

use App\Models\ReminderCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

describe('categorieën bekijken', function () {
    test('geeft alleen je eigen categorieën, op naam gesorteerd', function () {
        $user = User::factory()->create();
        ReminderCategory::factory()->for($user)->create(['name' => 'Werk']);
        ReminderCategory::factory()->for($user)->create(['name' => 'School']);
        ReminderCategory::factory()->create(['name' => 'Van iemand anders']);

        Sanctum::actingAs($user);

        $this->getJson('/api/reminder-categories')
            ->assertOk()
            ->assertJsonCount(2, 'data')
            ->assertJsonPath('data.0.name', 'School')
            ->assertJsonPath('data.1.name', 'Werk');
    });

    test('telt hoeveel reminders er in een categorie zitten', function () {
        $user = User::factory()->create();
        $category = ReminderCategory::factory()->for($user)->create();
        $user->reminders()->create(['title' => 'Eén', 'reminder_category_id' => $category->id]);
        $user->reminders()->create(['title' => 'Twee', 'reminder_category_id' => $category->id]);

        Sanctum::actingAs($user);

        $this->getJson('/api/reminder-categories')
            ->assertOk()
            ->assertJsonPath('data.0.reminders_count', 2);
    });

    test('geeft 401 zonder token', function () {
        $this->getJson('/api/reminder-categories')->assertUnauthorized();
    });
});

describe('categorie aanmaken', function () {
    test('lukt met naam en kleur, en zet de kleur in hoofdletters', function () {
        $user = User::factory()->create();
        Sanctum::actingAs($user);

        $this->postJson('/api/reminder-categories', [
            'name' => ' School ',
            'color' => '#8b5cf6',
        ])
            ->assertCreated()
            ->assertJsonPath('data.name', 'School')
            ->assertJsonPath('data.color', '#8B5CF6');

        expect($user->reminderCategories()->count())->toBe(1);
    });

    test('lukt zonder kleur', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/reminder-categories', ['name' => 'Thuis'])
            ->assertCreated()
            ->assertJsonPath('data.color', null);
    });

    test('weigert een naam die je al gebruikt', function () {
        $user = User::factory()->create();
        ReminderCategory::factory()->for($user)->create(['name' => 'School']);
        Sanctum::actingAs($user);

        $this->postJson('/api/reminder-categories', ['name' => 'School'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['name']);
    });

    test('staat dezelfde naam toe als een andere gebruiker hem heeft', function () {
        ReminderCategory::factory()->create(['name' => 'School']);
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/reminder-categories', ['name' => 'School'])
            ->assertCreated();
    });

    test('weigert een kleur die je al gebruikt, ook met kleine letters', function () {
        $user = User::factory()->create();
        ReminderCategory::factory()->for($user)->create(['color' => '#8B5CF6']);
        Sanctum::actingAs($user);

        $this->postJson('/api/reminder-categories', ['name' => 'Nieuw', 'color' => '#8b5cf6'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['color']);
    });

    test('weigert een ongeldige kleur', function () {
        Sanctum::actingAs(User::factory()->create());

        $this->postJson('/api/reminder-categories', ['name' => 'Nieuw', 'color' => 'paars'])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['color']);
    });
});

describe('categorie aanpassen', function () {
    test('lukt bij je eigen categorie, ook met dezelfde naam', function () {
        $user = User::factory()->create();
        $category = ReminderCategory::factory()->for($user)->create(['name' => 'School']);
        Sanctum::actingAs($user);

        $this->putJson("/api/reminder-categories/{$category->id}", [
            'name' => 'School',
            'color' => '#3B82F6',
        ])
            ->assertOk()
            ->assertJsonPath('data.color', '#3B82F6');
    });

    test('geeft 404 bij de categorie van iemand anders', function () {
        $category = ReminderCategory::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->putJson("/api/reminder-categories/{$category->id}", ['name' => 'Gehackt'])
            ->assertNotFound();

        expect($category->fresh()->name)->not->toBe('Gehackt');
    });
});

describe('categorie verwijderen', function () {
    test('verwijdert je eigen categorie, de reminders blijven bestaan', function () {
        $user = User::factory()->create();
        $category = ReminderCategory::factory()->for($user)->create();
        $reminder = $user->reminders()->create(['title' => 'Blijft', 'reminder_category_id' => $category->id]);
        Sanctum::actingAs($user);

        $this->deleteJson("/api/reminder-categories/{$category->id}")
            ->assertNoContent();

        expect(ReminderCategory::find($category->id))->toBeNull()
            ->and($reminder->fresh())->not->toBeNull()
            ->and($reminder->fresh()->reminder_category_id)->toBeNull();
    });

    test('geeft 404 bij de categorie van iemand anders', function () {
        $category = ReminderCategory::factory()->create();
        Sanctum::actingAs(User::factory()->create());

        $this->deleteJson("/api/reminder-categories/{$category->id}")
            ->assertNotFound();

        expect(ReminderCategory::find($category->id))->not->toBeNull();
    });
});
