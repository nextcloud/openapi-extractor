<?php

namespace OpenAPIExtractor;

use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;
use PhpParser\NodeAbstract;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprIntegerNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayShapeNode;
use PHPStan\PhpDocParser\Ast\Type\ArrayTypeNode;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IntersectionTypeNode;
use PHPStan\PhpDocParser\Ast\Type\NullableTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;
use stdClass;

class OpenApiType {
	/**
	 * @param OpenApiType[]|null $oneOf
	 * @param OpenApiType[]|null $anyOf
	 * @param OpenApiType[]|null $allOf
	 */
	public function __construct(
		public ?string $ref = null,
		public ?string $type = null,
		public ?string $format = null,
		public bool $nullable = false,
		public bool $hasDefaultValue = false,
		public mixed $defaultValue = null,
		public ?OpenApiType $items = null,
		public ?array $properties = null,
		public ?array $oneOf = null,
		public ?array $anyOf = null,
		public ?array $allOf = null,
		public bool|OpenApiType|null $additionalProperties = null,
		public ?array $required = null,
		public ?string $description = null,
		public ?int $minLength = null,
		public ?int $maxLength = null,
		public ?int $minimum = null,
		public ?int $maximum = null,
		public ?int $minItems = null,
		public ?int $maxItems = null,
		public ?array $enum = null,
	) {
	}

	public function toArray(string $openapiVersion, bool $isParameter = false): array|stdClass {
		$asContentString = $isParameter && (
			$this->type == "object" ||
			$this->ref !== null ||
			$this->anyOf !== null ||
			$this->allOf !== null);
		if ($asContentString) {
			$values = [
				"type" => "string",
			];
			if ($this->nullable) {
				$values["nullable"] = true;
			}
			if (version_compare($openapiVersion, "3.1.0", ">=")) {
				$values["contentMediaType"] = "application/json";
				$values["contentSchema"] = $this->toArray($openapiVersion);
			}

			return $values;
		}

		$type = $this->type;
		$defaultValue = $this->defaultValue;
		$enum = $this->enum;
		if ($isParameter && $type == "boolean") {
			$type = "integer";
			$enum = [0, 1];
			if ($this->hasDefaultValue) {
				$defaultValue = $defaultValue === true ? 1 : 0;
			}
		}

		$values = [];
		if ($this->ref !== null) {
			$values["\$ref"] = $this->ref;
		}
		if ($type !== null) {
			$values["type"] = $type;
		}
		if ($this->format !== null) {
			$values["format"] = $this->format;
		}
		if ($this->nullable) {
			$values["nullable"] = true;
		}
		if ($this->hasDefaultValue && $defaultValue !== null) {
			$values["default"] = $defaultValue;
		}
		if ($enum !== null) {
			$values["enum"] = $enum;
		}
		if ($this->description !== null && $this->description !== "" && !$isParameter) {
			$values["description"] = $this->description;
		}
		if ($this->items !== null) {
			$values["items"] = $this->items->toArray($openapiVersion);
		}
		if ($this->minLength !== null) {
			$values["minLength"] = $this->minLength;
		}
		if ($this->maxLength !== null) {
			$values["maxLength"] = $this->maxLength;
		}
		if ($this->minimum !== null) {
			$values["minimum"] = $this->minimum;
		}
		if ($this->maximum !== null) {
			$values["maximum"] = $this->maximum;
		}
		if ($this->minItems !== null) {
			$values["minItems"] = $this->minItems;
		}
		if ($this->maxItems !== null) {
			$values["maxItems"] = $this->maxItems;
		}
		if ($this->required !== null) {
			$values["required"] = $this->required;
		}
		if ($this->properties !== null && count($this->properties) > 0) {
			$values["properties"] = array_combine(array_keys($this->properties),
				array_map(static fn (OpenApiType $property) => $property->toArray($openapiVersion), array_values($this->properties)),
			);
		}
		if ($this->additionalProperties !== null) {
			if ($this->additionalProperties instanceof OpenApiType) {
				$values["additionalProperties"] = $this->additionalProperties->toArray($openapiVersion);
			} else {
				$values["additionalProperties"] = $this->additionalProperties;
			}
		}
		if ($this->oneOf !== null) {
			$values["oneOf"] = array_map(fn (OpenApiType $type) => $type->toArray($openapiVersion), $this->oneOf);
		}
		if ($this->anyOf !== null) {
			$values["anyOf"] = array_map(fn (OpenApiType $type) => $type->toArray($openapiVersion), $this->anyOf);
		}
		if ($this->allOf !== null) {
			$values["allOf"] = array_map(fn (OpenApiType $type) => $type->toArray($openapiVersion), $this->allOf);
		}

		return count($values) > 0 ? $values : new stdClass();
	}

	public static function resolve(string $context, array $definitions, ParamTagValueNode|NodeAbstract|TypeNode $node): OpenApiType {
		if ($node instanceof ParamTagValueNode) {
			$type = self::resolve($context, $definitions, $node->type);
			$type->description = $node->description;
			return $type;
		}
		if ($node instanceof Name) {
			return self::resolveIdentifier($context, $definitions, $node->getLast());
		}
		if ($node instanceof IdentifierTypeNode || $node instanceof Identifier) {
			return self::resolveIdentifier($context, $definitions, $node->name);
		}

		if ($node instanceof ArrayTypeNode) {
			return new OpenApiType(type: "array", items: self::resolve($context, $definitions, $node->type));
		}
		if ($node instanceof GenericTypeNode && ($node->type->name == "array" || $node->type->name == "list") && count($node->genericTypes) == 1) {
			if ($node->genericTypes[0] instanceof IdentifierTypeNode && $node->genericTypes[0]->name == "empty") {
				return new OpenApiType(type: "array", maxItems: 0);
			}
			return new OpenApiType(type: "array", items: self::resolve($context, $definitions, $node->genericTypes[0]));
		}
		if ($node instanceof GenericTypeNode && $node->type->name === 'value-of') {
			Logger::panic($context, "'value-of' is not supported");
		}

		if ($node instanceof ArrayShapeNode) {
			$properties = [];
			$required = [];
			foreach ($node->items as $item) {
				$type = self::resolve($context, $definitions, $item->valueType);
				$name = $item->keyName instanceof ConstExprStringNode ? $item->keyName->value : $item->keyName->name;
				$properties[$name] = $type;
				if (!$item->optional) {
					$required[] = $name;
				}
			}
			return new OpenApiType(type: "object", properties: $properties, required: count($required) > 0 ? $required : null);
		}

		if ($node instanceof GenericTypeNode && $node->type->name == "array" && count($node->genericTypes) == 2 && $node->genericTypes[0] instanceof IdentifierTypeNode) {
			if ($node->genericTypes[0]->name == "array-key") {
				Logger::error($context, "Instead of 'array-key' use 'string' or 'int'");
			}
			if ($node->genericTypes[0]->name == "string" || $node->genericTypes[0]->name == "array-key") {
				return new OpenApiType(type: "object", additionalProperties: self::resolve($context, $definitions, $node->genericTypes[1]));
			}
		}

		if ($node instanceof GenericTypeNode && $node->type->name == "int" && count($node->genericTypes) == 2) {
			$min = null;
			$max = null;
			if ($node->genericTypes[0] instanceof ConstTypeNode) {
				$min = $node->genericTypes[0]->constExpr->value;
			}
			if ($node->genericTypes[1] instanceof ConstTypeNode) {
				$max = $node->genericTypes[1]->constExpr->value;
			}
			return new OpenApiType(
				type: "integer",
				format: "int64",
				minimum: $min,
				maximum: $max,
			);
		}

		if ($node instanceof NullableTypeNode || $node instanceof NullableType) {
			$type = self::resolve($context, $definitions, $node->type);
			$type->nullable = true;
			return $type;
		}

		$isUnion = $node instanceof UnionTypeNode || $node instanceof UnionType;
		$isIntersection = $node instanceof IntersectionTypeNode || $node instanceof IntersectionType;
		if ($isUnion && count($node->types) == count(array_filter($node->types, fn ($type) => $type instanceof ConstTypeNode && $type->constExpr instanceof ConstExprStringNode))) {
			$values = [];
			/** @var ConstTypeNode $type */
			foreach ($node->types as $type) {
				$values[] = $type->constExpr->value;
			}

			if (count(array_filter($values, fn (string $value) => $value == '')) > 0) {
				// Not a valid enum
				return new OpenApiType(type: "string");
			}

			return new OpenApiType(type: "string", enum: $values);
		}
		if ($isUnion && count($node->types) == count(array_filter($node->types, fn ($type) => $type instanceof ConstTypeNode && $type->constExpr instanceof ConstExprIntegerNode))) {
			$values = [];
			/** @var ConstTypeNode $type */
			foreach ($node->types as $type) {
				$values[] = (int)$type->constExpr->value;
			}

			if (count(array_filter($values, fn (string $value) => $value == '')) > 0) {
				// Not a valid enum
				return new OpenApiType(
					type: "integer",
					format: "int64",
				);
			}

			return new OpenApiType(
				type: "integer",
				format: "int64",
				enum: $values,
			);
		}

		if ($isUnion || $isIntersection) {
			$nullable = false;
			$items = [];

			foreach ($node->types as $type) {
				if (($type instanceof IdentifierTypeNode || $type instanceof Identifier) && $type->name == "null") {
					$nullable = true;
					continue;
				}
				if (($type instanceof IdentifierTypeNode || $type instanceof Identifier) && $type->name == "mixed") {
					Logger::error($context, "Unions and intersections should not contain 'mixed'");
				}
				$items[] = self::resolve($context, $definitions, $type);
			}

			$items = array_unique($items, SORT_REGULAR);
			$items = self::mergeEnums($items);

			if (count($items) == 1) {
				$type = $items[0];
				$type->nullable = $nullable;
				return $type;
			}

			if ($isIntersection) {
				return new OpenApiType(
					nullable: $nullable,
					allOf: $items,
				);
			}

			$itemTypes = array_map(static function (OpenApiType $item) {
				if ($item->type === 'integer') {
					return 'number';
				}
				return $item->type;
			}, $items);

			if (!empty(array_filter($itemTypes, static fn (?string $type) => $type === null)) || count($itemTypes) !== count(array_unique($itemTypes))) {
				return new OpenApiType(
					nullable: $nullable,
					anyOf: $items,
				);
			}

			return new OpenApiType(
				nullable: $nullable,
				oneOf: $items,
			);
		}

		if ($node instanceof ConstTypeNode && $node->constExpr instanceof ConstExprStringNode) {
			$value = $node->constExpr->value;
			if ($value == '') {
				// Not a valid enum
				return new OpenApiType(type: "string");
			}
			return new OpenApiType(
				type: "string",
				enum: [$node->constExpr->value],
			);
		}

		if ($node instanceof ConstTypeNode && $node->constExpr instanceof ConstExprIntegerNode) {
			return new OpenApiType(
				type: "integer",
				format: "int64",
				enum: [(int)$node->constExpr->value],
			);
		}

		if ($node instanceof ConstTypeNode) {
			Logger::panic($context, 'Constants are not supported');
		}

		Logger::panic($context, "Unable to resolve OpenAPI type:\n" . var_export($node, true) . "\nPlease open an issue at https://github.com/nextcloud/openapi-extractor/issues/new with the error message and a link to your source code.");
	}

	/**
	 * @param OpenApiType[] $types
	 * @return OpenApiType[]
	 */
	private static function mergeEnums(array $types): array {
		$enums = [];
		$nonEnums = [];

		foreach ($types as $type) {
			if ($type->enum !== null) {
				if (array_key_exists($type->type, $enums)) {
					$enums[$type->type] = array_merge($enums[$type->type], $type->enum);
				} else {
					$enums[$type->type] = $type->enum;
				}
			} else {
				$nonEnums[] = $type;
			}
		}

		foreach (array_map(static fn (OpenApiType $type) => $type->type, $nonEnums) as $type) {
			if (array_key_exists($type, $enums)) {
				unset($enums[$type]);
			}
		}

		return array_merge($nonEnums, array_map(fn (string $type) => new OpenApiType(type: $type, enum: $enums[$type]), array_keys($enums)));
	}

	private static function resolveIdentifier(string $context, array $definitions, string $name): OpenApiType {
		if ($name == "array") {
			Logger::error($context, "Instead of 'array' use:\n'new stdClass()' for empty objects\n'array<string, mixed>' for non-empty objects\n'array<emtpy>' for empty lists\n'array<YourTypeHere>' for lists");
		}
		if (str_starts_with($name, "\\")) {
			$name = substr($name, 1);
		}
		return match ($name) {
			"string", "non-falsy-string", "numeric-string" => new OpenApiType(type: "string"),
			"non-empty-string" => new OpenApiType(type: "string", minLength: 1),
			"int", "integer" => new OpenApiType(type: "integer", format: "int64"),
			"non-negative-int" => new OpenApiType(type: "integer", format: "int64", minimum: 0),
			"positive-int" => new OpenApiType(type: "integer", format: "int64", minimum: 1),
			"negative-int" => new OpenApiType(type: "integer", format: "int64", maximum: -1),
			"non-positive-int" => new OpenApiType(type: "integer", format: "int64", maximum: 0),
			"bool", "boolean" => new OpenApiType(type: "boolean"),
			"true" => new OpenApiType(type: "boolean", enum: [true]),
			"false" => new OpenApiType(type: "boolean", enum: [false]),
			"numeric" => new OpenApiType(type: "number"),
			// https://www.php.net/manual/en/language.types.float.php: Both float and double are always stored with double precision
			"float", "double" => new OpenApiType(type: "number", format: "double"),
			"mixed", "empty", "array" => new OpenApiType(type: "object"),
			"object", "stdClass" => new OpenApiType(type: "object", additionalProperties: true),
			"null" => new OpenApiType(nullable: true),
			default => (function () use ($context, $definitions, $name) {
				if (array_key_exists($name, $definitions)) {
					return new OpenApiType(
						ref: "#/components/schemas/" . Helpers::cleanSchemaName($name),
					);
				}
				Logger::panic($context, "Unable to resolve OpenAPI type for identifier '" . $name . "'");
			})(),
		};
	}
}
