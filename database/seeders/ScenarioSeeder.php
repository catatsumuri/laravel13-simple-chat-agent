<?php

namespace Database\Seeders;

use App\Models\Scenario;
use Illuminate\Database\Seeder;

class ScenarioSeeder extends Seeder
{
    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        collect([
            [
                'title' => 'SFA刷新を検討する製造業への提案',
                'company_name' => '東都精機株式会社',
                'industry' => '製造業',
                'customer_persona' => '営業部長',
                'difficulty' => '中級',
                'sort_order' => 1,
                'summary' => 'Excel中心で案件管理を行っており、営業進捗の可視化と引き継ぎ品質に課題がある。現場は入力負荷に敏感で、導入効果を短期間で示す必要がある。',
                'goal' => 'SFA導入の必要性を認識してもらい、トライアル導入に向けた次回商談を獲得する。',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => '採用強化中のIT企業へ研修サービスを提案',
                'company_name' => 'BlueGrid Technologies',
                'industry' => 'IT・SaaS',
                'customer_persona' => '人事責任者',
                'difficulty' => '初級',
                'sort_order' => 2,
                'summary' => '新任営業が急増している一方で、オンボーディングが属人化している。離職率を下げつつ、早期戦力化を進めたいというニーズが強い。',
                'goal' => '営業研修プログラムの課題適合を確認し、提案書送付と関係者同席の打ち合わせ設定につなげる。',
                'created_at' => now(),
                'updated_at' => now(),
            ],
            [
                'title' => '多店舗展開する小売業へ需要予測ツールを提案',
                'company_name' => 'くらしマート合同会社',
                'industry' => '小売',
                'customer_persona' => '店舗運営部マネージャー',
                'difficulty' => '上級',
                'sort_order' => 3,
                'summary' => '欠品と過剰在庫が同時に発生しており、発注精度の改善が急務。システム投資には慎重で、既存業務フローを大きく変えたくない。',
                'goal' => '現状課題を定量化しながら需要予測ツールの価値を訴求し、PoC実施の合意を得る。',
                'created_at' => now(),
                'updated_at' => now(),
            ],
        ])->each(function (array $scenario): void {
            Scenario::query()->updateOrCreate(
                ['title' => $scenario['title']],
                $scenario,
            );
        });
    }
}
