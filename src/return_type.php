<?php

namespace OpenAPIExtractor;

use Exception;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;

class ResponseType {
	public function __construct(
		public string $className,
		public bool $hasStatusCodeTemplate,
		public bool $hasContentTypeTemplate,
		public bool $hasTypeTemplate,
		public ?int $defaultStatusCode,
		public ?string $defaultContentType,
		public ?OpenApiType $defaultType,
		public ?array $defaultHeaders,
	) {
	}
}

/** @return ResponseType[] */
function getResponseTypes(): array {
	$stringType = new OpenApiType(type: "string");
	$binaryType = new OpenApiType(type: "string", format: "binary");
	return [
		new ResponseType(
			"DataDisplayResponse",
			true,
			false,
			false,
			null,
			null,
			$binaryType,
			null,
		),
		new ResponseType(
			"DataDownloadResponse",
			true,
			true,
			false,
			null,
			null,
			$binaryType,
			null,
		),
		new ResponseType(
			"DataResponse",
			true,
			false,
			true,
			null,
			"application/json",
			$stringType,
			null,
		),
		new ResponseType(
			"DownloadResponse",
			true,
			true,
			false,
			null,
			null,
			$binaryType,
			null,
		),
		new ResponseType(
			"FileDisplayResponse",
			true,
			false,
			false,
			null,
			null,
			$binaryType,
			null,
		),
		new ResponseType(
			"JSONResponse",
			true,
			false,
			true,
			null,
			"application/json",
			$stringType,
			null,
		),
		new ResponseType(
			"NotFoundResponse",
			false,
			false,
			false,
			404,
			"text/html",
			$stringType,
			null,
		),
		new ResponseType(
			"RedirectResponse",
			false,
			false,
			false,
			303,
			null,
			null,
			["Location" => $stringType],
		),
		new ResponseType(
			"RedirectToDefaultAppResponse",
			false,
			false,
			false,
			303,
			null,
			null,
			["Location" => $stringType],
		),
		new ResponseType(
			"Response",
			true,
			false,
			false,
			null,
			null,
			null,
			null,
		),
		new ResponseType(
			"StandaloneTemplateResponse",
			true,
			false,
			false,
			null,
			"text/html",
			$stringType,
			null,
		),
		new ResponseType(
			"StreamResponse",
			true,
			false,
			false,
			null,
			null,
			$binaryType,
			null,
		),
		new ResponseType(
			"TemplateResponse",
			true,
			false,
			false,
			null,
			"text/html",
			$stringType,
			null,
		),
		new ResponseType(
			"TextPlainResponse",
			true,
			false,
			false,
			null,
			"text/plain",
			$stringType,
			null,
		),
		new ResponseType(
			"TooManyRequestsResponse",
			false,
			false,
			false,
			429,
			"text/html",
			$stringType,
			null,
		),
		new ResponseType(
			"ZipResponse",
			true,
			false,
			false,
			null,
			null,
			$binaryType,
			null,
		),
	];
}

/**
 * @param string $context
 * @param TypeNode $obj
 * @return ControllerMethodResponse[]
 * @throws Exception
 */
function resolveReturnTypes(string $context, TypeNode $obj): array {
	global $definitions;
	$responseTypes = getResponseTypes();

	$responses = [];
	if ($obj instanceof UnionTypeNode) {
		foreach ($obj->types as $subType) {
			$responses = array_merge($responses, resolveReturnTypes($context, $subType));
		}
		return $responses;
	}

	if ($obj instanceof IdentifierTypeNode) {
		$className = $obj->name;
		$args = [];
	} else if ($obj instanceof GenericTypeNode) {
		$className = $obj->type->name;
		$args = $obj->genericTypes;
	} else {
		throw new Exception($context . ": Failed to get class name for " . $obj);
	}

	if ($className == "void") {
		$responses[] = null;
	} else {
		if (count(array_filter($responseTypes, fn($responseType) => $responseType->className == $className)) == 0) {
			throw new Exception($context . ": Invalid return type '" . $obj . "'");
		}
		foreach ($responseTypes as $responseType) {
			if ($responseType->className == $className) {
				$expectedArgs = count(array_filter([$responseType->hasStatusCodeTemplate, $responseType->hasContentTypeTemplate, $responseType->hasTypeTemplate, true /* Headers */], fn($value) => $value));
				if (count($args) != $expectedArgs) {
					throw new Exception($context . ": '" . $className . "' needs " . $expectedArgs . " parameters");
				}

				$i = 0;

				if ($responseType->hasStatusCodeTemplate) {
					$statusCodes = resolveStatusCodes($context, $args[$i]);
					$i++;
				} else {
					$statusCodes = [$responseType->defaultStatusCode != null ? $responseType->defaultStatusCode : 200];
				}

				if ($responseType->hasContentTypeTemplate) {
					if ($args[$i] instanceof ConstTypeNode) {
						$contentTypes = [$args[$i]->constExpr->value];
					} else if ($args[$i] instanceof IdentifierTypeNode && $args[$i]->name == "string") {
						$contentTypes = ["*/*"];
					} else if ($args[$i] instanceof UnionTypeNode) {
						$contentTypes = array_map(fn($arg) => $arg->constExpr->value, $args[$i]->types);
					} else {
						throw new Exception($context . ": Unable to parse content type from " . get_class($args[$i]));
					}
					$i++;
				} else {
					$contentTypes = $responseType->defaultContentType != null ? [$responseType->defaultContentType] : [];
				}

				if ($responseType->hasTypeTemplate) {
					$type = resolveOpenApiType($context, $definitions, $args[$i]);
					$i++;
				} else {
					$type = $responseType->defaultType;
				}

				$headers = resolveOpenApiType($context, $definitions, $args[$i])->properties ?? [];
				if ($responseType->defaultHeaders != null) {
					$headers = array_merge($responseType->defaultHeaders, $headers);
				}

				if (array_key_exists("Content-Type", $headers)) {
					/** @var OpenApiType $value */
					$values = $headers["Content-Type"];
					if ($values->oneOf != null) {
						$values = $values->oneOf;
					} else {
						$values = [$values];
					}

					foreach ($values as $value) {
						if ($value->type == "string" && $value->enum != null) {
							$contentTypes = array_merge($contentTypes, $value->enum);
						}
					}

					// Content-Type is an illegal response header
					unset($headers["Content-Type"]);
				}

				$contentTypes = $contentTypes !== [] ? $contentTypes : [$type != null ? "*/*" : null];

				foreach ($statusCodes as $statusCode) {
					foreach ($contentTypes as $contentType) {
						$responses[] = new ControllerMethodResponse(
							$className,
							$statusCode,
							$contentType,
							$type,
							$headers,
						);
					}
				}

				break;
			}
		}
	}

	return $responses;
}
