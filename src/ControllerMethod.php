<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OpenAPIExtractor;

use PhpParser\Node\Stmt\ClassMethod;
use PHPStan\PhpDocParser\Ast\PhpDoc\DeprecatedTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ThrowsTagValueNode;
use PHPStan\PhpDocParser\Parser\TokenIterator;

class ControllerMethod {
	private const STATUS_CODE_DESCRIPTION_PATTERN = '/^(\d{3}): (.+)$/';

	/**
	 * @param ControllerMethodParameter[] $parameters
	 * @param list<ControllerMethodResponse|null> $responses
	 * @param OpenApiType[] $returns
	 * @param array<int, string> $responseDescription
	 * @param string[] $description
	 */
	public function __construct(
		public array $parameters,
		public array $responses,
		public array $returns,
		public array $responseDescription,
		public array $description,
		public ?string $summary,
		public bool $isDeprecated,
	) {
	}

	public static function parse(string $context,
		array $definitions,
		ClassMethod $method,
		bool $isAdmin,
		bool $isDeprecated,
		bool $isPasswordConfirmation,
		bool $isCORS,
	): ControllerMethod {
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
		if ($doc !== null) {
			$docNodes = $phpDocParser->parse(new TokenIterator($lexer->tokenize($doc)))->children;

			foreach ($docNodes as $docNode) {
				if ($docNode instanceof PhpDocTextNode) {
					$nodeDescription = (string)$docNode->text;
				} elseif ($docNode->value instanceof GenericTagValueNode) {
					$nodeDescription = (string)$docNode->value;
				} else {
					$nodeDescription = (string)$docNode->value->description;
				}

				$nodeDescriptionLines = array_filter(explode("\n", $nodeDescription), static fn (string $line) => trim($line) !== '');

				// Parse in blocks (separate by double newline) to preserve newlines within a block.
				$nodeDescriptionBlocks = preg_split("/\n\s*\n/", $nodeDescription);
				foreach ($nodeDescriptionBlocks as $nodeDescriptionBlock) {
					$methodDescriptionBlockLines = [];
					foreach (array_filter(explode("\n", $nodeDescriptionBlock), static fn (string $line) => trim($line) !== '') as $line) {
						if (preg_match(self::STATUS_CODE_DESCRIPTION_PATTERN, $line)) {
							$parts = preg_split(self::STATUS_CODE_DESCRIPTION_PATTERN, $line, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
							$responseDescriptions[(int)$parts[0]] = trim($parts[1]);
						} elseif ($docNode instanceof PhpDocTextNode) {
							$methodDescriptionBlockLines[] = $line;
						} elseif (
							$docNode instanceof PhpDocTagNode && (
								$docNode->value instanceof ParamTagValueNode ||
								$docNode->value instanceof ThrowsTagValueNode ||
								$docNode->value instanceof DeprecatedTagValueNode ||
								$docNode->name === '@license' ||
								$docNode->name === '@since' ||
								$docNode->name === '@psalm-suppress' ||
								$docNode->name === '@suppress'
							)) {
							// Only add lines from other node types, as these have special handling (e.g. @param or @throws) or should be ignored entirely (e.g. @deprecated or @license).
							continue;
						} else {
							$methodDescriptionBlockLines[] = $line;
						}
					}
					if ($methodDescriptionBlockLines !== []) {
						$methodDescription[] = Helpers::cleanDocComment(implode(' ', $methodDescriptionBlockLines));
					}
				}

				if ($docNode instanceof PhpDocTagNode) {
					if ($docNode->value instanceof ParamTagValueNode) {
						$docParameters[$docNode->name] ??= [];
						$docParameters[$docNode->name][] = $docNode->value;
					}

					if ($docNode->value instanceof ReturnTagValueNode) {
						$type = $docNode->value->type;

						$responses = array_merge($responses, ResponseType::resolve($context . ': @return', $type));
					}

					if ($docNode->value instanceof ThrowsTagValueNode) {
						$type = $docNode->value->type;
						$statusCode = StatusCodes::resolveException($context . ': @throws', $type);
						if ($statusCode !== null) {
							if (!$allowMissingDocs && $nodeDescriptionLines === [] && $statusCode < 500) {
								Logger::error($context, "Missing description for exception '" . $type . "'");
							} else {
								// Only add lines that don't match the status code pattern to the description
								$responseDescriptions[$statusCode] = implode("\n", array_filter($nodeDescriptionLines, static fn (string $line) => !preg_match(self::STATUS_CODE_DESCRIPTION_PATTERN, $line)));
							}

							if (str_starts_with($type->name, 'OCS') && str_ends_with($type->name, 'Exception')) {
								$responses[] = new ControllerMethodResponse($docNode->value->type, $statusCode, 'application/json', new OpenApiType(context: $context, type: 'array', maxItems: 0), null);
							} else {
								$responses[] = new ControllerMethodResponse($docNode->value->type, $statusCode, 'text/plain', new OpenApiType(context: $context, type: 'string'), null);
							}
						}
					}
				}
			}
		}

		if (!$allowMissingDocs) {
			foreach (array_unique(array_map(fn (ControllerMethodResponse $response): int => $response->statusCode, array_filter($responses, fn (?ControllerMethodResponse $response): bool => $response != null))) as $statusCode) {
				if ($statusCode < 500 && (!array_key_exists($statusCode, $responseDescriptions) || $responseDescriptions[$statusCode] === '')) {
					Logger::error($context, 'Missing description for status code ' . $statusCode);
				}
			}
		}

		foreach ($methodParameters as $methodParameter) {
			$methodParameterName = $methodParameter->var->name;

			$paramTag = null;
			$psalmParamTag = null;
			foreach ($docParameters as $docParameterType => $typeDocParameters) {
				foreach ($typeDocParameters as $docParameter) {
					$docParameterName = substr($docParameter->parameterName, 1);

					if ($docParameterName == $methodParameterName) {
						if ($docParameterType == '@param') {
							$paramTag = $docParameter;
						} elseif ($docParameterType == '@psalm-param') {
							$psalmParamTag = $docParameter;
						} else {
							Logger::panic($context . ': @param', 'Unknown param type ' . $docParameterType);
						}
					}
				}
			}

			// Use all the type information from @psalm-param because it is more specific,
			// but pull the description from @param and @psalm-param because usually only one of them has it.
			if (($psalmParamTag?->description ?? '') !== '') {
				$description = $psalmParamTag->description;
			} elseif (($paramTag?->description ?? '') !== '') {
				$description = $paramTag->description;
			} else {
				$description = '';
			}
			// Only keep lines that don't match the status code pattern in the description
			$description = implode("\n", array_filter(array_filter(explode("\n", $description), static fn (string $line) => trim($line) !== ''), static fn (string $line) => !preg_match(self::STATUS_CODE_DESCRIPTION_PATTERN, $line)));

			if ($paramTag instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode && $psalmParamTag instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode) {
				try {
					$type = OpenApiType::resolve(
						$context . ': @param: ' . $psalmParamTag->parameterName,
						$definitions,
						new ParamTagValueNode(
							$psalmParamTag->type,
							$psalmParamTag->isVariadic,
							$psalmParamTag->parameterName,
							$description,
							$psalmParamTag->isReference,
						),
					);
				} catch (LoggerException $e) {
					Logger::debug($context, 'Unable to parse parameter ' . $methodParameterName . ': ' . $e->message . "\n" . $e->getTraceAsString());
					// Fallback to the @param annotation
					$type = OpenApiType::resolve(
						$context . ': @param: ' . $psalmParamTag->parameterName,
						$definitions,
						new ParamTagValueNode(
							$paramTag->type,
							$paramTag->isVariadic,
							$paramTag->parameterName,
							$description,
							$paramTag->isReference,
						),
					);
				}

			} elseif ($psalmParamTag instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode) {
				$type = OpenApiType::resolve($context . ': @param: ' . $methodParameterName, $definitions, $psalmParamTag);
			} elseif ($paramTag instanceof \PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode) {
				$type = OpenApiType::resolve($context . ': @param: ' . $methodParameterName, $definitions, $paramTag);
			} elseif ($allowMissingDocs) {
				$type = null;
			} else {
				Logger::error($context, "Missing doc parameter for '" . $methodParameterName . "'");
				continue;
			}

			if ($type !== null) {
				$type->description = $description;
			}

			$param = new ControllerMethodParameter($context, $definitions, $methodParameterName, $methodParameter, $type);

			if (!$allowMissingDocs && $param->type->description == '') {
				Logger::error($context . ': @param: ' . $methodParameterName, 'Missing description');
				continue;
			}

			if (str_contains($param->type->description, '@deprecated')) {
				$param->type->deprecated = true;
				$param->type->description = str_replace('@deprecated', 'Deprecated:', $param->type->description);
			}

			$parameters[] = $param;
		}

		if (!$allowMissingDocs && count($methodDescription) == 0) {
			Logger::error($context, 'Missing method description');
		}

		if ($isAdmin) {
			$methodDescription[] = 'This endpoint requires admin access';
		}

		if ($isPasswordConfirmation) {
			$methodDescription[] = 'This endpoint requires password confirmation';
		}

		if ($isCORS) {
			$methodDescription[] = 'This endpoint allows CORS requests';
		}

		if (count($methodDescription) == 1) {
			$methodSummary = $methodDescription[0];
			$methodDescription = [];
		} elseif (count($methodDescription) > 1) {
			$methodSummary = $methodDescription[0];
			$methodDescription = array_slice($methodDescription, 1);
		}

		if ($methodSummary != null && preg_match('/[.,!?:-]$/', $methodSummary)) {
			Logger::warning($context, 'Summary ends with a punctuation mark');
		}

		return new ControllerMethod($parameters, $responses, $returns, $responseDescriptions, $methodDescription, $methodSummary, $isDeprecated);
	}

}
