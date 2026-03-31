import { Head, Link } from '@inertiajs/react';
import { Paperclip, Pencil, Play } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { chat, edit } from '@/routes/scenarios';

type Scenario = {
    id: number;
    title: string;
    company_name: string;
    industry: string;
    customer_persona: string;
    difficulty: string;
    summary: string;
    goal: string;
    attachments: Attachment[];
};

type Attachment = {
    id: number;
    name: string;
    mime_type: string;
    size: number;
};

type DashboardProps = {
    scenarios: Scenario[];
};

const difficultyTone: Record<string, string> = {
    初級: 'bg-emerald-100 text-emerald-800 hover:bg-emerald-100 dark:bg-emerald-950 dark:text-emerald-200',
    中級: 'bg-amber-100 text-amber-800 hover:bg-amber-100 dark:bg-amber-950 dark:text-amber-200',
    上級: 'bg-rose-100 text-rose-800 hover:bg-rose-100 dark:bg-rose-950 dark:text-rose-200',
};

export default function Dashboard({ scenarios }: DashboardProps) {
    const industries = new Set(scenarios.map((scenario) => scenario.industry)).size;

    return (
        <>
            <Head title="Dashboard" />
            <div className="flex h-full flex-1 flex-col gap-6 overflow-x-auto rounded-xl p-4">
                <section className="overflow-hidden rounded-3xl border border-border bg-linear-to-br from-slate-950 via-slate-800 to-orange-600 p-6 text-white shadow-sm">
                    <div className="max-w-3xl space-y-3">
                        <p className="text-sm font-medium uppercase tracking-[0.24em] text-orange-200">Sales Roleplay Dashboard</p>
                        <h1 className="text-3xl font-semibold tracking-tight">営業ロールプレイのシナリオ一覧</h1>
                        <p className="text-sm leading-6 text-slate-100/80">
                            初回ヒアリング、課題深掘り、PoC提案までを想定した練習用シナリオをダッシュボードで確認できます。
                        </p>
                    </div>
                </section>

                <section className="grid gap-4 md:grid-cols-3">
                    <Card className="gap-3 border-border/70 py-5">
                        <CardHeader>
                            <CardDescription>登録シナリオ数</CardDescription>
                            <CardTitle className="text-3xl">{scenarios.length}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card className="gap-3 border-border/70 py-5">
                        <CardHeader>
                            <CardDescription>対象業界</CardDescription>
                            <CardTitle className="text-3xl">{industries}</CardTitle>
                        </CardHeader>
                    </Card>
                    <Card className="gap-3 border-border/70 py-5">
                        <CardHeader>
                            <CardDescription>難易度レンジ</CardDescription>
                            <CardTitle className="text-3xl">初級 - 上級</CardTitle>
                        </CardHeader>
                    </Card>
                </section>

                {scenarios.length === 0 ? (
                    <div className="rounded-2xl border border-dashed border-border px-6 py-10 text-center text-sm text-muted-foreground">
                        表示するシナリオがありません。Seeder を実行して初期データを投入してください。
                    </div>
                ) : (
                    <section className="grid gap-4 xl:grid-cols-3">
                        {scenarios.map((scenario) => (
                            <Card key={scenario.id} className="border-border/70 py-0">
                                <CardHeader className="space-y-4 border-b border-border/70 py-6">
                                    <div className="flex items-start justify-between gap-3">
                                        <div className="space-y-2">
                                            <CardTitle className="text-xl leading-7">{scenario.title}</CardTitle>
                                            <CardDescription>
                                                {scenario.company_name} / {scenario.industry}
                                            </CardDescription>
                                        </div>
                                        <Badge className={difficultyTone[scenario.difficulty] ?? ''}>{scenario.difficulty}</Badge>
                                    </div>
                                    <div className="flex flex-wrap gap-2 text-xs text-muted-foreground">
                                        <span className="rounded-full bg-muted px-3 py-1">想定相手: {scenario.customer_persona}</span>
                                    </div>
                                </CardHeader>
                                <CardContent className="space-y-5 py-6">
                                    <div className="space-y-2">
                                        <p className="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">Situation</p>
                                        <p className="text-sm leading-6 text-foreground/90">{scenario.summary}</p>
                                    </div>
                                    <div className="space-y-2">
                                        <p className="text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">Goal</p>
                                        <p className="text-sm leading-6 text-foreground/90">{scenario.goal}</p>
                                    </div>
                                    <div className="space-y-2">
                                        <div className="flex items-center gap-2 text-xs font-semibold uppercase tracking-[0.2em] text-muted-foreground">
                                            <Paperclip className="h-3.5 w-3.5" />
                                            Attachments
                                        </div>
                                        {scenario.attachments.length === 0 ? (
                                            <p className="text-sm text-muted-foreground">添付ファイルはまだありません。</p>
                                        ) : (
                                            <ul className="space-y-1.5">
                                                {scenario.attachments.slice(0, 3).map((attachment) => (
                                                    <li
                                                        key={attachment.id}
                                                        className="truncate text-sm text-foreground/90"
                                                        title={attachment.name}
                                                    >
                                                        {attachment.name}
                                                    </li>
                                                ))}
                                                {scenario.attachments.length > 3 && (
                                                    <li className="text-sm text-muted-foreground">
                                                        他 {scenario.attachments.length - 3} 件
                                                    </li>
                                                )}
                                            </ul>
                                        )}
                                    </div>
                                    <div className="flex gap-2">
                                        <Link href={chat(scenario.id)} className="flex-1">
                                            <Button type="button" className="w-full">
                                                <Play />
                                                シナリオを開始
                                            </Button>
                                        </Link>
                                        <Link href={edit(scenario.id)}>
                                            <Button type="button" variant="outline" size="icon">
                                                <Pencil className="h-4 w-4" />
                                            </Button>
                                        </Link>
                                    </div>
                                </CardContent>
                            </Card>
                        ))}
                    </section>
                )}

                <section>
                    <Card className="border-border/70 bg-muted/30 py-0">
                        <CardHeader className="py-6">
                            <CardTitle>使い方</CardTitle>
                            <CardDescription>各シナリオを選び、顧客の課題確認から次回アクションの合意形成までをロールプレイしてください。</CardDescription>
                        </CardHeader>
                    </Card>
                </section>
            </div>
        </>
    );
}

Dashboard.layout = {
    breadcrumbs: [
        {
            title: 'Dashboard',
            href: dashboard(),
        },
    ],
};
