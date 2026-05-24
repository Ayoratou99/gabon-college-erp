<?php

declare(strict_types=1);

namespace App\Foundation\DTOs;

use Illuminate\Contracts\Support\Arrayable;
use Illuminate\Http\Request;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Minimal base for immutable data-transfer objects.
 *
 * DTOs are `final readonly` and carry validated input from a FormRequest
 * into a Service. We deliberately keep this lean — no validation rules,
 * no serialization layer — so each module can decide what library it
 * wants (Spatie/laravel-data is in composer.json for richer use cases).
 *
 *     final readonly class CreateCandidatDto extends Dto {
 *         public function __construct(
 *             public string $nom,
 *             public string $prenom,
 *             public string $email,
 *             public string $telephone,
 *             public string $centreId,
 *             public string $sectionId,
 *             public bool   $dejaBac,
 *             public ?int   $anneeBac,
 *         ) {}
 *     }
 *
 *     // In the controller:
 *     $dto = CreateCandidatDto::fromRequest($request);
 *     $service->create($dto);
 */
abstract readonly class Dto implements Arrayable
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): static
    {
        $reflection = new ReflectionClass(static::class);
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            // @phpstan-ignore-next-line dynamic constructor call on subclass
            return new static();
        }

        $args = array_map(
            static fn (ReflectionParameter $p) => self::resolveArg($p, $data),
            $constructor->getParameters(),
        );

        // @phpstan-ignore-next-line dynamic constructor call on subclass
        return new static(...$args);
    }

    public static function fromRequest(Request $request): static
    {
        return static::fromArray($request->all());
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        $out = [];
        foreach ((new ReflectionClass($this))->getProperties() as $prop) {
            $value = $prop->getValue($this);
            $out[$prop->getName()] = $value instanceof Arrayable ? $value->toArray() : $value;
        }
        return $out;
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private static function resolveArg(ReflectionParameter $param, array $data): mixed
    {
        $name = $param->getName();
        $snake = self::camelToSnake($name);

        if (array_key_exists($name, $data)) {
            return $data[$name];
        }
        if (array_key_exists($snake, $data)) {
            return $data[$snake];
        }
        if ($param->isDefaultValueAvailable()) {
            return $param->getDefaultValue();
        }
        $type = $param->getType();
        if ($type instanceof ReflectionNamedType && $type->allowsNull()) {
            return null;
        }

        throw new \InvalidArgumentException(
            sprintf('Missing required field "%s" (alias "%s") in DTO %s.', $name, $snake, static::class)
        );
    }

    private static function camelToSnake(string $value): string
    {
        return strtolower((string) preg_replace('/([a-z])([A-Z])/', '$1_$2', $value));
    }
}
