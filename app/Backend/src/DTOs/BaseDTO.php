<?php declare(strict_types=1);

namespace Besoiu\DTOs;

use InvalidArgumentException;

/**
 * Clasa de bază pentru toate Data Transfer Objects
 * 
 * Responsabilități:
 * - Validare automată proprietăți la instanțiere
 * - Serializare/deserializare JSON
 * - Comparare și clonare obiecte
 * - Validare tipuri stricte
 * - Transformare în array pentru API-uri
 * 
 * @version 2.0.0
 * @created 2026-07-02
 */
abstract class BaseDTO
{
    /**
     * Constructorul aplică validarea automată
     */
    public function __construct(array $data = [])
    {
        $this->populate($data);
        $this->validate();
    }

    /**
     * Populează proprietățile din array cu conversii de tip automate
     */
    private function populate(array $data): void
    {
        $reflection = new \ReflectionClass($this);
        
        foreach ($data as $key => $value) {
            if (!property_exists($this, $key)) {
                continue;
            }
            
            $property = $reflection->getProperty($key);
            $type = $property->getType();
            
            if ($type && !$type->allowsNull() && $value === null) {
                continue; // Skip null values for non-nullable properties
            }
            
            if ($value !== null && $type instanceof \ReflectionNamedType) {
                $value = $this->convertValueToType($value, $type->getName());
            }
            
            $this->{$key} = $value;
        }
    }

    /**
     * Convertește o valoare la tipul specificat
     */
    private function convertValueToType(mixed $value, string $targetType): mixed
    {
        return match ($targetType) {
            'int' => (int) $value,
            'float' => (float) $value,
            'string' => (string) $value,
            'bool' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
            'array' => is_array($value) ? $value : [$value],
            default => $value
        };
    }

    /**
     * Validează toate proprietățile obligatorii și tipurile
     */
    protected function validate(): void
    {
        $requiredFields = $this->getRequiredFields();
        $fieldTypes = $this->getFieldTypes();
        $fieldValidators = $this->getFieldValidators();

        // Validare câmpuri obligatorii
        foreach ($requiredFields as $field) {
            if (!isset($this->{$field}) || $this->{$field} === null) {
                throw new InvalidArgumentException("Required field '{$field}' is missing or null");
            }
        }

        // Validare tipuri
        foreach ($fieldTypes as $field => $expectedType) {
            if (isset($this->{$field}) && $this->{$field} !== null) {
                $this->validateFieldType($field, $this->{$field}, $expectedType);
            }
        }

        // Validare custom
        foreach ($fieldValidators as $field => $validator) {
            if (isset($this->{$field}) && $this->{$field} !== null) {
                if (!$validator($this->{$field})) {
                    throw new InvalidArgumentException("Field '{$field}' validation failed");
                }
            }
        }
    }

    /**
     * Validează tipul unui câmp
     */
    private function validateFieldType(string $field, mixed $value, string|array $expectedType): void
    {
        if (is_array($expectedType)) {
            // Multiple tipuri acceptate
            $valid = false;
            foreach ($expectedType as $type) {
                if ($this->checkType($value, $type)) {
                    $valid = true;
                    break;
                }
            }
            
            if (!$valid) {
                $types = implode('|', $expectedType);
                throw new InvalidArgumentException("Field '{$field}' must be of type {$types}, got " . gettype($value));
            }
        } else {
            if (!$this->checkType($value, $expectedType)) {
                throw new InvalidArgumentException("Field '{$field}' must be of type {$expectedType}, got " . gettype($value));
            }
        }
    }

    /**
     * Verifică dacă o valoare respectă tipul specificat
     */
    private function checkType(mixed $value, string $type): bool
    {
        return match ($type) {
            'string' => is_string($value),
            'int', 'integer' => is_int($value),
            'float', 'double' => is_float($value) || is_int($value),
            'bool', 'boolean' => is_bool($value),
            'array' => is_array($value),
            'object' => is_object($value),
            'null' => is_null($value),
            'numeric' => is_numeric($value),
            'scalar' => is_scalar($value),
            'callable' => is_callable($value),
            default => $value instanceof $type
        };
    }

    /**
     * Convertește DTO-ul la array
     */
    public function toArray(): array
    {
        $result = [];
        $reflection = new \ReflectionClass($this);
        
        foreach ($reflection->getProperties(\ReflectionProperty::IS_PUBLIC) as $property) {
            $name = $property->getName();
            $result[$name] = $this->{$name};
        }

        return $result;
    }

    /**
     * Convertește DTO-ul la JSON
     */
    public function toJson(): string
    {
        return json_encode($this->toArray(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE);
    }

    /**
     * Creează DTO din JSON
     */
    public static function fromJson(string $json): static
    {
        $data = json_decode($json, true, 512, JSON_THROW_ON_ERROR);
        return new static($data);
    }

    /**
     * Verifică dacă două DTO-uri sunt egale
     */
    public function equals(BaseDTO $other): bool
    {
        return get_class($this) === get_class($other) && 
               $this->toArray() === $other->toArray();
    }

    /**
     * Clonează DTO-ul cu modificări opționale
     */
    public function with(array $changes): static
    {
        $data = array_merge($this->toArray(), $changes);
        return new static($data);
    }

    /**
     * Validează și returnează DTO-ul curent
     */
    public function validated(): static
    {
        $this->validate();
        return $this;
    }

    /**
     * Merge mai multe DTO-uri într-unul singur
     */
    public static function merge(BaseDTO ...$dtos): static
    {
        $merged = [];
        
        foreach ($dtos as $dto) {
            $merged = array_merge($merged, $dto->toArray());
        }
        
        return new static($merged);
    }

    /**
     * Filtrează doar câmpurile non-null
     */
    public function nonNull(): array
    {
        return array_filter($this->toArray(), fn($value) => $value !== null);
    }

    /**
     * Filtrează doar câmpurile modificate față de alt DTO
     */
    public function diff(BaseDTO $other): array
    {
        if (get_class($this) !== get_class($other)) {
            throw new InvalidArgumentException('Cannot diff DTOs of different types');
        }

        $thisArray = $this->toArray();
        $otherArray = $other->toArray();
        $diff = [];

        foreach ($thisArray as $key => $value) {
            if (!array_key_exists($key, $otherArray) || $otherArray[$key] !== $value) {
                $diff[$key] = $value;
            }
        }

        return $diff;
    }

    /**
     * Validează un array de DTO-uri
     */
    public static function validateArray(array $dtos): array
    {
        $validated = [];
        
        foreach ($dtos as $index => $dto) {
            if (!($dto instanceof static)) {
                throw new InvalidArgumentException("Item at index {$index} is not a valid " . static::class);
            }
            
            $validated[] = $dto->validated();
        }
        
        return $validated;
    }

    /**
     * Implementare __toString pentru debugging
     */
    public function __toString(): string
    {
        return static::class . ' ' . $this->toJson();
    }

    /**
     * Implementare __debugInfo pentru var_dump
     */
    public function __debugInfo(): array
    {
        return $this->toArray();
    }

    // Metode abstracte implementate de clasele derivate

    /**
     * Returnează lista câmpurilor obligatorii
     */
    abstract protected function getRequiredFields(): array;

    /**
     * Returnează maparea câmp -> tip pentru validare
     */
    abstract protected function getFieldTypes(): array;

    /**
     * Returnează validatori custom pentru câmpuri
     * Format: ['field_name' => callable]
     */
    protected function getFieldValidators(): array
    {
        return [];
    }
}