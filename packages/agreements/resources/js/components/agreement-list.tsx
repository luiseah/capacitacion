import { Check } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { useTranslation } from '@/hooks/use-translation';
import { cn } from '@/lib/utils';
import { AGREEMENT_ACTIVITY_LABELS, AGREEMENT_STATUSES, AGREEMENT_STATUS_DESCRIPTIONS, AGREEMENT_STATUS_LABELS } from '@agreements/types';
import type { Agreement } from '@agreements/types';

/** How an agreement stands, by color. */
const STATUS_CLASS: Record<Agreement['status'], string> = {
    active: 'bg-emerald-100 text-emerald-800 dark:bg-emerald-950 dark:text-emerald-300',
    in_process: 'bg-sky-100 text-sky-800 dark:bg-sky-950 dark:text-sky-300',
    draft: 'bg-muted text-muted-foreground',
    expired: 'bg-muted text-muted-foreground',
    suspended: 'bg-amber-100 text-amber-800 dark:bg-amber-950 dark:text-amber-300',
    terminated: 'bg-red-100 text-red-800 dark:bg-red-950 dark:text-red-300',
};

/** Whether an agreement can be used today: in force and already started. */
export const isUsable = (agreement: Pick<Agreement, 'status' | 'starts_on'>): boolean =>
    agreement.status === 'active' && new Date(`${agreement.starts_on}T00:00:00`) <= new Date();

export function AgreementStatusBadge({ status }: { status: Agreement['status'] }) {
    const { t } = useTranslation();

    return <span className={cn('rounded-full px-2 py-0.5 text-xs font-medium', STATUS_CLASS[status])}>{t(AGREEMENT_STATUS_LABELS[status])}</span>;
}

/**
 * What the chosen status means, and a folded guide with all of them: so nobody has to guess what
 * "in process" or "suspended" imply.
 */
export function StatusGuide({ status }: { status: Agreement['status'] }) {
    const { t } = useTranslation();

    return (
        <div className="space-y-2 text-xs text-muted-foreground">
            <p>{t(AGREEMENT_STATUS_DESCRIPTIONS[status])}</p>
            <details className="rounded-md border bg-muted/30 px-3 py-2">
                <summary className="cursor-pointer font-medium text-foreground">{t('What does each status mean?')}</summary>
                <dl className="mt-2 grid gap-x-4 gap-y-2 sm:grid-cols-[8rem_1fr]">
                    {AGREEMENT_STATUSES.map((value) => (
                        <div key={value} className="contents">
                            <dt>
                                <AgreementStatusBadge status={value} />
                            </dt>
                            <dd>{t(AGREEMENT_STATUS_DESCRIPTIONS[value])}</dd>
                        </div>
                    ))}
                </dl>
            </details>
        </div>
    );
}

/** From and to, or "indefinite" when there is no end date. */
export function useValidity() {
    const { t, locale } = useTranslation();
    const day = (date: string) => new Date(`${date}T00:00:00`).toLocaleDateString(locale);

    return (agreement: Pick<Agreement, 'starts_on' | 'ends_on'>) =>
        agreement.ends_on === null
            ? t('From :start, indefinite', { start: day(agreement.starts_on) })
            : t(':start to :end', { start: day(agreement.starts_on), end: day(agreement.ends_on) });
}

/** The activities of an agreement, as badges ("Other" says what it is). */
export function ActivityBadges({ agreement }: { agreement: Pick<Agreement, 'activities' | 'other_activity'> }) {
    const { t } = useTranslation();

    return (
        <span className="flex flex-wrap gap-1">
            {agreement.activities.map((activity) => (
                <Badge key={activity} variant="secondary">
                    {activity === 'other' && agreement.other_activity
                        ? `${t(AGREEMENT_ACTIVITY_LABELS.other)}: ${agreement.other_activity}`
                        : t(AGREEMENT_ACTIVITY_LABELS[activity])}
                </Badge>
            ))}
        </span>
    );
}

/**
 * The agreements of an institution with the local one. With `onSelect` the ones in force can be picked (and
 * picked again to let go): a request is made under one of them. `highlightId` marks the one a request was made under.
 */
export function AgreementList({
    agreements,
    empty,
    selectedId,
    onSelect,
    highlightId,
}: {
    agreements: Agreement[];
    empty: string;
    selectedId?: string | null;
    onSelect?: (agreement: Agreement | null) => void;
    highlightId?: string | null;
}) {
    const { t } = useTranslation();
    const validity = useValidity();

    if (agreements.length === 0) {
        return <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">{empty}</p>;
    }

    return (
        <ul className="space-y-2">
            {agreements.map((agreement) => {
                const usable = isUsable(agreement);
                const chosen = selectedId === agreement.id || highlightId === agreement.id;
                const selectable = onSelect !== undefined;

                const content = (
                    <>
                        {selectable && (
                            <span
                                className={cn(
                                    'mt-0.5 flex size-5 shrink-0 items-center justify-center rounded-full border',
                                    chosen && 'border-primary bg-primary text-primary-foreground',
                                )}
                                aria-hidden
                            >
                                {chosen && <Check className="size-3" />}
                            </span>
                        )}
                        <span className="flex-1 space-y-1">
                            <span className="flex flex-wrap items-center gap-2">
                                <span className="font-medium">{agreement.name}</span>
                                <Badge variant="outline">{agreement.type === 'framework' ? t('Framework') : t('Specific')}</Badge>
                                <Badge variant="outline">{agreement.parties === 'multilateral' ? t('Multilateral') : t('Bilateral')}</Badge>
                                <AgreementStatusBadge status={agreement.status} />
                                {highlightId === agreement.id && <Badge>{t('Made under this agreement')}</Badge>}
                                {agreement.reference && <span className="ml-auto font-mono text-xs text-muted-foreground">{agreement.reference}</span>}
                            </span>
                            <span className="block text-xs text-muted-foreground">{validity(agreement)}</span>
                            {agreement.purpose && <span className="block text-xs text-muted-foreground">{agreement.purpose}</span>}
                            {agreement.parties === 'multilateral' && (
                                <span className="block text-xs text-muted-foreground">
                                    {t('With :institutions', {
                                        institutions: [agreement.institution?.name, ...(agreement.institutions ?? []).map((institution) => institution.name)]
                                            .filter(Boolean)
                                            .join(', '),
                                    })}
                                </span>
                            )}
                            <ActivityBadges agreement={agreement} />
                            {selectable && !usable && (
                                <span className="block text-xs text-muted-foreground">{t('Only agreements in force can be chosen.')}</span>
                            )}
                        </span>
                    </>
                );

                const frame = cn(
                    'flex items-start gap-3 rounded-lg border p-3 text-left text-sm',
                    chosen && 'border-primary bg-primary/5',
                    !usable && selectable && 'opacity-60',
                    ['expired', 'terminated'].includes(agreement.status) && !selectable && 'opacity-70',
                );

                return (
                    <li key={agreement.id}>
                        {selectable ? (
                            <button
                                type="button"
                                disabled={!usable}
                                aria-pressed={selectedId === agreement.id}
                                onClick={() => onSelect(selectedId === agreement.id ? null : agreement)}
                                className={cn(frame, 'w-full', usable ? 'hover:bg-accent' : 'cursor-not-allowed')}
                            >
                                {content}
                            </button>
                        ) : (
                            <div className={frame}>{content}</div>
                        )}
                    </li>
                );
            })}
        </ul>
    );
}
