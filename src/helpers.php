<?php

namespace OpenAPIExtractor;

use cebe\openapi\spec\Schema;
use Exception;
use PhpParser\Node;
use PHPUnit\Event\Code\ClassMethod;

function generateReadableAppID(string $appID): string {
	return implode("", array_map(fn(string $s) => ucfirst($s), explode("_", $appID)));
}

function securitySchemes(): array {
	return [
		"basic_auth" => [
			"type" => "http",
			"scheme" => "basic",
		],
		"bearer_auth" => [
			"type" => "http",
			"scheme" => "bearer",
		],
	];
}

function jsonFlags(): int {
	return JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
}

function cleanDocComment(string $comment): string {
	return trim(preg_replace("/\s+/", " ", $comment));
}

function splitOnUppercaseFollowedByNonUppercase(string $str): array {
	return preg_split('/(?=[A-Z][^A-Z])/', $str, -1, PREG_SPLIT_NO_EMPTY);
}

function mapVerb(string $verb): string {
	return match ($verb) {
		"index" => "list",
		default => $verb,
	};
}

/**
 * @param string $context
 * @param Schema[] $schemas
 * @return Schema
 */
function mergeCapabilities(string $context, array $schemas): Schema {
	$required = [];
	$properties = [];

	foreach ($schemas as $schema) {
		foreach (array_keys($schema->properties) as $propertyName) {
			if (array_key_exists($propertyName, $properties)) {
				throw new Exception($context . ": Overlapping capabilities key '" . $propertyName . "'");
			}
			$properties[$propertyName] = $schema->properties[$propertyName];
		}
		$required = array_merge($required, $schema->required ?? []);
	}

	return new Schema(array_merge([
		"type" => "object",
	],
		count($properties) > 0 ? ["properties" => $properties] : [],
		count($required) > 0 ? ["required" => $required] : [],
	));
}

function wrapOCSResponse(Schema $schema): array {
	return [
		"type" => "object",
		"required" => [
			"ocs",
		],
		"properties" => [
			"ocs" => [
				"type" => "object",
				"required" => [
					"meta",
					"data",
				],
				"properties" => [
					"meta" => [
						"\$ref" => "#/components/schemas/OCSMeta",
					],
					"data" => $schema,
				],
			],
		],
	];
}

function classMethodHasAnnotationOrAttribute(ClassMethod|Node $classMethod, string $annotation): bool {
	$doc = $classMethod->getDocComment()?->getText();
	if (str_contains($doc, "@" . $annotation)) {
		return true;
	}

	/** @var Node\AttributeGroup $attrGroup */
	foreach ($classMethod->getAttrGroups() as $attrGroup) {
		foreach ($attrGroup->attrs as $attr) {
			if ($attr->name->getLast() == $annotation) {
				return true;
			}
		}
	}

	return false;
}
