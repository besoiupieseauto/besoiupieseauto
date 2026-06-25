<?php

declare(strict_types=1);

namespace Evasystem\Controllers\Facturi;

use Evasystem\Core\Facturi\FacturiModel;
use Evasystem\Exceptions\NotFoundException;
use Evasystem\Exceptions\PersistenceException;

/**
 * Logică de business pentru facturi.
 */
final class FacturiService
{
    private FacturiModel $facturiModel;

    public function __construct(FacturiModel $facturiModel)
    {
        $this->facturiModel = $facturiModel;
    }

    /** @param array<string, string|int|float|null> $invoicePayload */
    public function createInvoice(array $invoicePayload): array
    {
        $randomId = $this->generateUniqueRandomId();
        $invoiceNumber = $invoicePayload['invoice_number'] ?? ('INV-' . $randomId);

        $invoicePayload['randomn_id'] = $randomId;
        $invoicePayload['invoice_number'] = $invoiceNumber;

        if (!$this->facturiModel->insert($invoicePayload)) {
            throw new PersistenceException('Factura nu a putut fi salvată.');
        }

        return ['randomn_id' => $randomId, 'invoice_number' => (string) $invoiceNumber];
    }

    /** @param array<string, string|int|float|null> $invoicePayload */
    public function updateInvoice(int $randomId, array $invoicePayload): array
    {
        $this->ensureInvoiceExists($randomId);

        if (!$this->facturiModel->updateByRandomId($randomId, $invoicePayload)) {
            throw new PersistenceException('Factura nu a putut fi actualizată.');
        }

        return ['randomn_id' => $randomId];
    }

    public function changeInvoiceStatus(int $randomId, string $invoiceStatus): void
    {
        $this->ensureInvoiceExists($randomId);

        if (!$this->facturiModel->updateInvoiceStatusByRandomId($randomId, $invoiceStatus)) {
            throw new PersistenceException('Statusul facturii nu a putut fi actualizat.');
        }
    }

    public function deleteInvoice(int $randomId): void
    {
        $this->ensureInvoiceExists($randomId);

        if (!$this->facturiModel->deleteByRandomId($randomId)) {
            throw new PersistenceException('Factura nu a putut fi ștearsă.');
        }
    }

    /** @return array<int, array<string, mixed>> */
    public function listInvoices(array $params = []): array
    {
        $page = max(1, (int) ($params['page'] ?? 1));
        $perPage = max(1, min(100, (int) ($params['per_page'] ?? 10)));
        return $this->facturiModel->findPaginated($page, $perPage, $params);
    }

    /** @return array{all:int,achitata:int,neachitata:int,anulata:int,storno:int,total_amount:float} */
    public function stats(): array
    {
        return $this->facturiModel->aggregateStats();
    }

    private function ensureInvoiceExists(int $randomId): void
    {
        if (!$this->facturiModel->existsByRandomId($randomId)) {
            throw new NotFoundException('Factura cerută nu există.');
        }
    }

    private function generateUniqueRandomId(): int
    {
        for ($attempt = 0; $attempt < 10; $attempt++) {
            $candidate = random_int(300000, 999999);
            if (!$this->facturiModel->existsByRandomId($candidate)) {
                return $candidate;
            }
        }

        throw new PersistenceException('Nu am reușit să generez un randomn_id unic pentru factură.');
    }
}
