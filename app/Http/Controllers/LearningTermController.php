<?php

namespace App\Http\Controllers;

use App\Models\LearningTerm;
use App\Models\LearningTermBox;
use App\Models\Question;
use App\Services\LearningTermSaveService;
use App\Services\TermExplanationService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;
use Illuminate\View\View;

class LearningTermController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->query('status') === 'deleted' ? 'deleted' : 'active';
        $searchQuery = Str::limit(trim((string) $request->query('q')), 100, '');
        $termsQuery = $this->ownedTermsQuery($request);
        $selectedBox = null;

        if ($status === 'deleted') {
            $termsQuery->onlyTrashed();
        } elseif ($request->has('box')) {
            $selectedBox = $request->user()->learningTermBoxes()
                ->where('kind', LearningTermBox::KIND_MANUAL)
                ->whereKey($request->integer('box'))
                ->firstOrFail();
            $termsQuery->whereHas('boxes', fn (Builder $query) => $query->whereKey($selectedBox->id));
            $searchQuery = '';
        }

        if ($searchQuery !== '') {
            $termsQuery->where(function (Builder $query) use ($searchQuery): void {
                $query->where('term', 'like', '%'.$searchQuery.'%')
                    ->orWhere('description', 'like', '%'.$searchQuery.'%')
                    ->orWhereHas('learningSetIncludingDeleted', function (Builder $learningSetQuery) use ($searchQuery): void {
                        $learningSetQuery->where('title', 'like', '%'.$searchQuery.'%')
                            ->orWhere('subject', 'like', '%'.$searchQuery.'%')
                            ->orWhere('topic', 'like', '%'.$searchQuery.'%');
                    });
            });
        }

        if ($status === 'active' && $selectedBox === null) {
            $termsQuery->with([
                'boxes' => fn (BelongsToMany $query) => $query
                    ->where('kind', LearningTermBox::KIND_MANUAL)
                    ->select(['learning_term_boxes.id', 'learning_term_boxes.name']),
            ]);
        }

        $terms = $termsQuery
            ->latest('created_at')
            ->latest('id')
            ->paginate(25);

        if ($selectedBox === null) {
            $terms->withQueryString();
        } else {
            $terms->appends(['box' => $selectedBox->id]);
        }

        $activeTermCount = $this->ownedTermsQuery($request)->count();
        $selectedBoxTermCount = $selectedBox === null
            ? $activeTermCount
            : $this->ownedTermsQuery($request)->whereHas('boxes', fn (Builder $query) => $query->whereKey($selectedBox->id))->count();
        $manualBoxes = $status === 'active' && $selectedBox === null
            ? $this->categoriesOverview($request, $searchQuery === '')
            : collect();
        $manualCategories = $status === 'active' && $selectedBox === null
            ? $request->user()->learningTermBoxes()
                ->where('kind', LearningTermBox::KIND_MANUAL)
                ->orderBy('name')
                ->get(['id', 'name'])
            : collect();
        $termPickerQuery = Str::limit(trim((string) $request->query('term_picker_q')), 100, '');
        $termPickerTerms = collect();
        $selectedPickerTermIds = collect();

        if ($status === 'active' && $selectedBox !== null) {
            $termPickerTermsQuery = $this->ownedTermsQuery($request);

            if ($termPickerQuery !== '') {
                $termPickerTermsQuery->where('term', 'like', '%'.$termPickerQuery.'%');
            }

            $termPickerTerms = $termPickerTermsQuery
                ->orderBy('term')
                ->orderBy('id')
                ->limit(50)
                ->get(['id', 'term']);
            $selectedPickerTermIds = $selectedBox->learningTerms()
                ->whereKey($termPickerTerms->pluck('id'))
                ->pluck('learning_terms.id');
        }

        return view('learning-terms.index', [
            'terms' => $terms,
            'activeTermCount' => $activeTermCount,
            'selectedBoxTermCount' => $selectedBoxTermCount,
            'selectedBox' => $selectedBox,
            'manualBoxes' => $manualBoxes,
            'manualCategories' => $manualCategories,
            'searchQuery' => $searchQuery,
            'status' => $status,
            'termPickerQuery' => $termPickerQuery,
            'termPickerTerms' => $termPickerTerms,
            'selectedPickerTermIds' => $selectedPickerTermIds,
        ]);
    }

    public function store(Request $request, LearningTermSaveService $learningTermSaveService, TermExplanationService $termExplanationService): JsonResponse
    {
        $validated = $request->validate([
            'question_id' => ['required', 'integer'],
            'selected_text' => ['required', 'string', 'max:'.LearningTermSaveService::MAX_TERM_LENGTH, function (string $attribute, mixed $value, \Closure $fail) use ($learningTermSaveService): void {
                if ($learningTermSaveService->normalizeTerm((string) $value) === '') {
                    $fail('用語を選択してください。');
                }
                if (mb_strlen($learningTermSaveService->normalizeTerm((string) $value)) < 2) {
                    $fail('用語は2文字以上で選択してください。');
                }
            }],
            'source_field' => ['required', 'string', 'in:question,input_question,explanation'],
        ]);

        $question = Question::query()
            ->whereKey($validated['question_id'])
            ->whereHas('learningSet', fn (Builder $query) => $query->whereBelongsTo($request->user()))
            ->with('learningSet:id,user_id,title,subject,topic,source_type,source_url')
            ->firstOrFail();

        $term = $learningTermSaveService->normalizeTerm($validated['selected_text']);
        $sourceText = $learningTermSaveService->sourceText($question, $validated['source_field']);
        if ($sourceText === null || ! $learningTermSaveService->containsSelectedTerm($sourceText, $term)) {
            return response()->json(['message' => '選択した用語を確認できませんでした。'], 422);
        }

        $termExplanation = $termExplanationService->ensure($request->user(), $question->learningSet, $term);
        $result = $learningTermSaveService->save(
            $request->user(),
            $question,
            $validated['source_field'],
            $term,
            $termExplanation?->explanation,
        );

        return response()->json($result, $result['created'] ? 201 : 200);
    }

    public function edit(Request $request, LearningTerm $learningTerm): View
    {
        $this->ensureOwnership($request, $learningTerm);
        $learningTerm->load([
            'learningSetIncludingDeleted:id,user_id,title,subject,topic,deleted_at',
            'boxes' => fn (BelongsToMany $query) => $query
                ->where('kind', LearningTermBox::KIND_MANUAL)
                ->select(['learning_term_boxes.id', 'learning_term_boxes.name']),
        ]);
        $manualBoxes = $request->user()->learningTermBoxes()
            ->where('kind', LearningTermBox::KIND_MANUAL)
            ->orderBy('name')
            ->get(['id', 'name']);
        $selectedManualBoxIds = $learningTerm->boxes->pluck('id');

        return view('learning-terms.edit', compact('learningTerm', 'manualBoxes', 'selectedManualBoxIds'));
    }

    public function update(Request $request, LearningTerm $learningTerm, LearningTermSaveService $learningTermSaveService): RedirectResponse
    {
        $this->ensureOwnership($request, $learningTerm);
        $request->merge([
            'term' => $learningTermSaveService->normalizeTerm((string) $request->input('term')),
            'description' => trim((string) $request->input('description')),
        ]);
        $validated = $request->validate([
            'term' => ['required', 'string', 'max:'.LearningTermSaveService::MAX_TERM_LENGTH],
            'description' => ['required', 'string', 'max:'.LearningTermSaveService::MAX_DESCRIPTION_LENGTH],
        ]);

        $hasDuplicate = $learningTermSaveService->hasActiveDuplicate($request->user(), $validated['term'], $learningTerm->id)
            || $learningTermSaveService->hasDuplicateInLearningSetIncludingDeleted($learningTerm->learningSetIncludingDeleted, $validated['term'], $learningTerm->id);

        if ($hasDuplicate) {
            return back()->withErrors(['term' => '同じ用語がすでに保存されています。'])->withInput();
        }

        $learningTerm->update([
            'term' => $validated['term'],
            'description' => $validated['description'],
        ]);

        return redirect()->route('learning-terms.index')->with('status', '用語を更新しました。');
    }

    public function destroy(Request $request, LearningTerm $learningTerm): RedirectResponse
    {
        $this->ensureOwnership($request, $learningTerm);
        $learningTerm->delete();

        return redirect()->route('learning-terms.index')->with('status', '用語を削除しました。');
    }

    public function restore(
        Request $request,
        int $learningTerm,
        LearningTermSaveService $learningTermSaveService,
    ): RedirectResponse {
        $deletedTerm = LearningTerm::onlyTrashed()
            ->whereKey($learningTerm)
            ->whereHas('learningSetIncludingDeleted', fn (Builder $query) => $query->whereBelongsTo($request->user()))
            ->firstOrFail();

        if ($learningTermSaveService->hasActiveDuplicate($request->user(), $deletedTerm->term)) {
            return redirect()->route('learning-terms.index', ['status' => 'deleted'])
                ->with('error', '同じ用語が保存中のため、復元できません。');
        }

        $deletedTerm->restore();

        return redirect()->route('learning-terms.index')->with('status', '用語を復元しました。');
    }

    private function ownedTermsQuery(Request $request): Builder
    {
        return LearningTerm::query()
            ->whereHas('learningSetIncludingDeleted', fn (Builder $query) => $query->whereBelongsTo($request->user()));
    }

    private function categoriesOverview(Request $request, bool $includePreviews): Collection
    {
        $user = $request->user();
        $ownedTerms = fn (Builder $query): Builder => $query->whereHas(
            'learningSetIncludingDeleted',
            fn (Builder $learningSetQuery) => $learningSetQuery->whereBelongsTo($user),
        );
        $boxesQuery = $user->learningTermBoxes()
            ->where('kind', LearningTermBox::KIND_MANUAL)
            ->withCount(['learningTerms as active_terms_count' => $ownedTerms]);

        if ($includePreviews) {
            $boxesQuery->with(['learningTerms' => function ($query) use ($user): void {
                $query->whereHas(
                    'learningSetIncludingDeleted',
                    fn (Builder $learningSetQuery) => $learningSetQuery->whereBelongsTo($user),
                )->latest('learning_terms.created_at')->limit(3);
            }]);
        }

        return $boxesQuery
            ->orderBy('name')
            ->get();
    }

    private function ensureOwnership(Request $request, LearningTerm $learningTerm): void
    {
        abort_unless(
            $learningTerm->learningSetIncludingDeleted()->whereBelongsTo($request->user())->exists(),
            404,
        );
    }
}
