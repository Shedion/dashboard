<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class ReminderResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'description' => $this->description,
            'category' => new ReminderCategoryResource($this->whenLoaded('category')),
            'deadline' => $this->deadline?->toIso8601String(),
            'deadline_date' => $this->deadline?->format('Y-m-d'),
            'deadline_time' => $this->deadline_has_time ? $this->deadline->format('H:i') : null,
            'remind_before' => $this->remind_before,
            'remind_at' => $this->remind_at?->toIso8601String(),
            'is_overdue' => $this->deadline !== null
                && $this->completed_at === null
                && $this->deadline->isPast(),
            'completed_at' => $this->completed_at?->toIso8601String(),
        ];
    }
}
