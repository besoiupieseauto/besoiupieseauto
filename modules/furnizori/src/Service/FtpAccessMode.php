<?php
declare(strict_types=1);

namespace Besoiu\Modules\Furnizori\Service;

/**
 * Cine dă accesul FTP/SFTP: ei (ne conectăm la serverul lor) sau noi (ei încarcă la noi).
 */
final class FtpAccessMode
{
    public const THEY = 'they';
    public const WE = 'we';

    /** @return list<string> */
    public static function allowed(): array
    {
        return [self::THEY, self::WE];
    }

    public static function normalize(mixed $value): string
    {
        $mode = strtolower(trim((string) $value));
        if (in_array($mode, ['we', 'ours', 'inbound', 'push', 'upload', 'our'], true)) {
            return self::WE;
        }

        return self::THEY;
    }

    /** @param array<string, mixed> $furnizor */
    public static function isOurs(array $furnizor): bool
    {
        return self::normalize($furnizor['ftp_access_mode'] ?? '') === self::WE;
    }

    /** @param array<string, mixed> $furnizor */
    public static function isTheirs(array $furnizor): bool
    {
        return !self::isOurs($furnizor);
    }
}
