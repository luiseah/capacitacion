import { Head, Link } from '@inertiajs/react';
import { ArrowRight } from 'lucide-react';
import { Badge } from '@/components/ui/badge';
import { Button } from '@/components/ui/button';
import { useTranslation } from '@/hooks/use-translation';
import { RequirementList } from '@academic/components/requirement-list';
import { MobilitySlot } from '@academic/extensions';
import { useMobility } from '@academic/hooks/use-mobilities';
import { PARTICIPANT_LABELS, enumLabel } from '@academic/types';

const LIST_URL = '/academic-mobility/mobilities';

export default function MobilitiesShow({ mobilityId }: { mobilityId: string }) {
    const { t, locale } = useTranslation();
    const { data: mobility, isPending, isError, error } = useMobility(mobilityId);

    if (isPending) {
        return <p className="p-4 text-sm text-muted-foreground">{t('Loading…')}</p>;
    }

    if (isError) {
        return <p className="p-4 text-sm text-red-600 dark:text-red-400">{error.message}</p>;
    }

    const requirements = mobility.requirements ?? [];
    // The institution on the other side: where the participant comes from or goes to.
    const partner = mobility.direction === 'incoming' ? mobility.origin : mobility.destination;
    const partnerId = partner?.id ?? null;
    const pending = requirements.filter((requirement) => requirement.is_mandatory && !requirement.fulfilled).length;

    return (
        <>
            <Head title={t('Mobility request')} />

            <div className="flex max-w-3xl flex-col gap-6 p-4">
                <div className="flex items-start justify-between gap-4">
                    <div className="space-y-2">
                        <h1 className="text-xl font-semibold tracking-tight">{t('Mobility request')}</h1>
                        <p className="font-mono text-xs text-muted-foreground">{mobility.id}</p>
                    </div>
                    <Badge variant={mobility.is_final ? 'secondary' : 'default'}>{t(enumLabel(mobility.status))}</Badge>
                </div>

                <div className="flex flex-wrap items-center gap-3 rounded-lg border bg-muted/40 p-4 text-sm">
                    <span className="font-medium">{mobility.origin?.name ?? '—'}</span>
                    <ArrowRight className="size-4 text-muted-foreground" aria-hidden />
                    <span className="font-medium">{mobility.destination?.name ?? '—'}</span>
                    {mobility.direction && mobility.scope && (
                        <span className="ml-auto rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium">
                            {t(enumLabel(mobility.direction))} · {t(enumLabel(mobility.scope))}
                        </span>
                    )}
                </div>

                <dl className="grid gap-4 rounded-xl border p-4 sm:grid-cols-2">
                    <div>
                        <dt className="text-sm text-muted-foreground">{t('Participant')}</dt>
                        <dd className="text-sm font-medium">{mobility.participant?.name ?? `#${mobility.participant_id}`}</dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted-foreground">{t('Participant type')}</dt>
                        <dd className="text-sm font-medium">{t(PARTICIPANT_LABELS[mobility.participant_type])}</dd>
                    </div>
                    {mobility.participant?.details.map((detail) => (
                        <div key={detail.label}>
                            <dt className="text-sm text-muted-foreground">{detail.label}</dt>
                            <dd className="text-sm font-medium">{detail.value}</dd>
                        </div>
                    ))}
                    <MobilitySlot name="show.summary" mobility={mobility} partnerId={partnerId} />
                    <div>
                        <dt className="text-sm text-muted-foreground">{t('Strategy')}</dt>
                        <dd className="text-sm font-medium">{mobility.strategy}</dd>
                    </div>
                    <div>
                        <dt className="text-sm text-muted-foreground">{t('Created')}</dt>
                        <dd className="text-sm font-medium">
                            {mobility.created_at ? new Date(mobility.created_at).toLocaleString(locale) : '—'}
                        </dd>
                    </div>
                </dl>

                <MobilitySlot name="show.section" mobility={mobility} partner={partner} partnerId={partnerId} />

                <section className="space-y-3">
                    <div className="flex items-baseline justify-between gap-2">
                        <h2 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">{t('Requirements')}</h2>
                        {requirements.length > 0 && (
                            <span className="text-xs text-muted-foreground">
                                {t(':count mandatory pending', { count: pending })}
                            </span>
                        )}
                    </div>
                    <RequirementList items={requirements} empty={t('This request has no requirements.')} />
                </section>

                <div>
                    <Button variant="secondary" asChild>
                        <Link href={LIST_URL}>{t('Back to the list')}</Link>
                    </Button>
                </div>
            </div>
        </>
    );
}

MobilitiesShow.layout = {
    breadcrumbs: [
        { title: 'Academic mobility', href: LIST_URL },
        { title: 'Mobility request', href: LIST_URL },
    ],
};
