<?php

namespace OpenAPIExtractor;

use Exception;
use PhpParser\Node;
use PhpParser\Node\Arg;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use stdClass;

class Helpers {
	public const OPENAPI_ATTRIBUTE_CLASSNAME = 'OpenAPI';

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

		$out = [
			"name" => "agpl",
		];
		if (version_compare($openapiVersion, "3.1.0", ">=")) {
			$out["identifier"] = $identifier;
		}
		return $out;
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
		if ($route->isOCS
			&& ($response->className === 'DataResponse'
				|| (str_starts_with($response->className, 'OCS') && str_ends_with($response->className, 'Exception')))) {
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
		if (array_key_exists('type', $schema) && $schema['type'] === 'array' && array_key_exists('maxItems', $schema) && $schema['maxItems'] === 0) {
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

	protected static function getScopeNameFromAttributeArgument(Arg $arg, int $key, string $routeName): ?string {
		if ($arg->name?->name === 'scope' || ($arg->name === null && $key === 0)) {
			if ($arg->value instanceof ClassConstFetch) {
				if ($arg->value->class->getLast() === self::OPENAPI_ATTRIBUTE_CLASSNAME) {
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

	public static function getOpenAPIAttributeScopes(ClassMethod|Class_|Node $node, string $routeName): array {
		$scopes = [];

		/** @var AttributeGroup $attrGroup */
		foreach ($node->attrGroups as $attrGroup) {
			foreach ($attrGroup->attrs as $attr) {
				if ($attr->name->getLast() === self::OPENAPI_ATTRIBUTE_CLASSNAME) {
					if (empty($attr->args)) {
						$scopes[] = 'default';
						continue;
					}

					foreach ($attr->args as $key => $arg) {
						$scope = self::getScopeNameFromAttributeArgument($arg, (int) $key, $routeName);
						if ($scope !== null) {
							$scopes[] = $scope;
						}
					}
				}
			}
		}

		return $scopes;
	}

	public static function getOpenAPIAttributeTagsByScope(ClassMethod|Class_|Node $node, string $routeName, string $defaultTag, string $defaultScope): array {
		$tags = [];

		/** @var AttributeGroup $attrGroup */
		foreach ($node->attrGroups as $attrGroup) {
			foreach ($attrGroup->attrs as $attr) {
				if ($attr->name->getLast() === self::OPENAPI_ATTRIBUTE_CLASSNAME) {
					if (empty($attr->args)) {
						$tags[$defaultScope] = [$defaultTag];
						continue;
					}

					$foundTags = [];
					$foundScopeName = null;
					foreach ($attr->args as $key => $arg) {
						$foundScopeName = self::getScopeNameFromAttributeArgument($arg, (int) $key, $routeName);

						if ($arg->name?->name !== 'tags' && ($arg->name !== null || $key !== 1)) {
							continue;
						}

						if (!$arg->value instanceof Array_) {
							Logger::panic($routeName, 'Can not read value of tags provided in OpenAPI attribute for route ' . $routeName);
						}

						foreach ($arg->value->items as $item) {
							if ($item?->value instanceof String_) {
								$tag = $item->value->value;
								$pattern = "/^[0-9a-zA-Z_-]+$/";
								if (!preg_match($pattern, $tag)) {
									Logger::error($routeName, 'Tag "' . $tag . '" has to match pattern "' . $pattern . '"');
								}
								$foundTags[] = $tag;
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
		array_walk_recursive($data, function ($value, $key) use (&$refs) {
			if ($key === '$ref') {
				$refs[] = $value;
			}
		});
		return $refs;
	}

	/**
	 * @throws LoggerException
	 * @throws UnsupportedExprException
	 */
	public static function exprToValue(string $context, Expr $expr): mixed {
		if ($expr instanceof ConstFetch) {
			$value = $expr->name->getLast();
			return match ($value) {
				'null' => null,
				'true' => true,
				'false' => false,
				default => Logger::panic($context, "Unable to evaluate constant value '$value'"),
			};
		}
		if ($expr instanceof String_) {
			return $expr->value;
		}
		if ($expr instanceof LNumber) {
			return intval($expr->value);
		}
		if ($expr instanceof UnaryMinus) {
			return -self::exprToValue($context, $expr->expr);
		}
		if ($expr instanceof Array_) {
			$array = [];
			foreach ($expr->items as $item) {
				try {
					$value = self::exprToValue($context, $item->value);
					if ($item->key !== null) {
						$array[self::exprToValue($context, $item->key)] = $value;
					} else {
						$array[] = $value;
					}
				} catch (UnsupportedExprException $e) {
					Logger::debug($context, $e);
				}
			}
			return $array;
		}

		throw new UnsupportedExprException($expr, $context);
	}
}
