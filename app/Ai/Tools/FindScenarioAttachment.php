<?php

namespace App\Ai\Tools;

use App\Models\Scenario;
use App\Models\ScenarioAttachment;
use Illuminate\Contracts\JsonSchema\JsonSchema;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Laravel\Ai\Contracts\Tool;
use Laravel\Ai\Tools\Request;
use Stringable;

class FindScenarioAttachment implements Tool
{
    public function __construct(private readonly Scenario $scenario) {}

    /**
     * Get the description of the tool's purpose.
     */
    public function description(): Stringable|string
    {
        return 'シナリオに添付された資料を探し、関連しそうなファイル名と本文抜粋を返します。名刺、製品カタログ、連絡先、会社名、役職、製品名などを確認したい時に使ってください。';
    }

    /**
     * Execute the tool.
     */
    public function handle(Request $request): Stringable|string
    {
        $query = $request->string('query')->trim()->value();
        $attachments = $this->scenario->attachments()
            ->orderBy('created_at')
            ->get(['name', 'path', 'mime_type']);

        if ($attachments->isEmpty()) {
            return 'このシナリオには添付資料がありません。';
        }

        if ($query === '') {
            return '利用可能な添付資料: '.$attachments->pluck('name')->implode('、');
        }

        $matches = $attachments
            ->map(fn (ScenarioAttachment $attachment) => $this->matchAttachment($attachment, $query))
            ->filter()
            ->sortByDesc('score')
            ->take(3)
            ->values();

        if ($matches->isEmpty()) {
            return '該当しそうな添付資料は見つかりませんでした。利用可能な添付資料: '.$attachments->pluck('name')->implode('、');
        }

        return collect([
            "照会内容: {$query}",
            '該当しそうな添付資料:',
            $matches->map(fn (array $match) => $this->formatMatch($match))->implode("\n\n"),
        ])->implode("\n");
    }

    /**
     * Get the tool's schema definition.
     */
    public function schema(JsonSchema $schema): array
    {
        return [
            'query' => $schema->string()->description('探したい資料の種類やキーワード。例: 名刺, 製品カタログ, 連絡先, 上田')->required(),
        ];
    }

    /**
     * @return array{attachment: ScenarioAttachment, excerpt: string|null, reasons: Collection<int, string>, score: int}|null
     */
    private function matchAttachment(ScenarioAttachment $attachment, string $query): ?array
    {
        $normalizedQuery = Str::of($query)->lower()->trim()->value();
        $keywords = collect(preg_split('/[\s,、。・\/]+/u', $normalizedQuery) ?: [])
            ->filter(fn (string $keyword) => $keyword !== '' && mb_strlen($keyword) >= 2)
            ->prepend($normalizedQuery)
            ->unique()
            ->values();

        $name = Str::lower($attachment->name);
        $text = Str::lower($this->textContent($attachment) ?? '');

        $score = 0;
        $reasons = collect();

        foreach ($keywords as $keyword) {
            if (Str::contains($name, $keyword)) {
                $score += $keyword === $normalizedQuery ? 12 : 5;
                $reasons->push("ファイル名に「{$keyword}」を含みます");
            }

            if ($text !== '' && Str::contains($text, $keyword)) {
                $score += $keyword === $normalizedQuery ? 7 : 3;
                $reasons->push("本文に「{$keyword}」を含みます");
            }
        }

        if ($this->looksLikeBusinessCardQuery($normalizedQuery) && $this->looksLikeBusinessCard($attachment, $text)) {
            $score += 4;
            $reasons->push('名刺らしい資料です');
        }

        if ($this->looksLikeCatalogQuery($normalizedQuery) && $this->looksLikeCatalog($attachment, $text)) {
            $score += 4;
            $reasons->push('製品資料らしい資料です');
        }

        if ($score === 0) {
            return null;
        }

        return [
            'attachment' => $attachment,
            'excerpt' => $this->excerpt($attachment),
            'reasons' => $reasons->unique()->values(),
            'score' => $score,
        ];
    }

    /**
     * @param  array{attachment: ScenarioAttachment, excerpt: string|null, reasons: Collection<int, string>, score: int}  $match
     */
    private function formatMatch(array $match): string
    {
        $attachment = $match['attachment'];

        $lines = [
            "[{$attachment->name}]",
            "種別: {$attachment->mime_type}",
            '一致理由: '.$match['reasons']->implode(' / '),
        ];

        if ($match['excerpt'] !== null) {
            $lines[] = "本文抜粋:\n{$match['excerpt']}";
        } else {
            $lines[] = '本文抜粋: この資料は本文抜粋を直接返せません。必要ならファイル名を手掛かりに内容を確認してください。';
        }

        return implode("\n", $lines);
    }

    private function looksLikeBusinessCardQuery(string $query): bool
    {
        return Str::contains($query, ['名刺', '連絡先', 'メール', 'email', '電話', 'tel']);
    }

    private function looksLikeCatalogQuery(string $query): bool
    {
        return Str::contains($query, ['製品', 'カタログ', '資料', 'パンフ', '概要', 'サービス']);
    }

    private function looksLikeBusinessCard(ScenarioAttachment $attachment, string $text): bool
    {
        return Str::contains(Str::lower($attachment->name), ['名刺', 'card'])
            || Str::contains($text, ['@', '株式会社', 'co.jp'])
            || preg_match('/\d{2,4}-\d{2,4}-\d{3,4}/', $text) === 1;
    }

    private function looksLikeCatalog(ScenarioAttachment $attachment, string $text): bool
    {
        return Str::contains(Str::lower($attachment->name), ['カタログ', 'catalog', '製品', 'product', '概要'])
            || Str::contains($text, ['機能', '導入', '料金', '価格', 'api', 'サポート']);
    }

    private function excerpt(ScenarioAttachment $attachment): ?string
    {
        $text = $this->textContent($attachment);

        if ($text === null || trim($text) === '') {
            return null;
        }

        return Str::of($text)
            ->replace("\r\n", "\n")
            ->replace("\r", "\n")
            ->trim()
            ->limit(1200, "\n…")
            ->value();
    }

    private function textContent(ScenarioAttachment $attachment): ?string
    {
        if (! $this->isPromptReadableText($attachment) || Storage::disk('local')->missing($attachment->path)) {
            return null;
        }

        return Storage::disk('local')->get($attachment->path);
    }

    private function isPromptReadableText(ScenarioAttachment $attachment): bool
    {
        return in_array($attachment->mime_type, [
            'text/plain',
            'text/markdown',
            'text/csv',
            'application/csv',
            'application/vnd.ms-excel',
        ], true);
    }
}
