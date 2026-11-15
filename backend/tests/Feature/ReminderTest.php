<?php

use App\Models\Reminder;
use App\Models\ReminderCategory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\Sanctum;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->travelTo(Carbon::parse('2026-11-15 12:00'));
    $this->user = User::factory()->create();
    Sanctum::actingAs($this->user);
});

describe('reminders bekijken', function () {
    test('geeft je open reminders op deadline, zonder deadline onderaan', function () {
        Reminder::factory()->for($this->user)->create(['title' => 'Later', 'deadline' => '2026-11-20 23:59']);
        Reminder::factory()->for($this->user)->create(['title' => 'Geen deadline']);
        Reminder::factory()->for($this->user)->create(['title' => 'Eerst', 'deadline' => '2026-11-17 10:00']);
        Reminder::factory()->for($this->user)->completed()->create(['title' => 'Al af']);
        Reminder::factory()->create(['title' => 'Van iemand anders']);

        $this->getJson('/api/reminders')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.title', 'Eerst')
            ->assertJsonPath('data.1.title', 'Later')
            ->assertJsonPath('data.2.title', 'Geen deadline');
    });

    test('geeft met status=afgerond alleen afgevinkte reminders', function () {
        Reminder::factory()->for($this->user)->create(['title' => 'Open']);
        Reminder::factory()->for($this->user)->completed()->create(['title' => 'Af']);

        $this->getJson('/api/reminders?status=afgerond')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Af');
    });

    test('filtert op categorie', function () {
        $school = ReminderCategory::factory()->for($this->user)->create();
        Reminder::factory()->for($this->user)->create(['title' => 'School', 'reminder_category_id' => $school->id]);
        Reminder::factory()->for($this->user)->create(['title' => 'Zonder categorie']);

        $this->getJson("/api/reminders?category={$school->id}")
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'School')
            ->assertJsonPath('data.0.category.id', $school->id);
    });

    test('markeert een verlopen reminder', function () {
        Reminder::factory()->for($this->user)->create(['deadline' => '2026-11-14 10:00']);

        $this->getJson('/api/reminders')
            ->assertJsonPath('data.0.is_overdue', true);
    });
});

test('reminders zijn niet bereikbaar zonder in te loggen', function () {
    $this->app['auth']->forgetGuards();

    $this->getJson('/api/reminders')->assertUnauthorized();
});

describe('reminder aanmaken', function () {
    test('lukt met alleen een titel', function () {
        $this->postJson('/api/reminders', ['title' => 'Fiets maken'])
            ->assertCreated()
            ->assertJsonPath('data.title', 'Fiets maken')
            ->assertJsonPath('data.deadline', null)
            ->assertJsonPath('data.remind_at', null);
    });

    test('alleen een datum wordt 23:59, de tijd blijft leeg', function () {
        $this->postJson('/api/reminders', [
            'title' => 'Verslag',
            'deadline_date' => '2026-11-20',
            'remind_before' => 1440,
        ])
            ->assertCreated()
            ->assertJsonPath('data.deadline', '2026-11-20T23:59:00+01:00')
            ->assertJsonPath('data.deadline_date', '2026-11-20')
            ->assertJsonPath('data.deadline_time', null)
            ->assertJsonPath('data.remind_at', '2026-11-19T23:59:00+01:00');
    });

    test('alleen een tijd wordt vandaag', function () {
        $this->postJson('/api/reminders', [
            'title' => 'Bellen',
            'deadline_time' => '14:00',
        ])
            ->assertCreated()
            ->assertJsonPath('data.deadline', '2026-11-15T14:00:00+01:00')
            ->assertJsonPath('data.deadline_time', '14:00');
    });

    test('weigert een deadline in het verleden', function () {
        $this->postJson('/api/reminders', [
            'title' => 'Te laat',
            'deadline_date' => '2026-11-14',
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['deadline_date']);
    });

    test('weigert een herinneringsmoment dat al voorbij is', function () {
        $this->postJson('/api/reminders', [
            'title' => 'Vandaag',
            'deadline_time' => '13:00',
            'remind_before' => 1440,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['remind_before']);
    });

    test('weigert een herinnering zonder deadline', function () {
        $this->postJson('/api/reminders', [
            'title' => 'Geen deadline',
            'remind_before' => 60,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['remind_before']);
    });

    test('weigert een herinnering die geen keuze is', function () {
        $this->postJson('/api/reminders', [
            'title' => 'Raar',
            'deadline_date' => '2026-11-20',
            'remind_before' => 30,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['remind_before']);
    });

    test('weigert de categorie van iemand anders', function () {
        $category = ReminderCategory::factory()->create();

        $this->postJson('/api/reminders', [
            'title' => 'Stiekem',
            'reminder_category_id' => $category->id,
        ])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['reminder_category_id']);
    });

    test('weigert een lege titel', function () {
        $this->postJson('/api/reminders', ['title' => '   '])
            ->assertStatus(422)
            ->assertJsonValidationErrors(['title']);
    });
});

describe('reminder aanpassen', function () {
    test('een nieuwe deadline rekent de herinnering opnieuw uit', function () {
        $reminder = Reminder::factory()->for($this->user)->create([
            'deadline' => '2026-11-20 23:59',
            'remind_before' => 1440,
        ]);
        $reminder->forceFill(['sent_at' => now()])->save();

        $this->putJson("/api/reminders/{$reminder->id}", [
            'title' => $reminder->title,
            'deadline_date' => '2026-11-27',
            'remind_before' => 1440,
        ])
            ->assertOk()
            ->assertJsonPath('data.remind_at', '2026-11-26T23:59:00+01:00');

        expect($reminder->fresh()->sent_at)->toBeNull();
    });

    test('een verlopen reminder kun je nog aanpassen als de deadline gelijk blijft', function () {
        $reminder = Reminder::factory()->for($this->user)->create([
            'title' => 'Oud',
            'deadline' => '2026-11-14 10:00',
            'deadline_has_time' => true,
        ]);

        $this->putJson("/api/reminders/{$reminder->id}", [
            'title' => 'Nieuwe titel',
            'deadline_date' => '2026-11-14',
            'deadline_time' => '10:00',
        ])
            ->assertOk()
            ->assertJsonPath('data.title', 'Nieuwe titel');
    });

    test('geeft 404 bij de reminder van iemand anders', function () {
        $reminder = Reminder::factory()->create(['title' => 'Niet van jou']);

        $this->putJson("/api/reminders/{$reminder->id}", ['title' => 'Gehackt'])
            ->assertNotFound();

        expect($reminder->fresh()->title)->toBe('Niet van jou');
    });
});

describe('afvinken en terugzetten', function () {
    test('afvinken zet completed_at, nog een keer afvinken verandert de tijd niet', function () {
        $reminder = Reminder::factory()->for($this->user)->create();

        $this->patchJson("/api/reminders/{$reminder->id}/complete")
            ->assertOk()
            ->assertJsonPath('data.completed_at', '2026-11-15T12:00:00+01:00');

        $this->travel(5)->minutes();

        $this->patchJson("/api/reminders/{$reminder->id}/complete")
            ->assertJsonPath('data.completed_at', '2026-11-15T12:00:00+01:00');
    });

    test('terugzetten maakt de reminder weer open', function () {
        $reminder = Reminder::factory()->for($this->user)->completed()->create();

        $this->patchJson("/api/reminders/{$reminder->id}/uncomplete")
            ->assertOk()
            ->assertJsonPath('data.completed_at', null);
    });

    test('geeft 404 bij de reminder van iemand anders', function () {
        $reminder = Reminder::factory()->create();

        $this->patchJson("/api/reminders/{$reminder->id}/complete")->assertNotFound();

        expect($reminder->fresh()->completed_at)->toBeNull();
    });
});

describe('reminder verwijderen', function () {
    test('verwijdert je eigen reminder', function () {
        $reminder = Reminder::factory()->for($this->user)->create();

        $this->deleteJson("/api/reminders/{$reminder->id}")->assertNoContent();

        expect(Reminder::find($reminder->id))->toBeNull();
    });

    test('geeft 404 bij de reminder van iemand anders', function () {
        $reminder = Reminder::factory()->create();

        $this->deleteJson("/api/reminders/{$reminder->id}")->assertNotFound();

        expect(Reminder::find($reminder->id))->not->toBeNull();
    });
});

describe('pop-ups', function () {
    test('geeft alleen reminders waarvan de herinnering nu moet komen', function () {
        Reminder::factory()->for($this->user)->create([
            'title' => 'Nu',
            'deadline' => '2026-11-15 13:00',
            'remind_before' => 60,
        ]);
        Reminder::factory()->for($this->user)->create([
            'title' => 'Later',
            'deadline' => '2026-11-20 13:00',
            'remind_before' => 60,
        ]);
        Reminder::factory()->for($this->user)->completed()->create([
            'title' => 'Al af',
            'deadline' => '2026-11-15 13:00',
            'remind_before' => 60,
        ]);
        Reminder::factory()->create([
            'title' => 'Van iemand anders',
            'deadline' => '2026-11-15 13:00',
            'remind_before' => 60,
        ]);

        $this->getJson('/api/reminders/due')
            ->assertOk()
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.title', 'Nu');
    });

    test('na het tonen komt dezelfde pop-up niet nog een keer', function () {
        $reminder = Reminder::factory()->for($this->user)->create([
            'deadline' => '2026-11-15 13:00',
            'remind_before' => 60,
        ]);

        $this->patchJson("/api/reminders/{$reminder->id}/shown")->assertNoContent();

        $this->getJson('/api/reminders/due')->assertJsonCount(0, 'data');
    });
});
