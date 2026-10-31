<?php

declare(strict_types=1);

use SupportManager\Sdk\Data\Signing\SignatureRequest;
use SupportManager\Sdk\Enums\Signing\SignatureRequestStatus;
use SupportManager\Sdk\Enums\Signing\SigningChannel;
use SupportManager\Sdk\Enums\Signing\SigningStatus;
use SupportManager\Sdk\Exceptions\ApiValidationException;
use SupportManager\Sdk\Exceptions\SupportManagerApiException;
use SupportManager\Sdk\Facades\SupportManager;
use SupportManager\Sdk\Testing\Factories\SignatureRequestFactory;

// Host-style tests: seed factories, call the SDK normally, assert on DTOs.
// No Http::fake(), no JSON:API envelopes by hand.

it('lists the awaiting inbox by default and signed requests on request, without fields', function () {
    SupportManager::fake()->seed(
        SignatureRequestFactory::new()->count(2),
        SignatureRequestFactory::new()->signed(),
    );

    $inbox = SupportManager::signatureRequests()->all();
    $signed = SupportManager::signatureRequests()->all(SignatureRequestStatus::Signed);

    expect($inbox->items())->toHaveCount(2)
        ->and($inbox->items()[0])->toBeInstanceOf(SignatureRequest::class)
        ->and($inbox->items()[0]->documentName)->toBe('Document 1')
        ->and($inbox->items()[0]->fields)->toBe([])
        ->and($inbox->items()[0]->consentStatement)->toBeNull()
        ->and($inbox->items()[0]->canSign)->toBeTrue()
        ->and($signed->items())->toHaveCount(1)
        ->and($signed->items()[0]->documentStatus)->toBe(SigningStatus::Completed)
        ->and($signed->items()[0]->canDownloadSigned)->toBeTrue();
});

it('finds a request with its fields, marking it viewed, and returns null for a miss', function () {
    SupportManager::fake()->seed(SignatureRequestFactory::new());

    $request = SupportManager::signatureRequests()->find(1);

    expect($request?->viewedAt)->not->toBeNull()
        ->and($request?->fields)->toHaveCount(2)
        ->and($request?->myFields()[0]->id)->toBe('field-1-signature')
        ->and($request?->consentStatement)->toBe(SignatureRequestFactory::CONSENT_STATEMENT)
        ->and(SupportManager::signatureRequests()->find(999))->toBeNull();
});

it('serves the original PDF', function () {
    SupportManager::fake()->seed(SignatureRequestFactory::new());

    expect(SupportManager::signatureRequests()->file(1)->body())->toStartWith('%PDF');
});

it('saves values, signs, and then serves the signed copy', function () {
    SupportManager::fake()->seed(SignatureRequestFactory::new());

    SupportManager::signatureRequests()->saveValues(1, ['field-1-signature' => 'data:image/png;base64,AAA']);
    $signed = SupportManager::signatureRequests()->sign(1, agreesToSignElectronically: true);

    expect($signed?->isSigned())->toBeTrue()
        ->and($signed?->signedVia)->toBe(SigningChannel::App)
        ->and($signed?->documentStatus)->toBe(SigningStatus::Completed)
        ->and($signed?->canSign)->toBeFalse()
        ->and($signed?->fields[0]->value)->toBe('data:image/png;base64,AAA')
        ->and(SupportManager::signatureRequests()->all()->items())->toBe([])
        ->and(SupportManager::signatureRequests()->downloadSigned(1)->json('data.filename'))->toBe('Document 1 (signed).pdf');
});

it('refuses to sign while a required field is empty', function () {
    SupportManager::fake()->seed(SignatureRequestFactory::new());

    try {
        SupportManager::signatureRequests()->sign(1, agreesToSignElectronically: true);
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('field-1-signature'))->toBe('Please fill in this field.');
    }
});

it('refuses to sign without consent', function () {
    SupportManager::fake()->seed(SignatureRequestFactory::new());

    try {
        SupportManager::signatureRequests()->sign(1, agreesToSignElectronically: false);
        test()->fail('Expected ApiValidationException');
    } catch (ApiValidationException $e) {
        expect($e->first('consent'))->toBe('Agree to sign electronically to finish signing.');
    }
});

it('forbids saving values on a request already signed', function () {
    SupportManager::fake()->seed(SignatureRequestFactory::new()->signed());

    SupportManager::signatureRequests()->saveValues(1, ['field-1-signature' => null]);
})->throws(SupportManagerApiException::class);

it('returns a raw 403 for the signed copy before the document is complete', function () {
    SupportManager::fake()->seed(SignatureRequestFactory::new());

    expect(SupportManager::signatureRequests()->downloadSigned(1)->status())->toBe(403);
});
