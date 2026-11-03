<?php

namespace Modules\Dashboard\Services\Backup;

/**
 * The container of a backup: gzip-compressed JSON lines, cut into chunks that are
 * each encrypted and authenticated (AES-256-GCM). Every chunk is bound to its
 * position, and the file ends with a sealed marker, so a chunk that was changed,
 * moved, dropped or a file that was cut short is refused instead of half-restored.
 *
 *   "JOCKOBK1"  then repeated:  length(4, big endian) nonce(12) ciphertext tag(16)
 *   the last chunk is empty and bound to the end marker.
 */
final class BackupFile
{
    public const MAGIC = 'JOCKOBK1';

    private const CHUNK = 1048576;

    private const CIPHER = 'aes-256-gcm';

    private const NONCE = 12;

    private const TAG = 16;

    /**
     * Compress $plainPath and write it encrypted to $path.
     */
    public static function seal(string $plainPath, string $path, string $secret): void
    {
        $key = self::key($secret);
        $in = fopen($plainPath, 'rb');
        $out = fopen($path, 'wb');

        if ($in === false || $out === false) {
            throw new BackupException('The backup file could not be written.');
        }

        try {
            fwrite($out, self::MAGIC);
            $index = 0;

            while (! feof($in)) {
                $plain = (string) fread($in, self::CHUNK);

                if ($plain === '') {
                    break;
                }

                fwrite($out, self::chunk($plain, $key, self::aad($index++, false)));
            }

            fwrite($out, self::chunk('', $key, self::aad($index, true)));
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    /**
     * Decrypt $path into $plainPath (the compressed stream).
     *
     * @throws BackupException when it is not a backup, the key is wrong, or the file was changed or cut short
     */
    public static function open(string $path, string $plainPath, string $secret): void
    {
        $key = self::key($secret);
        $in = fopen($path, 'rb');
        $out = fopen($plainPath, 'wb');

        if ($in === false || $out === false) {
            throw new BackupException('The backup file could not be read.');
        }

        try {
            if (fread($in, strlen(self::MAGIC)) !== self::MAGIC) {
                throw new BackupException('This is not a Jocko backup.');
            }

            $index = 0;

            while (true) {
                $head = fread($in, 4);

                if ($head === false || strlen($head) !== 4) {
                    throw new BackupException('The backup is incomplete.');
                }

                $length = (unpack('N', $head) ?: [1 => self::CHUNK + 1])[1];

                if ($length > self::CHUNK) {
                    throw new BackupException('The backup is damaged.');
                }

                $nonce = (string) fread($in, self::NONCE);
                $cipher = $length > 0 ? (string) fread($in, $length) : '';
                $tag = (string) fread($in, self::TAG);

                if (strlen($nonce) !== self::NONCE || strlen($cipher) !== $length || strlen($tag) !== self::TAG) {
                    throw new BackupException('The backup is incomplete.');
                }

                $final = $length === 0;
                $plain = openssl_decrypt($cipher, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag, self::aad($index, $final));

                if ($plain === false) {
                    throw new BackupException('The backup cannot be read: wrong key or passphrase, or the file was changed.');
                }

                if ($final) {
                    // Nothing may follow the end marker.
                    if (fread($in, 1) !== '') {
                        throw new BackupException('The backup is damaged.');
                    }

                    return;
                }

                fwrite($out, $plain);
                $index++;
            }
        } finally {
            fclose($in);
            fclose($out);
        }
    }

    private static function chunk(string $plain, string $key, string $aad): string
    {
        $nonce = random_bytes(self::NONCE);
        $tag = '';
        $cipher = openssl_encrypt($plain, self::CIPHER, $key, OPENSSL_RAW_DATA, $nonce, $tag, $aad, self::TAG);

        if ($cipher === false) {
            throw new BackupException('The backup could not be encrypted.');
        }

        return pack('N', strlen($cipher)).$nonce.$cipher.$tag;
    }

    private static function aad(int $index, bool $final): string
    {
        return self::MAGIC.pack('N', $index).($final ? 'END' : 'DAT');
    }

    private static function key(string $secret): string
    {
        return hash_hkdf('sha256', $secret, 32, 'jocko-backup-v1');
    }
}
