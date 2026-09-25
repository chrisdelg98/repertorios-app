<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;

/**
 * Signs requests to Cloudflare R2 with AWS Signature Version 4.
 *
 * R2 speaks the S3 API, and the official way to reach it is the AWS SDK. That
 * SDK is 67 MB and 3,500 files for the three things this app needs — a signed
 * upload URL, a signed playback URL, and a delete — on a project deployed by
 * FTP, where thousands of small files is exactly what gets truncated.
 *
 * SigV4 itself is a published format, not invented cryptography: an HMAC-SHA256
 * chain over a canonical description of the request. A wrong signature is
 * rejected outright by R2, so mistakes here are loud rather than silent.
 *
 * Nothing in this class touches the bucket's contents from the server: the
 * browser uploads and downloads directly, so audio never passes through PHP.
 */
class R2Signer
{
    private const ALGORITHM = 'AWS4-HMAC-SHA256';
    private const SERVICE = 's3';
    private const REGION = 'auto';          // R2 has no regions; 'auto' is required
    private const UNSIGNED_PAYLOAD = 'UNSIGNED-PAYLOAD';

    public function __construct(
        private readonly ?string $accessKey = null,
        private readonly ?string $secretKey = null,
        private readonly ?string $bucket = null,
        private readonly ?string $endpoint = null,
    ) {
    }

    public static function fromConfig(): self
    {
        return new self(
            config('services.r2.key'),
            config('services.r2.secret'),
            config('services.r2.bucket'),
            rtrim((string) config('services.r2.endpoint'), '/'),
        );
    }

    public function isConfigured(): bool
    {
        return filled($this->accessKey)
            && filled($this->secretKey)
            && filled($this->bucket)
            && filled($this->endpoint);
    }

    /**
     * A URL the browser can PUT the file to, valid for a short while.
     *
     * The content type is signed in, so the stored object keeps it and plays
     * back as audio instead of downloading as a blob.
     */
    public function presignPut(string $key, string $contentType, int $minutes = 20): string
    {
        return $this->presign('PUT', $key, $minutes, [
            'Content-Type' => $contentType,
        ]);
    }

    /** A URL the browser can play from, valid for a short while. */
    public function presignGet(string $key, int $minutes = 60): string
    {
        return $this->presign('GET', $key, $minutes);
    }

    /** Removes an object. Runs from the server, so it is a signed request. */
    public function delete(string $key): bool
    {
        if (!$this->isConfigured()) {
            return false;
        }

        $response = Http::withHeaders(
            $this->authorizationHeaders('DELETE', $key)
        )->delete($this->objectUrl($key));

        // R2 answers 204 on success and on an object that was already gone.
        return $response->successful() || $response->status() === 404;
    }

    // ── Signing ──────────────────────────────────────────────────────────

    /**
     * Builds a presigned URL: the signature travels in the query string, so
     * the browser needs no headers and no credentials of its own.
     */
    private function presign(string $method, string $key, int $minutes, array $signedHeaders = []): string
    {
        $now = gmdate('Ymd\THis\Z');
        $date = substr($now, 0, 8);
        $scope = "{$date}/" . self::REGION . '/' . self::SERVICE . '/aws4_request';

        $headers = ['host' => $this->host()] + array_change_key_case($signedHeaders);
        ksort($headers);

        $signedHeaderNames = implode(';', array_keys($headers));

        $query = [
            'X-Amz-Algorithm'     => self::ALGORITHM,
            'X-Amz-Credential'    => "{$this->accessKey}/{$scope}",
            'X-Amz-Date'          => $now,
            'X-Amz-Expires'       => (string) ($minutes * 60),
            'X-Amz-SignedHeaders' => $signedHeaderNames,
        ];
        ksort($query);

        $canonicalRequest = implode("\n", [
            $method,
            $this->canonicalUri($key),
            $this->canonicalQuery($query),
            $this->canonicalHeaders($headers),
            $signedHeaderNames,
            self::UNSIGNED_PAYLOAD,
        ]);

        $signature = $this->sign($canonicalRequest, $now, $date, $scope);

        return $this->objectUrl($key) . '?' . $this->canonicalQuery($query) . '&X-Amz-Signature=' . $signature;
    }

    /** Header-based signing, for requests this server makes itself. */
    private function authorizationHeaders(string $method, string $key): array
    {
        $now = gmdate('Ymd\THis\Z');
        $date = substr($now, 0, 8);
        $scope = "{$date}/" . self::REGION . '/' . self::SERVICE . '/aws4_request';

        $headers = [
            'host'                 => $this->host(),
            'x-amz-content-sha256' => self::UNSIGNED_PAYLOAD,
            'x-amz-date'           => $now,
        ];
        ksort($headers);

        $signedHeaderNames = implode(';', array_keys($headers));

        $canonicalRequest = implode("\n", [
            $method,
            $this->canonicalUri($key),
            '',
            $this->canonicalHeaders($headers),
            $signedHeaderNames,
            self::UNSIGNED_PAYLOAD,
        ]);

        $signature = $this->sign($canonicalRequest, $now, $date, $scope);

        return [
            'x-amz-content-sha256' => self::UNSIGNED_PAYLOAD,
            'x-amz-date'           => $now,
            'Authorization'        => self::ALGORITHM
                . " Credential={$this->accessKey}/{$scope}"
                . ", SignedHeaders={$signedHeaderNames}"
                . ", Signature={$signature}",
        ];
    }

    /** The HMAC chain: date, region, service, request — then the payload. */
    private function sign(string $canonicalRequest, string $now, string $date, string $scope): string
    {
        $toSign = implode("\n", [
            self::ALGORITHM,
            $now,
            $scope,
            hash('sha256', $canonicalRequest),
        ]);

        $key = hash_hmac('sha256', $date, 'AWS4' . $this->secretKey, true);
        $key = hash_hmac('sha256', self::REGION, $key, true);
        $key = hash_hmac('sha256', self::SERVICE, $key, true);
        $key = hash_hmac('sha256', 'aws4_request', $key, true);

        return hash_hmac('sha256', $toSign, $key);
    }

    // ── Canonical forms ──────────────────────────────────────────────────

    private function canonicalUri(string $key): string
    {
        // Each path segment is encoded, but the slashes between them are not.
        $segments = array_map(rawurlencode(...), explode('/', ltrim($key, '/')));

        return '/' . $this->bucket . '/' . implode('/', $segments);
    }

    private function canonicalQuery(array $query): string
    {
        $pairs = [];

        foreach ($query as $name => $value) {
            $pairs[] = rawurlencode((string) $name) . '=' . rawurlencode((string) $value);
        }

        return implode('&', $pairs);
    }

    private function canonicalHeaders(array $headers): string
    {
        $lines = '';

        foreach ($headers as $name => $value) {
            $lines .= strtolower($name) . ':' . trim((string) $value) . "\n";
        }

        return $lines;
    }

    private function host(): string
    {
        return parse_url($this->endpoint, PHP_URL_HOST);
    }

    private function objectUrl(string $key): string
    {
        return $this->endpoint . $this->canonicalUri($key);
    }
}
