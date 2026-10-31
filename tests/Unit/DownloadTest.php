<?php

declare(strict_types=1);

use GuzzleHttp\Psr7\Response as Psr7Response;
use Illuminate\Http\Client\Response;
use SupportManager\Sdk\Http\Download;
use SupportManager\Sdk\Http\DownloadLink;

/**
 * @param  array<string, string>  $headers
 */
function fileDownload(string $body = '%PDF-1.4 bytes', array $headers = []): Download
{
    return new Download(new Response(new Psr7Response(200, $headers, $body)));
}

function downloadScratchDirectory(): string
{
    $directory = sys_get_temp_dir().DIRECTORY_SEPARATOR.'sm-sdk-download-'.bin2hex(random_bytes(6));
    mkdir($directory);

    return $directory;
}

// ── Download ────────────────────────────────────────────────────────────────

it('exposes the body, size, media type and underlying response', function () {
    $download = fileDownload('%PDF-1.4 bytes', ['Content-Type' => 'application/PDF; charset=binary']);

    expect($download->body())->toBe('%PDF-1.4 bytes')
        ->and($download->size())->toBe(14)
        ->and($download->mimeType())->toBe('application/pdf')
        ->and($download->response())->toBeInstanceOf(Response::class)
        ->and($download->response()->status())->toBe(200);
});

it('returns null for a missing Content-Type or Content-Disposition', function () {
    $download = fileDownload();

    expect($download->mimeType())->toBeNull()
        ->and($download->filename())->toBeNull();
});

it('parses the filename from Content-Disposition', function (string $header, ?string $expected) {
    expect(fileDownload(headers: ['Content-Disposition' => $header])->filename())->toBe($expected);
})->with([
    'quoted' => ['attachment; filename="Ada - Payslip - June 2026.pdf"', 'Ada - Payslip - June 2026.pdf'],
    'unquoted token' => ['attachment; filename=timesheet.pdf', 'timesheet.pdf'],
    'inline' => ['inline; filename="Contract.pdf"', 'Contract.pdf'],
    'escaped quotes' => ['attachment; filename="the \"final\" copy.pdf"', 'the "final" copy.pdf'],
    'semicolon inside quotes' => ['attachment; filename="a; b.pdf"; size=10', 'a; b.pdf'],
    'case-insensitive parameter name' => ['attachment; FileName="Upper.pdf"', 'Upper.pdf'],
    'RFC 5987 UTF-8' => ["attachment; filename*=UTF-8''%E2%82%AC%20rates.pdf", '€ rates.pdf'],
    'RFC 5987 lower-case charset with language' => ["attachment; filename*=utf-8'en'na%C3%AFve.pdf", 'naïve.pdf'],
    'RFC 5987 ISO-8859-1' => ["attachment; filename*=ISO-8859-1''caf%E9.pdf", 'café.pdf'],
    'filename* wins over filename (Symfony fallback order)' => ["attachment; filename=Prix.pdf; filename*=utf-8''Pr%C3%AEx.pdf", 'Prîx.pdf'],
    'filename* wins when listed first' => ["attachment; filename*=UTF-8''%C3%A9t%C3%A9.pdf; filename=\"ete.pdf\"", 'été.pdf'],
    'unsupported charset falls back to filename' => ["attachment; filename*=KOI8-R''%C1.pdf; filename=\"fallback.pdf\"", 'fallback.pdf'],
    'invalid UTF-8 falls back to filename' => ["attachment; filename*=UTF-8''%FF.pdf; filename=\"fallback.pdf\"", 'fallback.pdf'],
    'directory parts are stripped' => ['attachment; filename="../../etc/passwd"', 'passwd'],
    'Windows directory parts are stripped' => ['attachment; filename="C:\\\\temp\\\\evil.pdf"', 'evil.pdf'],
    'no filename parameter' => ['attachment', null],
    'empty filename' => ['attachment; filename=""', null],
    'dot-dot filename' => ['attachment; filename=".."', null],
]);

it('saves into a directory under the suggested filename', function () {
    $directory = downloadScratchDirectory();

    $path = fileDownload('%PDF-1.4 p60', ['Content-Disposition' => 'attachment; filename="P60 2025-26.pdf"'])->saveTo($directory);

    expect($path)->toBe($directory.DIRECTORY_SEPARATOR.'P60 2025-26.pdf')
        ->and(file_get_contents($path))->toBe('%PDF-1.4 p60');

    unlink($path);
    rmdir($directory);
});

it('saves into a directory as "download" when no filename was suggested', function () {
    $directory = downloadScratchDirectory();

    $path = fileDownload('bytes')->saveTo($directory.DIRECTORY_SEPARATOR);

    expect($path)->toBe($directory.DIRECTORY_SEPARATOR.'download')
        ->and(file_get_contents($path))->toBe('bytes');

    unlink($path);
    rmdir($directory);
});

it('saves to an explicit file path, ignoring the suggested filename', function () {
    $directory = downloadScratchDirectory();
    $target = $directory.DIRECTORY_SEPARATOR.'mine.pdf';

    $path = fileDownload('%PDF', ['Content-Disposition' => 'attachment; filename="theirs.pdf"'])->saveTo($target);

    expect($path)->toBe($target)
        ->and(file_get_contents($target))->toBe('%PDF')
        ->and(file_exists($directory.DIRECTORY_SEPARATOR.'theirs.pdf'))->toBeFalse();

    unlink($target);
    rmdir($directory);
});

it('throws when the file cannot be written', function () {
    fileDownload()->saveTo(sys_get_temp_dir().DIRECTORY_SEPARATOR.'sm-sdk-missing-'.bin2hex(random_bytes(6)).DIRECTORY_SEPARATOR.'file.pdf');
})->throws(RuntimeException::class, 'Unable to save the download');

// ── DownloadLink ────────────────────────────────────────────────────────────

it('hydrates a download link from the platform payload', function () {
    $link = DownloadLink::fromArray([
        'download_url' => 'https://s3.test/signed/7.pdf',
        'filename' => 'contract.pdf',
        'expires_at' => '2026-10-31T14:35:00.000000Z',
    ]);

    expect($link->url)->toBe('https://s3.test/signed/7.pdf')
        ->and($link->filename)->toBe('contract.pdf')
        ->and($link->expiresAt)->toBe('2026-10-31T14:35:00.000000Z');
});

it('leaves a download link\'s optional fields null when absent', function () {
    $link = DownloadLink::fromArray(['download_url' => 'https://s3.test/x']);

    expect($link->filename)->toBeNull()
        ->and($link->expiresAt)->toBeNull();
});
