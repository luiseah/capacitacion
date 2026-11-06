<?php

namespace Database\Seeders;

use App\Enums\RequestTypeKey;
use App\Models\RequestType;
use Illuminate\Database\Seeder;

class RequestTypeSeeder extends Seeder
{
    public function run(): void
    {
        $descriptions = [
            RequestTypeKey::BookReservation->value => 'Reserve a book from the library.',
            RequestTypeKey::Purchase->value => 'Request the purchase of goods or services.',
            RequestTypeKey::GradeChange->value => 'Request a correction of a final grade.',
            RequestTypeKey::WorkPermit->value => 'Request time off from work.',
        ];

        foreach (RequestTypeKey::cases() as $key) {
            RequestType::updateOrCreate(
                ['key' => $key->value],
                ['name' => $key->label(), 'description' => $descriptions[$key->value]],
            );
        }
    }
}
