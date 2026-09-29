<?php

namespace App\Console\Commands;

use App\Models\LearningTermBox;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('learning-terms:retire-automatic-categories')]
#[Description('Remove automatic personal-term memberships and soft delete automatic categories')]
class RetireAutomaticLearningTermCategories extends Command
{
    public function handle(): int
    {
        $learningTermCountBefore = DB::table('learning_terms')->count();
        $manualMembershipCountBefore = DB::table('learning_term_learning_term_box')
            ->where('assigned_by', LearningTermBox::KIND_MANUAL)
            ->count();
        $retiredAutomaticCategoryCount = 0;

        $removedAutomaticMembershipCount = DB::transaction(function () use (&$retiredAutomaticCategoryCount): int {
            $removedMembershipCount = DB::table('learning_term_learning_term_box')
                ->where('assigned_by', LearningTermBox::KIND_AUTO)
                ->delete();
            $automaticCategories = LearningTermBox::query()
                ->where('kind', LearningTermBox::KIND_AUTO)
                ->lockForUpdate()
                ->get();

            foreach ($automaticCategories as $category) {
                $category->update([
                    'name_key' => 'retired-auto:'.$category->id.':'.substr(hash('sha256', $category->name_key), 0, 48),
                    'auto_rule' => null,
                ]);
                $category->delete();
            }

            $retiredAutomaticCategoryCount = $automaticCategories->count();

            return $removedMembershipCount;
        });

        $this->info($retiredAutomaticCategoryCount.'件の自動カテゴリを停止しました。');
        $this->info($removedAutomaticMembershipCount.'件の自動所属を削除しました。');
        $this->info('LearningTerm: '.$learningTermCountBefore.'件（変更なし）');
        $this->info('手動所属: '.$manualMembershipCountBefore.'件（変更なし）');

        return self::SUCCESS;
    }
}
