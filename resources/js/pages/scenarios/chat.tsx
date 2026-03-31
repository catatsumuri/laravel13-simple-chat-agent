import { Head, Link } from '@inertiajs/react';
import { ArrowLeft, Send } from 'lucide-react';
import ReactMarkdown from 'react-markdown';
import remarkGfm from 'remark-gfm';
import { useEffect, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { message as messageRoute } from '@/routes/scenarios/chat';

type Scenario = {
    id: number;
    title: string;
    company_name: string;
    industry: string;
    customer_persona: string;
    difficulty: string;
    summary: string;
    goal: string;
};

type Message = {
    role: 'user' | 'assistant';
    content: string;
};

type ChatProps = {
    scenario: Scenario;
};

const difficultyTone: Record<string, string> = {
    初級: 'bg-emerald-100 text-emerald-800 hover:bg-emerald-100 dark:bg-emerald-950 dark:text-emerald-200',
    中級: 'bg-amber-100 text-amber-800 hover:bg-amber-100 dark:bg-amber-950 dark:text-amber-200',
    上級: 'bg-rose-100 text-rose-800 hover:bg-rose-100 dark:bg-rose-950 dark:text-rose-200',
};

function getCsrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

export default function Chat({ scenario }: ChatProps) {
    const [messages, setMessages] = useState<Message[]>([]);
    const [input, setInput] = useState('');
    const [conversationId, setConversationId] = useState<string | null>(null);
    const [isLoading, setIsLoading] = useState(false);
    const messagesEndRef = useRef<HTMLDivElement>(null);

    useEffect(() => {
        messagesEndRef.current?.scrollIntoView({ behavior: 'smooth' });
    }, [messages]);

    async function handleSend() {
        const text = input.trim();
        if (!text || isLoading) {
            return;
        }

        setInput('');
        setMessages((prev) => [...prev, { role: 'user', content: text }]);
        setIsLoading(true);

        // Add empty assistant message to stream into
        setMessages((prev) => [...prev, { role: 'assistant', content: '' }]);

        try {
            const res = await fetch(messageRoute(scenario.id).url, {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'text/event-stream',
                },
                body: JSON.stringify({
                    message: text,
                    conversation_id: conversationId,
                }),
            });

            if (!res.ok || !res.body) {
                throw new Error(`HTTP ${res.status}`);
            }

            const reader = res.body.getReader();
            const decoder = new TextDecoder();
            let buffer = '';

            while (true) {
                const { done, value } = await reader.read();
                if (done) {
                    break;
                }

                buffer += decoder.decode(value, { stream: true });

                const lines = buffer.split('\n');
                buffer = lines.pop() ?? '';

                for (const line of lines) {
                    if (!line.startsWith('data: ')) {
                        continue;
                    }

                    const payload = line.slice(6);

                    if (payload === '[DONE]') {
                        break;
                    }

                    const event = JSON.parse(payload) as Record<string, unknown>;

                    if (event.type === 'text_delta') {
                        setMessages((prev) => {
                            const updated = [...prev];
                            updated[updated.length - 1] = {
                                role: 'assistant',
                                content: updated[updated.length - 1].content + (event.delta as string),
                            };
                            return updated;
                        });
                    } else if (event.type === 'conversation_id') {
                        setConversationId(event.conversation_id as string);
                    }
                }
            }
        } catch {
            setMessages((prev) => {
                const updated = [...prev];
                updated[updated.length - 1] = {
                    role: 'assistant',
                    content: 'エラーが発生しました。もう一度お試しください。',
                };
                return updated;
            });
        } finally {
            setIsLoading(false);
        }
    }

    function handleKeyDown(e: React.KeyboardEvent<HTMLTextAreaElement>) {
        if (e.key === 'Enter' && !e.shiftKey) {
            e.preventDefault();
            handleSend();
        }
    }

    return (
        <>
            <Head title={scenario.title} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-hidden p-4">
                {/* Header */}
                <div className="flex items-center gap-3">
                    <Link href={dashboard()}>
                        <Button variant="ghost" size="icon" className="shrink-0">
                            <ArrowLeft className="h-4 w-4" />
                        </Button>
                    </Link>
                    <div className="min-w-0 flex-1">
                        <div className="flex items-center gap-2">
                            <h1 className="truncate text-lg font-semibold">{scenario.title}</h1>
                            <Badge className={difficultyTone[scenario.difficulty] ?? ''}>{scenario.difficulty}</Badge>
                        </div>
                        <p className="text-sm text-muted-foreground">
                            {scenario.company_name} / {scenario.industry}
                        </p>
                    </div>
                </div>

                <div className="flex flex-1 gap-4 overflow-hidden">
                    {/* Scenario info panel */}
                    <aside className="hidden w-72 shrink-0 flex-col gap-3 overflow-y-auto lg:flex">
                        <Card className="border-border/70">
                            <CardHeader className="pb-3">
                                <CardTitle className="text-sm">想定相手</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm text-foreground/90">{scenario.customer_persona}</p>
                            </CardContent>
                        </Card>
                        <Card className="border-border/70">
                            <CardHeader className="pb-3">
                                <CardTitle className="text-sm">Situation</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm leading-6 text-foreground/90">{scenario.summary}</p>
                            </CardContent>
                        </Card>
                        <Card className="border-border/70">
                            <CardHeader className="pb-3">
                                <CardTitle className="text-sm">Goal</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <p className="text-sm leading-6 text-foreground/90">{scenario.goal}</p>
                            </CardContent>
                        </Card>
                    </aside>

                    {/* Chat area */}
                    <div className="flex flex-1 flex-col overflow-hidden rounded-xl border border-border/70 bg-background">
                        {/* Messages */}
                        <div className="flex-1 overflow-y-auto p-4 space-y-4">
                            {messages.length === 0 ? (
                                <div className="flex h-full flex-col items-center justify-center gap-3 text-center text-muted-foreground">
                                    <div className="rounded-full bg-muted p-4">
                                        <Send className="h-6 w-6" />
                                    </div>
                                    <div className="space-y-1">
                                        <p className="text-sm font-medium">チャットを開始してください</p>
                                        <p className="text-xs">メッセージを送信するとAIが応答します。</p>
                                    </div>
                                </div>
                            ) : (
                                messages.map((msg, i) => (
                                    <div key={i} className={`flex ${msg.role === 'user' ? 'justify-end' : 'justify-start'}`}>
                                        <div
                                            className={`max-w-[75%] rounded-2xl px-4 py-2.5 ${
                                                msg.role === 'user'
                                                    ? 'whitespace-pre-wrap text-sm leading-6 bg-primary text-primary-foreground'
                                                    : 'bg-muted text-foreground'
                                            }`}
                                        >
                                            {msg.role === 'user' ? (
                                                msg.content
                                            ) : (
                                                <>
                                                    {msg.content ? (
                                                        <div className="prose prose-sm dark:prose-invert max-w-none">
                                                            <ReactMarkdown remarkPlugins={[remarkGfm]}>{msg.content}</ReactMarkdown>
                                                        </div>
                                                    ) : (
                                                        isLoading && i === messages.length - 1 && (
                                                            <span className="inline-flex gap-0.5 items-end h-4">
                                                                <span className="animate-bounce [animation-delay:0ms] text-xs">●</span>
                                                                <span className="animate-bounce [animation-delay:150ms] text-xs">●</span>
                                                                <span className="animate-bounce [animation-delay:300ms] text-xs">●</span>
                                                            </span>
                                                        )
                                                    )}
                                                </>
                                            )}
                                        </div>
                                    </div>
                                ))
                            )}
                            <div ref={messagesEndRef} />
                        </div>

                        {/* Input */}
                        <div className="border-t border-border/70 p-3">
                            <div className="flex items-end gap-2">
                                <textarea
                                    value={input}
                                    onChange={(e) => setInput(e.target.value)}
                                    onKeyDown={handleKeyDown}
                                    placeholder="メッセージを入力… (Enter で送信、Shift+Enter で改行)"
                                    rows={2}
                                    disabled={isLoading}
                                    className="flex-1 resize-none rounded-lg border border-border bg-muted/30 px-3 py-2 text-sm placeholder:text-muted-foreground focus:outline-none focus:ring-1 focus:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
                                />
                                <Button onClick={handleSend} disabled={isLoading || !input.trim()} size="icon" className="shrink-0">
                                    <Send className="h-4 w-4" />
                                </Button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </>
    );
}

Chat.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Chat', href: '' },
    ],
};
