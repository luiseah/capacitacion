import { Form, Head, Link } from '@inertiajs/react';
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
import { cn } from '@/lib/utils';
import { create, form, index, store } from '@/routes/requests';
import type { RequestField, RequestTypeOption } from '@/types';

export default function RequestsForm({
    types,
    type,
    fields,
    priorities,
}: {
    types: RequestTypeOption[];
    type: RequestTypeOption;
    fields: RequestField[];
    priorities: string[];
}) {
    const [action, setAction] = useState<'draft' | 'submit'>('draft');

    return (
        <>
            <Head title={type.name} />

            <div className="flex flex-col gap-6 p-4">
                <Heading
                    title="New request"
                    description="Choose the type of request and fill in its details"
                />

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

                <Form
                    {...store.form(type.key)}
                    className="mx-auto w-full max-w-3xl space-y-6"
                >
                    {({ processing, errors }) => (
                        <>
                            <input type="hidden" name="action" value={action} />

                            <div className="grid gap-6 md:grid-cols-3">
                                <div className="grid gap-2 md:col-span-2">
                                    <Label htmlFor="title">Title *</Label>
                                    <Input id="title" name="title" />
                                    <InputError message={errors.title} />
                                </div>

                                <div className="grid gap-2">
                                    <Label htmlFor="priority">Priority *</Label>
                                    <Select name="priority" defaultValue="normal">
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
                                    Save draft
                                </Button>
                                <Button
                                    type="submit"
                                    disabled={processing}
                                    onClick={() => setAction('submit')}
                                >
                                    Submit
                                </Button>
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
