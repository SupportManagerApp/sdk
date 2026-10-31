<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Testing\Factories;

use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Testing\Factory;
use SupportManager\Sdk\Testing\FakeRoute;
use SupportManager\Sdk\Testing\ProvidesFakeRoutes;
use SupportManager\Sdk\Testing\RecordStore;

/**
 * Fake payroll starter declaration — a flat singleton (`type()` is null).
 *
 * Reads answer `{data: {...}, meta: {can: {update}}}`. Saves take either a JSON
 * PUT or a multipart POST spoofed to PUT, merge the answers into the record
 * (multipart `1`/`0` read back as booleans and empty strings as null), and store
 * a fake signed `tax_form_url` when a `tax_form_file` part is uploaded. A
 * {@see readOnly()} declaration, as a former personal assistant sees it, refuses
 * saves with a 403.
 *
 * ```php
 * SupportManager::fake()->seed(StarterDeclarationFactory::new());
 * SupportManager::starterDeclaration()->show()->niNumber; // 'AB123456C'
 * ```
 */
final class StarterDeclarationFactory extends Factory implements ProvidesFakeRoutes
{
    private const array BOOLEANS = ['postgraduate_loan', 'week1_month1'];

    private const array NUMBERS = ['total_pay_to_date', 'total_tax_to_date'];

    public function path(): string
    {
        return 'payroll/starter-declaration';
    }

    public function definition(int $index): array
    {
        return [
            'ni_number' => 'AB123456C',
            'tax_form_type' => 'New Starter Checklist',
            'starter_declaration_circumstances' => 'A',
            'leaving_date' => null,
            'total_pay_to_date' => null,
            'total_tax_to_date' => null,
            'final_pay_period_type' => null,
            'final_pay_period_number' => null,
            'tax_form_url' => null,
            'national_insurance_letter' => 'A',
            'student_loan_plan' => 'none',
            'postgraduate_loan' => false,
            'tax_code' => '1257L',
            'week1_month1' => false,
            'updated_at' => '2026-07-01T09:30:00.000000Z',
            'can_update' => true,
        ];
    }

    /**
     * A declaration answered with a P45, including a stored form.
     */
    public function withP45(): static
    {
        return $this->state([
            'tax_form_type' => 'P45',
            'leaving_date' => '2026-06-30',
            'total_pay_to_date' => 8000.5,
            'total_tax_to_date' => 950.25,
            'final_pay_period_type' => 'monthly',
            'final_pay_period_number' => 3,
            'tax_form_url' => 'https://files.test/payroll-starter-declarations/p45.pdf?signature=fake',
        ]);
    }

    /**
     * A read-only declaration, as a former personal assistant sees it.
     */
    public function readOnly(): static
    {
        return $this->state(['can_update' => false]);
    }

    public function fakeRoutes(): array
    {
        $save = static function (RecordStore $store, array $matches, Request $request): mixed {
            $record = $store->all('payroll/starter-declaration')[0] ?? [];

            if (! ($record['can_update'] ?? false)) {
                return Http::response(['message' => 'This action is unauthorized.'], 403);
            }

            $record = [...$record, ...self::answers($request), 'updated_at' => '2026-10-31T09:00:00.000000Z'];
            $store->put('payroll/starter-declaration', [$record]);

            return self::declaration($record);
        };

        return [
            FakeRoute::get('payroll/starter-declaration', static function (RecordStore $store): mixed {
                $record = $store->all('payroll/starter-declaration')[0] ?? null;

                return $record === null
                    ? Http::response(['message' => 'This action is unauthorized.'], 403)
                    : self::declaration($record);
            }),

            FakeRoute::put('payroll/starter-declaration', $save),
            FakeRoute::post('payroll/starter-declaration', $save),
        ];
    }

    /**
     * The `{data, meta}` body for a stored record.
     *
     * @param  array<string, mixed>  $record
     */
    private static function declaration(array $record): mixed
    {
        $canUpdate = (bool) ($record['can_update'] ?? false);
        unset($record['can_update']);

        return Http::response(['data' => $record, 'meta' => ['can' => ['update' => $canUpdate], 'team_id' => 42]]);
    }

    /**
     * The answers a save request carries, from a JSON body or multipart parts.
     * An uploaded `tax_form_file` part becomes a fake signed `tax_form_url`.
     *
     * @return array<string, mixed>
     */
    private static function answers(Request $request): array
    {
        if (! $request->isMultipart()) {
            return $request->data();
        }

        $answers = [];

        foreach ($request->data() as $part) {
            if (! is_array($part) || ! is_string($part['name'] ?? null) || $part['name'] === '_method') {
                continue;
            }

            if ($part['name'] === 'tax_form_file') {
                $answers['tax_form_url'] = 'https://files.test/payroll-starter-declarations/'.rawurlencode((string) ($part['filename'] ?? 'p45.pdf')).'?signature=fake';

                continue;
            }

            $value = (string) $part['contents'];

            $answers[$part['name']] = match (true) {
                $value === '' => null,
                in_array($part['name'], self::BOOLEANS, true) => $value === '1',
                in_array($part['name'], self::NUMBERS, true) => (float) $value,
                $part['name'] === 'final_pay_period_number' => (int) $value,
                default => $value,
            };
        }

        return $answers;
    }
}
