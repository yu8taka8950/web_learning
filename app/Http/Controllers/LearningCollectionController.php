<?php

namespace App\Http\Controllers;

use App\Models\LearningCollection;
use App\Models\LearningSet;
use App\Services\LearningCollectionAutoAssignService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class LearningCollectionController extends Controller
{
    public function index(Request $request): View
    {
        $collections = $request->user()->learningCollections()
            ->with(['learningSets' => fn ($query) => $query->withCount('questions')->latest('created_at')])
            ->latest('updated_at')
            ->get()
            ->each(function (LearningCollection $collection): void {
                $collection->setAttribute('learning_sets_count', $collection->learningSets->count());
                $collection->setAttribute('questions_count', $collection->learningSets->sum('questions_count'));
            });

        return view('collections.index', compact('collections'));
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);

        $collection = $request->user()->learningCollections()->create($validated);

        return redirect()->route('collections.show', $collection)->with('status', 'コレクションを作成しました。');
    }

    public function show(Request $request, LearningCollection $collection): View
    {
        $this->ensureOwnership($request, $collection);
        $collection->load(['learningSets' => fn ($query) => $query->withCount('questions')->latest('created_at')]);
        $questionCount = $collection->learningSets->sum('questions_count');

        return view('collections.show', compact('collection', 'questionCount'));
    }

    public function update(Request $request, LearningCollection $collection): RedirectResponse
    {
        $this->ensureOwnership($request, $collection);
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
        ]);
        $collection->update($validated);

        return redirect()->route('collections.show', $collection)->with('status', 'コレクションを更新しました。');
    }

    public function destroy(Request $request, LearningCollection $collection): RedirectResponse
    {
        $this->ensureOwnership($request, $collection);
        $collection->delete();

        return redirect()->route('collections.index')->with('status', 'コレクションを削除しました。学習セットは保持されています。');
    }

    public function deleted(Request $request): View
    {
        $deletedCollections = LearningCollection::onlyTrashed()
            ->whereBelongsTo($request->user())
            ->latest('deleted_at')
            ->get();
        $deletedLearningSets = LearningSet::onlyTrashed()
            ->whereBelongsTo($request->user())
            ->withCount('questions')
            ->latest('deleted_at')
            ->get();

        return view('collections.deleted', compact('deletedCollections', 'deletedLearningSets'));
    }

    public function restore(Request $request, int $collection): RedirectResponse
    {
        $deletedCollection = LearningCollection::onlyTrashed()
            ->whereKey($collection)
            ->whereBelongsTo($request->user())
            ->firstOrFail();
        $deletedCollection->restore();

        return redirect()->route('collections.show', $deletedCollection)->with('status', 'コレクションを復元しました。');
    }

    public function autoOrganize(Request $request, LearningCollectionAutoAssignService $autoAssignService): RedirectResponse
    {
        $assignedCount = DB::transaction(fn (): int => $autoAssignService->organize($request->user()));

        return redirect()->route('collections.index')
            ->with('status', $assignedCount.'件の学習セットをコレクションへ整理しました。');
    }

    private function ensureOwnership(Request $request, LearningCollection $collection): void
    {
        abort_unless($collection->user()->is($request->user()), 404);
    }
}
