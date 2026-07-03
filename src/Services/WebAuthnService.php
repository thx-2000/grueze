<?php

declare(strict_types=1);

namespace App\Services;

use RuntimeException;

final class WebAuthnService
{
    public function beginRegistration(array $user, array $existingCredentials): array
    {
        $challenge = random_bytes(32);
        $_SESSION['webauthn_register'] = [
            'challenge' => self::base64urlEncode($challenge),
            'user_id' => (int) $user['id'],
        ];

        return [
            'challenge' => self::base64urlEncode($challenge),
            'rp' => [
                'name' => (string) config('app.name', 'Adress-Zentrale'),
                'id' => $this->rpId(),
            ],
            'user' => [
                'id' => self::base64urlEncode((string) $user['id']),
                'name' => (string) $user['email'],
                'displayName' => (string) $user['name'],
            ],
            'pubKeyCredParams' => [
                ['type' => 'public-key', 'alg' => -7],
            ],
            'timeout' => 60000,
            'attestation' => 'none',
            'excludeCredentials' => array_map(
                static fn (array $credential): array => [
                    'type' => 'public-key',
                    'id' => self::base64urlEncode((string) $credential['credential_id']),
                    'transports' => json_decode((string) ($credential['transports'] ?? '[]'), true) ?: [],
                ],
                $existingCredentials
            ),
            'authenticatorSelection' => [
                'residentKey' => 'required',
                'userVerification' => 'required',
            ],
        ];
    }

    public function finishRegistration(array $payload): array
    {
        $session = $_SESSION['webauthn_register'] ?? null;
        unset($_SESSION['webauthn_register']);

        if (!is_array($session) || empty($session['challenge']) || empty($session['user_id'])) {
            throw new RuntimeException('Die Passkey-Registrierung muss neu gestartet werden.');
        }

        $id = self::base64urlDecode((string) ($payload['id'] ?? ''));
        $clientDataJson = self::base64urlDecode((string) ($payload['response']['clientDataJSON'] ?? ''));
        $attestationObject = self::base64urlDecode((string) ($payload['response']['attestationObject'] ?? ''));
        $transports = (array) ($payload['response']['transports'] ?? []);

        if ($id === '' || $clientDataJson === '' || $attestationObject === '') {
            throw new RuntimeException('Die Antwort des Passkeys ist unvollständig.');
        }

        $this->assertClientData($clientDataJson, 'webauthn.create', (string) $session['challenge']);
        $attestation = self::decodeCbor($attestationObject);
        if (!is_array($attestation) || !isset($attestation['authData'])) {
            throw new RuntimeException('Die Attestierungsdaten konnten nicht gelesen werden.');
        }

        $authData = $this->parseAuthData((string) $attestation['authData']);
        $this->assertRpIdHash($authData['rpIdHash']);
        $this->assertFlags($authData['flags'], true, true, true);

        if (($authData['credentialId'] ?? '') !== $id) {
            throw new RuntimeException('Die zurückgelieferte Passkey-ID stimmt nicht mit den Authenticator-Daten überein.');
        }

        $publicKeyPem = $this->coseKeyToPem($authData['credentialPublicKey'] ?? null);

        return [
            'user_id' => (int) $session['user_id'],
            'credential_id' => $id,
            'public_key_pem' => $publicKeyPem,
            'algorithm' => -7,
            'sign_count' => (int) $authData['signCount'],
            'aaguid' => self::formatUuid((string) ($authData['aaguid'] ?? '')),
            'transports' => json_encode(array_values(array_unique(array_filter(array_map('strval', $transports)))), JSON_THROW_ON_ERROR),
        ];
    }

    public function beginAuthentication(): array
    {
        $challenge = random_bytes(32);
        $_SESSION['webauthn_auth'] = [
            'challenge' => self::base64urlEncode($challenge),
        ];

        return [
            'challenge' => self::base64urlEncode($challenge),
            'rpId' => $this->rpId(),
            'timeout' => 60000,
            'userVerification' => 'required',
        ];
    }

    public function finishAuthentication(array $storedCredential, array $payload): array
    {
        $session = $_SESSION['webauthn_auth'] ?? null;
        unset($_SESSION['webauthn_auth']);

        if (!is_array($session) || empty($session['challenge'])) {
            throw new RuntimeException('Die Passkey-Anmeldung muss neu gestartet werden.');
        }

        $credentialId = self::base64urlDecode((string) ($payload['id'] ?? ''));
        if ($credentialId === '' || $credentialId !== (string) ($storedCredential['credential_id'] ?? '')) {
            throw new RuntimeException('Der ausgewählte Passkey ist unbekannt.');
        }

        $clientDataJson = self::base64urlDecode((string) ($payload['response']['clientDataJSON'] ?? ''));
        $authenticatorData = self::base64urlDecode((string) ($payload['response']['authenticatorData'] ?? ''));
        $signature = self::base64urlDecode((string) ($payload['response']['signature'] ?? ''));

        if ($clientDataJson === '' || $authenticatorData === '' || $signature === '') {
            throw new RuntimeException('Die Passkey-Antwort ist unvollständig.');
        }

        $this->assertClientData($clientDataJson, 'webauthn.get', (string) $session['challenge']);
        $authData = $this->parseAuthData($authenticatorData);
        $this->assertRpIdHash($authData['rpIdHash']);
        $this->assertFlags($authData['flags'], true, true, false);

        $signedData = $authenticatorData . hash('sha256', $clientDataJson, true);
        $verified = openssl_verify($signedData, $signature, (string) $storedCredential['public_key_pem'], OPENSSL_ALGO_SHA256);
        if ($verified !== 1) {
            throw new RuntimeException('Die Signatur des Passkeys konnte nicht bestätigt werden.');
        }

        $previousCount = (int) ($storedCredential['sign_count'] ?? 0);
        $currentCount = (int) $authData['signCount'];
        if ($previousCount > 0 && $currentCount > 0 && $currentCount <= $previousCount) {
            throw new RuntimeException('Der Signaturzähler des Passkeys ist nicht mehr gültig.');
        }

        return [
            'passkey_id' => (int) $storedCredential['id'],
            'user_id' => (int) $storedCredential['user_id'],
            'sign_count' => $currentCount,
        ];
    }

    private function assertClientData(string $clientDataJson, string $expectedType, string $expectedChallenge): void
    {
        $clientData = json_decode($clientDataJson, true);
        if (!is_array($clientData)) {
            throw new RuntimeException('Die Client-Daten des Passkeys sind ungültig.');
        }

        if (($clientData['type'] ?? '') !== $expectedType) {
            throw new RuntimeException('Die Antwort stammt vom falschen WebAuthn-Vorgang.');
        }

        if (($clientData['challenge'] ?? '') !== $expectedChallenge) {
            throw new RuntimeException('Die Passkey-Challenge stimmt nicht.');
        }

        if (($clientData['origin'] ?? '') !== $this->origin()) {
            throw new RuntimeException('Die Passkey-Antwort stammt von einer unerwarteten Herkunft.');
        }

        if (!empty($clientData['crossOrigin'])) {
            throw new RuntimeException('Cross-Origin-Passkeys werden hier nicht akzeptiert.');
        }
    }

    private function assertRpIdHash(string $rpIdHash): void
    {
        if (!hash_equals(hash('sha256', $this->rpId(), true), $rpIdHash)) {
            throw new RuntimeException('Die Passkey-Domain stimmt nicht mit der erwarteten RP-ID überein.');
        }
    }

    private function assertFlags(int $flags, bool $requireUserPresent, bool $requireUserVerified, bool $requireAttestedData): void
    {
        if ($requireUserPresent && ($flags & 0x01) === 0) {
            throw new RuntimeException('Der Passkey bestätigt keine aktive Benutzerinteraktion.');
        }

        if ($requireUserVerified && ($flags & 0x04) === 0) {
            throw new RuntimeException('Der Passkey liefert keine bestätigte Benutzerprüfung.');
        }

        if ($requireAttestedData && ($flags & 0x40) === 0) {
            throw new RuntimeException('Die Passkey-Registrierung enthält keine Credential-Daten.');
        }
    }

    private function parseAuthData(string $authData): array
    {
        if (strlen($authData) < 37) {
            throw new RuntimeException('Die Authenticator-Daten sind zu kurz.');
        }

        $rpIdHash = substr($authData, 0, 32);
        $flags = ord($authData[32]);
        $signCount = unpack('N', substr($authData, 33, 4))[1];

        $parsed = [
            'rpIdHash' => $rpIdHash,
            'flags' => $flags,
            'signCount' => $signCount,
        ];

        $offset = 37;
        if (($flags & 0x40) !== 0) {
            if (strlen($authData) < $offset + 18) {
                throw new RuntimeException('Die Credential-Daten des Passkeys sind unvollständig.');
            }

            $aaguid = substr($authData, $offset, 16);
            $offset += 16;
            $credentialLength = unpack('n', substr($authData, $offset, 2))[1];
            $offset += 2;
            $credentialId = substr($authData, $offset, $credentialLength);
            $offset += $credentialLength;

            [$credentialPublicKey, $offset] = self::decodeCborAt($authData, $offset);

            $parsed['aaguid'] = $aaguid;
            $parsed['credentialId'] = $credentialId;
            $parsed['credentialPublicKey'] = $credentialPublicKey;
            $parsed['credentialPublicKeyOffset'] = $offset;
        }

        return $parsed;
    }

    private function coseKeyToPem(mixed $credentialPublicKey): string
    {
        if (!is_array($credentialPublicKey)) {
            throw new RuntimeException('Der öffentliche Schlüssel des Passkeys fehlt.');
        }

        $kty = $credentialPublicKey[1] ?? null;
        $alg = $credentialPublicKey[3] ?? null;
        $crv = $credentialPublicKey[-1] ?? null;
        $x = $credentialPublicKey[-2] ?? null;
        $y = $credentialPublicKey[-3] ?? null;

        if ($kty !== 2 || $alg !== -7 || $crv !== 1 || !is_string($x) || !is_string($y)) {
            throw new RuntimeException('Dieser Passkey-Typ wird aktuell nicht unterstützt.');
        }

        $uncompressedPoint = "\x04" . $x . $y;

        $algorithmIdentifier = hex2bin('301306072A8648CE3D020106082A8648CE3D030107');
        if ($algorithmIdentifier === false) {
            throw new RuntimeException('Der Algorithmus-Identifier konnte nicht erzeugt werden.');
        }

        $subjectPublicKeyInfo = self::derSequence(
            $algorithmIdentifier .
            self::derBitString($uncompressedPoint)
        );

        return "-----BEGIN PUBLIC KEY-----\n"
            . chunk_split(base64_encode($subjectPublicKeyInfo), 64, "\n")
            . "-----END PUBLIC KEY-----\n";
    }

    private function rpId(): string
    {
        $host = parse_url((string) config('app.base_url', ''), PHP_URL_HOST);
        if (!is_string($host) || $host === '') {
            throw new RuntimeException('Die RP-ID konnte nicht aus der App-URL ermittelt werden.');
        }

        return strtolower($host);
    }

    private function origin(): string
    {
        $origin = rtrim((string) config('app.base_url', ''), '/');
        if ($origin === '') {
            throw new RuntimeException('Die Passkey-Origin ist nicht konfiguriert.');
        }

        return $origin;
    }

    public static function base64urlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }

    public static function base64urlDecode(string $value): string
    {
        if ($value === '') {
            return '';
        }

        $padding = strlen($value) % 4;
        if ($padding > 0) {
            $value .= str_repeat('=', 4 - $padding);
        }

        $decoded = base64_decode(strtr($value, '-_', '+/'), true);
        if ($decoded === false) {
            throw new RuntimeException('Eine Base64URL-kodierte Passkey-Angabe ist ungültig.');
        }

        return $decoded;
    }

    private static function decodeCbor(string $bytes): mixed
    {
        [$value] = self::decodeCborAt($bytes, 0);
        return $value;
    }

    private static function decodeCborAt(string $bytes, int $offset): array
    {
        if (!isset($bytes[$offset])) {
            throw new RuntimeException('Unerwartetes Ende in den CBOR-Daten.');
        }

        $initial = ord($bytes[$offset++]);
        $majorType = $initial >> 5;
        $additionalInfo = $initial & 0x1f;
        $argument = self::decodeCborLength($bytes, $offset, $additionalInfo);

        return match ($majorType) {
            0 => [$argument, $offset],
            1 => [-1 - $argument, $offset],
            2 => [substr($bytes, $offset, $argument), $offset + $argument],
            3 => [substr($bytes, $offset, $argument), $offset + $argument],
            4 => self::decodeCborArray($bytes, $offset, $argument),
            5 => self::decodeCborMap($bytes, $offset, $argument),
            7 => match ($additionalInfo) {
                20 => [false, $offset],
                21 => [true, $offset],
                22 => [null, $offset],
                default => throw new RuntimeException('Nicht unterstützter CBOR-Spezialwert.'),
            },
            default => throw new RuntimeException('Nicht unterstützter CBOR-Typ in der Passkey-Antwort.'),
        };
    }

    private static function decodeCborLength(string $bytes, int &$offset, int $additionalInfo): int
    {
        return match (true) {
            $additionalInfo < 24 => $additionalInfo,
            $additionalInfo === 24 => ord($bytes[$offset++] ?? "\x00"),
            $additionalInfo === 25 => unpack('n', self::readBytes($bytes, $offset, 2))[1],
            $additionalInfo === 26 => unpack('N', self::readBytes($bytes, $offset, 4))[1],
            default => throw new RuntimeException('Nicht unterstützte CBOR-Länge in der Passkey-Antwort.'),
        };
    }

    private static function decodeCborArray(string $bytes, int $offset, int $length): array
    {
        $items = [];
        for ($index = 0; $index < $length; $index++) {
            [$item, $offset] = self::decodeCborAt($bytes, $offset);
            $items[] = $item;
        }

        return [$items, $offset];
    }

    private static function decodeCborMap(string $bytes, int $offset, int $length): array
    {
        $map = [];
        for ($index = 0; $index < $length; $index++) {
            [$key, $offset] = self::decodeCborAt($bytes, $offset);
            [$value, $offset] = self::decodeCborAt($bytes, $offset);
            $map[$key] = $value;
        }

        return [$map, $offset];
    }

    private static function readBytes(string $bytes, int &$offset, int $length): string
    {
        $segment = substr($bytes, $offset, $length);
        if (strlen($segment) !== $length) {
            throw new RuntimeException('Unerwartetes Ende in den Passkey-Daten.');
        }

        $offset += $length;

        return $segment;
    }

    private static function derSequence(string $payload): string
    {
        return "\x30" . self::derLength(strlen($payload)) . $payload;
    }

    private static function derBitString(string $payload): string
    {
        return "\x03" . self::derLength(strlen($payload) + 1) . "\x00" . $payload;
    }

    private static function derLength(int $length): string
    {
        if ($length < 128) {
            return chr($length);
        }

        $bytes = '';
        while ($length > 0) {
            $bytes = chr($length & 0xff) . $bytes;
            $length >>= 8;
        }

        return chr(0x80 | strlen($bytes)) . $bytes;
    }

    private static function formatUuid(string $bytes): string
    {
        if (strlen($bytes) !== 16) {
            return '';
        }

        $hex = bin2hex($bytes);

        return sprintf(
            '%s-%s-%s-%s-%s',
            substr($hex, 0, 8),
            substr($hex, 8, 4),
            substr($hex, 12, 4),
            substr($hex, 16, 4),
            substr($hex, 20, 12)
        );
    }
}
