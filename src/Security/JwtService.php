<?php

declare(strict_types=1);

namespace Pterodactyl\Security;

use Pterodactyl\Core\Config;

/**
 * Pure PHP JWT Builder and Signer for Wings Daemon authentication.
 * Uses HMAC-SHA256 and adheres to RFC 7519 without external dependencies.
 */
class JwtService
{
    private array $claims = [];
    private array $scopes = [];
    private ?string $userUuid = null;
    private int $expiresAt;
    private ?string $subject = null;

    public function __construct()
    {
        $this->expiresAt = time() + (15 * 60); // default 15 mins
    }

    public function setClaims(array $claims): self
    {
        $this->claims = array_merge($this->claims, $claims);
        return $this;
    }

    public function setScopes(string ...$scopes): self
    {
        $this->scopes = $scopes;
        return $this;
    }

    public function setUserUuid(?string $uuid): self
    {
        $this->userUuid = $uuid;
        return $this;
    }

    public function setExpiresAt(int $timestamp): self
    {
        $this->expiresAt = $timestamp;
        return $this;
    }

    public function setSubject(string $subject): self
    {
        $this->subject = $subject;
        return $this;
    }

    public function generate(string $nodeSecret, string $nodeConnectionAddress, string $identifiedBy): string
    {
        $now = time();
        $identifier = hash('sha256', $identifiedBy);

        $header = [
            'typ' => 'JWT',
            'alg' => 'HS256',
            'jti' => $identifier,
        ];
        if (!empty($this->subject)) {
            $header['sub'] = $this->subject;
        }

        $appUrl = (string)Config::get('APP_URL', 'http://localhost');

        $payload = [
            'iss' => $appUrl,
            'aud' => $nodeConnectionAddress,
            'jti' => $identifier,
            'iat' => $now,
            'nbf' => $now - 300,
            'exp' => $this->expiresAt,
            'scope' => implode(' ', $this->scopes),
            'unique_id' => bin2hex(random_bytes(16)),
        ];

        if (!empty($this->subject)) {
            $payload['sub'] = $this->subject;
        }

        if ($this->userUuid !== null) {
            $payload['user_uuid'] = $this->userUuid;
        }

        foreach ($this->claims as $key => $value) {
            $payload[$key] = $value;
        }

        $encodedHeader = self::base64UrlEncode((string)json_encode($header, JSON_UNESCAPED_SLASHES));
        $encodedPayload = self::base64UrlEncode((string)json_encode($payload, JSON_UNESCAPED_SLASHES));

        $dataToSign = "{$encodedHeader}.{$encodedPayload}";
        $signature = hash_hmac('sha256', $dataToSign, $nodeSecret, true);
        $encodedSignature = self::base64UrlEncode($signature);

        return "{$dataToSign}.{$encodedSignature}";
    }

    public static function base64UrlEncode(string $data): string
    {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }

    public static function base64UrlDecode(string $data): string
    {
        return base64_decode(strtr($data, '-_', '+/') . str_repeat('=', 3 - (3 + strlen($data)) % 4));
    }
}
