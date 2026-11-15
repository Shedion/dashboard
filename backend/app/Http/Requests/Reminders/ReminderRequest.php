<?php

namespace App\Http\Requests\Reminders;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class ReminderRequest extends FormRequest
{
    public const REMIND_OPTIONS = [15, 60, 1440, 10080];

    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        $this->merge([
            'title' => trim((string) $this->input('title')),
        ]);
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:5000'],
            'reminder_category_id' => [
                'nullable',
                'integer',
                Rule::exists('reminder_categories', 'id')->where('user_id', $this->user()->id),
            ],
            'deadline_date' => ['nullable', 'date_format:Y-m-d'],
            'deadline_time' => ['nullable', 'date_format:H:i'],
            'remind_before' => ['nullable', 'integer', Rule::in(self::REMIND_OPTIONS)],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($validator->errors()->hasAny(['deadline_date', 'deadline_time', 'remind_before'])) {
                    return;
                }

                $deadline = $this->deadline();

                if ($deadline === null) {
                    if ($this->filled('remind_before')) {
                        $validator->errors()->add('remind_before', 'Een herinnering kan alleen met een deadline.');
                    }

                    return;
                }

                if ($this->deadlineIsUnchanged($deadline)) {
                    return;
                }

                if ($deadline->isPast()) {
                    $validator->errors()->add('deadline_date', 'De deadline mag niet in het verleden liggen.');

                    return;
                }

                if (
                    $this->filled('remind_before')
                    && $deadline->copy()->subMinutes((int) $this->input('remind_before'))->isPast()
                ) {
                    $validator->errors()->add('remind_before', 'Dit herinneringsmoment is al voorbij. Kies een andere datum/tijd.');
                }
            },
        ];
    }

    public function deadline(): ?Carbon
    {
        $date = $this->input('deadline_date');
        $time = $this->input('deadline_time');

        if (! $date && ! $time) {
            return null;
        }

        $day = $date ? Carbon::createFromFormat('Y-m-d', $date) : now();
        [$hour, $minute] = $time ? explode(':', $time) : [23, 59];

        return $day->setTime((int) $hour, (int) $minute);
    }

    public function reminderData(): array
    {
        return [
            'title' => $this->validated('title'),
            'description' => $this->validated('description'),
            'reminder_category_id' => $this->validated('reminder_category_id'),
            'deadline' => $this->deadline(),
            'deadline_has_time' => $this->filled('deadline_time'),
            'remind_before' => $this->deadline() ? $this->validated('remind_before') : null,
        ];
    }

    private function deadlineIsUnchanged(Carbon $deadline): bool
    {
        $id = $this->route('reminder');

        if (! $id) {
            return false;
        }

        $current = $this->user()->reminders()->find($id);

        return $current
            && $current->deadline?->equalTo($deadline)
            && $current->remind_before === ($this->filled('remind_before') ? (int) $this->input('remind_before') : null);
    }
}
