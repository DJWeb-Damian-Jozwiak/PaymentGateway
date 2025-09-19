<?php

declare(strict_types=1);

namespace DjWeb\Payments\DTOs;

use DjWeb\Payments\Contracts\Arrayable;
use ReflectionClass;
use ReflectionProperty;

abstract class BaseDto implements Arrayable
{
    /**
     * Convert the DTO to an array using reflection
     *
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        $result = [];
        $reflection = new ReflectionClass($this);

        // Get all public properties
        $properties = $reflection->getProperties(ReflectionProperty::IS_PUBLIC);

        foreach ($properties as $property) {
            $name = $property->getName();
            $value = $this->convertValue($property->getValue($this));
            $result[$name] = $value;
        }

        return $result;
    }

    /**
     * Convert property value to array-compatible format
     */
    private function convertValue(mixed $value): mixed
    {
        return match (true) {
            is_array($value) => array_map($this->convertValue(...), $value),
            $value instanceof Arrayable => $value->toArray(),
            $value instanceof \DateTimeInterface, $value instanceof \DateTimeImmutable => $value->format('Y-m-d'),
            $value instanceof \Stringable, is_object($value) && method_exists($value, '__toString') => (string) $value,
            is_object($value) => $value::class,
            default => $value,
        };
    }
}
