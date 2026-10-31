<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Resources\Payroll;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Http\Client\Response;
use SupportManager\Sdk\Data\Payroll\StarterDeclaration;
use SupportManager\Sdk\Http\ResourceObject;
use SupportManager\Sdk\Resources\Resource;

/**
 * The authenticated employee's own payroll starter declaration — a singleton.
 *
 * The platform exposes `show()` and `update()` returning a flat (non-JSON:API)
 * `data` payload, so this resource hand-rolls both. Employees (personal
 * assistants and managers) can read and save their own; former personal
 * assistants can only read it ({@see StarterDeclaration::$canUpdate} is false);
 * admins, family members and contractors get a 403. Saving also completes the
 * employee's `payroll.payroll_starter` task.
 *
 * ```php
 * SupportManager::starterDeclaration()->show();
 * SupportManager::starterDeclaration()->update([
 *     'tax_form_type' => 'P45',
 *     'ni_number' => 'AB123456C',
 *     'starter_declaration_circumstances' => 'A',
 *     'national_insurance_letter' => 'A',
 *     'tax_code' => '1257L',
 *     'leaving_date' => '2026-06-30',
 *     'total_pay_to_date' => 8000.50,
 *     'total_tax_to_date' => 950.25,
 * ], $upload->get(), $upload->getClientOriginalName());
 * ```
 *
 * @extends resource<StarterDeclaration>
 */
final class StarterDeclarationResource extends Resource
{
    /**
     * The current declaration. Answers not given yet are null.
     */
    public function show(): ?StarterDeclaration
    {
        return $this->declarationFromResponse($this->get($this->path()));
    }

    /**
     * Save the declaration, returning it as stored.
     *
     * `tax_form_type`, `ni_number`, `starter_declaration_circumstances` and
     * `national_insurance_letter` are required. The P45 fields (`tax_code`,
     * `week1_month1`, `leaving_date`, `total_pay_to_date`, `total_tax_to_date`,
     * `final_pay_period_type`, `final_pay_period_number`) are only validated and
     * saved when `tax_form_type` is `P45`, and a P45 needs the scanned form
     * unless one is already stored (leaving it out then keeps the stored file).
     *
     * Without a form this sends a JSON PUT. With one it sends multipart form
     * data as a POST spoofed to PUT with `_method`, because PHP only parses
     * multipart bodies on POST; booleans go as `1`/`0` and nulls as empty
     * strings, which the platform reads back as null. The form is taken as raw
     * bytes, as {@see \SupportManager\Sdk\Resources\Profile\ProfileResource::uploadAvatar()}
     * does, so the request can be replayed after a token refresh.
     *
     * @param  array<string, mixed>  $answers
     * @param  string|null  $taxForm  The P45's raw bytes, if uploading one.
     * @param  string  $taxFormFilename  The client filename, e.g. `p45.pdf`; its extension sets the part's Content-Type.
     */
    public function update(array $answers, ?string $taxForm = null, string $taxFormFilename = 'p45.pdf'): ?StarterDeclaration
    {
        if ($taxForm === null) {
            return $this->declarationFromResponse($this->put($this->path(), $answers));
        }

        $fields = ['_method' => 'PUT'];

        foreach ($answers as $key => $value) {
            $fields[$key] = match (true) {
                is_bool($value) => $value ? '1' : '0',
                $value === null => '',
                default => $value,
            };
        }

        return $this->declarationFromResponse($this->tenant(fn (PendingRequest $request): Response => $request
            ->attach('tax_form_file', $taxForm, $taxFormFilename)
            ->post($this->path(), $fields)));
    }

    protected function path(): string
    {
        return 'payroll/starter-declaration';
    }

    protected function hydrate(ResourceObject $object): StarterDeclaration
    {
        return StarterDeclaration::fromArray($object->flat());
    }

    private function declarationFromResponse(Response $response): ?StarterDeclaration
    {
        $this->throwForErrorResponse($response, $this->path());

        $data = $response->json('data');
        $meta = $response->json('meta');

        return is_array($data) ? StarterDeclaration::fromArray($data, is_array($meta) ? $meta : []) : null;
    }
}
