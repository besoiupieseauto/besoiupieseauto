<?php
/*
 * ============================================================================
 * FIȘIER: app/Core/Repository/AbstractRepository.php
 * ============================================================================
 * Scop: Clasă de bază pentru repository-urile storefront care accesează DB.
 *       Oferă acces uniform la conexiunea PDO via Connection singleton.
 *
 * Include/require: Storefront\Core\Database\Connection (shop-db PDO)
 *
 * Bază de date: PDO MySQL via Connection::get() — conexiune lazy storefront.
 * ============================================================================
 */

declare(strict_types=1);

namespace Storefront\Core\Repository;

use PDO;
use Storefront\Core\Database\Connection;

/**
 * Repository abstract — subclasele implementează interogări pe tabele specifice.
 */
abstract class AbstractRepository
{
    /**
     * Returnează conexiunea PDO activă a vitrinei.
     *
     * @return PDO Conexiune MySQL pregătită pentru prepared statements
     */
    protected function pdo(): PDO
    {
        return Connection::get();
    }
}
