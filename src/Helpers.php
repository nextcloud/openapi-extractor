<?php

namespace OpenAPIExtractor;

use Exception;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Scalar\String_;
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

		/** @var AttributeGroup $attrGroup */
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

	protected static function getScopeNameFromAttributeArgument(Arg $arg, string $routeName): ?string {
		if ($arg->name->name === 'scope') {
			if ($arg->value instanceof ClassConstFetch) {
				if ($arg->value->class->getLast() === 'OpenAPI') {
					return self::getScopeNameFromConst($arg->value);
				}
			} elseif ($arg->value instanceof String_) {
				return $arg->value->value;
			} else {
				Logger::panic($routeName, 'Can not interpret value of scope provided in OpenAPI(scope: …) attribute. Please use string or OpenAPI::SCOPE_* constants');
			}
		}

		return null;
	}

	protected static function getScopeNameFromConst(ClassConstFetch $scope): string {
		return match ($scope->name->name) {
			'SCOPE_DEFAULT' => 'default',
			'SCOPE_ADMINISTRATION' => 'administration',
			'SCOPE_FEDERATION' => 'federation',
			'SCOPE_IGNORE' => 'ignore',
			// Fall back for future scopes assuming we follow the pattern (cut of 'SCOPE_' and lower case)
			default => strtolower(substr($scope->name->name, 6)),
		};
	}

	public static function getAttributeScopes(ClassMethod|Class_|Node $node, string $annotation, string $routeName): array {
		$scopes = [];

		/** @var AttributeGroup $attrGroup */
		foreach ($node->attrGroups as $attrGroup) {
			foreach ($attrGroup->attrs as $attr) {
				if ($attr->name->getLast() === $annotation) {
					if (empty($attr->args)) {
						$scopes[] = 'default';
						continue;
					}

					foreach ($attr->args as $arg) {
						$scope = self::getScopeNameFromAttributeArgument($arg, $routeName);
						if ($scope !== null) {
							$scopes[] = $scope;
						}
					}
				}
			}
		}

		return $scopes;
	}

	public static function getAttributeTagsByScope(ClassMethod|Class_|Node $node, string $annotation, string $routeName, string $defaultTag, string $defaultScope): array {
		$tags = [];

		/** @var AttributeGroup $attrGroup */
		foreach ($node->attrGroups as $attrGroup) {
			foreach ($attrGroup->attrs as $attr) {
				if ($attr->name->getLast() === $annotation) {
					if (empty($attr->args)) {
						$tags[$defaultScope] = [$defaultTag];
						continue;
					}

					$foundTags = [];
					$foundScopeName = null;
					foreach ($attr->args as $arg) {
						$foundScopeName = self::getScopeNameFromAttributeArgument($arg, $routeName);

						if ($arg->name->name === 'tags') {
							if ($arg->value instanceof Array_) {
								foreach ($arg->value->items as $item) {
									if ($item instanceof ArrayItem) {
										if ($item->value instanceof String_) {
											$foundTags[] = $item->value->value;
										}
									}
								}
							}
						}
					}

					if (!empty($foundTags)) {
						$tags[$foundScopeName ?: $defaultScope] = $foundTags;
					}
				}
			}
		}

		return $tags;
	}

	public static function collectUsedRefs(array $data): array {
		$refs = [];
		if (isset($data['$ref'])) {
			$refs[] = [$data['$ref']];
		}

		foreach (['allOf', 'oneOf', 'anyOf', 'properties', 'additionalProperties'] as $group) {
			if (isset($data[$group]) && is_array($data[$group])) {
				foreach ($data[$group] as $property) {
					if (is_array($property)) {
						$refs[] = self::collectUsedRefs($property);
					}
				}
			}
		}

		if (isset($data['items'])) {
			$refs[] = self::collectUsedRefs($data['items']);
		}
		return array_merge(...$refs);
	}
}
