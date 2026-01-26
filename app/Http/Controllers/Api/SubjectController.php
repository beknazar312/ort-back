<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Subject;
use Illuminate\Http\JsonResponse;

class SubjectController extends Controller
{
    public function index(): JsonResponse
    {
        $subjects = Subject::active()
            ->ordered()
            ->withCount(['questions' => fn ($q) => $q->active()])
            ->get();

        return response()->json([
            'success' => true,
            'data' => $subjects->map(fn ($subject) => [
                'id' => $subject->id,
                'name' => $subject->name,
                'slug' => $subject->slug,
                'icon' => $subject->icon,
                'color' => $subject->color,
                'description' => $subject->description,
                'questions_count' => $subject->questions_count,
            ]),
        ]);
    }

    public function show(string $slug): JsonResponse
    {
        $subject = Subject::active()
            ->where('slug', $slug)
            ->withCount(['questions' => fn ($q) => $q->active()])
            ->first();

        if (!$subject) {
            return response()->json([
                'success' => false,
                'message' => 'Предмет не найден',
            ], 404);
        }

        return response()->json([
            'success' => true,
            'data' => [
                'id' => $subject->id,
                'name' => $subject->name,
                'slug' => $subject->slug,
                'icon' => $subject->icon,
                'color' => $subject->color,
                'description' => $subject->description,
                'questions_count' => $subject->questions_count,
            ],
        ]);
    }
}
