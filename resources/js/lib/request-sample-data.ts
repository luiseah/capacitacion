import type { RequestField } from '@/types';

export type SampleValue = string | boolean;

export type RequestSample = {
    typeKey: string;
    title: string;
    description: string;
    priority: string;
    metadata: Record<string, SampleValue>;
};

const pad = (value: number) => String(value).padStart(2, '0');

const toDate = (date: Date) =>
    `${date.getFullYear()}-${pad(date.getMonth() + 1)}-${pad(date.getDate())}`;

const toDateTime = (date: Date) =>
    `${toDate(date)}T${pad(date.getHours())}:${pad(date.getMinutes())}`;

const addDays = (date: Date, days: number) => {
    const copy = new Date(date);
    copy.setDate(copy.getDate() + days);

    return copy;
};

/**
 * Builds realistic sample data for a request form. Development helper only:
 * faker is imported lazily so it never ships in the production bundle.
 */
export async function generateRequestSample(
    typeKey: string,
    typeName: string,
    fields: RequestField[],
    priorities: string[],
): Promise<RequestSample> {
    const { faker } = await import('@faker-js/faker');

    const dates: Record<string, Date> = {};
    const numbers: Record<string, number> = {};
    const metadata: Record<string, SampleValue> = {};

    // Fields are generated in order so dependent fields (end after start,
    // return after pickup, requested grade different from current) stay valid.
    for (const field of fields) {
        switch (field.type) {
            case 'text':
                metadata[field.key] = sampleText(faker, field.key);
                break;
            case 'textarea':
                metadata[field.key] = faker.lorem.sentences(2);
                break;
            case 'email':
                metadata[field.key] = faker.internet.email();
                break;
            case 'number':
                numbers[field.key] = sampleNumber(faker, field.key, numbers);
                metadata[field.key] = String(numbers[field.key]);
                break;
            case 'date': {
                const date =
                    field.key === 'return_date' && dates.pickup_date
                        ? addDays(dates.pickup_date, faker.number.int({ min: 1, max: 14 }))
                        : addDays(new Date(), faker.number.int({ min: 1, max: 30 }));
                dates[field.key] = date;
                metadata[field.key] = toDate(date);
                break;
            }
            case 'datetime': {
                const date =
                    field.key === 'end_at' && dates.start_at
                        ? new Date(dates.start_at.getTime() + faker.number.int({ min: 1, max: 8 }) * 3_600_000)
                        : (() => {
                              const start = addDays(new Date(), faker.number.int({ min: 1, max: 30 }));
                              start.setHours(8, 0, 0, 0);

                              return start;
                          })();
                dates[field.key] = date;
                metadata[field.key] = toDateTime(date);
                break;
            }
            case 'select':
                metadata[field.key] = faker.helpers.arrayElement(field.options);
                break;
            case 'boolean':
                metadata[field.key] = faker.datatype.boolean();
                break;
        }
    }

    const title = faker.lorem.words(3);

    return {
        typeKey,
        title: `${typeName}: ${title}`,
        description: faker.lorem.sentence(),
        priority: faker.helpers.arrayElement(priorities),
        metadata,
    };
}

type Faker = Awaited<typeof import('@faker-js/faker')>['faker'];

function sampleText(faker: Faker, key: string): string {
    switch (key) {
        case 'book_title':
            return faker.book.title();
        case 'author':
            return faker.book.author();
        case 'isbn':
            return faker.commerce.isbn();
        case 'supplier':
            return faker.company.name();
        case 'student_name':
            return faker.person.fullName();
        case 'course':
            return faker.helpers.arrayElement(['Calculus', 'Physics', 'Algorithms', 'Databases', 'Statistics']);
        case 'academic_period':
            return `${new Date().getFullYear()}-${faker.helpers.arrayElement([1, 2])}`;
        default:
            return faker.lorem.words(3);
    }
}

function sampleNumber(faker: Faker, key: string, current: Record<string, number>): number {
    switch (key) {
        case 'copies':
        case 'quantity':
            return faker.number.int({ min: 1, max: 10 });
        case 'estimated_amount':
            return faker.number.int({ min: 100, max: 5000 });
        case 'current_grade':
            return faker.number.float({ min: 1.5, max: 3.5, fractionDigits: 1 });
        case 'requested_grade':
            return Math.min(5, Number(((current.current_grade ?? 3) + 1).toFixed(1)));
        default:
            return faker.number.int({ min: 1, max: 100 });
    }
}
