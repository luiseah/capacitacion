import { Form, Head, Link } from '@inertiajs/react';
import { Sparkles } from 'lucide-react';
import { useState } from 'react';
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
import { generateRequestSample } from '@/lib/request-sample-data';
import type { RequestSample } from '@/lib/request-sample-data';
import { cn } from '@/lib/utils';
import { create, form, index, show, store, update } from '@/routes/requests';
import type { RequestEditable, RequestField, RequestTypeOption } from '@/types';

/**
 * Converts a stored metadata value to what the matching input expects as its
 * default (e.g. `2026-12-01 08:00:00` -> `2026-12-01T08:00`).
 */
function storedValue(
    field: RequestField,
    value: RequestEditable['metadata'][string] | undefined,
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

export default function RequestsForm({
    types,
    type,
    fields,
    priorities,
    request,
}: {
    types: RequestTypeOption[];
    type: RequestTypeOption;
    fields: RequestField[];
    priorities: string[];
    request?: RequestEditable;
}) {
    const editing = request !== undefined;
    const canSubmit = !editing || request.status === 'draft';
    const [action, setAction] = useState<'draft' | 'submit'>('draft');
    // Development helper: values are applied as defaults and the form is
    // remounted (formKey) so the uncontrolled inputs pick them up.
    const [sample, setSample] = useState<RequestSample | null>(null);
    const [formKey, setFormKey] = useState(0);
    const typeSample = sample?.typeKey === type.key ? sample : null;

    const fillWithSampleData = async () => {
        setSample(
            await generateRequestSample(type.key, type.name, fields, priorities),
        );
        setFormKey((key) => key + 1);
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
                                only={['type', 'fields']}
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

                <Form
                    key={formKey}
                    action={editing ? update(request.uuid) : store(type.key)}
                    className="mx-auto w-full max-w-3xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <input type="hidden" name="action" value={action} />

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
                                        defaultValue={
                                            sample?.priority ??
                                            request?.priority ??
                                            'normal'
                                        }
                                    >
                                        <SelectTrigger
                                            id="priority"
                                            className="w-full capitalize"
                                        >
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
                                        defaultValue={
                                            sample?.description ??
                                            request?.description ??
                                            undefined
                                        }
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
                                            (request
                                                ? storedValue(
                                                      field,
                                                      request.metadata[
                                                          field.key
                                                      ],
                                                  )
                                                : undefined)
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
                                    <Button variant="ghost" asChild>
                                        <Link href={show(request.uuid)}>
                                            Cancel
                                        </Link>
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
                        </>
                    )}
                </Form>
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
