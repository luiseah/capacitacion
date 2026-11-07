import { Head, Link, router } from '@inertiajs/react';
import {
    ArrowDownLeft,
    ArrowRight,
    ArrowUpRight,
    Briefcase,
    Check,
    ChevronsUpDown,
    Globe,
    GraduationCap,
    MapPin,
    Presentation,
    UserPlus,
} from 'lucide-react';
import { useEffect, useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { Button } from '@/components/ui/button';
import {
    DropdownMenu,
    DropdownMenuContent,
    DropdownMenuItem,
    DropdownMenuLabel,
    DropdownMenuSeparator,
    DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { firstErrors } from '@/lib/request-payload';
import { cn } from '@/lib/utils';
import { ApiError } from '@/services';
import { useCreateMobility, useMobilityOptions, useParticipants } from '@academic/hooks/use-mobilities';
import { useRequirementsPreview } from '@academic/hooks/use-requirements';
import { MobilitySlot } from '@academic/extensions';
import { RequirementList } from '@academic/components/requirement-list';
import { InstitutionPicker } from '@academic/components/institution-picker';
import { VisitorFormDialog } from '@academic/components/visitor-form-dialog';
import { PARTICIPANT_LABELS, enumLabel } from '@academic/types';
import type { Institution, MobilityDirection, MobilityScope, ParticipantKind, ParticipantOption } from '@academic/types';
import { useTranslation } from '@/hooks/use-translation';

const LIST_URL = '/academic-mobility/mobilities';

const PARTICIPANT_CHOICES: Record<ParticipantKind, { icon: typeof Globe; hint: string }> = {
    student: { icon: GraduationCap, hint: 'Enrolled in a program' },
    professor: { icon: Presentation, hint: 'Teaching and research staff' },
    staff: { icon: Briefcase, hint: 'Administrative personnel' },
};

/** Big, selectable cards for the button groups. */
const choiceClass =
    'h-auto flex-1 flex-col items-start gap-1 px-4 py-3 text-left data-[state=on]:border-primary data-[state=on]:bg-primary/10 data-[state=on]:text-foreground';

function Choice({ icon: Icon, title, hint }: { icon: typeof Globe; title: string; hint: string }) {
    return (
        <>
            <span className="flex items-center gap-2 text-sm font-medium">
                <Icon className="size-4" aria-hidden />
                {title}
            </span>
            <span className="text-xs font-normal whitespace-normal text-muted-foreground">{hint}</span>
        </>
    );
}

/** What tells two profiles of the same person apart: their details in one line. */
const summary = (participant: ParticipantOption): string =>
    participant.details.map((detail) => detail.value).join(' · ') || `#${participant.id}`;

function ParticipantRow({ participant, selected }: { participant: ParticipantOption; selected: boolean }) {
    return (
        <>
            <span className="flex-1">
                <span className="block font-medium">{participant.name}</span>
                <span className="block text-xs text-muted-foreground">{summary(participant)}</span>
            </span>
            {selected && <Check className="size-4" aria-hidden />}
        </>
    );
}

export default function MobilitiesCreate() {
    const { t } = useTranslation();
    const { data: options, isPending, isError, error } = useMobilityOptions();
    const create = useCreateMobility();
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [eligibility, setEligibility] = useState<string[]>([]);
    const [kindChoice, setKindChoice] = useState<ParticipantKind | ''>('');
    const [participantChoice, setParticipantChoice] = useState<{ source: string; participant: ParticipantOption } | null>(null);
    const [menuOpen, setMenuOpen] = useState(false);
    const [registering, setRegistering] = useState(false);
    const [participantSearch, setParticipantSearch] = useState('');
    const [debouncedSearch, setDebouncedSearch] = useState('');
    const [chosenDirection, setChosenDirection] = useState<MobilityDirection | ''>('');
    const [chosenScope, setChosenScope] = useState<MobilityScope | ''>('');
    const [institution, setInstitution] = useState<Institution | null>(null);
    // The agreement it is made under (optional): one in force with that institution.
    const [agreementId, setAgreementId] = useState<string | null>(null);

    const local = options?.institutions.find((institution) => institution.is_local);
    // Only the rules that serve the chosen direction (a strategy may be for incoming or outgoing only).
    const strategies = (options?.strategies ?? []).filter(
        (candidate) => chosenDirection === '' || candidate.direction === null || candidate.direction === chosenDirection,
    );
    // The eligibility rules are not asked for now: the default ones apply.
    const wanted = options?.default_strategy ?? '';
    const alias = strategies.find((candidate) => candidate.alias === wanted)?.alias ?? strategies.at(0)?.alias ?? '';
    const strategy = strategies.find((candidate) => candidate.alias === alias);
    // The strategy may fix the direction; otherwise it is chosen here.
    const direction: MobilityDirection | '' = strategy?.direction ?? chosenDirection;
    const partner = institution ?? undefined;
    const institutionId = institution?.id ?? '';
    // The origin decides where participants come from: the university's own people leave, visitors of
    // the origin institution arrive. Nothing can be picked before that is known.
    const kinds: ParticipantKind[] = direction === '' ? [] : (options?.participant_types[direction] ?? []);
    const kind: ParticipantKind | '' = kinds.includes(kindChoice as ParticipantKind) ? (kindChoice as ParticipantKind) : (kinds.at(0) ?? '');
    // Visitors that are not in the list yet can be registered from here; own people cannot.
    const visitorForm = direction === 'incoming' && kind !== '' ? options?.participant_forms.incoming?.[kind] : undefined;
    // What this kind of mobility will have to bring: the sum of the catalog's entries that match.
    const { data: requirements, isPending: loadingRequirements } = useRequirementsPreview(direction, chosenScope, kind);
    const sourceReady = direction === 'outgoing' || (direction === 'incoming' && institutionId !== '');
    const source = `${direction}|${direction === 'incoming' ? institutionId : ''}|${kind}`;
    // The signed-in user's own profiles of this kind ("Me") only apply to the university's own people.
    const mine = direction === 'outgoing' ? (options?.current_participants ?? []).filter((candidate) => candidate.type === kind) : [];
    const isMine = (candidate: ParticipantOption) => mine.some((own) => own.id === candidate.id);
    // The first of the user's profiles is the default participant; the dropdown picks any other.
    const participant: ParticipantOption | null =
        sourceReady && kind !== '' ? (participantChoice?.source === source ? participantChoice.participant : (mine[0] ?? null)) : null;
    const { data: results, isFetching: searchingParticipants } = useParticipants(kind, direction, institutionId, debouncedSearch, menuOpen && sourceReady);
    // The user's own profiles have their own group, so the results never repeat them.
    const found = results?.filter((candidate) => !isMine(candidate));
    const needle = participantSearch.trim().toLowerCase();
    const shownMine = mine.filter(
        (own) =>
            needle === '' ||
            own.name.toLowerCase().includes(needle) ||
            own.details.some((detail) => detail.value.toLowerCase().includes(needle)),
    );
    const ready = kind !== '' && participant !== null && sourceReady && chosenScope !== '' && institutionId !== '';

    useEffect(() => {
        const timer = setTimeout(() => setDebouncedSearch(participantSearch.trim()), 300);

        return () => clearTimeout(timer);
    }, [participantSearch]);

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setErrors({});
        setEligibility([]);

        create.mutate(
            {
                participant_type: kind as ParticipantKind,
                participant_id: participant?.id ?? '',
                strategy: alias,
                direction: strategy?.direction ? null : (direction as MobilityDirection),
                scope: chosenScope as MobilityScope,
                institution_id: institutionId,
                agreement_id: agreementId,
            },
            {
                onSuccess: (response) => {
                    toast.success(response.message ?? t('Mobility created.'));
                    // Straight to the request, to see what it has to bring.
                    router.visit(`${LIST_URL}/${response.data.id}`);
                },
                onError: (failure) => {
                    if (failure instanceof ApiError && failure.isValidation) {
                        setErrors(firstErrors(failure.errors));
                        setEligibility(failure.errors.eligibility ?? []);

                        return;
                    }

                    toast.error(failure.message);
                },
            },
        );
    };

    const [origin, destination] =
        local && direction === 'incoming' ? [partner, local] : local && direction === 'outgoing' ? [local, partner] : [undefined, undefined];

    return (
        <>
            <Head title={t('New mobility')} />

            <div className="flex flex-col gap-6 p-4">
                <Heading title={t('New mobility')} description={t('Created as a draft once the participant passes the rules.')} />

                {isPending && <p className="text-sm text-muted-foreground">{t('Loading…')}</p>}
                {isError && <p className="text-sm text-red-600 dark:text-red-400">{error.message}</p>}

                {options && !local && (
                    <div
                        role="alert"
                        className="max-w-2xl rounded-md border border-amber-300 bg-amber-50 p-3 text-sm text-amber-800 dark:border-amber-900 dark:bg-amber-950 dark:text-amber-300"
                    >
                        {t('There is no local institution yet.')}{' '}
                        <Link href="/academic-mobility/institutions/create" className="underline">
                            {t('Create it first')}
                        </Link>
                        .
                    </div>
                )}

                {options && local && (
                    <form onSubmit={handleSubmit} className="w-full max-w-2xl space-y-8">
                        {eligibility.length > 0 && (
                            <div
                                role="alert"
                                className="rounded-md border border-red-300 bg-red-50 p-3 text-sm text-red-700 dark:border-red-900 dark:bg-red-950 dark:text-red-300"
                            >
                                <p className="font-medium">{t('The participant is not eligible:')}</p>
                                <ul className="list-disc pl-5">
                                    {eligibility.map((message) => (
                                        <li key={message}>{message}</li>
                                    ))}
                                </ul>
                            </div>
                        )}

                        <section className="space-y-4">
                            <h2 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                                {t('1 · Type of mobility')}
                            </h2>

                            <div className="grid gap-2">
                                <Label>{t('Direction')} *</Label>
                                <ToggleGroup
                                    type="single"
                                    variant="outline"
                                    value={direction}
                                    onValueChange={(value) => value && setChosenDirection(value as MobilityDirection)}
                                    disabled={strategy?.direction !== null && strategy?.direction !== undefined}
                                    className="w-full items-stretch"
                                    aria-label={t('Direction')}
                                >
                                    <ToggleGroupItem value="outgoing" className={choiceClass}>
                                        <Choice icon={ArrowUpRight} title={t('Outgoing')} hint={t('Leaves :name', { name: local.name })} />
                                    </ToggleGroupItem>
                                    <ToggleGroupItem value="incoming" className={choiceClass}>
                                        <Choice icon={ArrowDownLeft} title={t('Incoming')} hint={t('Arrives at :name', { name: local.name })} />
                                    </ToggleGroupItem>
                                </ToggleGroup>
                                {strategy?.direction && (
                                    <p className="text-xs text-muted-foreground">
                                        {t('The selected rules are always :direction.', { direction: t(enumLabel(strategy.direction)).toLowerCase() })}
                                    </p>
                                )}
                                <InputError message={errors.direction} />
                            </div>

                            <div className="grid gap-2">
                                <Label>{t('Scope')} *</Label>
                                <ToggleGroup
                                    type="single"
                                    variant="outline"
                                    value={chosenScope}
                                    onValueChange={(value) => {
                                        if (value) {
                                            setChosenScope(value as MobilityScope);
                                            setInstitution(null);
                                            setAgreementId(null);
                                        }
                                    }}
                                    className="w-full items-stretch"
                                    aria-label={t('Scope')}
                                >
                                    <ToggleGroupItem value="national" className={choiceClass}>
                                        <Choice icon={MapPin} title={t('National')} hint={t('Institutions in :country', { country: local.country })} />
                                    </ToggleGroupItem>
                                    <ToggleGroupItem value="international" className={choiceClass}>
                                        <Choice icon={Globe} title={t('International')} hint={t('Institutions outside :country', { country: local.country })} />
                                    </ToggleGroupItem>
                                </ToggleGroup>
                                <InputError message={errors.scope} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="institution_id">
                                    {direction === 'incoming'
                                        ? `${t('Origin institution')} *`
                                        : direction === 'outgoing'
                                          ? `${t('Destination institution')} *`
                                          : `${t('Other institution')} *`}
                                </Label>
                                <InstitutionPicker id="institution_id" scope={chosenScope} value={institution} onChange={(chosen) => {
                                        setInstitution(chosen);
                                        // The agreement belongs to the institution: choose it again.
                                        setAgreementId(null);
                                    }}
                                />
                                <InputError message={errors.institution_id} />
                                <InputError message={errors.route} />

                                {institution && (
                                    <MobilitySlot
                                        name="create.partner"
                                        institution={institution}
                                        local={local}
                                        value={agreementId}
                                        onChange={setAgreementId}
                                        error={errors.agreement_id}
                                    />
                                )}
                            </div>

                            <div
                                aria-live="polite"
                                className="flex flex-wrap items-center gap-3 rounded-lg border bg-muted/40 p-4 text-sm"
                            >
                                <span className={cn('font-medium', !origin && 'text-muted-foreground')}>
                                    {origin?.name ?? t('Origin')}
                                </span>
                                <ArrowRight className="size-4 text-muted-foreground" aria-hidden />
                                <span className={cn('font-medium', !destination && 'text-muted-foreground')}>
                                    {destination?.name ?? t('Destination')}
                                </span>
                                {direction && chosenScope && (
                                    <span className="ml-auto rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium capitalize">
                                        {t(enumLabel(direction))} · {t(enumLabel(chosenScope))}
                                    </span>
                                )}
                            </div>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                                {t('2 · Who')}
                            </h2>

                            <p className="text-sm text-muted-foreground">
                                {direction === 'incoming'
                                    ? t('Visitors from the origin institution.')
                                    : direction === 'outgoing'
                                      ? t('People of the university.')
                                      : t('Choose the direction first: it tells where the participants come from.')}
                            </p>

                            <div className="grid gap-2">
                                <Label>{t('Participant type')} *</Label>
                                <ToggleGroup
                                    type="single"
                                    variant="outline"
                                    value={kind}
                                    onValueChange={(value) => {
                                        if (value) {
                                            setKindChoice(value as ParticipantKind);
                                            setParticipantSearch('');
                                        }
                                    }}
                                    className="w-full items-stretch"
                                    disabled={kinds.length === 0}
                                    aria-label={t('Participant type')}
                                >
                                    {kinds.map((value) => (
                                        <ToggleGroupItem key={value} value={value} className={choiceClass}>
                                            <Choice
                                                icon={PARTICIPANT_CHOICES[value].icon}
                                                title={t(PARTICIPANT_LABELS[value])}
                                                hint={t(PARTICIPANT_CHOICES[value].hint)}
                                            />
                                        </ToggleGroupItem>
                                    ))}
                                </ToggleGroup>
                                <InputError message={errors.participant_type} />
                            </div>

                            <div className="grid gap-2">
                                <Label htmlFor="participant">{t('Participant')} *</Label>
                                <DropdownMenu
                                    open={menuOpen}
                                    onOpenChange={(open) => {
                                        setMenuOpen(open);

                                        if (!open) {
                                            setParticipantSearch('');
                                        }
                                    }}
                                >
                                    <DropdownMenuTrigger asChild>
                                        <Button
                                            id="participant"
                                            type="button"
                                            variant="outline"
                                            disabled={!sourceReady || kind === ''}
                                            className="h-auto w-full justify-between px-3 py-2 text-left font-normal"
                                        >
                                            {participant ? (
                                                <span>
                                                    <span className="font-medium">{participant.name}</span>
                                                    {isMine(participant) && (
                                                        <span className="ml-2 rounded-full bg-primary/10 px-2 py-0.5 text-xs font-medium">
                                                            {t('You')}
                                                        </span>
                                                    )}
                                                    <span className="block text-xs text-muted-foreground">{summary(participant)}</span>
                                                </span>
                                            ) : (
                                                <span className="text-muted-foreground">
                                                    {direction === 'incoming' && institutionId === ''
                                                        ? t('Choose the origin institution first')
                                                        : direction === ''
                                                          ? t('Choose the direction first')
                                                          : t('Select a participant…')}
                                                </span>
                                            )}
                                            <ChevronsUpDown className="size-4 shrink-0 opacity-50" aria-hidden />
                                        </Button>
                                    </DropdownMenuTrigger>
                                    <DropdownMenuContent align="start" className="w-(--radix-dropdown-menu-trigger-width) min-w-72 p-0">
                                        <div className="border-b p-2">
                                            <Input
                                                type="search"
                                                value={participantSearch}
                                                onChange={(event) => setParticipantSearch(event.target.value)}
                                                // Typing must not trigger the menu's own type-ahead.
                                                onKeyDown={(event) => event.stopPropagation()}
                                                placeholder={t('Search by name, email or id')}
                                                aria-label={t('Search participants')}
                                                autoFocus
                                            />
                                        </div>
                                        <div className={cn('max-h-64 overflow-auto p-1', searchingParticipants && 'opacity-60')}>
                                            {shownMine.length > 0 && (
                                                <>
                                                    <DropdownMenuLabel className="text-xs text-muted-foreground">{t('Me')}</DropdownMenuLabel>
                                                    {shownMine.map((own) => (
                                                        <DropdownMenuItem key={own.id} onSelect={() => setParticipantChoice({ source, participant: own })}>
                                                            <ParticipantRow participant={own} selected={participant?.id === own.id} />
                                                        </DropdownMenuItem>
                                                    ))}
                                                    {found && found.length > 0 && <DropdownMenuSeparator />}
                                                </>
                                            )}
                                            {found?.length === 0 && shownMine.length === 0 && (
                                                <p className="p-3 text-sm text-muted-foreground">
                                                    {t('No participants found.')}
                                                    {visitorForm && ` ${t('You can register them below.')}`}
                                                </p>
                                            )}
                                            {found?.map((candidate) => (
                                                <DropdownMenuItem key={candidate.id} onSelect={() => setParticipantChoice({ source, participant: candidate })}>
                                                    <ParticipantRow participant={candidate} selected={participant?.id === candidate.id} />
                                                </DropdownMenuItem>
                                            ))}
                                        </div>
                                        {visitorForm && (
                                            <div className="border-t p-1">
                                                <DropdownMenuItem onSelect={() => setRegistering(true)}>
                                                    <UserPlus className="size-4" aria-hidden />
                                                    {t('Register a new visitor')}
                                                </DropdownMenuItem>
                                            </div>
                                        )}
                                    </DropdownMenuContent>
                                </DropdownMenu>
                                {visitorForm && institution && kind !== '' && (
                                    <VisitorFormDialog
                                        open={registering}
                                        onOpenChange={setRegistering}
                                        kind={kind}
                                        institution={institution}
                                        form={visitorForm}
                                        onCreated={(created) => setParticipantChoice({ source, participant: created })}
                                    />
                                )}
                                {participant && participant.details.length > 0 && (
                                    <dl className="grid gap-x-6 gap-y-2 rounded-lg border bg-muted/40 p-3 text-sm sm:grid-cols-2">
                                        {participant.details.map((detail) => (
                                            <div key={detail.label}>
                                                <dt className="text-xs text-muted-foreground">{detail.label}</dt>
                                                <dd className="font-medium">{detail.value}</dd>
                                            </div>
                                        ))}
                                    </dl>
                                )}
                                <InputError message={errors.participant_id} />
                            </div>
                        </section>

                        <section className="space-y-4">
                            <h2 className="text-sm font-semibold tracking-wide text-muted-foreground uppercase">
                                {t('3 · Requirements')}
                            </h2>
                            <p className="text-sm text-muted-foreground">
                                {t('What this request will have to bring depends on its direction, scope and participant type.')}
                            </p>
                            {direction === '' || chosenScope === '' || kind === '' ? (
                                <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                                    {t('Choose the direction, the scope and the participant type to see them.')}
                                </p>
                            ) : loadingRequirements ? (
                                <p className="text-sm text-muted-foreground">{t('Loading…')}</p>
                            ) : (
                                <RequirementList
                                    items={requirements ?? []}
                                    empty={t('No requirements for this kind of mobility.')}
                                />
                            )}
                        </section>

                        <div className="flex items-center gap-2">
                            <Button type="submit" disabled={!ready || create.isPending}>
                                {t('Create mobility')}
                            </Button>
                            <Button type="button" variant="secondary" asChild>
                                <Link href={LIST_URL}>{t('Cancel')}</Link>
                            </Button>
                            {!ready && (
                                <span className="text-xs text-muted-foreground">
                                    {t('Complete the participant, direction, scope and institution.')}
                                </span>
                            )}
                        </div>
                    </form>
                )}
            </div>
        </>
    );
}

MobilitiesCreate.layout = {
    breadcrumbs: [
        { title: 'Academic mobility', href: LIST_URL },
        { title: 'New', href: `${LIST_URL}/create` },
    ],
};
