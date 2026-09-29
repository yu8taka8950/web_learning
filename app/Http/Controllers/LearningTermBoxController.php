<?php

namespace App\Http\Controllers;

use App\Models\LearningTermBox;
use App\Services\DictionaryNormalizer;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class LearningTermBoxController extends Controller
{
    public function store(Request $request, DictionaryNormalizer $normalizer): RedirectResponse
    {
        $name = $this->normalizedName($request, $normalizer);

        try {
            $box = $request->user()->learningTermBoxes()->createOrFirst(
                ['name_key' => $normalizer->key($name)],
                ['name' => $name, 'kind' => LearningTermBox::KIND_MANUAL, 'auto_rule' => null],
            );
        } catch (QueryException $exception) {
            $this->throwDuplicateNameValidationException($exception);
        }

        if (! $box->wasRecentlyCreated) {
            throw ValidationException::withMessages(['name' => '同じ名前のカテゴリがあります。']);
        }

        return redirect()->route('learning-terms.index')->with('status', '新しいカテゴリを作成しました。');
    }

    public function update(Request $request, LearningTermBox $learningTermBox, DictionaryNormalizer $normalizer): RedirectResponse
    {
        $this->ensureOwnership($request, $learningTermBox);
        abort_unless($learningTermBox->kind === LearningTermBox::KIND_MANUAL, 403);
        $name = $this->normalizedName($request, $normalizer);
        $nameKey = $normalizer->key($name);

        if ($request->user()->learningTermBoxes()->where('name_key', $nameKey)->whereKeyNot($learningTermBox->id)->exists()) {
            throw ValidationException::withMessages(['name' => '同じ名前のカテゴリがあります。']);
        }

        try {
            $learningTermBox->update(['name' => $name, 'name_key' => $nameKey]);
        } catch (QueryException $exception) {
            $this->throwDuplicateNameValidationException($exception);
        }

        return redirect()->route('learning-terms.index', ['box' => $learningTermBox->id])->with('status', 'カテゴリ名を変更しました。');
    }

    public function destroy(Request $request, LearningTermBox $learningTermBox): RedirectResponse
    {
        $this->ensureOwnership($request, $learningTermBox);
        abort_unless($learningTermBox->kind === LearningTermBox::KIND_MANUAL, 403);

        DB::transaction(function () use ($learningTermBox): void {
            $learningTermBox->learningTerms()->detach();
            $learningTermBox->update(['name_key' => 'deleted:'.$learningTermBox->id.':'.hash('sha256', $learningTermBox->name_key)]);
            $learningTermBox->delete();
        });

        return redirect()->route('learning-terms.index')->with('status', 'カテゴリを削除しました。用語は保持されています。');
    }

    private function normalizedName(Request $request, DictionaryNormalizer $normalizer): string
    {
        $request->merge(['name' => $normalizer->display((string) $request->input('name'))]);

        return $request->validate([
            'name' => ['required', 'string', 'max:'.LearningTermBox::MAX_NAME_LENGTH],
        ])['name'];
    }

    private function ensureOwnership(Request $request, LearningTermBox $learningTermBox): void
    {
        abort_unless($learningTermBox->user()->is($request->user()), 404);
    }

    private function throwDuplicateNameValidationException(QueryException $exception): never
    {
        if (($exception->errorInfo[1] ?? null) === 1062 || $exception->getCode() === '23000') {
            throw ValidationException::withMessages(['name' => '同じ名前のカテゴリがあります。']);
        }

        throw $exception;
    }
}
