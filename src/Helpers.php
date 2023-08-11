<?php

namespace OpenAPIExtractor;

use PhpParser\Node;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Class_;
use stdClass;

class Helpers {
	static function generateReadableAppID(string $appID): string {
		return implode("", array_map(fn(string $s) => ucfirst($s), explode("_", $appID)));
	}

	static function securitySchemes(): array {
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

	static function license(string $openapiVersion, string $license): array {
		$identifier = match ($license) {
			"agpl" => "AGPL-3.0-only",
			default => Logger::panic("license", "Unable to convert " . $license . " to SPDX identifier"),
		};
		return array_merge([
			"name" => "agpl",
		],
			version_compare($openapiVersion, "3.1.0", ">=") ? ["identifier" => $identifier] : [],
		);
	}

	static function jsonFlags(): int {
		return JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
	}

	static function cleanDocComment(string $comment): string {
		return trim(preg_replace("/\s+/", " ", $comment));
	}

	static function splitOnUppercaseFollowedByNonUppercase(string $str): array {
		return preg_split('/(?=[A-Z][^A-Z])/', $str, -1, PREG_SPLIT_NO_EMPTY);
	}

	static function mapVerb(string $verb): string {
		return match ($verb) {
			"index" => "list",
			default => $verb,
		};
	}

	/**
	 * @param array[] $schemas
	 * @return array
	 */
	static function mergeCapabilities(array $schemas): array {
		$required = [];
		$properties = [];

		foreach ($schemas as $schema) {
			foreach (array_keys($schema["properties"]) as $propertyName) {
				$properties[$propertyName] = array_merge_recursive(array_key_exists($propertyName, $properties) ? $properties[$propertyName] : [], $schema["properties"][$propertyName]);
			}
			$required = array_merge($required, $schema->required ?? []);
		}

		return array_merge([
			"type" => "object",
		],
			count($properties) > 0 ? ["properties" => $properties] : [],
			count($required) > 0 ? ["required" => $required] : [],
		);
	}

	static function wrapOCSResponse(Route $route, ControllerMethodResponse $response, array|stdClass $schema): array|stdClass {
		if ($route->isOCS && $response->className == "DataResponse") {
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

		return $schema;
	}

	static function classMethodHasAnnotationOrAttribute(ClassMethod|Class_|Node $node, string $annotation): bool {
		$doc = $node->getDocComment()?->getText();
		if (str_contains($doc, "@" . $annotation)) {
			return true;
		}

		/** @var Node\AttributeGroup $attrGroup */
		foreach ($node->attrGroups as $attrGroup) {
			foreach ($attrGroup->attrs as $attr) {
				if ($attr->name->getLast() == $annotation) {
					return true;
				}
			}
		}

		return false;
	}
}