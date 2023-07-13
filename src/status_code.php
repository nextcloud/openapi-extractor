<?php

namespace OpenAPIExtractor;


use Exception;
use PhpParser\Node\Arg;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;

/**
 * @param string $context
 * @param ConstTypeNode|UnionTypeNode|Arg $type
 * @return int[]
 * @throws Exception
 */
function resolveStatusCodes(string $context, ConstTypeNode|UnionTypeNode|Arg $type): array {
	$statusCodes = [];
	if ($type instanceof TypeNode) {
		$nodes = [];
		if ($type instanceof UnionTypeNode) {
			$nodes = $type->types;
		} else {
			$nodes[] = $type;
		}
		foreach ($nodes as $node) {
			$statusCodes[] = statusEnumToCode($context, $node->constExpr->name);
		}
	} else {
		$statusCodes[] = statusEnumToCode($context, $type->value->name->name);
	}

	return $statusCodes;
}

function statusEnumToCode(string $context, string $name): int {
	return match ($name) {
		"STATUS_OK" => 200,
		"STATUS_CREATED" => 201,
		"STATUS_ACCEPTED" => 202,
		"STATUS_NO_CONTENT" => 204,
		"STATUS_SEE_OTHER" => 303,
		"STATUS_NOT_MODIFIED" => 304,
		"STATUS_BAD_REQUEST" => 400,
		"STATUS_UNAUTHORIZED" => 401,
		"STATUS_FORBIDDEN" => 403,
		"STATUS_NOT_FOUND" => 404,
		"STATUS_METHOD_NOT_ALLOWED" => 405,
		"STATUS_NOT_ACCEPTABLE" => 406,
		"STATUS_CONFLICT" => 409,
		"STATUS_PRECONDITION_FAILED" => 412,
		"STATUS_REQUEST_ENTITY_TOO_LARGE" => 413,
		"STATUS_LOCKED" => 423,
		"STATUS_INTERNAL_SERVER_ERROR" => 500,
		"STATUS_NOT_IMPLEMENTED" => 501,
		"STATUS_INSUFFICIENT_STORAGE" => 507,
		default => Logger::panic($context, "Unknown status code '" . $name . "'"),
	};
}

function exceptionToStatusCode(string $context, string $name): ?int {
	if (!str_starts_with($name, "OCS")) {
		return 500;
	}
	return match ($name) {
		"OCSException" => null,
		"OCSBadRequestException" => 400,
		"OCSForbiddenException" => 403,
		"OCSNotFoundException" => 404,
		default => Logger::panic($context, "Unknown exception '" . $name . "'"),
	};
}
