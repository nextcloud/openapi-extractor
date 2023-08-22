<?php

namespace OpenAPIExtractor;

use Exception;
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

	static function mergeSchemas(array $schemas) {
		if (!in_array(true, array_map(fn($schema) => is_array($schema), $schemas))) {
			$results = array_values(array_unique($schemas));
			if (count($results) > 1) {
				throw new Exception("Incompatibles types: " . join(", ", $results));
			}
			return $results[0];
		}

		$keys = [];
		foreach ($schemas as $schema) {
			foreach ($schema as $key => $value) {
				$keys[] = $key;
			}
		}
		$result = [];
		foreach ($keys as $key) {
			if ($key == "required") {
				$result["required"] = array_unique(array_merge(...array_map(function (array $schema) {
					if (array_key_exists("required", $schema)) {
						return $schema["required"];
					}
					return [];
				}, $schemas)));
				continue;
			}

			$result[$key] = self::mergeSchemas(array_filter(array_map(function (array $schema) use ($key) {
				if (array_key_exists($key, $schema)) {
					return $schema[$key];
				}
				return null;
			}, $schemas), fn($schema) => $schema != null));
		}

		return $result;
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
