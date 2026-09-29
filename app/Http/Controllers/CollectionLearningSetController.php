<?php

namespace App\Http\Controllers;

use App\Models\LearningCollection;
use App\Models\LearningSet;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class CollectionLearningSetController extends Controller
{
    public function update(Request $request, LearningSet $learningSet): RedirectResponse
    {
        $this->ensureLearningSetOwnership($request, $learningSet);
        $validated = $request->validate([
            'collection_ids' => ['sometimes', 'array'],
            'collection_ids.*' => ['integer', 'distinct'],
        ]);
        $collectionIds = collect($validated['collection_ids'] ?? [])->map(fn (int|string $id): int => (int) $id)->values();
        $ownedCollectionIds = $request->user()->learningCollections()
            ->whereKey($collectionIds)
            ->pluck('id');
        abort_unless($ownedCollectionIds->count() === $collectionIds->count(), 404);

        DB::transaction(function () use ($learningSet, $ownedCollectionIds): void {
            $activeMembershipIds = $learningSet->collections()->pluck('learning_collections.id');
            $learningSet->collections()->detach($activeMembershipIds->diff($ownedCollectionIds));
            $learningSet->collections()->syncWithoutDetaching(
                $ownedCollectionIds->mapWithKeys(fn (int $collectionId): array => [
                    $collectionId => ['assigned_by' => 'manual'],
                ])->all()
            );
        });

        return redirect()->route('dashboard')->with('status', 'コレクションへの追加内容を保存しました。');
    }

    public function destroy(Request $request, LearningCollection $collection, LearningSet $learningSet): RedirectResponse
    {
        abort_unless($collection->user()->is($request->user()), 404);
        $this->ensureLearningSetOwnership($request, $learningSet);

        DB::transaction(fn () => $collection->learningSets()->detach($learningSet->id));

        return redirect()->route('collections.show', $collection)->with('status', '学習セットをコレクションから外しました。');
    }

    private function ensureLearningSetOwnership(Request $request, LearningSet $learningSet): void
    {
        abort_unless($learningSet->user()->is($request->user()), 404);
    }
}
