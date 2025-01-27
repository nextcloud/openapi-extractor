<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

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
		public string $context,
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

	public function toArray(bool $isParameter = false): array|stdClass {
		if ($isParameter) {
			if ($this->type === 'boolean') {
				return (new OpenApiType(
					context: $this->context,
					type: 'integer',
					nullable: $this->nullable,
					hasDefaultValue: $this->hasDefaultValue,
					defaultValue: $this->hasDefaultValue ? ($this->defaultValue === true ? 1 : 0) : (null),
					description: $this->description,
					enum: [0, 1],
				))->toArray($isParameter);
			}

			if ($this->type === 'object' || $this->ref !== null || $this->anyOf !== null || $this->allOf !== null) {
				Logger::warning($this->context, 'Complex types can not be part of query or URL parameters. Falling back to string due to undefined serialization!');

				return (new OpenApiType(
					context: $this->context,
					type: 'string',
					nullable: $this->nullable,
					description: $this->description,
				))->toArray($isParameter);
			}
		}

		$values = [];
		if ($this->ref !== null) {
			$values['$ref'] = $this->ref;
		}
		if ($this->type !== null) {
			$values['type'] = $this->type;
		}
		if ($this->format !== null) {
			$values['format'] = $this->format;
		}
		if ($this->nullable) {
			$values['nullable'] = true;
		}
		if ($this->hasDefaultValue && $this->defaultValue !== null) {
			$values['default'] = $this->type === 'object' && empty($this->defaultValue) ? new stdClass() : $this->defaultValue;
		}
		if ($this->enum !== null) {
			$values['enum'] = $this->enum;
		}
		if ($this->description !== null && $this->description !== '' && !$isParameter) {
			$values['description'] = Helpers::cleanDocComment($this->description);
		}
		if ($this->items instanceof \OpenAPIExtractor\OpenApiType) {
			$values['items'] = $this->items->toArray();
		}
		if ($this->minLength !== null) {
			$values['minLength'] = $this->minLength;
		}
		if ($this->maxLength !== null) {
			$values['maxLength'] = $this->maxLength;
		}
		if ($this->minimum !== null) {
			$values['minimum'] = $this->minimum;
		}
		if ($this->maximum !== null) {
			$values['maximum'] = $this->maximum;
		}
		if ($this->minItems !== null) {
			$values['minItems'] = $this->minItems;
		}
		if ($this->maxItems !== null) {
			$values['maxItems'] = $this->maxItems;
		}
		if ($this->required !== null) {
			$values['required'] = $this->required;
		}
		if ($this->properties !== null && $this->properties !== []) {
			$values['properties'] = array_combine(array_keys($this->properties),
				array_map(static fn (OpenApiType $property): array|\stdClass => $property->toArray(), array_values($this->properties)),
			);
		}
		if ($this->additionalProperties !== null) {
			if ($this->additionalProperties instanceof OpenApiType) {
				$values['additionalProperties'] = $this->additionalProperties->toArray();
			} else {
				$values['additionalProperties'] = $this->additionalProperties;
			}
		}
		if ($this->oneOf !== null) {
			$values['oneOf'] = array_map(fn (OpenApiType $type): array|\stdClass => $type->toArray(), $this->oneOf);
		}
		if ($this->anyOf !== null) {
			$values['anyOf'] = array_map(fn (OpenApiType $type): array|\stdClass => $type->toArray(), $this->anyOf);
		}
		if ($this->allOf !== null) {
			$values['allOf'] = array_map(fn (OpenApiType $type): array|\stdClass => $type->toArray(), $this->allOf);
		}

		return $values !== [] ? $values : new stdClass();
	}

	public static function resolve(string $context, array $definitions, ParamTagValueNode|NodeAbstract|TypeNode $node, ?bool $isResponseDefinition = false): OpenApiType {
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
			Logger::error($context, "The 'TYPE[]' syntax for arrays is forbidden due to ambiguities. Use 'list<TYPE>' for JSON arrays or 'array<string, TYPE>' for JSON objects instead.");

			return new OpenApiType(
				context: $context,
				type: 'array',
				items: self::resolve($context . ': items', $definitions, $node->type),
			);
		}
		if ($node instanceof GenericTypeNode && ($node->type->name === 'array' || $node->type->name === 'list' || $node->type->name === 'non-empty-list') && count($node->genericTypes) === 1) {
			if ($node->type->name === 'array') {
				Logger::error($context, "The 'array<TYPE>' syntax for arrays is forbidden due to ambiguities. Use 'list<TYPE>' for JSON arrays or 'array<string, TYPE>' for JSON objects instead.");
			}

			if ($node->genericTypes[0] instanceof IdentifierTypeNode && $node->genericTypes[0]->name === 'empty') {
				return new OpenApiType(
					context: $context,
					type: 'array',
					maxItems: 0,
				);
			}
			return new OpenApiType(
				context: $context,
				type: 'array',
				items: self::resolve($context, $definitions, $node->genericTypes[0]),
				minItems: $node->type->name === 'non-empty-list' ? 1 : null,
			);
		}
		if ($node instanceof GenericTypeNode && $node->type->name === 'value-of') {
			Logger::panic($context, "'value-of' is not supported");
		}

		if ($node instanceof ArrayShapeNode) {
			$properties = [];
			$required = [];
			foreach ($node->items as $item) {
				$name = $item->keyName instanceof ConstExprStringNode ? $item->keyName->value : $item->keyName->name;
				$type = self::resolve($context . ': ' . $name, $definitions, $item->valueType);
				$properties[$name] = $type;
				if (!$item->optional) {
					$required[] = $name;
				} elseif ($type->nullable) {
					Logger::warning($context, 'Property "' . $name . '" is both nullable and not required. Please consider only using one of these at once.');
				}
			}
			return new OpenApiType(
				context: $context,
				type: 'object',
				properties: $properties,
				required: $required !== [] ? $required : null,
			);
		}

		if ($node instanceof GenericTypeNode && $node->type->name === 'array' && count($node->genericTypes) === 2 && $node->genericTypes[0] instanceof IdentifierTypeNode) {
			$allowedTypes = ['string', 'lowercase-string', 'non-empty-string', 'non-empty-lowercase-string'];
			if (in_array($node->genericTypes[0]->name, $allowedTypes, true)) {
				return new OpenApiType(
					context: $context,
					type: 'object',
					additionalProperties: self::resolve($context . ': additionalProperties', $definitions, $node->genericTypes[1]),
				);
			}

			Logger::panic($context, "JSON objects can only be indexed by '" . implode("', '", $allowedTypes) . "' but got '" . $node->genericTypes[0]->name . "'");
		}

		if ($node instanceof GenericTypeNode && $node->type->name == 'int' && count($node->genericTypes) == 2) {
			$min = null;
			$max = null;
			if ($node->genericTypes[0] instanceof ConstTypeNode) {
				$min = $node->genericTypes[0]->constExpr->value;
			}
			if ($node->genericTypes[1] instanceof ConstTypeNode) {
				$max = $node->genericTypes[1]->constExpr->value;
			}
			return new OpenApiType(
				context: $context,
				type: 'integer',
				format: 'int64',
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
		if ($isUnion && count($node->types) === count(array_filter($node->types, fn ($type): bool => $type instanceof ConstTypeNode && $type->constExpr instanceof ConstExprStringNode))) {
			$values = [];
			/** @var ConstTypeNode $type */
			foreach ($node->types as $type) {
				$values[] = $type->constExpr->value;
			}

			if (array_filter($values, fn (string $value): bool => $value === '') !== []) {
				// Not a valid enum
				return new OpenApiType(
					context: $context,
					type: 'string',
				);
			}

			if (!$isResponseDefinition) {
				Logger::warning($context, 'Consider using a Response definition for this enum to improve readability and reusability.');
			}

			return new OpenApiType(
				context: $context,
				type: 'string',
				enum: $values,
			);
		}
		if ($isUnion && count($node->types) === count(array_filter($node->types, fn ($type): bool => $type instanceof ConstTypeNode && $type->constExpr instanceof ConstExprIntegerNode))) {
			$values = [];
			/** @var ConstTypeNode $type */
			foreach ($node->types as $type) {
				$values[] = (int)$type->constExpr->value;
			}

			if (array_filter($values, fn (string $value): bool => $value === '') !== []) {
				// Not a valid enum
				return new OpenApiType(
					context: $context,
					type: 'integer',
					format: 'int64',
				);
			}

			if (!$isResponseDefinition) {
				Logger::warning($context, 'Consider using a Response definition for this enum to improve readability and reusability.');
			}

			return new OpenApiType(
				context: $context,
				type: 'integer',
				format: 'int64',
				enum: $values,
			);
		}

		if ($isUnion || $isIntersection) {
			$nullable = false;
			$items = [];

			foreach ($node->types as $type) {
				if (($type instanceof IdentifierTypeNode || $type instanceof Identifier) && $type->name == 'null') {
					$nullable = true;
					continue;
				}
				if (($type instanceof IdentifierTypeNode || $type instanceof Identifier) && $type->name == 'mixed') {
					Logger::error($context, "Unions and intersections should not contain 'mixed'");
				}
				$items[] = self::resolve($context, $definitions, $type);
			}

			$items = array_unique($items, SORT_REGULAR);
			$items = self::mergeEnums($context, $items);

			if (count($items) == 1) {
				$type = $items[0];
				$type->nullable = $nullable;
				return $type;
			}

			if ($isIntersection) {
				return new OpenApiType(
					context: $context,
					nullable: $nullable,
					allOf: $items,
				);
			}

			$itemTypes = array_map(static function (OpenApiType $item): ?string {
				if ($item->type === 'integer') {
					return 'number';
				}
				return $item->type;
			}, $items);

			if (array_filter($itemTypes, static fn (?string $type): bool => $type === null) !== [] || count($itemTypes) !== count(array_unique($itemTypes))) {
				return new OpenApiType(
					context: $context,
					nullable: $nullable,
					anyOf: $items,
				);
			}

			return new OpenApiType(
				context: $context,
				nullable: $nullable,
				oneOf: $items,
			);
		}

		if ($node instanceof ConstTypeNode && $node->constExpr instanceof ConstExprStringNode) {
			$value = $node->constExpr->value;
			if ($value == '') {
				// Not a valid enum
				return new OpenApiType(
					context: $context,
					type: 'string'
				);
			}
			return new OpenApiType(
				context: $context,
				type: 'string',
				enum: [$node->constExpr->value],
			);
		}

		if ($node instanceof ConstTypeNode && $node->constExpr instanceof ConstExprIntegerNode) {
			return new OpenApiType(
				context: $context,
				type: 'integer',
				format: 'int64',
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
	private static function mergeEnums(string $context, array $types): array {
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

		foreach (array_map(static fn (OpenApiType $type): ?string => $type->type, $nonEnums) as $type) {
			if (array_key_exists($type, $enums)) {
				unset($enums[$type]);
			}
		}

		return array_merge($nonEnums, array_map(static fn (string $type): \OpenAPIExtractor\OpenApiType => new OpenApiType(
			context: $context,
			type: $type,
			enum: $enums[$type],
		), array_keys($enums)));
	}

	private static function resolveIdentifier(string $context, array $definitions, string $name): OpenApiType {
		if ($name === 'array') {
			Logger::error($context, "Instead of 'array' use:\n'new stdClass()' for empty objects\n'array<string, mixed>' for non-empty objects\n'array<emtpy>' for empty lists\n'array<YourTypeHere>' for lists");
		}
		if (str_starts_with($name, '\\')) {
			$name = substr($name, 1);
		}
		return match ($name) {
			'string', 'non-falsy-string', 'numeric-string' => new OpenApiType(context: $context, type: 'string'),
			'non-empty-string' => new OpenApiType(context: $context, type: 'string', minLength: 1),
			'int', 'integer' => new OpenApiType(context: $context, type: 'integer', format: 'int64'),
			'non-negative-int' => new OpenApiType(context: $context, type: 'integer', format: 'int64', minimum: 0),
			'positive-int' => new OpenApiType(context: $context, type: 'integer', format: 'int64', minimum: 1),
			'negative-int' => new OpenApiType(context: $context, type: 'integer', format: 'int64', maximum: -1),
			'non-positive-int' => new OpenApiType(context: $context, type: 'integer', format: 'int64', maximum: 0),
			'bool', 'boolean' => new OpenApiType(context: $context, type: 'boolean'),
			'true' => new OpenApiType(context: $context, type: 'boolean', enum: [true]),
			'false' => new OpenApiType(context: $context, type: 'boolean', enum: [false]),
			'numeric' => new OpenApiType(context: $context, type: 'number'),
			// https://www.php.net/manual/en/language.types.float.php: Both float and double are always stored with double precision
			'float', 'double' => new OpenApiType(context: $context, type: 'number', format: 'double'),
			'mixed', 'empty', 'array' => new OpenApiType(context: $context, type: 'object'),
			'object', 'stdClass' => new OpenApiType(context: $context, type: 'object', additionalProperties: true),
			'null' => new OpenApiType(context: $context, nullable: true),
			default => (function () use ($context, $definitions, $name) {
				if (array_key_exists($name, $definitions)) {
					return new OpenApiType(
						context: $context,
						ref: '#/components/schemas/' . Helpers::cleanSchemaName($name),
					);
				}
				Logger::panic($context, "Unable to resolve OpenAPI type for identifier '" . $name . "'");
			})(),
		};
	}
}
