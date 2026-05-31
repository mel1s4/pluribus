<?php

namespace App\Support\WalletLedger;

use ParagonIE_Sodium_Compat;
use RuntimeException;

/**
 * Ed25519 signatures over raw 32-byte block commitments (binary SHA-256 output).
 */
final class LedgerSigner
{
    private string $secretKey;

    private string $publicKey;

    public function __construct(?string $secretKeyBase64 = null, ?string $publicKeyBase64 = null)
    {
        $secret = $secretKeyBase64 ?? (string) config('wallet_ledger.secret_key');
        if ($secret === '') {
            throw new RuntimeException('WALLET_LEDGER_SECRET_KEY is not configured.');
        }
        $decoded = base64_decode($secret, true);
        if ($decoded === false || strlen($decoded) !== ParagonIE_Sodium_Compat::CRYPTO_SIGN_SECRETKEYBYTES) {
            throw new RuntimeException('WALLET_LEDGER_SECRET_KEY must be base64 of a '.ParagonIE_Sodium_Compat::CRYPTO_SIGN_SECRETKEYBYTES.'-byte libsodium secret key.');
        }
        $this->secretKey = $decoded;

        $pubOverride = $publicKeyBase64 ?? (string) config('wallet_ledger.public_key');
        if ($pubOverride !== '') {
            $pubDecoded = base64_decode($pubOverride, true);
            if ($pubDecoded === false || strlen($pubDecoded) !== ParagonIE_Sodium_Compat::CRYPTO_SIGN_PUBLICKEYBYTES) {
                throw new RuntimeException('WALLET_LEDGER_PUBLIC_KEY must be base64 of a '.ParagonIE_Sodium_Compat::CRYPTO_SIGN_PUBLICKEYBYTES.'-byte public key.');
            }
            $this->publicKey = $pubDecoded;
        } else {
            $this->publicKey = ParagonIE_Sodium_Compat::crypto_sign_publickey_from_secretkey($this->secretKey);
        }
    }

    public function publicKeyBase64(): string
    {
        return base64_encode($this->publicKey);
    }

    public function publicKeyBinary(): string
    {
        return $this->publicKey;
    }

    /**
     * @param  string  $commitmentHex  64-char hex SHA-256 commitment
     */
    public function signCommitmentHex(string $commitmentHex): string
    {
        $binary = hex2bin($commitmentHex);
        if ($binary === false || strlen($binary) !== 32) {
            throw new RuntimeException('Invalid commitment hex.');
        }

        return ParagonIE_Sodium_Compat::crypto_sign_detached($binary, $this->secretKey);
    }

    /**
     * @param  string  $commitmentHex  64-char hex
     */
    public static function verifyDetached(string $commitmentHex, string $signatureBinary, string $publicKeyBinary): bool
    {
        $binary = hex2bin($commitmentHex);
        if ($binary === false || strlen($binary) !== 32) {
            return false;
        }

        return ParagonIE_Sodium_Compat::crypto_sign_verify_detached($signatureBinary, $binary, $publicKeyBinary);
    }
}
