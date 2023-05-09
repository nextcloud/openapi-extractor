<?php

namespace OpenAPIExtractor;

use Exception;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;

class ResponseType {
	public function __construct(
		public string $className,
		public bool $hasTypeTemplate,
		public bool $hasContentTypeTemplate,
		public bool $hasStatusCodeTemplate,
		public ?OpenApiType $defaultType,
		public ?string $defaultContentType,
		public ?int $defaultStatusCode,
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
			true,
			$binaryType,
			null,
			null,
			null,
		),
		new ResponseType(
			"DataDownloadResponse",
			false,
			true,
			false,
			$binaryType,
			null,
			200,
			null,
		),
		new ResponseType(
			"DataResponse",
			true,
			false,
			true,
			null,
			"application/json",
			null,
			null,
		),
		new ResponseType(
			"DownloadResponse",
			false,
			true,
			false,
			$binaryType,
			null,
			200,
			null,
		),
		new ResponseType(
			"FileDisplayResponse",
			false,
			false,
			true,
			$binaryType,
			null,
			null,
			null,
		),
		new ResponseType(
			"JSONResponse",
			true,
			false,
			true,
			null,
			"application/json",
			null,
			null,
		),
		new ResponseType(
			"NotFoundResponse",
			false,
			false,
			false,
			$stringType,
			"text/html",
			404,
			null,
		),
		new ResponseType(
			"RedirectResponse",
			false,
			false,
			false,
			null,
			null,
			303,
			["Location" => $stringType],
		),
		new ResponseType(
			"RedirectToDefaultAppResponse",
			false,
			false,
			false,
			null,
			null,
			303,
			["Location" => $stringType],
		),
		new ResponseType(
			"Response",
			false,
			false,
			true,
			null,
			null,
			null,
			null,
		),
		new ResponseType(
			"StandaloneTemplateResponse",
			false,
			false,
			false,
			$stringType,
			"text/html",
			200,
			null,
		),
		new ResponseType(
			"StreamResponse",
			false,
			false,
			false,
			$binaryType,
			"*/*",
			200,
			null,
		),
		new ResponseType(
			"TemplateResponse",
			false,
			false,
			false,
			$stringType,
			"text/html",
			200,
			null,
		),
		new ResponseType(
			"TextPlainResponse",
			false,
			false,
			true,
			$stringType,
			"text/plain",
			null,
			null,
		),
		new ResponseType(
			"TooManyRequestsResponse",
			false,
			false,
			false,
			$stringType,
			"text/html",
			429,
			null,
		),
		new ResponseType(
			"ZipResponse",
			false,
			false,
			false,
			$binaryType,
			null,
			200,
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
				$expectedArgs = count(array_filter([$responseType->hasTypeTemplate, $responseType->hasContentTypeTemplate, $responseType->hasStatusCodeTemplate], fn($value) => $value));
				if (count($args) != $expectedArgs) {
					throw new Exception($context . ": '" . $className . "' needs " . $expectedArgs . " parameters");
				}

				$type = null;
				$contentType = null;
				$statusCodes = null;

				$i = 0;
				if ($responseType->hasTypeTemplate) {
					$type = resolveOpenApiType($context, $definitions, $args[$i]);
					$i++;
				}
				if ($responseType->hasContentTypeTemplate) {
					$contentType = $args[$i]->constExpr->value;
					$i++;
				}
				if ($responseType->hasStatusCodeTemplate) {
					$statusCodes = resolveStatusCodes($context, $args[$i]);
				}

				$type = $responseType->defaultType ?? $type;
				$contentType = $responseType->defaultContentType ?? $contentType;
				$statusCodes = $statusCodes ?? [200];
				if ($type != null && $contentType == null) {
					$contentType = "*/*";
				}

				foreach ($statusCodes as $statusCode) {
					$statusCode = $responseType->defaultStatusCode ?? $statusCode;
					$responses[] = new ControllerMethodResponse(
						$responseType->defaultStatusCode ?? $statusCode,
						$responseType->defaultContentType ?? $contentType,
						$responseType->defaultType ?? $type,
						$responseType->defaultHeaders,
					);
				}

				break;
			}
		}
	}

	return $responses;
}
