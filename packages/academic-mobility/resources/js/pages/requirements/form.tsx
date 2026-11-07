import { Head, Link, router } from '@inertiajs/react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { useTranslation } from '@/hooks/use-translation';
import { firstErrors } from '@/lib/request-payload';
import { ApiError } from '@/services';
import { useRequirement, useSaveRequirement } from '@academic/hooks/use-requirements';
import { PARTICIPANT_LABELS } from '@academic/types';
import type { MobilityDirection, MobilityScope, ParticipantKind, Requirement } from '@academic/types';

const BASE = '/academic-mobility/requirements';
const ANY = 'any';
const textareaClass = 'min-h-20 w-full rounded-md border bg-background px-3 py-2 text-sm';

/** One criterion of the requirement as a button group; "Any" leaves it empty. */
function Criterion<T extends string>({
    label,
    value,
    onChange,
    options,
    hint,
    error,
}: {
    label: string;
    value: T | '';
    onChange: (value: T | '') => void;
    options: { value: T; label: string }[];
    hint: string;
    error?: string;
}) {
    const { t } = useTranslation();

    return (
        <div className="grid gap-2">
            <Label>{label}</Label>
            <ToggleGroup
                type="single"
                variant="outline"
                value={value === '' ? ANY : value}
                onValueChange={(next) => next && onChange(next === ANY ? '' : (next as T))}
                className="flex-wrap"
                aria-label={label}
            >
                <ToggleGroupItem value={ANY} className="px-4">
                    {t('Any')}
                </ToggleGroupItem>
                {options.map((option) => (
                    <ToggleGroupItem key={option.value} value={option.value} className="px-4">
                        {option.label}
                    </ToggleGroupItem>
                ))}
            </ToggleGroup>
            <p className="text-xs text-muted-foreground">{hint}</p>
            <InputError message={error} />
        </div>
    );
}

function RequirementForm({ requirement }: { requirement?: Requirement }) {
    const { t, locales } = useTranslation();
    const save = useSaveRequirement(requirement?.id);
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [direction, setDirection] = useState<MobilityDirection | ''>(requirement?.direction ?? '');
    const [scope, setScope] = useState<MobilityScope | ''>(requirement?.scope ?? '');
    const [kind, setKind] = useState<ParticipantKind | ''>(requirement?.participant_kind ?? '');
    const [mandatory, setMandatory] = useState(requirement?.is_mandatory ?? true);
    const [active, setActive] = useState(requirement?.is_active ?? true);
    // The texts as written are in English; every other language of the app gets its own pair of fields.
    const others = locales.filter((locale) => locale !== 'en');

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setErrors({});

        const form = new FormData(event.currentTarget);
        const text = (name: string) => String(form.get(name) ?? '').trim();

        save.mutate(
            {
                name: text('name'),
                description: text('description') || null,
                direction: direction || null,
                scope: scope || null,
                participant_kind: kind || null,
                is_mandatory: mandatory,
                is_active: active,
                position: text('position') === '' ? null : Number(text('position')),
                translations: Object.fromEntries(
                    others.map((locale) => [locale, { name: text(`name_${locale}`), description: text(`description_${locale}`) }]),
                ),
            },
            {
                onSuccess: (response) => {
                    toast.success(response.message ?? t('Requirement saved.'));
                    router.visit(BASE);
                },
                onError: (failure) => {
                    if (failure instanceof ApiError && failure.isValidation) {
                        setErrors(firstErrors(failure.errors));

                        return;
                    }

                    toast.error(failure.message);
                },
            },
        );
    };

    return (
        <>
            <Head title={requirement ? t('Edit requirement') : t('New requirement')} />

            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title={requirement ? t('Edit requirement') : t('New requirement')}
                    description={t('It applies to the mobilities that match every criterion below; "Any" matches all.')}
                />

                <form onSubmit={handleSubmit} className="w-full max-w-2xl space-y-8">
                    <section className="space-y-4">
                        <h2 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">{t('What is asked')}</h2>

                        <div className="grid gap-2">
                            <Label htmlFor="name">{t('Name')} *</Label>
                            <Input id="name" name="name" defaultValue={requirement?.source.name} required />
                            <InputError message={errors.name} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="description">{t('Description')}</Label>
                            <textarea id="description" name="description" defaultValue={requirement?.source.description ?? ''} className={textareaClass} />
                            <InputError message={errors.description} />
                        </div>

                        {others.map((locale) => (
                            <div key={locale} className="grid gap-4 rounded-lg border bg-muted/30 p-3">
                                <p className="text-xs font-medium uppercase">{locale === 'es' ? 'Español' : locale}</p>
                                <div className="grid gap-2">
                                    <Label htmlFor={`name_${locale}`}>{t('Name')}</Label>
                                    <Input id={`name_${locale}`} name={`name_${locale}`} defaultValue={requirement?.translations[locale]?.name ?? ''} />
                                    <InputError message={errors[`translations.${locale}.name`]} />
                                </div>
                                <div className="grid gap-2">
                                    <Label htmlFor={`description_${locale}`}>{t('Description')}</Label>
                                    <textarea
                                        id={`description_${locale}`}
                                        name={`description_${locale}`}
                                        defaultValue={requirement?.translations[locale]?.description ?? ''}
                                        className={textareaClass}
                                    />
                                </div>
                            </div>
                        ))}
                    </section>

                    <section className="space-y-4">
                        <h2 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">{t('Applies to')}</h2>

                        <Criterion
                            label={t('Direction')}
                            value={direction}
                            onChange={setDirection}
                            options={[
                                { value: 'outgoing', label: t('Outgoing') },
                                { value: 'incoming', label: t('Incoming') },
                            ]}
                            hint={t('Own people leaving, or visitors arriving.')}
                            error={errors.direction}
                        />
                        <Criterion
                            label={t('Scope')}
                            value={scope}
                            onChange={setScope}
                            options={[
                                { value: 'national', label: t('National') },
                                { value: 'international', label: t('International') },
                            ]}
                            hint={t('Same country as the local institution, or another one.')}
                            error={errors.scope}
                        />
                        <Criterion
                            label={t('Participant type')}
                            value={kind}
                            onChange={setKind}
                            options={(Object.keys(PARTICIPANT_LABELS) as ParticipantKind[]).map((value) => ({
                                value,
                                label: t(PARTICIPANT_LABELS[value]),
                            }))}
                            hint={t('Students, professors or administrative staff.')}
                            error={errors.participant_kind}
                        />
                    </section>

                    <section className="space-y-4">
                        <h2 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">{t('Options')}</h2>

                        <div className="flex items-center gap-2">
                            <Checkbox id="is_mandatory" checked={mandatory} onCheckedChange={(checked) => setMandatory(checked === true)} />
                            <Label htmlFor="is_mandatory">{t('Mandatory')}</Label>
                        </div>
                        <div className="flex items-center gap-2">
                            <Checkbox id="is_active" checked={active} onCheckedChange={(checked) => setActive(checked === true)} />
                            <Label htmlFor="is_active">{t('Active (asked from new requests)')}</Label>
                        </div>
                        <div className="grid gap-2 sm:max-w-40">
                            <Label htmlFor="position">{t('Order')}</Label>
                            <Input id="position" name="position" type="number" min={0} defaultValue={requirement?.position ?? ''} />
                            <InputError message={errors.position} />
                        </div>
                    </section>

                    <div className="flex gap-2">
                        <Button type="submit" disabled={save.isPending}>
                            {t('Save')}
                        </Button>
                        <Button type="button" variant="secondary" asChild>
                            <Link href={BASE}>{t('Cancel')}</Link>
                        </Button>
                    </div>
                </form>
            </div>
        </>
    );
}

export default function RequirementsForm({ requirementId }: { requirementId?: string }) {
    const { t } = useTranslation();
    const { data: requirement, isPending, isError, error } = useRequirement(requirementId);

    if (requirementId === undefined) {
        return <RequirementForm />;
    }

    if (isPending) {
        return <p className="p-4 text-sm text-muted-foreground">{t('Loading…')}</p>;
    }

    if (isError) {
        return <p className="p-4 text-sm text-red-600 dark:text-red-400">{error.message}</p>;
    }

    return <RequirementForm requirement={requirement} />;
}

RequirementsForm.layout = {
    breadcrumbs: [
        { title: 'Requirements', href: BASE },
        { title: 'Form', href: BASE },
    ],
};
