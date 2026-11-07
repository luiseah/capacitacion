import { useTranslation } from '@/hooks/use-translation';
import InputError from '@/components/input-error';
import { AgreementList } from '@agreements/components/agreement-list';
import { useAgreementsOf } from '@agreements/hooks/use-agreements';
import type { MobilitySlots } from '@academic/extensions';

/** Creation form of a mobility: the agreements with the chosen institution, to pick the one it is made under. */
export function PartnerAgreementsPicker({ institution, local, value, onChange, error }: MobilitySlots['create.partner']) {
    const { t } = useTranslation();
    const { data: agreements, isPending } = useAgreementsOf(institution.id);

    return (
        <div className="mt-2 space-y-2">
            <p className="text-sm font-medium">{t('Agreements with :name', { name: institution.name })}</p>
            {isPending ? (
                <p className="text-sm text-muted-foreground">{t('Loading…')}</p>
            ) : (
                <>
                    {!(agreements ?? []).some((agreement) => agreement.status === 'active') && (
                        <p className="rounded-md border border-amber-300 bg-amber-50 p-2 text-xs text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300">
                            {t('There is no agreement in force with this institution.')}
                        </p>
                    )}
                    <p className="text-xs text-muted-foreground">{t('Choose the agreement this mobility is made under (optional).')}</p>
                    <AgreementList
                        agreements={agreements ?? []}
                        empty={t('This institution has no agreements with :local.', { local: local.name })}
                        selectedId={value}
                        onSelect={(chosen) => onChange(chosen?.id ?? null)}
                    />
                    <InputError message={error} />
                </>
            )}
        </div>
    );
}

/** Summary of a mobility: the agreement it was made under. */
export function MobilityAgreementRow({ mobility, partnerId }: MobilitySlots['show.summary']) {
    const { t } = useTranslation();
    const { data: agreements } = useAgreementsOf(partnerId ?? '');
    const name = mobility.agreement_id ? agreements?.find((agreement) => agreement.id === mobility.agreement_id)?.name : null;

    return (
        <div>
            <dt className="text-sm text-muted-foreground">{t('Agreement')}</dt>
            <dd className="text-sm font-medium">{name ?? t('None')}</dd>
        </div>
    );
}

/** Page of a mobility: every agreement with the institution on the other side, the one used highlighted. */
export function MobilityAgreementsSection({ mobility, partner, partnerId }: MobilitySlots['show.section']) {
    const { t } = useTranslation();
    const { data: agreements } = useAgreementsOf(partnerId ?? '');

    return (
        <section className="space-y-3">
            <h2 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                {t('Agreements with :name', { name: partner?.name ?? '—' })}
            </h2>
            <AgreementList
                agreements={agreements ?? []}
                empty={t('This institution has no agreements with the local one.')}
                highlightId={mobility.agreement_id}
            />
        </section>
    );
}
