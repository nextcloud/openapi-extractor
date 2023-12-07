<?php

namespace OpenAPIExtractor;

use Exception;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\Attribute;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Scalar\String_;
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

	static function cleanEmptyResponseArray(array|stdClass $schema): array|stdClass {
		if (key_exists("type", $schema) && $schema["type"] == "array" && key_exists("maxLength", $schema) && $schema["maxLength"] === 0) {
			return new stdClass();
		}

		return $schema;
	}

	static function classMethodHasAnnotationOrAttribute(ClassMethod|Class_|Node $node, string $annotation): bool {
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

	static function getAttributeScopes(ClassMethod|Class_|Node $node, string $annotation, string $routeName): array {
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

	static function getAttributeTagsByScope(ClassMethod|Class_|Node $node, string $annotation, string $routeName, string $defaultTag, string $defaultScope): array {
		$tags = [];

		/** @var AttributeGroup $attrGroup */
		foreach ($node->attrGroups as $attrGroup) {
			foreach ($attrGroup->attrs as $attr) {
				if ($attr->name->getLast() === $annotation) {
					if (empty($attr->args)) {
						$tags[$defaultScope] = [$defaultTag];
						continue;
					}

					$foundsTags = [];
					$foundScopeName = null;
					foreach ($attr->args as $arg) {
						$foundScopeName = self::getScopeNameFromAttributeArgument($arg, $routeName);

						if ($arg->name->name === 'tags') {
							if ($arg->value instanceof Array_) {
								foreach ($arg->value->items as $item) {
									if ($item instanceof ArrayItem) {
										if ($item->value instanceof String_) {
											$foundsTags[] = $item->value->value;
										}
									}
								}
							}
						}
					}

					if (!empty($foundsTags)) {
						$tags[$foundScopeName ?: $defaultScope] = $foundsTags;
					}
				}
			}
		}

		return $tags;
	}

	static function collectUsedRefs(array $data): array {
		$refs = [];
		if (isset($data['$ref'])) {
			$refs[] = [$data['$ref']];
		}
		if (isset($data['properties'])) {
			foreach ($data['properties'] as $property) {
				if (is_array($property)) {
					$refs[] = self::collectUsedRefs($property);
				}
			}
		}
		if (isset($data['items'])) {
			$refs[] = self::collectUsedRefs($data['items']);
		}
		return array_merge(...$refs);
	}
}
