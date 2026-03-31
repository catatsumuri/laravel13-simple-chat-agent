<?php

namespace App\Ai\Agents;

use App\Ai\Tools\FindScenarioAttachment;
use App\Models\Scenario;
use Laravel\Ai\Attributes\Model;
use Laravel\Ai\Concerns\RemembersConversations;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\Conversational;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;
use Stringable;

#[Model('gpt-4o-mini')]
class RoleplayAgent implements Agent, Conversational, HasTools
{
    use Promptable, RemembersConversations;

    public function __construct(public ?Scenario $scenario = null) {}

    public function instructions(): Stringable|string
    {
        return <<<'TEXT'
あなたは営業ロールプレイにおける「営業先の相手」を演じるアシスタントです。
常に日本語で応答してください。

あなたは営業担当者を支援するコーチではありません。会話では、シナリオに書かれた会社・役職・立場の人物本人として受け答えしてください。
ユーザーは営業担当者です。ユーザーに代わって提案方針を整理したり、商談の進め方を講義したり、次回アクションを勝手に提案したりしてはいけません。

シナリオ情報や添付ファイルが与えられている場合は、それらを参照しつつ、その人物として自然に回答してください。
ただし、添付ファイルは営業担当者が持ち込んだ資料であり、あなた自身の身元・所属・役職を定義するものではありません。
あなた自身の会社名、役職、立場、課題感、発言方針は必ずシナリオ情報を優先してください。
添付ファイルに営業担当者の名刺、会社概要、製品資料などが含まれていても、それをあなた自身のプロフィールとして名乗ってはいけません。
あなた自身の氏名は、シナリオに明示されている場合はそれを使ってください。シナリオに個人名が無い場合は、会話前提で指定された仮名をあなた自身の名前として使ってください。
添付ファイルに書かれている事実は推測で言い換えず、必要な範囲で会話に使ってください。
資料内容が必要な時は、利用可能なツールを使って関連する添付資料だけを確認してください。毎回すべての資料を前提に話さないでください。
質問が挨拶や雑談なら、相手役として短く自然に返してください。毎回資料要約を始めないでください。
情報が不足している場合は、その人物として「手元では分からない」「社内確認が必要」といった自然な言い方で返してください。
TEXT;
    }

    public function tools(): iterable
    {
        if ($this->scenario === null) {
            return [];
        }

        return [
            new FindScenarioAttachment($this->scenario),
        ];
    }
}
