import { Head, Link, router } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { useState } from 'react';
import type { FormEvent } from 'react';
import { toast } from 'sonner';
import Heading from '@/components/heading';
import InputError from '@/components/input-error';
import { RequestFieldInput } from '@/components/request-field-input';
import { Button } from '@/components/ui/button';
import {
    Card,
    CardDescription,
    CardHeader,
    CardTitle,
} from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import {
    useCreateRequest,
    useUpdateRequest,
} from '@/hooks/requests/use-request-mutations';
import {
    useRequest,
    useRequestTypes,
} from '@/hooks/requests/use-request-queries';
import { buildRequestPayload, firstErrors } from '@/lib/request-payload';
import { generateRequestSample } from '@/lib/request-sample-data';
import type { RequestSample } from '@/lib/request-sample-data';
import { cn } from '@/lib/utils';
import { create, form, index, show } from '@/routes/requests';
import { ApiError } from '@/services';
import type {
    ApiRequestDetail,
    ApiRequestTypeDefinition,
    RequestField,
} from '@/types';

/**
 * Converts a stored value to what the matching input expects as its default
 * (e.g. `2026-12-01 08:00:00` -> `2026-12-01T08:00`).
 */
function storedValue(
    field: RequestField,
    value: ApiRequestDetail['details'][number]['value'] | undefined,
): string | boolean | undefined {
    if (value === null || value === undefined || value === '') {
        return field.type === 'boolean' ? false : undefined;
    }

    switch (field.type) {
        case 'boolean':
            return value === true || value === 1 || value === '1';
        case 'datetime':
            return String(value).replace(' ', 'T').slice(0, 16);
        case 'date':
            return String(value).slice(0, 10);
        default:
            return String(value);
    }
}

type Props = {
    /** Create mode: the type chosen in the URL. */
    typeKey?: string;
    /** Edit mode: the request being edited. */
    requestUuid?: string;
};

export default function RequestsForm({ typeKey, requestUuid }: Props) {
    const editing = requestUuid !== undefined;

    const catalog = useRequestTypes();
    const existing = useRequest(requestUuid);

    if (catalog.isError || existing.isError) {
        const failure = catalog.error ?? existing.error;

        return (
            <p className="p-4 text-sm text-red-600 dark:text-red-400">
                {failure?.message}
            </p>
        );
    }

    if (catalog.isPending || (editing && existing.isPending)) {
        return <p className="p-4 text-sm text-muted-foreground">Loading…</p>;
    }

    const activeKey = editing ? existing.data?.type_key : typeKey;
    const type = catalog.data.types.find((option) => option.key === activeKey);

    if (!type) {
        return <p className="p-4 text-sm text-muted-foreground">Request type not found.</p>;
    }

    return (
        <RequestFormBody
            types={catalog.data.types}
            priorities={catalog.data.priorities}
            type={type}
            request={existing.data}
        />
    );
}

function RequestFormBody({
    types,
    priorities,
    type,
    request,
}: {
    types: ApiRequestTypeDefinition[];
    priorities: string[];
    type: ApiRequestTypeDefinition;
    request?: ApiRequestDetail;
}) {
    const editing = request !== undefined;
    const canSubmit = !editing || request.status === 'draft';
    const fields = type.fields;

    const creation = useCreateRequest();
    const update = useUpdateRequest();
    const processing = creation.isPending || update.isPending;

    const [action, setAction] = useState<'draft' | 'submit'>('draft');
    const [errors, setErrors] = useState<Record<string, string>>({});
    // Development helper: values are applied as defaults and the form is
    // remounted (formKey) so the uncontrolled inputs pick them up.
    const [sample, setSample] = useState<RequestSample | null>(null);
    const [formKey, setFormKey] = useState(0);
    const typeSample = sample?.typeKey === type.key ? sample : null;

    const stored = Object.fromEntries(
        (request?.details ?? []).map((detail) => [detail.key, detail.value]),
    );

    const fillWithSampleData = async () => {
        setSample(await generateRequestSample(type.key, type.name, fields, priorities));
        setFormKey((key) => key + 1);
    };

    const handleSuccess = (saved: ApiRequestDetail) => {
        toast.success(
            !editing
                ? action === 'submit'
                    ? 'Request submitted.'
                    : 'Request saved as draft.'
                : action === 'submit'
                  ? 'Request submitted.'
                  : 'Request updated.',
        );
        router.visit(show(saved.uuid));
    };

    const handleError = (failure: Error) => {
        if (failure instanceof ApiError && failure.isValidation) {
            setErrors(firstErrors(failure.errors));

            return;
        }

        toast.error(failure.message);
    };

    const handleSubmit = (event: FormEvent<HTMLFormElement>) => {
        event.preventDefault();
        setErrors({});

        const payload = buildRequestPayload(new FormData(event.currentTarget), fields, action);
        const callbacks = { onSuccess: handleSuccess, onError: handleError };

        if (request) {
            update.mutate({ uuid: request.uuid, payload }, callbacks);
        } else {
            creation.mutate({ type: type.key, payload }, callbacks);
        }
    };

    return (
        <>
            <Head title={editing ? `Edit ${request.title}` : type.name} />

            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title={editing ? 'Edit request' : 'New request'}
                    description={
                        editing
                            ? type.name
                            : 'Choose the type of request and fill in its details'
                    }
                />

                {!editing && (
                    <nav
                        aria-label="Request type"
                        className="grid gap-4 md:grid-cols-2 xl:grid-cols-4"
                    >
                        {types.map((option) => {
                            const active = option.key === type.key;

                            return (
                                <Link
                                    key={option.key}
                                    href={form(option.key)}
                                    preserveState
                                    preserveScroll
                                    replace
                                    only={['typeKey']}
                                    aria-current={active ? 'page' : undefined}
                                >
                                    <Card
                                        className={cn(
                                            'h-full transition-colors hover:bg-muted/50',
                                            active &&
                                                'border-primary bg-primary/5 ring-1 ring-primary',
                                        )}
                                    >
                                        <CardHeader>
                                            <CardTitle>{option.name}</CardTitle>
                                            <CardDescription>
                                                Create a {option.name.toLowerCase()}
                                            </CardDescription>
                                        </CardHeader>
                                    </Card>
                                </Link>
                            );
                        })}
                    </nav>
                )}

                <form
                    key={formKey}
                    onSubmit={handleSubmit}
                    className="mx-auto w-full max-w-3xl space-y-6"
                >
                    <div className="grid gap-6 md:grid-cols-3">
                        <div className="grid gap-2 md:col-span-2">
                            <Label htmlFor="title">Title *</Label>
                            <Input
                                id="title"
                                name="title"
                                defaultValue={sample?.title ?? request?.title}
                            />
                            <InputError message={errors.title} />
                        </div>

                        <div className="grid gap-2">
                            <Label htmlFor="priority">Priority *</Label>
                            <Select
                                name="priority"
                                defaultValue={sample?.priority ?? request?.priority ?? 'normal'}
                            >
                                <SelectTrigger id="priority" className="w-full capitalize">
                                    <SelectValue />
                                </SelectTrigger>
                                <SelectContent>
                                    {priorities.map((priority) => (
                                        <SelectItem
                                            key={priority}
                                            value={priority}
                                            className="capitalize"
                                        >
                                            {priority}
                                        </SelectItem>
                                    ))}
                                </SelectContent>
                            </Select>
                            <InputError message={errors.priority} />
                        </div>

                        <div className="grid gap-2 md:col-span-3">
                            <Label htmlFor="description">Description</Label>
                            <textarea
                                id="description"
                                name="description"
                                rows={3}
                                defaultValue={sample?.description ?? request?.description ?? undefined}
                                className="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                            />
                            <InputError message={errors.description} />
                        </div>
                    </div>

                    <div
                        key={type.key}
                        className="grid gap-6 border-t pt-6 md:grid-cols-2"
                    >
                        {fields.map((field) => (
                            <RequestFieldInput
                                key={field.key}
                                field={field}
                                defaultValue={
                                    typeSample?.metadata[field.key] ??
                                    (request ? storedValue(field, stored[field.key]) : undefined)
                                }
                                error={errors[`metadata.${field.key}`]}
                            />
                        ))}
                    </div>

                    <div className="flex items-center gap-3">
                        <Button
                            type="submit"
                            variant="outline"
                            disabled={processing}
                            onClick={() => setAction('draft')}
                        >
                            {editing ? 'Save changes' : 'Save draft'}
                        </Button>
                        {canSubmit && (
                            <Button
                                type="submit"
                                disabled={processing}
                                onClick={() => setAction('submit')}
                            >
                                Submit
                            </Button>
                        )}
                        {editing && (
                            <Button type="button" variant="ghost" asChild>
                                <Link href={show(request.uuid)}>Cancel</Link>
                            </Button>
                        )}
                        {import.meta.env.DEV && (
                            <Button
                                type="button"
                                variant="ghost"
                                className="ml-auto"
                                onClick={fillWithSampleData}
                            >
                                <Sparkles /> Fill with sample data
                            </Button>
                        )}
                    </div>
                </form>
            </div>
        </>
    );
}

RequestsForm.layout = {
    breadcrumbs: [
        { title: 'Requests', href: index() },
        { title: 'New request', href: create() },
    ],
};
