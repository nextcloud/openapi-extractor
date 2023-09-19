<?php

namespace OpenAPIExtractor;


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

	static function parse(string $context, array $definitions, ClassMethod $method, bool $isAdmin, bool $isDeprecated): ControllerMethod {
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
					$block = Helpers::cleanDocComment($docNode->text);
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

						$responses = array_merge($responses, ResponseType::resolve($context, $type));
					}

					if ($docNode->value instanceof ThrowsTagValueNode) {
						$type = $docNode->value->type;
						$statusCode = StatusCodes::resolveException($context, $type);
						if ($statusCode != null) {
							if (!$allowMissingDocs && $docNode->value->description == "" && $statusCode < 500) {
								Logger::error($context, "Missing description for exception '" . $type . "'");
							} else {
								$responseDescriptions[$statusCode] = $docNode->value->description;
							}
							$responses[] = new ControllerMethodResponse($docNode->value->type, $statusCode, "text/plain", new OpenApiType(type: "string"), null);
						}
					}
				}
			}
		}

		if (!$allowMissingDocs) {
			foreach (array_unique(array_map(fn(ControllerMethodResponse $response) => $response->statusCode, array_filter($responses, fn(?ControllerMethodResponse $response) => $response != null))) as $statusCode) {
				if ($statusCode < 500 && (!array_key_exists($statusCode, $responseDescriptions) || $responseDescriptions[$statusCode] == "")) {
					Logger::error($context, "Missing description for status code " . $statusCode);
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
					Logger::error($context, "Missing doc parameter for '" . $methodParameterName . "'");
					continue;
				}
			}

			if (!$allowMissingDocs && $param->type->description == "") {
				Logger::error($context, "Missing description for parameter '" . $methodParameterName . "'");
				continue;
			}

			$parameters[] = $param;
		}

		if (!$allowMissingDocs && count($methodDescription) == 0) {
			Logger::error($context, "Missing method description");
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
			Logger::warning($context, "Summary ends with a punctuation mark");
		}

		return new ControllerMethod($parameters, array_values($responses), $returns, $responseDescriptions, $methodDescription, $methodSummary, $isDeprecated);
	}

}
