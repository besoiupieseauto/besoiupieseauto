<?php declare(strict_types=1);

namespace Besoiu\DTOs;

/**
 * Data Transfer Object pentru produse
 * 
 * Responsabilități:
 * - Validare strictă date produs la import/export
 * - Normalizare și curățare automată câmpuri
 * - Verificare constrainte business (preț, stoc, etc.)
 * - Suport pentru metadata și îmbogățire TecDoc
 * - Compatibilitate cu sistemul existent de produse
 * 
 * @version 2.0.0
 * @created 2026-07-02
 */
class ProductDTO extends BaseDTO
{
    // Câmpuri de bază obligatorii
    public string $pName;
    public string $pCode;
    public float $pPrice;

    // Câmpuri opționale de identificare
    public ?string $pBrand = null;
    public ?string $pCategory = null;
    public ?string $pSubcategory = null;
    public ?string $pSupplier = null;

    // Câmpuri compatibilitate vehicul
    public ?string $pMarca = null;
    public ?string $pModel = null;
    public ?string $pMotorizare = null;
    public ?string $pCar = null;

    // Câmpuri descriere și specificații
    public ?string $pNote = null;
    public ?string $pSpecs = null;
    public ?string $pCompatibilitati = null;

    // Câmpuri coduri și referințe
    public ?string $pOem = null;
    public ?string $pCodeNorm = null;
    public ?string $pBrandNorm = null;

    // Câmpuri preț și stoc
    public ?float $pBasePrice = null;
    public ?string $pStock = null;
    public ?int $pMarkupRuleId = null;
    public ?string $pMarkupRuleName = null;
    public ?string $pMarkupAppliedAt = null;

    // Câmpuri imagini și media
    public ?string $pImages = null; // JSON array
    public ?string $pImageSource = null;

    // Câmpuri livrare și servicii
    public ?string $pShipping = null;
    public ?string $pWarranty = null;
    public ?string $pReturn = null;

    // Câmpuri geografice
    public ?string $pState = null;
    public ?string $pCity = null;

    // Câmpuri sistem
    public ?int $id = null;
    public ?string $randomn_id = null;
    public ?string $status = null;
    public ?string $pBadge = null;
    public ?bool $pWhatsapp = null;
    public ?string $raw_json = null;

    // Câmpuri metadata
    public ?int $connect_id = null;
    public ?int $id_users = null;
    public ?string $name = null;
    public ?string $email = null;
    public ?string $phone = null;

    /**
     * Câmpurile obligatorii pentru un produs valid
     */
    protected function getRequiredFields(): array
    {
        return ['pName', 'pCode', 'pPrice'];
    }

    /**
     * Tipurile de date pentru validare
     */
    protected function getFieldTypes(): array
    {
        return [
            'pName' => 'string',
            'pCode' => 'string',
            'pPrice' => 'float',
            'pBasePrice' => 'float',
            'pStock' => 'string',
            'pMarkupRuleId' => 'int',
            'pWhatsapp' => 'bool',
            'id' => 'int',
            'connect_id' => 'int',
            'id_users' => 'int',
            'status' => 'string',
            'pImages' => 'string', // JSON string
            'raw_json' => 'string' // JSON string
        ];
    }

    /**
     * Validatori custom pentru câmpuri specifice
     */
    protected function getFieldValidators(): array
    {
        return [
            'pPrice' => fn($value) => $value > 0 && $value <= 999999.99,
            'pBasePrice' => fn($value) => $value === null || ($value > 0 && $value <= 999999.99),
            'pCode' => fn($value) => strlen($value) >= 2 && strlen($value) <= 100,
            'pName' => fn($value) => strlen($value) >= 3 && strlen($value) <= 500,
            'pImages' => fn($value) => $value === null || $this->isValidJson($value),
            'raw_json' => fn($value) => $value === null || $this->isValidJson($value),
            'status' => fn($value) => $value === null || in_array($value, ['0', '1', 'active', 'inactive']),
            'pImageSource' => fn($value) => $value === null || in_array($value, [
                'tecdoc_api', 'caietcomenzi', 'csv', 'missing', 'processed', 'manual'
            ])
        ];
    }

    /**
     * Verifică dacă un string este JSON valid
     */
    private function isValidJson(?string $value): bool
    {
        if ($value === null) return true;
        
        json_decode($value);
        return json_last_error() === JSON_ERROR_NONE;
    }

    /**
     * Normalizează codul produsului (uppercase, alfanumeric)
     */
    public function normalizeCode(): static
    {
        if ($this->pCode) {
            $this->pCodeNorm = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $this->pCode));
        }
        
        return $this;
    }

    /**
     * Normalizează brandul (uppercase, clean)
     */
    public function normalizeBrand(): static
    {
        if ($this->pBrand) {
            $this->pBrandNorm = strtoupper(trim($this->pBrand));
        }
        
        return $this;
    }

    /**
     * Aplică normalizări automate
     */
    public function autoNormalize(): static
    {
        // Normalizare cod și brand
        $this->normalizeCode();
        $this->normalizeBrand();

        // Curățare câmpuri text
        $this->pName = $this->cleanText($this->pName);
        $this->pNote = $this->cleanText($this->pNote);
        $this->pSpecs = $this->cleanText($this->pSpecs);

        // Formatare preț
        if ($this->pPrice) {
            $this->pPrice = round($this->pPrice, 2);
        }
        
        if ($this->pBasePrice) {
            $this->pBasePrice = round($this->pBasePrice, 2);
        }

        // Normalizare OEM (uppercase, clean separators)
        if ($this->pOem) {
            $oemCodes = array_map('trim', explode(',', $this->pOem));
            $oemCodes = array_filter($oemCodes);
            $oemCodes = array_unique($oemCodes);
            $this->pOem = implode(',', $oemCodes);
        }

        return $this;
    }

    /**
     * Curăță și trimite text
     */
    private function cleanText(?string $text): ?string
    {
        if ($text === null) return null;
        
        // Remove excessive whitespace
        $text = preg_replace('/\s+/', ' ', $text);
        
        // Trim
        $text = trim($text);
        
        return empty($text) ? null : $text;
    }

    /**
     * Verifică dacă produsul are imagini
     */
    public function hasImages(): bool
    {
        if (empty($this->pImages)) {
            return false;
        }

        $images = json_decode($this->pImages, true);
        return is_array($images) && !empty($images);
    }

    /**
     * Obține array-ul de imagini
     */
    public function getImagesArray(): array
    {
        if (empty($this->pImages)) {
            return [];
        }

        $images = json_decode($this->pImages, true);
        return is_array($images) ? $images : [];
    }

    /**
     * Setează imaginile din array
     */
    public function setImages(array $images): static
    {
        $this->pImages = empty($images) ? null : json_encode($images);
        return $this;
    }

    /**
     * Adaugă o imagine la listă
     */
    public function addImage(string $imageUrl): static
    {
        $images = $this->getImagesArray();
        
        if (!in_array($imageUrl, $images)) {
            $images[] = $imageUrl;
            $this->setImages($images);
        }
        
        return $this;
    }

    /**
     * Verifică dacă produsul are date TecDoc
     */
    public function hasTecDocData(): bool
    {
        if (empty($this->raw_json)) {
            return false;
        }

        $rawData = json_decode($this->raw_json, true);
        return isset($rawData['tecdoc_enrichment']) || isset($rawData['tecdoc_api']);
    }

    /**
     * Obține metadata TecDoc
     */
    public function getTecDocMetadata(): ?array
    {
        if (empty($this->raw_json)) {
            return null;
        }

        $rawData = json_decode($this->raw_json, true);
        return $rawData['tecdoc_enrichment'] ?? $rawData['tecdoc_api'] ?? null;
    }

    /**
     * Adaugă metadata TecDoc
     */
    public function setTecDocMetadata(array $metadata): static
    {
        $rawData = empty($this->raw_json) ? [] : json_decode($this->raw_json, true);
        $rawData['tecdoc_enrichment'] = $metadata;
        
        $this->raw_json = json_encode($rawData, JSON_UNESCAPED_UNICODE);
        return $this;
    }

    /**
     * Calculează hash pentru detectarea duplicatelor
     */
    public function getDeduplicationHash(): string
    {
        $key = $this->pCodeNorm . '|' . $this->pBrandNorm;
        return md5(strtolower($key));
    }

    /**
     * Verifică dacă este un produs valid pentru publicare
     */
    public function isReadyForPublish(): bool
    {
        return !empty($this->pName) && 
               !empty($this->pCode) && 
               $this->pPrice > 0 &&
               !empty($this->pCategory);
    }

    /**
     * Generează un ID random pentru produs
     */
    public function generateRandomId(): static
    {
        if (empty($this->randomn_id)) {
            $this->randomn_id = bin2hex(random_bytes(8));
        }
        
        return $this;
    }

    /**
     * Convertește la format pentru inserare în BD
     */
    public function toDatabaseArray(): array
    {
        $data = $this->nonNull();
        
        // Exclude câmpuri care nu sunt în schema BD
        unset($data['id']); // ID-ul se generează automat
        
        // Asigură-te că câmpurile obligatorii sunt prezente
        if (!isset($data['status'])) {
            $data['status'] = '1'; // Activ by default
        }
        
        return $data;
    }

    /**
     * Creează DTO din rând de bază de date
     */
    public static function fromDatabaseRow(array $row): static
    {
        return new static($row);
    }

    /**
     * Creează DTO din rând CSV de import
     */
    public static function fromCsvRow(array $row, array $mapping = []): static
    {
        // Mapping implicit pentru CSV standard
        $defaultMapping = [
            0 => 'pName',
            1 => 'pCode',
            2 => 'pBrand',
            3 => 'pPrice',
            4 => 'pCategory',
            5 => 'pSubcategory',
            6 => 'pNote',
            7 => 'pStock',
            8 => 'pOem',
            9 => 'pMarca',
            10 => 'pModel'
        ];

        $mapping = array_merge($defaultMapping, $mapping);
        $data = [];

        foreach ($mapping as $csvIndex => $field) {
            if (isset($row[$csvIndex]) && $row[$csvIndex] !== '') {
                $value = trim($row[$csvIndex]);
                
                // Conversii tip specific
                if (in_array($field, ['pPrice', 'pBasePrice'])) {
                    $value = (float) str_replace(',', '.', $value);
                } elseif (in_array($field, ['pMarkupRuleId', 'id_users'])) {
                    $value = (int) $value;
                } elseif ($field === 'pWhatsapp') {
                    $value = filter_var($value, FILTER_VALIDATE_BOOLEAN);
                }
                
                $data[$field] = $value;
            }
        }

        return (new static($data))->autoNormalize();
    }

    /**
     * Validare specifică business rules
     */
    public function validateBusinessRules(): array
    {
        $errors = [];

        // Validare preț rezonabil
        if ($this->pPrice > 50000) {
            $errors[] = 'Preț suspicioasă de mare: ' . number_format($this->pPrice, 2) . ' RON';
        }
        
        if ($this->pPrice < 0.1) {
            $errors[] = 'Preț prea mic: ' . number_format($this->pPrice, 2) . ' RON';
        }

        // Validare completitudine
        if (empty($this->pCategory)) {
            $errors[] = 'Categoria produsului lipsește';
        }

        if (empty($this->pBrand)) {
            $errors[] = 'Marca produsului lipsește';
        }

        // Validare cod format
        if (!preg_match('/^[A-Za-z0-9\-\.\/]+$/', $this->pCode)) {
            $errors[] = 'Cod produs cu format invalid: ' . $this->pCode;
        }

        return $errors;
    }
}