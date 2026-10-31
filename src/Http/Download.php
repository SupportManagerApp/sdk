<?php

declare(strict_types=1);

namespace SupportManager\Sdk\Http;

use Illuminate\Http\Client\Response;
use RuntimeException;

/**
 * A file the platform sent as the response body itself, such as a payslip PDF
 * or the original document to sign.
 *
 * It wraps the successful {@see Response}: an error response has already
 * thrown by the time a resource hands one of these back. It reads the
 * filename from `Content-Disposition`, preferring the RFC 6266/5987
 * `filename*=UTF-8''…` form over the plain `filename="…"` one. The bytes are
 * in memory already, so {@see self::saveTo()} just writes them out.
 *
 * Endpoints that answer with a temporary link to the file instead return a
 * {@see DownloadLink}.
 *
 * ```php
 * $pdf = SupportManager::payslips()->download(9);
 * $pdf->filename();                       // "Ada Lovelace - Payslip - June 2026.pdf"
 * $pdf->saveTo(storage_path('payslips')); // …/Ada Lovelace - Payslip - June 2026.pdf
 * ```
 */
final readonly class Download
{
    public function __construct(private Response $response) {}

    /**
     * The file's raw bytes.
     */
    public function body(): string
    {
        return $this->response->body();
    }

    /**
     * The filename the platform suggested in `Content-Disposition`, or null
     * when it sent none. Any directory part is stripped, so the name is safe
     * to join onto a local path.
     */
    public function filename(): ?string
    {
        $header = $this->response->header('Content-Disposition');

        return $header === '' ? null : self::filenameFromDisposition($header);
    }

    /**
     * The media type from `Content-Type` without its parameters, e.g.
     * `application/pdf`, or null when the header is missing.
     */
    public function mimeType(): ?string
    {
        $type = mb_strtolower(mb_trim(explode(';', $this->response->header('Content-Type'), 2)[0]));

        return $type === '' ? null : $type;
    }

    /**
     * The file's size in bytes.
     */
    public function size(): int
    {
        // '8bit' counts bytes, not characters.
        return mb_strlen($this->body(), '8bit');
    }

    /**
     * Write the file to disk and return the path written.
     *
     * Given an existing directory, the file goes inside it under
     * {@see self::filename()}, or `download` when the platform suggested no
     * name. Any other path is used as the file's full path. The parent
     * directory must already exist.
     *
     * @throws RuntimeException When the file cannot be written.
     */
    public function saveTo(string $path): string
    {
        if (is_dir($path)) {
            $path = mb_rtrim($path, '/\\').DIRECTORY_SEPARATOR.($this->filename() ?? 'download');
        }

        if (@file_put_contents($path, $this->body(), LOCK_EX) === false) {
            throw new RuntimeException("Unable to save the download to [{$path}].");
        }

        return $path;
    }

    /**
     * Escape hatch: the underlying HTTP response, for any header or detail not
     * exposed above.
     */
    public function response(): Response
    {
        return $this->response;
    }

    /**
     * Pull the filename out of a `Content-Disposition` header value, preferring
     * `filename*` (RFC 5987: `charset'language'percent-encoded`) over the plain,
     * possibly quoted, `filename`.
     */
    private static function filenameFromDisposition(string $header): ?string
    {
        preg_match_all('/;\s*([^\s=;]+)\s*=\s*("(?:[^"\\\\]|\\\\.)*"|[^;]*)/', ';'.$header, $matches, PREG_SET_ORDER);

        $parameters = [];

        foreach ($matches as [, $name, $value]) {
            $parameters[mb_strtolower($name)] ??= mb_trim($value);
        }

        $filename = null;

        if (isset($parameters['filename*']) && preg_match("/^([^']*)'[^']*'(.*)$/", $parameters['filename*'], $parts) === 1) {
            $filename = self::decodeExtendedValue($parts[1], $parts[2]);
        }

        if ($filename === null && isset($parameters['filename'])) {
            $filename = $parameters['filename'];

            if (str_starts_with($filename, '"') && str_ends_with($filename, '"') && mb_strlen($filename) >= 2) {
                $filename = (string) preg_replace('/\\\\(.)/', '$1', mb_substr($filename, 1, -1));
            }
        }

        if ($filename === null) {
            return null;
        }

        $filename = mb_trim(basename(str_replace('\\', '/', $filename)));

        return $filename === '' || $filename === '.' || $filename === '..' ? null : $filename;
    }

    /**
     * Decode an RFC 5987 extended value, or null for a charset other than
     * UTF-8 or ISO-8859-1 (the two the RFC requires support for).
     */
    private static function decodeExtendedValue(string $charset, string $encoded): ?string
    {
        $decoded = rawurldecode($encoded);

        return match (mb_strtoupper($charset)) {
            'UTF-8' => mb_check_encoding($decoded, 'UTF-8') ? $decoded : null,
            'ISO-8859-1' => mb_convert_encoding($decoded, 'UTF-8', 'ISO-8859-1'),
            default => null,
        };
    }
}
