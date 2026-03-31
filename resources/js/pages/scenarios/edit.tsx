import { Head, Link, useHttp } from '@inertiajs/react';
import { ArrowLeft, File, FileImage, FileText, Trash2, UploadCloud } from 'lucide-react';
import { useCallback, useRef, useState } from 'react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import {
    Dialog,
    DialogClose,
    DialogContent,
    DialogDescription,
    DialogFooter,
    DialogTitle,
} from '@/components/ui/dialog';
import { destroy as destroyAttachment, download as downloadAttachment, store as storeAttachment } from '@/actions/App/Http/Controllers/ScenarioAttachmentController';
import { dashboard } from '@/routes';

type Attachment = {
    id: number;
    name: string;
    mime_type: string;
    size: number;
};

type Scenario = {
    id: number;
    title: string;
    company_name: string;
    industry: string;
    difficulty: string;
};

type EditProps = {
    scenario: Scenario;
    attachments: Attachment[];
};

const difficultyTone: Record<string, string> = {
    初級: 'bg-emerald-100 text-emerald-800 hover:bg-emerald-100 dark:bg-emerald-950 dark:text-emerald-200',
    中級: 'bg-amber-100 text-amber-800 hover:bg-amber-100 dark:bg-amber-950 dark:text-amber-200',
    上級: 'bg-rose-100 text-rose-800 hover:bg-rose-100 dark:bg-rose-950 dark:text-rose-200',
};

function formatBytes(bytes: number): string {
    if (bytes < 1024) return `${bytes} B`;
    if (bytes < 1024 * 1024) return `${(bytes / 1024).toFixed(1)} KB`;
    return `${(bytes / (1024 * 1024)).toFixed(1)} MB`;
}

function FileIcon({ mimeType }: { mimeType: string }) {
    if (mimeType.startsWith('image/')) return <FileImage className="h-4 w-4 shrink-0 text-blue-500" />;
    if (mimeType === 'application/pdf' || mimeType.includes('text')) return <FileText className="h-4 w-4 shrink-0 text-orange-500" />;
    return <File className="h-4 w-4 shrink-0 text-muted-foreground" />;
}

function getCsrfToken(): string {
    return (document.querySelector('meta[name="csrf-token"]') as HTMLMetaElement)?.content ?? '';
}

export default function Edit({ scenario, attachments: initialAttachments }: EditProps) {
    const [attachments, setAttachments] = useState<Attachment[]>(initialAttachments);
    const [isDragging, setIsDragging] = useState(false);
    const [attachmentPendingDelete, setAttachmentPendingDelete] = useState<Attachment | null>(null);
    const [deletingAttachmentId, setDeletingAttachmentId] = useState<number | null>(null);
    const inputRef = useRef<HTMLInputElement>(null);
    const {
        setData,
        post,
        progress,
        processing: uploading,
        errors,
        reset,
    } = useHttp<{ file: File | null }, Attachment>({ file: null });

    function uploadFile(file: File): Promise<void> {
        setData('file', file);

        return new Promise((resolve) => {
            post(storeAttachment(scenario.id).url, {
                onSuccess: (attachment) => {
                    setAttachments((current) => [...current, attachment]);
                    reset();
                    resolve();
                },
                onError: () => {
                    resolve();
                },
                onFinish: () => {
                    resolve();
                },
            });
        });
    }

    async function deleteAttachment(id: number) {
        setDeletingAttachmentId(id);

        try {
            const res = await fetch(destroyAttachment({ scenario: scenario.id, attachment: id }).url, {
                method: 'DELETE',
                headers: {
                    'X-CSRF-TOKEN': getCsrfToken(),
                    Accept: 'application/json',
                },
            });

            if (res.ok || res.status === 204) {
                setAttachments((prev) => prev.filter((a) => a.id !== id));
                setAttachmentPendingDelete((current) => (current?.id === id ? null : current));
            }
        } finally {
            setDeletingAttachmentId(null);
        }
    }

    const handleFiles = useCallback(
        async (files: FileList | null) => {
            if (!files || uploading) return;

            for (const file of Array.from(files)) {
                await uploadFile(file);
            }
        },
        [uploading],
    );

    function onDragOver(e: React.DragEvent) {
        e.preventDefault();
        setIsDragging(true);
    }

    function onDragLeave(e: React.DragEvent) {
        if (!e.currentTarget.contains(e.relatedTarget as Node)) {
            setIsDragging(false);
        }
    }

    function onDrop(e: React.DragEvent) {
        e.preventDefault();
        setIsDragging(false);
        handleFiles(e.dataTransfer.files);
    }

    return (
        <>
            <Head title={`${scenario.title} — 編集`} />
            <div className="flex h-full flex-1 flex-col gap-4 overflow-y-auto p-4">
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

                {/* Attachments */}
                <Card className="border-border/70">
                    <CardHeader className="pb-3">
                        <CardTitle className="text-base">添付ファイル</CardTitle>
                    </CardHeader>
                    <CardContent className="space-y-4">
                        {/* Drop zone */}
                        <div
                            onDragOver={onDragOver}
                            onDragLeave={onDragLeave}
                            onDrop={onDrop}
                            onClick={() => !uploading && inputRef.current?.click()}
                            className={`flex cursor-pointer flex-col items-center justify-center gap-2 rounded-xl border-2 border-dashed p-8 text-center transition-colors ${
                                isDragging
                                    ? 'border-primary bg-primary/5'
                                    : 'border-border hover:border-muted-foreground/50 hover:bg-muted/30'
                            } ${uploading ? 'cursor-not-allowed opacity-50' : ''}`}
                        >
                            <UploadCloud className="h-8 w-8 text-muted-foreground" />
                            <div className="space-y-1">
                                <p className="text-sm font-medium">
                                    {uploading ? 'アップロード中…' : 'ここにファイルをドロップ、またはクリックして選択'}
                                </p>
                                <p className="text-xs text-muted-foreground">PDF, テキスト, 画像, CSV, Word, Excel — 最大 10MB</p>
                                {progress && <p className="text-xs text-muted-foreground">{progress.percentage}%</p>}
                            </div>
                        </div>
                        <input
                            ref={inputRef}
                            type="file"
                            multiple
                            className="hidden"
                            onChange={(e) => handleFiles(e.target.files)}
                            accept=".pdf,.txt,.md,.png,.jpg,.jpeg,.gif,.webp,.csv,.docx,.xlsx"
                        />

                        {errors.file && <p className="text-sm text-destructive">{errors.file}</p>}

                        {/* File list */}
                        {attachments.length > 0 && (
                            <ul className="space-y-2">
                                {attachments.map((attachment) => (
                                    <li
                                        key={attachment.id}
                                        className="flex items-center gap-3 rounded-lg border border-border/70 bg-muted/20 px-3 py-2.5"
                                    >
                                        <FileIcon mimeType={attachment.mime_type} />
                                        <div className="min-w-0 flex-1">
                                            <a
                                                href={downloadAttachment({ scenario: scenario.id, attachment: attachment.id }).url}
                                                className="block truncate text-sm font-medium underline-offset-4 hover:underline"
                                            >
                                                {attachment.name}
                                            </a>
                                            <p className="text-xs text-muted-foreground">{formatBytes(attachment.size)}</p>
                                        </div>
                                        <Button
                                            variant="ghost"
                                            size="icon"
                                            className="shrink-0 text-muted-foreground hover:text-destructive"
                                            onClick={() => setAttachmentPendingDelete(attachment)}
                                        >
                                            <Trash2 className="h-4 w-4" />
                                        </Button>
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>
            </div>
            <Dialog
                open={attachmentPendingDelete !== null}
                onOpenChange={(open) => {
                    if (!open && deletingAttachmentId === null) {
                        setAttachmentPendingDelete(null);
                    }
                }}
            >
                <DialogContent>
                    <DialogTitle>この添付ファイルを削除しますか？</DialogTitle>
                    <DialogDescription>
                        {attachmentPendingDelete?.name ?? '選択中のファイル'} を削除します。この操作は元に戻せません。
                    </DialogDescription>

                    <DialogFooter className="gap-2">
                        <DialogClose asChild>
                            <Button variant="secondary" disabled={deletingAttachmentId !== null}>
                                キャンセル
                            </Button>
                        </DialogClose>

                        <Button
                            variant="destructive"
                            disabled={attachmentPendingDelete === null || deletingAttachmentId !== null}
                            onClick={() => {
                                if (attachmentPendingDelete !== null) {
                                    deleteAttachment(attachmentPendingDelete.id);
                                }
                            }}
                        >
                            削除する
                        </Button>
                    </DialogFooter>
                </DialogContent>
            </Dialog>
        </>
    );
}

Edit.layout = {
    breadcrumbs: [
        { title: 'Dashboard', href: dashboard() },
        { title: 'Edit', href: '' },
    ],
};
