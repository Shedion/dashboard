<?php

namespace App\Http\Controllers\Reminders;

use App\Http\Controllers\Controller;
use App\Http\Requests\Reminders\ReminderCategoryRequest;
use App\Http\Resources\ReminderCategoryResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Http\Response;

class ReminderCategoryController extends Controller
{
    public function index(Request $request): AnonymousResourceCollection
    {
        $categories = $request->user()
            ->reminderCategories()
            ->withCount('reminders')
            ->orderBy('name')
            ->get();

        return ReminderCategoryResource::collection($categories);
    }

    public function store(ReminderCategoryRequest $request): JsonResponse
    {
        $category = $request->user()
            ->reminderCategories()
            ->create($request->validated());

        return (new ReminderCategoryResource($category))
            ->response()
            ->setStatusCode(201);
    }

    public function update(ReminderCategoryRequest $request, string $id): ReminderCategoryResource
    {
        $category = $request->user()->reminderCategories()->findOrFail($id);

        $category->update($request->validated());

        return new ReminderCategoryResource($category);
    }

    public function destroy(Request $request, string $id): Response
    {
        $request->user()->reminderCategories()->findOrFail($id)->delete();

        return response()->noContent();
    }
}
