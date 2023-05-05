<?php

namespace OpenAPIExtractor;

use cebe\openapi\spec\Schema;
use Exception;
use PhpParser\Node\Identifier;
use PhpParser\Node\IntersectionType;
use PhpParser\Node\Name;
use PhpParser\Node\NullableType;
use PhpParser\Node\UnionType;
use PhpParser\NodeAbstract;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstExprStringNode;
use PHPStan\PhpDocParser\Ast\ConstExpr\ConstFetchNode;
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

	public function toSchema(bool $isParameter = false): Schema {
		return new Schema($this->toArray($isParameter));
	}

	public function toArray(bool $isParameter = false): array {
		return array_merge(
			$this->ref != null ? ["\$ref" => $this->ref] : [],
			$this->type != null ? ["type" => $isParameter && $this->type == "boolean" ? "integer" : $this->type] : [],
			$this->nullable ? ["nullable" => true] : [],
			$this->format != null ? ["format" => $this->format] : [],
			$this->hasDefaultValue ? ["default" => $isParameter && $this->type == "boolean" ? $this->defaultValue === true ? 1 : 0 : $this->defaultValue] : [],
			$this->items != null ? ["items" => $this->items->toArray($isParameter)] : [],
			$this->properties != null ? ["properties" =>
				array_combine(array_keys($this->properties),
					array_map(function (OpenApiType $property) use ($isParameter) {
						return $property->toArray($isParameter);
					}, array_values($this->properties)),
				)] : [],
			$this->oneOf != null ? ["oneOf" => array_map(function (OpenApiType $type) use ($isParameter) {
				return $type->toArray($isParameter);
			}, $this->oneOf)] : [],
			$this->anyOf != null ? ["anyOf" => array_map(function (OpenApiType $type) use ($isParameter) {
				return $type->toArray($isParameter);
			}, $this->anyOf)] : [],
			$this->allOf != null ? ["allOf" => array_map(function (OpenApiType $type) use ($isParameter) {
				return $type->toArray($isParameter);
			}, $this->allOf)] : [],
			$this->additionalProperties != null ? [
				"additionalProperties" => $this->additionalProperties instanceof OpenApiType ? $this->additionalProperties->toArray() : $this->additionalProperties,
			] : [],
			$this->required != null && count($this->required) > 0 ? ["required" => $this->required] : [],
			$this->description != null && $this->description != "" && !$isParameter ? ["description" => $this->description] : [],
			$this->minLength != null && $this->minLength != 0 ? ["minLength" => $this->minLength] : [],
			$this->maxLength != null && $this->maxLength != 0 ? ["maxLength" => $this->maxLength] : [],
			$this->enum != null && count($this->enum) > 0 ? ["enum" => $this->enum] : [],
		);
	}
}


function resolveOpenApiType(string $context, array $definitions, ParamTagValueNode|NodeAbstract|TypeNode $node): OpenApiType {
	if ($node instanceof ParamTagValueNode) {
		$type = resolveOpenApiType($context, $definitions, $node->type);
		$type->description = $node->description;
		return $type;
	}
	if ($node instanceof Name) {
		return resolveIdentifier($context, $definitions, $node->getLast());
	}
	if ($node instanceof IdentifierTypeNode || $node instanceof Identifier) {
		return resolveIdentifier($context, $definitions, $node->name);
	}

	if ($node instanceof ArrayTypeNode) {
		return new OpenApiType(type: "array", items: resolveOpenApiType($context, $definitions, $node->type));
	}
	if ($node instanceof GenericTypeNode && ($node->type->name == "array" || $node->type->name == "list") && count($node->genericTypes) == 1) {
		return new OpenApiType(type: "array", items: resolveOpenApiType($context, $definitions, $node->genericTypes[0]));
	}

	if ($node instanceof ArrayShapeNode) {
		if (count($node->items) == 0) {
			throw new Exception($context . ": Instead of 'array{}' use 'array<string, mixed>' for objects");
		}
		$properties = [];
		$required = [];
		foreach ($node->items as $item) {
			$type = resolveOpenApiType($context, $definitions, $item->valueType);
			$name = $item->keyName instanceof ConstExprStringNode ? $item->keyName->value : $item->keyName->name;
			$properties[$name] = $type;
			if (!$item->optional) {
				$required[] = $name;
			}
		}
		return new OpenApiType(type: "object", properties: $properties, required: count($required) > 0 ? $required : null);
	}

	if ($node instanceof GenericTypeNode && $node->type->name == "array" && count($node->genericTypes) == 2 && $node->genericTypes[0] instanceof IdentifierTypeNode && $node->genericTypes[0]->name == "string") {
		return new OpenApiType(type: "object", additionalProperties: resolveOpenApiType($context, $definitions, $node->genericTypes[1]));
	}

	if ($node instanceof NullableTypeNode || $node instanceof NullableType) {
		$type = resolveOpenApiType($context, $definitions, $node->type);
		$type->nullable = true;
		return $type;
	}

	if (($node instanceof UnionTypeNode || $node instanceof UnionType) && count($node->types) == count(array_filter($node->types, fn($type) => $type instanceof ConstTypeNode && $type->constExpr instanceof ConstExprStringNode))) {
		$values = [];
		/** @var ConstTypeNode $type */
		foreach ($node->types as $type) {
			$values[] = $type->constExpr->value;
		}

		return new OpenApiType(type: "string", enum: $values);
	}

	if ($node instanceof UnionTypeNode || $node instanceof IntersectionTypeNode || $node instanceof UnionType || $node instanceof IntersectionType) {
		$nullable = false;
		$types = [];

		foreach ($node->types as $type) {
			if (($type instanceof IdentifierTypeNode || $type instanceof Identifier) && $type->name == "null") {
				$nullable = true;
			} else {
				$types[] = resolveOpenApiType($context, $definitions, $type);
			}
		}

		if (count($types) == 1) {
			$type = $types[0];
			$type->nullable = true;
			return $type;
		}

		return new OpenApiType(
			nullable: $nullable,
			oneOf: $node instanceof UnionTypeNode || $node instanceof UnionType ? $types : null,
			allOf: $node instanceof IntersectionTypeNode || $node instanceof IntersectionType ? $types : null,
		);
	}

	if ($node instanceof ConstTypeNode && $node->constExpr instanceof ConstFetchNode && $node->constExpr->className == "\stdClass" && $node->constExpr->name == "class") {
		return new OpenApiType(type: "object", additionalProperties: true);
	}

	throw new Exception($context . ": Unable to resolve OpenAPI type for type '" . get_class($node) . "'");
}


function resolveIdentifier(string $context, array $definitions, string $name): OpenApiType {
	if ($name == "array") {
		throw new Exception($context . ": Instead of 'array' use '\stdClass::class' for empty objects, 'array<string, mixed>' for non-empty objects and 'array<mixed>' for lists");
	}
	return match ($name) {
		"string" => new OpenApiType(type: "string"),
		"non-empty-string" => new OpenApiType(type: "string", minLength: 1),
		"int", "integer" => new OpenApiType(type: "integer", format: "int64"),
		"bool", "boolean" => new OpenApiType(type: "boolean"),
		"double" => new OpenApiType(type: "number", format: "double"),
		"float" => new OpenApiType(type: "number", format: "float"),
		"mixed", "empty" => new OpenApiType(type: "object"),
		"object" => new OpenApiType(type: "object", additionalProperties: true),
		"null" => new OpenApiType(nullable: true),
		default => (function () use ($context, $definitions, $name) {
			if (array_key_exists($name, $definitions)) {
				return new OpenApiType(
					ref: "#/components/schemas/" . cleanSchemaName($name),
				);
			}
			throw new Exception($context . ": Unable to resolve OpenAPI type for identifier '" . $name . "'");
		})(),
	};
}