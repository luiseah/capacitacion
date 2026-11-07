<?php

namespace Module\Agreements\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;
use Module\Agreements\Enums\AgreementActivity;
use Module\Agreements\Enums\AgreementParties;
use Module\Agreements\Enums\AgreementRenewal;
use Module\Agreements\Enums\AgreementStatus;
use Module\Agreements\Enums\AgreementType;
use Module\Agreements\Models\Agreement;

/**
 * Creates or updates an agreement (the same rules for both).
 */
class AgreementRequest extends FormRequest
{
    public function authorize(): bool
    {
        $agreement = $this->route('agreement');

        return Gate::forUser($this->user())->allows(
            $agreement === null ? 'create' : 'update',
            $agreement ?? config('agreements.models.agreement'),
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'institution_id' => ['required', 'string', 'max:26'],
            'type' => ['required', Rule::enum(AgreementType::class)],
            // Bilateral unless it says it is multilateral.
            'parties' => ['nullable', Rule::enum(AgreementParties::class)],
            // The other institutions of a multilateral agreement; none when it is bilateral.
            'institution_ids' => ['nullable', 'array', 'max:50'],
            'institution_ids.*' => ['string', 'max:26', 'distinct'],
            'framework_id' => ['nullable', 'string', 'max:26'],
            // A draft unless it says otherwise (an update keeps what it had).
            'status' => ['nullable', Rule::enum(AgreementStatus::class)],
            'name' => ['required', 'string', 'max:200'],
            'purpose' => ['nullable', 'string', 'max:5000'],
            'signed_on' => ['nullable', 'date'],
            // Manual unless it says otherwise.
            'renewal' => ['nullable', Rule::enum(AgreementRenewal::class)],
            'reference' => ['nullable', 'string', 'max:60'],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['nullable', 'date', 'after_or_equal:starts_on'],
            'activities' => ['required', 'array', 'min:1'],
            'activities.*' => ['distinct', Rule::enum(AgreementActivity::class)],
            'other_activity' => ['nullable', 'string', 'max:200', Rule::requiredIf(fn () => in_array('other', (array) $this->input('activities'), true))],
            'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    /**
     * What the model also guarantees, said as validation errors: the institution is a partner one,
     * and a framework agreement is one of that institution.
     *
     * @return array<int, \Closure>
     */
    public function after(): array
    {
        return [function (Validator $validator) {
            $institutionId = $this->input('institution_id');

            if (is_string($institutionId) && $institutionId !== '' && ! $validator->errors()->has('institution_id')) {
                $institution = config('academic-mobility.models.institution')::query()->find($institutionId);

                if ($institution === null) {
                    $validator->errors()->add('institution_id', __('The institution was not found.'));
                } elseif ($institution->is_local) {
                    $validator->errors()->add('institution_id', __('An agreement is with a partner institution, not with the local one.'));
                }
            }

            $this->validateParties($validator, $institutionId);

            $frameworkId = $this->input('framework_id');

            if (! is_string($frameworkId) || $frameworkId === '' || $validator->errors()->hasAny(['institution_id', 'framework_id', 'type'])) {
                return;
            }

            if ($this->input('type') === AgreementType::Framework->value) {
                $validator->errors()->add('framework_id', __('A framework agreement does not depend on another one.'));
            } elseif (! config('agreements.models.agreement', Agreement::class)::query()->frameworks()
                ->where('institution_id', $institutionId)->whereKey($frameworkId)->exists()) {
                $validator->errors()->add('framework_id', __('A specific agreement can only depend on a framework agreement of the same institution.'));
            }
        }];
    }

    private function validateParties(Validator $validator, mixed $institutionId): void
    {
        if ($validator->errors()->hasAny(['parties', 'institution_ids', 'institution_ids.*', 'institution_id'])) {
            return;
        }

        $ids = array_values(array_filter((array) $this->input('institution_ids')));

        if ($this->input('parties', AgreementParties::Bilateral->value) !== AgreementParties::Multilateral->value) {
            if ($ids !== []) {
                $validator->errors()->add('institution_ids', __('A bilateral agreement is with one institution only.'));
            }

            return;
        }

        if ($ids === []) {
            $validator->errors()->add('institution_ids', __('A multilateral agreement needs at least one more institution.'));

            return;
        }

        $partners = config('academic-mobility.models.institution')::query()
            ->whereKey($ids)->where('is_local', false)->where('id', '!=', $institutionId)->count();

        if ($partners !== count($ids)) {
            $validator->errors()->add('institution_ids', __('The other institutions of an agreement must be partner institutions, different from its main one.'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function attributesToStore(): array
    {
        $data = [...['purpose' => null, 'signed_on' => null, 'renewal' => AgreementRenewal::Manual->value, 'parties' => AgreementParties::Bilateral->value, 'framework_id' => null, 'reference' => null, 'ends_on' => null, 'other_activity' => null, 'notes' => null], ...$this->validated()];
        $data['parties'] ??= AgreementParties::Bilateral->value;
        $data['renewal'] ??= AgreementRenewal::Manual->value;
        $data['status'] ??= $this->route('agreement')?->status->value ?? AgreementStatus::Draft->value;
        unset($data['institution_ids']);

        // "Other" is described only when it is one of the activities.
        if (! in_array('other', $data['activities'], true)) {
            $data['other_activity'] = null;
        }

        return $data;
    }

    /**
     * The other institutions of the agreement, none when it is bilateral.
     *
     * @return array<int, string>
     */
    public function otherInstitutions(): array
    {
        return $this->input('parties') === AgreementParties::Multilateral->value
            ? array_values(array_filter((array) $this->input('institution_ids')))
            : [];
    }
}
