<?php

namespace OpenAPIExtractor;

use Exception;
use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ThrowsTagValueNode;
use PHPStan\PhpDocParser\Parser\TokenIterator;

class ControllerMethod {
	/**
	 * @param ControllerMethodParameter[] $parameters
	 * @param ControllerMethodResponse[] $responses
	 * @param OpenApiType[] $returns
	 * @param array<int, string> $responseDescription
	 * @param string[] $description
	 */
	public function __construct(public array $parameters, public array $responses, public array $returns, public array $responseDescription, public array $description, public ?string $summary, public bool $isDeprecated) {
	}
}

class ControllerMethodResponse {
	/**
	 * @param array<string, OpenApiType>|null $headers
	 */
	public function __construct(
		public string $className,
		public int $statusCode,
		public ?string $contentType = null,
		public ?OpenApiType $type = null,
		public ?array $headers = null,
	) {
	}

}

class ControllerMethodParameter {
	public OpenApiType $type;

	public function __construct(string $context, array $definitions, public string $name, public Param $methodParameter, public ?ParamTagValueNode $docParameter) {
		if ($docParameter != null) {
			$this->type = resolveOpenApiType($context, $definitions, $docParameter);
		} else {
			$this->type = resolveOpenApiType($context, $definitions, $methodParameter->type);
		}
		if ($methodParameter->default != null) {
			$this->type->hasDefaultValue = true;
			$this->type->defaultValue = exprToValue($context, $methodParameter->default);
		}
	}
}

function extractControllerMethod(string $context, array $definitions, ClassMethod $method, bool $isAdmin, bool $isDeprecated): ControllerMethod {
	global $phpDocParser, $lexer, $allowMissingDocs;

	$parameters = [];
	$responses = [];
	$responseDescriptions = [];
	$returns = [];

	$methodDescription = [];
	$methodSummary = null;
	$methodParameters = $method->getParams();
	$docParameters = [];

	$doc = $method->getDocComment()?->getText();
	if ($doc != null) {
		$docNodes = $phpDocParser->parse(new TokenIterator($lexer->tokenize($doc)))->children;

		foreach ($docNodes as $docNode) {
			if ($docNode instanceof PhpDocTextNode) {
				$block = cleanDocComment($docNode->text);
				if ($block == "") {
					continue;
				}
				$pattern = "/([0-9]{3}): /";
				if (preg_match($pattern, $block)) {
					$parts = preg_split($pattern, $block, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
					for ($i = 0; $i < count($parts); $i += 2) {
						$statusCode = intval($parts[$i]);
						$responseDescriptions[$statusCode] = trim($parts[$i + 1]);
					}
				} else {
					$methodDescription[] = $block;
				}
			}
		}

		foreach ($docNodes as $docNode) {
			if ($docNode instanceof PhpDocTagNode) {
				if ($docNode->value instanceof ParamTagValueNode) {
					$docParameters[] = $docNode->value;
				}

				if ($docNode->value instanceof ReturnTagValueNode) {
					$type = $docNode->value->type;

					$responses = array_merge($responses, resolveReturnTypes($context, $type));
				}

				if ($docNode->value instanceof ThrowsTagValueNode) {
					$type = $docNode->value->type;
					$statusCode = exceptionToStatusCode($context, $type);
					if ($statusCode != null) {
						if (!$allowMissingDocs && $docNode->value->description == "" && $statusCode < 500) {
							throw new Exception($context . ": Missing description for exception '" . $type . "'");
						}
						$responseDescriptions[$statusCode] = $docNode->value->description;
						$responses[] = new ControllerMethodResponse($docNode->value->type, $statusCode, "text/plain", new OpenApiType(type: "string"), null);
					}
				}
			}
		}
	}

	if (!$allowMissingDocs && count($responses) > 1) {
		foreach (array_unique(array_map(fn(ControllerMethodResponse $response) => $response->statusCode, array_filter($responses, fn(?ControllerMethodResponse $response) => $response != null))) as $statusCode) {
			if ($statusCode < 500 && (!array_key_exists($statusCode, $responseDescriptions) || $responseDescriptions[$statusCode] == "")) {
				throw new Exception($context . ": Missing description for status code " . $statusCode);
			}
		}
	}

	foreach ($methodParameters as $methodParameter) {
		$param = null;
		$methodParameterName = $methodParameter->var->name;

		foreach ($docParameters as $docParameter) {
			$docParameterName = substr($docParameter->parameterName, 1);

			if ($docParameterName == $methodParameterName) {
				$param = new ControllerMethodParameter($context, $definitions, $methodParameterName, $methodParameter, $docParameter);
				break;
			}
		}

		if ($param == null) {
			if ($allowMissingDocs) {
				$param = new ControllerMethodParameter($context, $definitions, $methodParameterName, $methodParameter, null);
			} else {
				throw new Exception($context . ": Missing doc parameter for '" . $methodParameterName . "'");
			}
		}

		if (!$allowMissingDocs && $param->type->description == "") {
			throw new Exception($context . ": Missing description for parameter '" . $methodParameterName . "'");
		}

		$parameters[] = $param;
	}

	if (!$allowMissingDocs && count($methodDescription) == 0) {
		throw new Exception($context . ": Missing method description");
	}

	if ($isAdmin) {
		$methodDescription[] = "This endpoint requires admin access";
	}

	if (count($methodDescription) == 1) {
		$methodSummary = $methodDescription[0];
		$methodDescription = [];
	} else if (count($methodDescription) > 1) {
		$methodSummary = $methodDescription[0];
		$methodDescription = array_slice($methodDescription, 1);
	}

	if ($methodSummary != null && preg_match("/[.,!?:-]$/", $methodSummary)) {
		throw new Exception($context . ": Summary ends with a punctuation mark");
	}

	return new ControllerMethod($parameters, array_values($responses), $returns, $responseDescriptions, $methodDescription, $methodSummary, $isDeprecated);
}

function exprToValue(string $context, Expr $expr): mixed {
	if ($expr instanceof ConstFetch) {
		$value = $expr->name->getLast();
		return match ($value) {
			"null" => null,
			"true" => true,
			"false" => false,
			default => throw new Exception($context . ": Unable to evaluate constant value '" . $value . "'"),
		};
	}
	if ($expr instanceof String_) {
		return $expr->value;
	}
	if ($expr instanceof LNumber) {
		return intval($expr->value);
	}
	if ($expr instanceof UnaryMinus) {
		return exprToValue($context, $expr->expr);
	}
	if ($expr instanceof Array_) {
		$values = array_map(fn(ArrayItem $item) => exprToValue($context, $item), $expr->items);
		$filteredValues = array_filter($values, fn(mixed $value) => $value !== null);
		if (count($filteredValues) != count($values)) {
			return null;
		}
		return $values;
	}
	if ($expr instanceof ArrayItem) {
		return exprToValue($context, $expr->value);
	}
	if ($expr instanceof Expr\ClassConstFetch || $expr instanceof Expr\BinaryOp) {
		// Not supported
		return null;
	}

	throw new Exception($context . ": Unable to evaluate expression '" . get_class($expr) . "'");
}
