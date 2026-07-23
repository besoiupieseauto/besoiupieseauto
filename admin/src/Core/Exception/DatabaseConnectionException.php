<?php

declare(strict_types=1);

namespace Besoiu\Core\Exception;

use RuntimeException;

/**
 * Eșec la conectarea PDO — nu expune mesajul driver-ului către utilizator.
 */
final class DatabaseConnectionException extends RuntimeException
{
    public static function fromDriverError(string $connectionName, string $internalMessage): self
    {
        error_log(sprintf(
            '[Besoiu][Database][%s] Connection failed: %s',
            $connectionName,
            $internalMessage
        ));

        return new self('Conexiunea la baza de date nu este disponibilă.');
    }
}
