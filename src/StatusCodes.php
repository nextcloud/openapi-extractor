<?php

namespace OpenAPIExtractor;

use Exception;
use PhpParser\Node\Arg;
use PHPStan\PhpDocParser\Ast\Type\ConstTypeNode;
use PHPStan\PhpDocParser\Ast\Type\TypeNode;
use PHPStan\PhpDocParser\Ast\Type\UnionTypeNode;

class StatusCodes {
	/**
	 * @param string $context
	 * @param ConstTypeNode|UnionTypeNode|Arg $type
	 * @return int[]
	 * @throws Exception
	 */
	public static function resolveType(string $context, ConstTypeNode|UnionTypeNode|Arg $type): array {
		$statusCodes = [];
		if ($type instanceof TypeNode) {
			$nodes = [];
			if ($type instanceof UnionTypeNode) {
				$nodes = $type->types;
			} else {
				$nodes[] = $type;
			}
			foreach ($nodes as $node) {
				$statusCodes[] = self::statusEnumToCode($context, $node->constExpr->name);
			}
		} else {
			$statusCodes[] = self::statusEnumToCode($context, $type->value->name->name);
		}

		return $statusCodes;
	}

	private static function statusEnumToCode(string $context, string $name): int {
		return match ($name) {
			"STATUS_CONTINUE" => 100,
			"STATUS_SWITCHING_PROTOCOLS" => 101,
			"STATUS_PROCESSING" => 102,
			"STATUS_OK" => 200,
			"STATUS_CREATED" => 201,
			"STATUS_ACCEPTED" => 202,
			"STATUS_NON_AUTHORATIVE_INFORMATION" => 203,
			"STATUS_NO_CONTENT" => 204,
			"STATUS_RESET_CONTENT" => 205,
			"STATUS_PARTIAL_CONTENT" => 206,
			"STATUS_MULTI_STATUS" => 207,
			"STATUS_ALREADY_REPORTED" => 208,
			"STATUS_IM_USED" => 226,
			"STATUS_MULTIPLE_CHOICES" => 300,
			"STATUS_MOVED_PERMANENTLY" => 301,
			"STATUS_FOUND" => 302,
			"STATUS_SEE_OTHER" => 303,
			"STATUS_NOT_MODIFIED" => 304,
			"STATUS_USE_PROXY" => 305,
			"STATUS_RESERVED" => 306,
			"STATUS_TEMPORARY_REDIRECT" => 307,
			"STATUS_BAD_REQUEST" => 400,
			"STATUS_UNAUTHORIZED" => 401,
			"STATUS_PAYMENT_REQUIRED" => 402,
			"STATUS_FORBIDDEN" => 403,
			"STATUS_NOT_FOUND" => 404,
			"STATUS_METHOD_NOT_ALLOWED" => 405,
			"STATUS_NOT_ACCEPTABLE" => 406,
			"STATUS_PROXY_AUTHENTICATION_REQUIRED" => 407,
			"STATUS_REQUEST_TIMEOUT" => 408,
			"STATUS_CONFLICT" => 409,
			"STATUS_GONE" => 410,
			"STATUS_LENGTH_REQUIRED" => 411,
			"STATUS_PRECONDITION_FAILED" => 412,
			"STATUS_REQUEST_ENTITY_TOO_LARGE" => 413,
			"STATUS_REQUEST_URI_TOO_LONG" => 414,
			"STATUS_UNSUPPORTED_MEDIA_TYPE" => 415,
			"STATUS_REQUEST_RANGE_NOT_SATISFIABLE" => 416,
			"STATUS_EXPECTATION_FAILED" => 417,
			"STATUS_IM_A_TEAPOT" => 418,
			"STATUS_UNPROCESSABLE_ENTITY" => 422,
			"STATUS_LOCKED" => 423,
			"STATUS_FAILED_DEPENDENCY" => 424,
			"STATUS_UPGRADE_REQUIRED" => 426,
			"STATUS_PRECONDITION_REQUIRED" => 428,
			"STATUS_TOO_MANY_REQUESTS" => 429,
			"STATUS_REQUEST_HEADER_FIELDS_TOO_LARGE" => 431,
			"STATUS_INTERNAL_SERVER_ERROR" => 500,
			"STATUS_NOT_IMPLEMENTED" => 501,
			"STATUS_BAD_GATEWAY" => 502,
			"STATUS_SERVICE_UNAVAILABLE" => 503,
			"STATUS_GATEWAY_TIMEOUT" => 504,
			"STATUS_HTTP_VERSION_NOT_SUPPORTED" => 505,
			"STATUS_VARIANT_ALSO_NEGOTIATES" => 506,
			"STATUS_INSUFFICIENT_STORAGE" => 507,
			"STATUS_LOOP_DETECTED" => 508,
			"STATUS_BANDWIDTH_LIMIT_EXCEEDED" => 509,
			"STATUS_NOT_EXTENDED" => 510,
			"STATUS_NETWORK_AUTHENTICATION_REQUIRED" => 511,
			default => Logger::panic($context, "Unknown status code '" . $name . "'"),
		};
	}

	public static function resolveException(string $context, string $name): ?int {
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
}
