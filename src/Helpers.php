<?php

namespace OpenAPIExtractor;

use Exception;
use PhpParser\Node;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use stdClass;

class Helpers {
	public static function generateReadableAppID(string $appID): string {
		return implode("", array_map(fn (string $s) => ucfirst($s), explode("_", $appID)));
	}

	public static function securitySchemes(): array {
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

	public static function license(string $openapiVersion, string $license): array {
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

	public static function jsonFlags(): int {
		return JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
	}

	public static function cleanDocComment(string $comment): string {
		return trim(preg_replace("/\s+/", " ", $comment));
	}

	public static function splitOnUppercaseFollowedByNonUppercase(string $str): array {
		return preg_split('/(?=[A-Z][^A-Z])/', $str, -1, PREG_SPLIT_NO_EMPTY);
	}

	public static function mapVerb(string $verb): string {
		return match ($verb) {
			"index" => "list",
			default => $verb,
		};
	}

	public static function mergeSchemas(array $schemas): mixed {
		if (!in_array(true, array_map(fn ($schema) => is_array($schema), $schemas))) {
			$results = array_values(array_unique($schemas));
			if (count($results) > 1) {
				throw new Exception("Incompatibles types: " . join(", ", $results));
			}
			return $results[0];
		}

		$keys = [];
		foreach ($schemas as $schema) {
			foreach (array_keys($schema) as $key) {
				$keys[] = $key;
			}
		}
		$result = [];
		/** @var string $key */
		foreach ($keys as $key) {
			if ($key == "required") {
				$required = [];
				foreach ($schemas as $schema) {
					if (array_key_exists("required", $schema)) {
						$required = array_merge($required, $schema["required"]);
					}
				}
				$result["required"] = array_unique($required);
				continue;
			}

			$subSchemas = [];
			foreach ($schemas as $schema) {
				if (array_key_exists($key, $schema)) {
					$subSchemas[] = $schema[$key];
				}
			}
			$result[$key] = self::mergeSchemas($subSchemas);
		}

		return $result;
	}

	public static function wrapOCSResponse(Route $route, ControllerMethodResponse $response, array|stdClass $schema): array|stdClass {
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

	public static function cleanEmptyResponseArray(array $schema): array|stdClass {
		if (key_exists("type", $schema) && $schema["type"] == "array" && key_exists("maxLength", $schema) && $schema["maxLength"] === 0) {
			return new stdClass();
		}

		return $schema;
	}

	public static function classMethodHasAnnotationOrAttribute(ClassMethod|Class_|Node $node, string $annotation): bool {
		$doc = $node->getDocComment()?->getText();
		if ($doc !== null && str_contains($doc, "@" . $annotation)) {
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

	public static function cleanSchemaName(string $name): string {
		global $readableAppID;
		return substr($name, strlen($readableAppID));
	}
}
