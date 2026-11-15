<?php

namespace App\Http\Controllers\Reminders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reminders\ReminderRequest;
use App\Http\Resources\ReminderResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ReminderController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $query = $request->user()->reminders()->with('category');

        if ($request->query('status') === 'afgerond') {
            $query->whereNotNull('completed_at')->latest('completed_at');
        } else {
            $query->whereNull('completed_at')
                ->orderByRaw('deadline is null')
                ->orderBy('deadline');
        }

        if ($request->filled('category')) {
            $query->where('reminder_category_id', $request->query('category'));
        }

        return ReminderResource::collection($query->get());
    }

    public function store(ReminderRequest $request): JsonResponse
    {
        $reminder = $request->user()->reminders()->create($request->reminderData());

        return (new ReminderResource($reminder->load('category')))
            ->response()
            ->setStatusCode(201);
    }

    public function update(ReminderRequest $request, string $id): ReminderResource
    {
        $reminder = $request->user()->reminders()->findOrFail($id);

        $reminder->update($request->reminderData());

        return new ReminderResource($reminder->load('category'));
    }

    public function destroy(Request $request, string $id): Response
    {
        $request->user()->reminders()->findOrFail($id)->delete();

        return response()->noContent();
    }

    public function complete(Request $request, string $id): ReminderResource
    {
        $reminder = $request->user()->reminders()->findOrFail($id);

        $reminder->completed_at ??= now();
        $reminder->save();

        return new ReminderResource($reminder->load('category'));
    }

    public function uncomplete(Request $request, string $id): ReminderResource
    {
        $reminder = $request->user()->reminders()->findOrFail($id);

        $reminder->completed_at = null;
        $reminder->save();

        return new ReminderResource($reminder->load('category'));
    }

    public function due(Request $request): AnonymousResourceCollection
    {
        $reminders = $request->user()
            ->reminders()
            ->with('category')
            ->whereNull('completed_at')
            ->whereNull('sent_at')
            ->where('remind_at', '<=', now())
            ->orderBy('remind_at')
            ->get();

        return ReminderResource::collection($reminders);
    }

    public function markShown(Request $request, string $id): Response
    {
        $reminder = $request->user()->reminders()->findOrFail($id);

        $reminder->sent_at ??= now();
        $reminder->save();

        return response()->noContent();
    }
}
