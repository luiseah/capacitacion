import InputError from '@/components/input-error';
import { Checkbox } from '@/components/ui/checkbox';
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
import type { RequestField } from '@/types';

const inputTypes = {
    text: 'text',
    number: 'number',
    email: 'email',
    date: 'date',
    datetime: 'datetime-local',
} as const;

export function RequestFieldInput({
    field,
    error,
    defaultValue,
}: {
    field: RequestField;
    error?: string;
    defaultValue?: string | boolean;
}) {
    const name = `metadata[${field.key}]`;
    const id = `metadata-${field.key}`;

    return (
        <div
            className={cn(
                'grid content-start gap-2',
                field.type === 'textarea' && 'md:col-span-full',
            )}
        >
            {field.type === 'boolean' ? (
                <div className="flex items-center gap-2">
                    <Checkbox
                        id={id}
                        name={name}
                        value="1"
                        defaultChecked={defaultValue === true}
                    />
                    <Label htmlFor={id}>{field.label}</Label>
                </div>
            ) : (
                <>
                    <Label htmlFor={id}>
                        {field.label}
                        {field.required && ' *'}
                    </Label>

                    {field.type === 'textarea' && (
                        <textarea
                            id={id}
                            name={name}
                            rows={4}
                            defaultValue={
                                typeof defaultValue === 'string' ? defaultValue : undefined
                            }
                            className="min-h-20 w-full rounded-md border border-input bg-transparent px-3 py-2 text-sm shadow-xs outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50"
                        />
                    )}

                    {field.type === 'select' && (
                        <Select
                            name={name}
                            defaultValue={
                                typeof defaultValue === 'string' ? defaultValue : undefined
                            }
                        >
                            <SelectTrigger id={id} className="w-full">
                                <SelectValue placeholder="Select an option" />
                            </SelectTrigger>
                            <SelectContent>
                                {field.options.map((option) => (
                                    <SelectItem key={option} value={option}>
                                        {option}
                                    </SelectItem>
                                ))}
                            </SelectContent>
                        </Select>
                    )}

                    {field.type in inputTypes && (
                        <Input
                            id={id}
                            name={name}
                            defaultValue={
                                typeof defaultValue === 'string' ? defaultValue : undefined
                            }
                            type={
                                inputTypes[field.type as keyof typeof inputTypes]
                            }
                            step={field.type === 'number' ? 'any' : undefined}
                        />
                    )}
                </>
            )}

            <InputError message={error} />
        </div>
    );
}
