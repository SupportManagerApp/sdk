<?php

declare(strict_types=1);

use Illuminate\Support\Facades\Http;
use SupportManager\Sdk\Data\Signing\SignatureRequest;
use SupportManager\Sdk\Data\Signing\SigningField;
use SupportManager\Sdk\Enums\Signing\FieldType;
use SupportManager\Sdk\Enums\Signing\SignatureRequestStatus;
use SupportManager\Sdk\Enums\Signing\SigningChannel;
use SupportManager\Sdk\Enums\Signing\SigningStatus;
use SupportManager\Sdk\Exceptions\ApiValidationException;

function signatureRequestObject(string $id, array $attributes = [], array $can = ['sign' => true, 'download_signed' => false]): array
{
    return [
        'type' => 'signature-requests',
        'id' => $id,
        'attributes' => [
            'document' => [
                'name' => 'Working pattern change',
                'status' => 'sent',
                'sent_at' => '2026-10-30T09:15:00.000000Z',
                'completed_at' => null,
            ],
            'name' => 'Jane Smith',
            'email' => 'jane@example.com',
            'role' => null,
            'sent_at' => '2026-10-30T09:15:00.000000Z',
            'viewed_at' => null,
            'signed_at' => null,
            'signed_via' => null,
            ...$attributes,
        ],
        'meta' => ['can' => $can],
    ];
}

function signingFieldEntry(string $id, string $type = 'signature', bool $mine = true): array
{
    return [
        'id' => $id,
        'type' => $type,
        'is_mine' => $mine,
        'is_required' => $type !== 'checkbox',
        'page' => 2,
        'x' => 0.12,
        'y' => 0.81,
        'width' => 0.3,
        'height' => 0.06,
        'value' => null,
    ];
}

it('lists the awaiting inbox from an unpaginated JSON:API collection', function () {
    fakeApi(['signing/requests*' => [
        'data' => [signatureRequestObject('12'), signatureRequestObject('13')],
    ]]);

    $page = sdk()->signatureRequests()->all();

    expect($page->items())->toHaveCount(2)
        ->and($page->items()[0])->toBeInstanceOf(SignatureRequest::class)
        ->and($page->items()[0]->id)->toBe('12')
        ->and($page->items()[0]->documentName)->toBe('Working pattern change')
        ->and($page->items()[0]->documentStatus)->toBe(SigningStatus::Sent)
        ->and($page->items()[0]->email)->toBe('jane@example.com')
        ->and($page->items()[0]->fields)->toBe([])
        ->and($page->items()[0]->consentStatement)->toBeNull()
        ->and($page->items()[0]->canSign)->toBeTrue()
        ->and($page->items()[0]->canDownloadSigned)->toBeFalse()
        ->and($page->items()[0]->isSigned())->toBeFalse();

    Http::assertSent(fn ($request) => $request->method() === 'GET'
        && ! str_contains($request->url(), 'status=')
        && $request->hasHeader('X-Team-ID', '42'));
});

it('lists signed requests with the status query', function () {
    fakeApi(['signing/requests*' => [
        'data' => [signatureRequestObject('14', [
            'signed_at' => '2026-10-31T10:00:00.000000Z',
            'signed_via' => 'app',
        ], ['sign' => false, 'download_signed' => true])],
    ]]);

    $request = sdk()->signatureRequests()->all(SignatureRequestStatus::Signed)->items()[0];

    expect($request->signedVia)->toBe(SigningChannel::App)
        ->and($request->isSigned())->toBeTrue()
        ->and($request->canDownloadSigned)->toBeTrue();

    Http::assertSent(fn ($request) => str_contains($request->url(), 'signing/requests?status=signed'));
});

it('finds a request with its fields and consent statement', function () {
    fakeApi(['signing/requests/12' => [
        'data' => signatureRequestObject('12', [
            'viewed_at' => '2026-10-31T08:02:11.000000Z',
            'fields' => [signingFieldEntry('sig-1'), signingFieldEntry('theirs', 'name', mine: false)],
            'consent_statement' => 'I agree to sign this document electronically.',
        ]),
    ]]);

    $request = sdk()->signatureRequests()->find(12);

    expect($request)->toBeInstanceOf(SignatureRequest::class)
        ->and($request->viewedAt)->toBe('2026-10-31T08:02:11.000000Z')
        ->and($request->fields)->toHaveCount(2)
        ->and($request->fields[0])->toBeInstanceOf(SigningField::class)
        ->and($request->fields[0]->id)->toBe('sig-1')
        ->and($request->fields[0]->type)->toBe(FieldType::Signature)
        ->and($request->fields[0]->isRequired)->toBeTrue()
        ->and($request->fields[0]->page)->toBe(2)
        ->and($request->fields[0]->x)->toBe(0.12)
        ->and($request->myFields())->toHaveCount(1)
        ->and($request->consentStatement)->toBe('I agree to sign this document electronically.');
});

it('returns null when finding a request in another team (404)', function () {
    fakeApi(['signing/requests/999' => Http::response(['message' => 'Not Found'], 404)]);

    expect(sdk()->signatureRequests()->find(999))->toBeNull();
});

it('returns the original PDF as a raw response', function () {
    fakeApi(['signing/requests/12/file' => Http::response('%PDF-1.4 bytes', 200, ['Content-Type' => 'application/pdf'])]);

    $response = sdk()->signatureRequests()->downloadOriginal(12);

    expect($response->body())->toBe('%PDF-1.4 bytes')
        ->and($response->mimeType())->toBe('application/pdf');
});

it('saves field values with a PUT keyed by field id', function () {
    fakeApi(['signing/requests/12/values' => Http::response('', 204)]);

    sdk()->signatureRequests()->saveValues(12, ['sig-1' => 'data:image/png;base64,AAA', 'date-1' => null]);

    Http::assertSent(fn ($request) => $request->method() === 'PUT'
        && json_decode($request->body(), true) === ['sig-1' => 'data:image/png;base64,AAA', 'date-1' => null]);
});

it('maps a rejected field value to a validation exception keyed by field id', function () {
    fakeApi(['signing/requests/12/values' => Http::response([
        'message' => 'The sig-1 field must start with one of the following: data:image/png;base64,.',
        'errors' => ['sig-1' => ['The sig-1 field must start with one of the following: data:image/png;base64,.']],
    ], 422)]);

    try {
        sdk()->signatureRequests()->saveValues(12, ['sig-1' => 'not-an-image']);
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('sig-1'))->toStartWith('The sig-1 field must start with');
    }
});

it('signs with the consent flag and returns the updated request', function () {
    fakeApi(['signing/requests/12/sign' => [
        'data' => signatureRequestObject('12', [
            'document' => ['name' => 'Working pattern change', 'status' => 'completed', 'sent_at' => null, 'completed_at' => '2026-10-31T10:00:00.000000Z'],
            'signed_at' => '2026-10-31T10:00:00.000000Z',
            'signed_via' => 'app',
            'fields' => [],
            'consent_statement' => 'I agree.',
        ], ['sign' => false, 'download_signed' => true]),
    ]]);

    $request = sdk()->signatureRequests()->sign(12, agreesToSignElectronically: true);

    expect($request?->documentStatus)->toBe(SigningStatus::Completed)
        ->and($request?->signedVia)->toBe(SigningChannel::App)
        ->and($request?->canSign)->toBeFalse();

    Http::assertSent(fn ($request) => $request->method() === 'POST'
        && $request['agrees_to_sign_electronically'] === true);
});

it('returns the raw signed-copy download payload', function () {
    fakeApi(['signing/requests/12/signed' => [
        'data' => [
            'download_url' => 'https://s3.test/signed/12.pdf',
            'filename' => 'Working pattern change (signed).pdf',
            'expires_at' => '2026-10-31T14:35:00.000000Z',
        ],
    ]]);

    $response = sdk()->signatureRequests()->downloadSigned(12);

    expect($response->url)->toBe('https://s3.test/signed/12.pdf')
        ->and($response->filename)->toBe('Working pattern change (signed).pdf');
});
