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
		public bool $hasContentTypeTemplate,
		public bool $hasTypeTemplate,
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
			false,
			false,
			null,
			$binaryType,
			null,
		),
		new ResponseType(
			"DataDownloadResponse",
			true,
			false,
			null,
			$binaryType,
			null,
		),
		new ResponseType(
			"DataResponse",
			false,
			true,
			"application/json",
			$stringType,
			null,
		),
		new ResponseType(
			"DownloadResponse",
			true,
			false,
			null,
			$binaryType,
			null,
		),
		new ResponseType(
			"FileDisplayResponse",
			false,
			false,
			null,
			$binaryType,
			null,
		),
		new ResponseType(
			"JSONResponse",
			false,
			true,
			"application/json",
			$stringType,
			null,
		),
		new ResponseType(
			"NotFoundResponse",
			false,
			false,
			"text/html",
			$stringType,
			null,
		),
		new ResponseType(
			"RedirectResponse",
			false,
			false,
			null,
			null,
			["Location" => $stringType],
		),
		new ResponseType(
			"RedirectToDefaultAppResponse",
			false,
			false,
			null,
			null,
			["Location" => $stringType],
		),
		new ResponseType(
			"Response",
			false,
			false,
			null,
			null,
			null,
		),
		new ResponseType(
			"StandaloneTemplateResponse",
			false,
			false,
			"text/html",
			$stringType,
			null,
		),
		new ResponseType(
			"StreamResponse",
			false,
			false,
			null,
			$binaryType,
			null,
		),
		new ResponseType(
			"TemplateResponse",
			false,
			false,
			"text/html",
			$stringType,
			null,
		),
		new ResponseType(
			"TextPlainResponse",
			false,
			false,
			"text/plain",
			$stringType,
			null,
		),
		new ResponseType(
			"TooManyRequestsResponse",
			false,
			false,
			"text/html",
			$stringType,
			null,
		),
		new ResponseType(
			"ZipResponse",
			false,
			false,
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
	$classNameParts = explode("\\", $className);
	$className = end($classNameParts);

	if ($className == "void") {
		$responses[] = null;
	} else {
		if (count(array_filter($responseTypes, fn($responseType) => $responseType->className == $className)) == 0) {
			throw new Exception($context . ": Invalid return type '" . $obj . "'");
		}
		foreach ($responseTypes as $responseType) {
			if ($responseType->className == $className) {
				// +2 for status code and headers which are always present
				$expectedArgs = count(array_filter([$responseType->hasContentTypeTemplate, $responseType->hasTypeTemplate], fn($value) => $value)) + 2;
				if (count($args) != $expectedArgs) {
					throw new Exception($context . ": '" . $className . "' needs " . $expectedArgs . " parameters");
				}

				$statusCodes = resolveStatusCodes($context, $args[0]);
				$i = 1;

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
