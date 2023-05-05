<?php

namespace OpenAPIExtractor;


use Exception;
use PhpParser\Node\Expr\New_;
use PHPStan\PhpDocParser\Ast\Type\GenericTypeNode;
use PHPStan\PhpDocParser\Ast\Type\IdentifierTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;

/**
 * @param string $context
 * @param TypeNode|New_ $type
 * @return ControllerMethodResponse[]
 * @throws Exception
 */
function resolveReturnTypes(string $context, TypeNode|New_ $type): array {
	global $responseTypes, $definitions;

	$responses = [];
	if ($type instanceof UnionTypeNode) {
		foreach ($type->types as $subType) {
			$responses = array_merge($responses, resolveReturnTypes($context, $subType));
		}
		return $responses;
	}

	if ($type instanceof IdentifierTypeNode) {
		$className = $type->name;
		$args = [];
	} else if ($type instanceof GenericTypeNode) {
		$className = $type->type->name;
		$args = $type->genericTypes;
	} else if ($type instanceof New_) {
		$className = $type->class->getLast();
		$args = $type->args;
	} else {
		throw new Exception($context . ": Failed to get class name for " . $type);
	}

	if ($className == "void") {
		$responses[] = null;
	} else if ($className == "RedirectResponse") {
		$responses[] = new ControllerMethodResponse(
			303,
			null,
			null,
			["Location" => new OpenApiType(type: "string")],
		);
	} else if ($className == "NotFoundResponse") {
		$responses[] = new ControllerMethodResponse(
			404,
			"text/html",
			new OpenApiType(type: "string"),
			null,
		);
	} else if ($className == "DataDownloadResponse") {
		if ($type instanceof New_) {
			$contentType = $args[2]->value->value;
		} else {
			$contentType = $args[0]->constExpr->value;
		}
		$responses[] = new ControllerMethodResponse(
			200,
			$contentType,
			new OpenApiType(type: "string"),
			null,
		);
	} else if (in_array($className, $responseTypes)) {
		$statusCodes = [];
		if (in_array($className, ["Response", "FileDisplayResponse", "DataDisplayResponse", "TemplateResponse", "StreamResponse"])) {
			$isRawResponse = in_array($className, ["FileDisplayResponse", "DataDisplayResponse", "StreamResponse"]);
			if ($type instanceof New_) {
				if ($isRawResponse) {
					$statusCodes = resolveStatusCodesForArg($context, $type, $args, 1);
				}
			} else {
				if (count($args) != 1) {
					throw new Exception($context . ": '" . $className . "' needs one parameter");
				}
				$statusCodes = resolveStatusCodes($context, $args[0]);
			}
			$realType = new OpenApiType(
				type: $className == "Response" ? null : "string",
				format: $isRawResponse ? "binary" : null,
			);
			if ($className == "Response") {
				$contentType = null;
			} else if ($className == "TemplateResponse") {
				$contentType = "text/html";
			} else {
				// TODO: This is really annoying, because we need to match any content type since it's not possible to know which one will be returned
				$contentType = "*/*";
			}
		} else {
			if ($type instanceof TypeNode && count($args) != 2) {
				throw new Exception($context . ": '" . $className . "' needs two parameters");
			}
			$statusCodes = resolveStatusCodesForArg($context, $type, $args, 1);
			$realType = $type instanceof New_ ? null : resolveOpenApiType($context, $definitions, $args[0]);
			$contentType = "application/json";
		}
		foreach ($statusCodes as $statusCode) {
			$responses[] = new ControllerMethodResponse(
				$statusCode,
				$contentType,
				$realType,
				null,
			);
		}
	} else {
		throw new Exception($context . ": Invalid return type '" . $type . "'");
	}

	return $responses;
}
