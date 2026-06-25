<?php

declare(strict_types=1);

namespace Evasystem\Core\Crud;

use Evasystem\Controllers\Alerts\Alerts;
use Evasystem\Controllers\Bots\Bots;
use Evasystem\Controllers\Bots\BotsService;
use Evasystem\Controllers\Clienti\Clienti;
use Evasystem\Controllers\Clienti\ClientiService;
use Evasystem\Controllers\Comenzi\Comenzi;
use Evasystem\Controllers\Comenzi\ComenziService;
use Evasystem\Controllers\Cron\Cron;
use Evasystem\Controllers\CrossReference\CrossReference;
use Evasystem\Controllers\Facturi\Facturi;
use Evasystem\Controllers\Facturi\FacturiService;
use Evasystem\Controllers\Livrare\Livrare;
use Evasystem\Controllers\Livrare\LivrareService;
use Evasystem\Controllers\Marketplace\Marketplace;
use Evasystem\Controllers\Marketplace\MarketplaceService;
use Evasystem\Controllers\Messages\Messages;
use Evasystem\Controllers\Messages\MessagesService;
use Evasystem\Controllers\Report\Report;
use Evasystem\Controllers\Scan\Scan;
use Evasystem\Controllers\SearchLogs\SearchLogsCrud;
use Evasystem\Controllers\Settings\Settings;
use Evasystem\Core\Bots\BotsModel;
use Evasystem\Core\Clienti\ClientiModel;
use Evasystem\Core\Comenzi\ComenziModel;
use Evasystem\Core\Facturi\FacturiModel;
use Evasystem\Core\Livrare\LivrareModel;
use Evasystem\Core\Marketplace\MarketplaceModel;
use Evasystem\Core\Messages\MessagesModel;
use InvalidArgumentException;

/**
 * Instanțiere corectă Controller + Service + Model pentru endpoint-urile crudu.
 */
final class CrudModuleFactory
{
    /** @return array{label: string, session_key: string} */
    public static function modernModuleMeta(string $moduleKey): array
    {
        $meta = self::modernModules()[$moduleKey] ?? null;
        if ($meta === null) {
            throw new InvalidArgumentException('Modul CRUD modern necunoscut: ' . $moduleKey);
        }

        return $meta;
    }

    public static function createModernController(string $moduleKey): object
    {
        switch ($moduleKey) {
            case 'Comenzi':
                return new Comenzi(new ComenziService(new ComenziModel()));
            case 'Clienti':
                return new Clienti(new ClientiService(new ClientiModel()));
            case 'Bots':
                return new Bots(new BotsService(new BotsModel()));
            case 'Livrare':
                return new Livrare(new LivrareService(new LivrareModel()));
            case 'Facturi':
                return new Facturi(new FacturiService(new FacturiModel()));
            case 'Messages':
                return new Messages(new MessagesService(new MessagesModel()));
            case 'Marketplace':
                return new Marketplace(new MarketplaceService(new MarketplaceModel()));
            case 'Alerts':
                return new Alerts();
            case 'Scan':
                return new Scan();
            case 'Cron':
                return new Cron();
            case 'Report':
                return new Report();
            case 'Settings':
                return new Settings();
            case 'CrossReference':
                return new CrossReference();
            case 'SearchLogsCrud':
                return new SearchLogsCrud();
            default:
                throw new InvalidArgumentException('Modul CRUD modern necunoscut: ' . $moduleKey);
        }
    }

    /** @return array<string, array{label: string, session_key: string}> */
    private static function modernModules(): array
    {
        return [
            'Comenzi' => ['label' => 'Comandă', 'session_key' => 'comenzi'],
            'Clienti' => ['label' => 'Client', 'session_key' => 'clienti'],
            'Bots' => ['label' => 'Bot', 'session_key' => 'bots'],
            'Livrare' => ['label' => 'Livrare', 'session_key' => 'livrare'],
            'Facturi' => ['label' => 'Factură', 'session_key' => 'facturi'],
            'Messages' => ['label' => 'Mesaj', 'session_key' => 'messages'],
            'Marketplace' => ['label' => 'Marketplace', 'session_key' => 'marketplace'],
            'Alerts' => ['label' => 'Alert', 'session_key' => 'alerts'],
            'Scan' => ['label' => 'Scan', 'session_key' => 'scan'],
            'Cron' => ['label' => 'Cron', 'session_key' => 'cron'],
            'Report' => ['label' => 'Raport', 'session_key' => 'report'],
            'Settings' => ['label' => 'Setare', 'session_key' => 'settings'],
            'CrossReference' => ['label' => 'Cross-reference', 'session_key' => 'cross_reference'],
            'SearchLogsCrud' => ['label' => 'Search logs', 'session_key' => 'search_logs'],
        ];
    }
}
