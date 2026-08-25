<?php

declare(strict_types=1);

namespace SitePack\Signature;

use RuntimeException;
use SitePack\Validator\SchemaValidator;

final class SignatureService
{
    private const SPEC_VERSION = '1.0.0';
    private const ALGORITHM = 'Ed25519';

    public function __construct(private ?SchemaValidator $schemas = null)
    {
    }

    /** @return array<string, mixed> */
    public function sign(string $file, string $keyId, string $privateSeedBase64, ?string $createdAt = null): array
    {
        if (!is_file($file)) {
            throw new RuntimeException('Signed payload must be a regular file.');
        }
        if (!preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $keyId)) {
            throw new RuntimeException('Invalid keyId.');
        }
        $seed = $this->decodeBase64($privateSeedBase64, SODIUM_CRYPTO_SIGN_SEEDBYTES, 'Ed25519 private seed');
        $keypair = sodium_crypto_sign_seed_keypair($seed);
        $secretKey = sodium_crypto_sign_secretkey($keypair);
        $size = filesize($file);
        $hex = hash_file('sha256', $file);
        if ($size === false || $hex === false) {
            throw new RuntimeException('Signed payload cannot be read.');
        }
        $digest = 'sha256:' . $hex;
        return [
            'spec' => ['name' => 'sitepack-signature', 'version' => self::SPEC_VERSION],
            'createdAt' => $createdAt ?? gmdate('Y-m-d\TH:i:s\Z'),
            'algorithm' => self::ALGORITHM,
            'keyId' => $keyId,
            'payload' => ['digest' => $digest, 'size' => $size],
            'signature' => base64_encode(sodium_crypto_sign_detached($this->signingInput($digest, $size), $secretKey)),
        ];
    }

    /**
     * @param array<string, mixed> $document
     * @param array<string, mixed> $trustStore
     * @return array<string, mixed>
     */
    public function verify(string $file, array $document, array $trustStore): array
    {
        $errors = $this->validateDocument($document);
        if ($errors !== []) {
            return ['status' => 'invalid', 'code' => 'signature_document_invalid', 'errors' => $errors];
        }
        $keyId = (string)$document['keyId'];
        $trusted = $trustStore['keys'][$keyId] ?? null;
        if (!is_array($trusted) || ($trusted['algorithm'] ?? null) !== self::ALGORITHM || !is_string($trusted['publicKey'] ?? null)) {
            return ['status' => 'untrusted', 'code' => 'signature_key_untrusted', 'keyId' => $keyId];
        }
        if (!is_file($file)) {
            return ['status' => 'invalid', 'code' => 'signature_payload_unavailable'];
        }
        $size = filesize($file);
        if ($size === false || $size !== (int)$document['payload']['size']) {
            return ['status' => 'invalid', 'code' => 'signature_size_mismatch'];
        }
        $hex = hash_file('sha256', $file);
        if ($hex === false) {
            return ['status' => 'invalid', 'code' => 'signature_payload_unavailable'];
        }
        $digest = 'sha256:' . $hex;
        if (!hash_equals((string)$document['payload']['digest'], $digest)) {
            return ['status' => 'invalid', 'code' => 'signature_digest_mismatch'];
        }
        try {
            $publicKey = $this->decodeBase64((string)$trusted['publicKey'], SODIUM_CRYPTO_SIGN_PUBLICKEYBYTES, 'Ed25519 public key');
            $signature = $this->decodeBase64((string)$document['signature'], SODIUM_CRYPTO_SIGN_BYTES, 'Ed25519 signature');
            $valid = sodium_crypto_sign_verify_detached($signature, $this->signingInput($digest, $size), $publicKey);
        } catch (RuntimeException) {
            return ['status' => 'invalid', 'code' => 'signature_key_invalid', 'keyId' => $keyId];
        }
        return $valid
            ? ['status' => 'valid', 'code' => 'signature_valid', 'keyId' => $keyId, 'digest' => $digest]
            : ['status' => 'invalid', 'code' => 'signature_verification_failed', 'keyId' => $keyId];
    }

    /** @param array<string, mixed> $document @return list<string> */
    private function validateDocument(array $document): array
    {
        if ($this->schemas !== null) {
            $result = $this->schemas->validate('signature', json_decode(json_encode($document, JSON_THROW_ON_ERROR), false, 512, JSON_THROW_ON_ERROR));
            if (!$result['valid']) {
                return $result['errors'];
            }
        }
        try {
            $this->decodeBase64((string)($document['signature'] ?? ''), SODIUM_CRYPTO_SIGN_BYTES, 'Ed25519 signature');
        } catch (RuntimeException $exception) {
            return [$exception->getMessage()];
        }
        return [];
    }

    private function signingInput(string $digest, int $size): string
    {
        return "sitepack-signature-v1\n{$digest}\n{$size}";
    }

    private function decodeBase64(string $value, int $expectedLength, string $label): string
    {
        $normalized = trim($value);
        $decoded = base64_decode($normalized, true);
        if ($decoded === false || strlen($decoded) !== $expectedLength
            || rtrim(base64_encode($decoded), '=') !== rtrim($normalized, '=')) {
            throw new RuntimeException($label . ' is invalid.');
        }
        return $decoded;
    }
}
