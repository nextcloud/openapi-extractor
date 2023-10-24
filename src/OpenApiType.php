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
		public ?array $enum = null,
	) {
	}

	public function toArray(bool $isParameter = false): array|stdClass {
		$defaultValue = null;
		if ($this->hasDefaultValue) {
			if ($isParameter && $this->type == "boolean") {
				$defaultValue = $this->defaultValue === true ? 1 : 0;
			} else if (is_array($this->defaultValue) && count($this->defaultValue) == 0 && $this->type !== "array") {
				$defaultValue = new \stdClass();
			} else {
				$defaultValue = $this->defaultValue;
			}
		}
		$values = array_merge(
			$this->ref != null ? ["\$ref" => $this->ref] : [],
			$this->type != null ? ["type" => $isParameter && $this->type == "boolean" ? "integer" : $this->type] : [],
			$this->format != null ? ["format" => $this->format] : [],
			$this->nullable ? ["nullable" => true] : [],
			$this->hasDefaultValue ? ["default" => $defaultValue] : [],
			$this->enum != null && count($this->enum) > 0 ? ["enum" => $this->enum] : [],
			$this->description != null && $this->description != "" && !$isParameter ? ["description" => $this->description] : [],
			$this->items != null ? ["items" => $this->items->toArray()] : [],
			$this->minLength !== null ? ["minLength" => $this->minLength] : [],
			$this->maxLength !== null ? ["maxLength" => $this->maxLength] : [],
			$this->required != null && count($this->required) > 0 ? ["required" => $this->required] : [],
			$this->properties != null ? ["properties" =>
				array_combine(array_keys($this->properties),
					array_map(fn(OpenApiType $property) => $property->toArray(), array_values($this->properties)),
				)] : [],
			$this->additionalProperties != null ? [
				"additionalProperties" => $this->additionalProperties instanceof OpenApiType ? $this->additionalProperties->toArray() : $this->additionalProperties,
			] : [],
			$this->oneOf != null ? ["oneOf" => array_map(fn(OpenApiType $type) => $type->toArray(), $this->oneOf)] : [],
			$this->anyOf != null ? ["anyOf" => array_map(fn(OpenApiType $type) => $type->toArray(), $this->anyOf)] : [],
			$this->allOf != null ? ["allOf" => array_map(fn(OpenApiType $type) => $type->toArray(), $this->allOf)] : [],
		);
		return count($values) > 0 ? $values : new stdClass();
	}

	static function resolve(string $context, array $definitions, ParamTagValueNode|NodeAbstract|TypeNode $node): OpenApiType {
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
				return new OpenApiType();
			}
			return new OpenApiType(type: "array", items: self::resolve($context, $definitions, $node->genericTypes[0]));
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

		if ($node instanceof NullableTypeNode || $node instanceof NullableType) {
			$type = self::resolve($context, $definitions, $node->type);
			$type->nullable = true;
			return $type;
		}

		$isUnion = $node instanceof UnionTypeNode || $node instanceof UnionType;
		$isIntersection = $node instanceof IntersectionTypeNode || $node instanceof IntersectionType;
		if ($isUnion && count($node->types) == count(array_filter($node->types, fn($type) => $type instanceof ConstTypeNode && $type->constExpr instanceof ConstExprStringNode))) {
			$values = [];
			/** @var ConstTypeNode $type */
			foreach ($node->types as $type) {
				$values[] = $type->constExpr->value;
			}

			if (count(array_filter($values, fn(string $value) => $value == '')) > 0) {
				// Not a valid enum
				return new OpenApiType(type: "string");
			}

			return new OpenApiType(type: "string", enum: $values);
		}

		if ($isUnion || $isIntersection) {
			$nullable = false;
			$items = [];

			foreach ($node->types as $type) {
				if (($type instanceof IdentifierTypeNode || $type instanceof Identifier) && $type->name == "null") {
					$nullable = true;
					continue;
				}
				$items[] = self::resolve($context, $definitions, $type);
			}

			$items = array_unique($items, SORT_REGULAR);

			if (count($items) == 1) {
				$type = $items[0];
				$type->nullable = $nullable;
				return $type;
			}

			return new OpenApiType(
				nullable: $nullable,
				oneOf: $isUnion ? $items : null,
				allOf: $isIntersection ? $items : null,
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
			);
		}

		Logger::panic($context, "Unable to resolve OpenAPI type for type '" . get_class($node) . "'");
	}

	private static function resolveIdentifier(string $context, array $definitions, string $name): OpenApiType {
		if ($name == "array") {
			Logger::error($context, "Instead of 'array' use:\n'\stdClass::class' for empty objects\n'array<string, mixed>' for non-empty objects\n'array<emtpy>' for empty lists\n'array<YourTypeHere>' for lists");
		}
		if (str_starts_with($name, "\\")) {
			$name = substr($name, 1);
		}
		return match ($name) {
			"string", "non-falsy-string", "numeric-string" => new OpenApiType(type: "string"),
			"non-empty-string" => new OpenApiType(type: "string", minLength: 1),
			"int", "integer" => new OpenApiType(type: "integer", format: "int64"),
			"bool", "boolean", "true", "false" => new OpenApiType(type: "boolean"),
			"double" => new OpenApiType(type: "number", format: "double"),
			"float" => new OpenApiType(type: "number", format: "float"),
			"mixed", "empty", "array" => new OpenApiType(type: "object"),
			"object", "stdClass" => new OpenApiType(type: "object", additionalProperties: true),
			"null" => new OpenApiType(nullable: true),
			default => (function () use ($context, $definitions, $name) {
				if (array_key_exists($name, $definitions)) {
					return new OpenApiType(
						ref: "#/components/schemas/" . cleanSchemaName($name),
					);
				}
				Logger::panic($context, "Unable to resolve OpenAPI type for identifier '" . $name . "'");
			})(),
		};
	}
}
