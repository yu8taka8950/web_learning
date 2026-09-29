<?php

namespace App\Http\Controllers;

use App\Models\LearningTerm;
use App\Models\LearningTermBox;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class LearningTermBoxMembershipController extends Controller
{
    public function update(Request $request, LearningTerm $learningTerm): RedirectResponse
    {
        $this->ensureTermOwnership($request, $learningTerm);
        $validated = $request->validate([
            'manual_box_ids' => ['sometimes', 'array'],
            'manual_box_ids.*' => ['integer', 'distinct'],
            'return_to' => ['sometimes', 'string', 'in:index'],
            'return_box_id' => ['sometimes', 'integer'],
        ]);
        $requestedBoxIds = collect($validated['manual_box_ids'] ?? [])->map(fn (int|string $id): int => (int) $id)->values();
        $ownedBoxIds = $request->user()->learningTermBoxes()
            ->where('kind', LearningTermBox::KIND_MANUAL)
            ->whereKey($requestedBoxIds)
            ->pluck('id');
        abort_unless($ownedBoxIds->count() === $requestedBoxIds->count(), 404);

        $returnBox = null;
        if (isset($validated['return_box_id'])) {
            $returnBox = $request->user()->learningTermBoxes()
                ->where('kind', LearningTermBox::KIND_MANUAL)
                ->whereKey($validated['return_box_id'])
                ->firstOrFail();
        }

        DB::transaction(function () use ($learningTerm, $ownedBoxIds): void {
            $currentManualBoxIds = DB::table('learning_term_learning_term_box')
                ->where('learning_term_id', $learningTerm->id)
                ->where('assigned_by', 'manual')
                ->pluck('learning_term_box_id');

            $learningTerm->boxes()->detach($currentManualBoxIds->diff($ownedBoxIds));
            $learningTerm->boxes()->syncWithoutDetaching(
                $ownedBoxIds->diff($currentManualBoxIds)->mapWithKeys(fn (int $boxId): array => [
                    $boxId => ['assigned_by' => 'manual'],
                ])->all(),
            );
        });

        if (($validated['return_to'] ?? null) === 'index') {
            return redirect()->route('learning-terms.index', array_filter([
                'box' => $returnBox?->id,
            ]))->with('status', 'カテゴリを更新しました。');
        }

        return redirect()->route('learning-terms.edit', $learningTerm)->with('status', 'カテゴリを更新しました。');
    }

    public function addTerms(Request $request, LearningTermBox $learningTermBox): RedirectResponse
    {
        $this->ensureBoxOwnership($request, $learningTermBox);
        $validated = $request->validate([
            'term_ids' => ['required', 'array', 'min:1', 'max:50'],
            'term_ids.*' => ['integer', 'distinct'],
        ]);
        $requestedTermIds = collect($validated['term_ids'])->map(fn (int|string $id): int => (int) $id)->values();
        $ownedTermIds = LearningTerm::query()
            ->whereKey($requestedTermIds)
            ->whereHas('learningSetIncludingDeleted', fn (Builder $query) => $query->whereBelongsTo($request->user()))
            ->pluck('id');
        abort_unless($ownedTermIds->count() === $requestedTermIds->count(), 404);

        $learningTermBox->learningTerms()->syncWithoutDetaching(
            $ownedTermIds->mapWithKeys(fn (int $termId): array => [
                $termId => ['assigned_by' => 'manual'],
            ])->all(),
        );

        return redirect()->route('learning-terms.index', ['box' => $learningTermBox->id])
            ->with('status', 'カテゴリへ用語を追加しました。');
    }

    public function destroy(Request $request, LearningTermBox $learningTermBox, LearningTerm $learningTerm): RedirectResponse
    {
        $this->ensureBoxOwnership($request, $learningTermBox);
        $this->ensureTermOwnership($request, $learningTerm);

        DB::table('learning_term_learning_term_box')
            ->where('learning_term_box_id', $learningTermBox->id)
            ->where('learning_term_id', $learningTerm->id)
            ->where('assigned_by', 'manual')
            ->delete();

        return redirect()->route('learning-terms.index', ['box' => $learningTermBox->id])
            ->with('status', 'カテゴリから用語を外しました。');
    }

    public function bulkUpdate(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'term_ids' => ['required', 'array', 'min:1', 'max:50'],
            'term_ids.*' => ['integer', 'distinct'],
            'category_ids' => ['required', 'array', 'min:1', 'max:50'],
            'category_ids.*' => ['integer', 'distinct'],
        ]);
        $requestedTermIds = collect($validated['term_ids'])->map(fn (int|string $id): int => (int) $id)->values();
        $requestedCategoryIds = collect($validated['category_ids'])->map(fn (int|string $id): int => (int) $id)->values();
        $ownedTerms = LearningTerm::query()
            ->whereKey($requestedTermIds)
            ->whereHas('learningSetIncludingDeleted', fn (Builder $query) => $query->whereBelongsTo($request->user()))
            ->get(['id']);
        $ownedCategoryIds = $request->user()->learningTermBoxes()
            ->where('kind', LearningTermBox::KIND_MANUAL)
            ->whereKey($requestedCategoryIds)
            ->pluck('id');
        abort_unless($ownedTerms->count() === $requestedTermIds->count(), 404);
        abort_unless($ownedCategoryIds->count() === $requestedCategoryIds->count(), 404);

        $timestamp = now();
        $pivotRows = [];
        foreach ($ownedTerms as $term) {
            foreach ($ownedCategoryIds as $categoryId) {
                $pivotRows[] = [
                    'learning_term_id' => $term->id,
                    'learning_term_box_id' => $categoryId,
                    'assigned_by' => 'manual',
                    'created_at' => $timestamp,
                    'updated_at' => $timestamp,
                ];
            }
        }
        DB::transaction(function () use ($pivotRows): void {
            DB::table('learning_term_learning_term_box')->insertOrIgnore($pivotRows);
        });

        return redirect()->route('learning-terms.index')
            ->with('status', $ownedTerms->count().'語を'.$ownedCategoryIds->count().'カテゴリに追加しました。');
    }

    private function ensureTermOwnership(Request $request, LearningTerm $learningTerm): void
    {
        abort_unless(
            $learningTerm->learningSetIncludingDeleted()->whereBelongsTo($request->user())->exists(),
            404,
        );
    }

    private function ensureBoxOwnership(Request $request, LearningTermBox $learningTermBox): void
    {
        abort_unless(
            $learningTermBox->user_id === $request->user()->id
                && $learningTermBox->kind === LearningTermBox::KIND_MANUAL,
            404,
        );
    }
}
