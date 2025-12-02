<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OpenAPIExtractor;

use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\MethodCall;
use PhpParser\Node\Expr\PropertyFetch;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Return_;
use PHPStan\PhpDocParser\Ast\PhpDoc\DeprecatedTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\GenericTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ThrowsTagValueNode;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use RuntimeException;

class ControllerMethod {
	private const STATUS_CODE_DESCRIPTION_PATTERN = '/^(\d{3}): (.+)$/';

	// Generate the list using this command:
	// curl https://www.iana.org/assignments/http-fields/field-names.csv | cut -d ',' -f 1 | tail -n +2 | head -n -1 | tr '[:upper:]' '[:lower:]' | grep -E '^[a-z]' | sed -e "s/^/'/g" | sed -e "s/$/',/g"
	private const HTTP_STANDARD_HEADERS = [
		'a-im',
		'accept',
		'accept-additions',
		'accept-ch',
		'accept-charset',
		'accept-datetime',
		'accept-encoding',
		'accept-features',
		'accept-language',
		'accept-patch',
		'accept-post',
		'accept-ranges',
		'accept-signature',
		'access-control',
		'access-control-allow-credentials',
		'access-control-allow-headers',
		'access-control-allow-methods',
		'access-control-allow-origin',
		'access-control-expose-headers',
		'access-control-max-age',
		'access-control-request-headers',
		'access-control-request-method',
		'activate-storage-access',
		'age',
		'allow',
		'alpn',
		'alt-svc',
		'alt-used',
		'alternates',
		'amp-cache-transform',
		'apply-to-redirect-ref',
		'authentication-control',
		'authentication-info',
		'authorization',
		'available-dictionary',
		'c-ext',
		'c-man',
		'c-opt',
		'c-pep',
		'c-pep-info',
		'cache-control',
		'cache-group-invalidation',
		'cache-groups',
		'cache-status',
		'cal-managed-id',
		'caldav-timezones',
		'capsule-protocol',
		'cdn-cache-control',
		'cdn-loop',
		'cert-not-after',
		'cert-not-before',
		'clear-site-data',
		'client-cert',
		'client-cert-chain',
		'close',
		'cmcd-object',
		'cmcd-request',
		'cmcd-session',
		'cmcd-status',
		'cmsd-dynamic',
		'cmsd-static',
		'concealed-auth-export',
		'configuration-context',
		'connection',
		'content-base',
		'content-digest',
		'content-disposition',
		'content-encoding',
		'content-id',
		'content-language',
		'content-length',
		'content-location',
		'content-md5',
		'content-range',
		'content-script-type',
		'content-security-policy',
		'content-security-policy-report-only',
		'content-style-type',
		'content-type',
		'content-version',
		'cookie',
		'cookie2',
		'cross-origin-embedder-policy',
		'cross-origin-embedder-policy-report-only',
		'cross-origin-opener-policy',
		'cross-origin-opener-policy-report-only',
		'cross-origin-resource-policy',
		'cta-common-access-token',
		'dasl',
		'date',
		'dav',
		'default-style',
		'delta-base',
		'deprecation',
		'depth',
		'derived-from',
		'destination',
		'detached-jws',
		'differential-id',
		'dictionary-id',
		'digest',
		'dpop',
		'dpop-nonce',
		'early-data',
		'ediint-features',
		'etag',
		'expect',
		'expect-ct',
		'expires',
		'ext',
		'forwarded',
		'from',
		'getprofile',
		'hobareg',
		'host',
		'http2-settings',
		'if',
		'if-match',
		'if-modified-since',
		'if-none-match',
		'if-range',
		'if-schedule-tag-match',
		'if-unmodified-since',
		'im',
		'include-referred-token-binding-id',
		'isolation',
		'keep-alive',
		'label',
		'last-event-id',
		'last-modified',
		'link',
		'link-template',
		'location',
		'lock-token',
		'man',
		'max-forwards',
		'memento-datetime',
		'meter',
		'method-check',
		'method-check-expires',
		'mime-version',
		'negotiate',
		'nel',
		'odata-entityid',
		'odata-isolation',
		'odata-maxversion',
		'odata-version',
		'opt',
		'optional-www-authenticate',
		'ordering-type',
		'origin',
		'origin-agent-cluster',
		'oscore',
		'oslc-core-version',
		'overwrite',
		'p3p',
		'pep',
		'pep-info',
		'permissions-policy',
		'pics-label',
		'ping-from',
		'ping-to',
		'position',
		'pragma',
		'prefer',
		'preference-applied',
		'priority',
		'profileobject',
		'protocol',
		'protocol-info',
		'protocol-query',
		'protocol-request',
		'proxy-authenticate',
		'proxy-authentication-info',
		'proxy-authorization',
		'proxy-features',
		'proxy-instruction',
		'proxy-status',
		'public',
		'public-key-pins',
		'public-key-pins-report-only',
		'range',
		'redirect-ref',
		'referer',
		'referer-root',
		'referrer-policy',
		'refresh',
		'repeatability-client-id',
		'repeatability-first-sent',
		'repeatability-request-id',
		'repeatability-result',
		'replay-nonce',
		'reporting-endpoints',
		'repr-digest',
		'retry-after',
		'safe',
		'schedule-reply',
		'schedule-tag',
		'sec-fetch-dest',
		'sec-fetch-mode',
		'sec-fetch-site',
		'sec-fetch-storage-access',
		'sec-fetch-user',
		'sec-gpc',
		'sec-purpose',
		'sec-token-binding',
		'sec-websocket-accept',
		'sec-websocket-extensions',
		'sec-websocket-key',
		'sec-websocket-protocol',
		'sec-websocket-version',
		'security-scheme',
		'server',
		'server-timing',
		'set-cookie',
		'set-cookie2',
		'setprofile',
		'signature',
		'signature-input',
		'slug',
		'soapaction',
		'status-uri',
		'strict-transport-security',
		'sunset',
		'surrogate-capability',
		'surrogate-control',
		'tcn',
		'te',
		'timeout',
		'timing-allow-origin',
		'topic',
		'traceparent',
		'tracestate',
		'trailer',
		'transfer-encoding',
		'ttl',
		'upgrade',
		'urgency',
		'uri',
		'use-as-dictionary',
		'user-agent',
		'variant-vary',
		'vary',
		'via',
		'want-content-digest',
		'want-digest',
		'want-repr-digest',
		'warning',
		'www-authenticate',
		'x-content-type-options',
		'x-frame-options',
	];

	/**
	 * @param ControllerMethodParameter[] $parameters
	 * @param array<string, string> $requestHeaders
	 * @param list<ControllerMethodResponse|null> $responses
	 * @param OpenApiType[] $returns
	 * @param array<int, string> $responseDescription
	 * @param string[] $description
	 */
	public function __construct(
		public array $parameters,
		public array $requestHeaders,
		public array $responses,
		public array $responseDescription,
		public array $description,
		public ?string $summary,
		public bool $isDeprecated,
	) {
	}

	public static function parse(string $context,
		array $definitions,
		ClassMethod $method,
		bool $isPublic,
		bool $isAdmin,
		bool $isDeprecated,
		bool $isPasswordConfirmation,
		bool $isCORS,
		bool $isOCS,
	): ControllerMethod {
		global $phpDocParser, $lexer, $nodeFinder, $allowMissingDocs;

		$parameters = [];
		$responses = [];
		$responseDescriptions = [];

		$methodDescription = [];
		$methodSummary = null;
		$methodParameters = $method->getParams();
		$docParameters = [];

		$returnStmtCount = count($nodeFinder->findInstanceOf($method->getStmts(), Return_::class));
		$returnTagCount = 0;

		$doc = $method->getDocComment()?->getText();
		if ($doc !== null) {
			$docNodes = $phpDocParser->parse(new TokenIterator($lexer->tokenize($doc)))->children;

			foreach ($docNodes as $docNode) {
				if ($docNode instanceof PhpDocTextNode) {
					$nodeDescription = $docNode->text;
				} elseif ($docNode->value instanceof GenericTagValueNode) {
					$nodeDescription = (string)$docNode->value;
				} else {
					$nodeDescription = (string)$docNode->value->description;
				}

				$nodeDescriptionLines = array_filter(explode("\n", $nodeDescription), static fn (string $line): bool => trim($line) !== '');

				// Parse in blocks (separate by double newline) to preserve newlines within a block.
				$nodeDescriptionBlocks = preg_split("/\n\s*\n/", $nodeDescription);
				foreach ($nodeDescriptionBlocks as $nodeDescriptionBlock) {
					$methodDescriptionBlockLines = [];
					foreach (array_filter(explode("\n", $nodeDescriptionBlock), static fn (string $line): bool => trim($line) !== '') as $line) {
						if (preg_match(self::STATUS_CODE_DESCRIPTION_PATTERN, $line)) {
							$parts = preg_split(self::STATUS_CODE_DESCRIPTION_PATTERN, $line, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
							$responseDescriptions[(int)$parts[0]] = trim($parts[1]);
						} elseif ($docNode instanceof PhpDocTextNode) {
							$methodDescriptionBlockLines[] = $line;
						} elseif (
							$docNode instanceof PhpDocTagNode && (
								$docNode->value instanceof ParamTagValueNode
								|| $docNode->value instanceof ThrowsTagValueNode
								|| $docNode->value instanceof DeprecatedTagValueNode
								|| $docNode->name === '@license'
								|| $docNode->name === '@since'
								|| $docNode->name === '@psalm-suppress'
								|| $docNode->name === '@suppress'
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
						$returnTagCount++;

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
								$responseDescriptions[$statusCode] = implode("\n", array_filter($nodeDescriptionLines, static fn (string $line): bool => in_array(preg_match(self::STATUS_CODE_DESCRIPTION_PATTERN, $line), [0, false], true)));
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

		if ($returnStmtCount !== 0 && $returnTagCount === 0) {
			Logger::error($context, 'Missing @return annotation');
		}

		if (!$isPublic || $isAdmin) {
			$statusCodes = [];
			if (!$isPublic) {
				$responseDescriptions[401] ??= 'Current user is not logged in';
				$statusCodes[] = 401;
			}
			if ($isAdmin) {
				$responseDescriptions[403] ??= 'Logged in account must be an admin';
				$statusCodes[] = 403;
			}

			foreach ($statusCodes as $statusCode) {
				if ($isOCS) {
					$responses[] = new ControllerMethodResponse(
						'DataResponse',
						$statusCode,
						'application/json',
						new OpenApiType($context),
					);
				} else {
					$responses[] = new ControllerMethodResponse(
						'JsonResponse',
						$statusCode,
						'application/json',
						new OpenApiType(
							$context,
							type: 'object',
							properties: [
								'message' => new OpenApiType(
									$context,
									type: 'string',
								),
							],
							required: ['message'],
						),
					);
				}
			}
		}

		$responseStatusCodes = array_unique(array_map(static fn (ControllerMethodResponse $response): int => $response->statusCode, $responses));
		$unusedResponseDescriptions = array_diff(array_keys($responseDescriptions), $responseStatusCodes);
		if ($unusedResponseDescriptions !== []) {
			Logger::error($context, 'Unused descriptions for status codes ' . implode(', ', $unusedResponseDescriptions));
		}

		if (!$allowMissingDocs) {
			foreach ($responseStatusCodes as $statusCode) {
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
						if ($docParameterType === '@param') {
							$paramTag = $docParameter;
						} elseif ($docParameterType === '@psalm-param') {
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
			$description = Helpers::cleanDocComment(implode("\n", array_filter(array_filter(explode("\n", $description), static fn (string $line): bool => trim($line) !== ''), static fn (string $line): bool => in_array(preg_match(self::STATUS_CODE_DESCRIPTION_PATTERN, $line), [0, false], true))));

			if ($paramTag instanceof ParamTagValueNode && $psalmParamTag instanceof ParamTagValueNode) {
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

			} elseif ($psalmParamTag instanceof ParamTagValueNode) {
				$type = OpenApiType::resolve($context . ': @param: ' . $methodParameterName, $definitions, $psalmParamTag);
			} elseif ($paramTag instanceof ParamTagValueNode) {
				$type = OpenApiType::resolve($context . ': @param: ' . $methodParameterName, $definitions, $paramTag);
			} elseif ($allowMissingDocs) {
				$type = OpenApiType::resolve($context . ': $' . $methodParameterName . ': ' . $methodParameterName, $definitions, $methodParameter->type);
			} else {
				Logger::error($context, "Missing doc parameter for '" . $methodParameterName . "'");
				continue;
			}

			$type->description = $description;

			if ($methodParameter->default !== null) {
				try {
					$type->defaultValue = Helpers::exprToValue($context, $methodParameter->default);
					$type->hasDefaultValue = true;
				} catch (UnsupportedExprException $e) {
					$type->hasDefaultValue = true;
					$type->hasUnknownDefaultValue = true;
					Logger::debug($context, $e);
				}
			}

			$param = new ControllerMethodParameter($context, $definitions, $methodParameterName, $type);

			if (!$allowMissingDocs && $param->type->description == '') {
				Logger::error($context . ': @param: ' . $methodParameterName, 'Missing description');
				continue;
			}

			if (str_contains((string)$param->type->description, '@deprecated')) {
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

		$codeRequestHeaders = [];
		foreach ($nodeFinder->findInstanceOf($method->getStmts(), MethodCall::class) as $methodCall) {
			if ($methodCall->var instanceof PropertyFetch
				&& $methodCall->var->var instanceof Variable
				&& $methodCall->var->var->name === 'this'
				&& $methodCall->var->name->name === 'request') {
				if ($methodCall->name->name === 'getHeader') {
					$headerName = self::cleanHeaderName($methodCall->args[0]->value->value);

					if ($headerName !== $methodCall->args[0]->value->value) {
						Logger::error($context, 'Request header "' . $methodCall->args[0]->value->value . '" should be "' . $headerName . '".');
					}

					self::checkCustomHeaderName($context, $headerName);

					$codeRequestHeaders[] = $headerName;
				}
				if ($methodCall->name->name === 'getParam') {
					$name = $methodCall->args[0]->value->value;

					if (preg_match('/^[a-zA-Z]\w*$/', (string)$name)) {
						Logger::error($context . ': getParam: ' . $name, 'Do not use getParam() when a controller method parameter also works. With getParam() it is not possible to add a comment and specify the parameter type, therefore it should be avoided whenever possible.');
					}

					$defaultValue = null;
					$hasDefaultValue = false;
					try {
						$defaultValue = count($methodCall->args) > 1 ? Helpers::exprToValue($context . ': getParam: ' . $name, $methodCall->args[1]->value) : null;
						$hasDefaultValue = true;
					} catch (UnsupportedExprException $e) {
						Logger::debug($context, $e);
					}

					$type = new OpenApiType(
						context: $context,
						// We can not know the type, so need to fallback to object :/
						type: 'object',
						// IRequest::getParam() has null as a default value, so the parameter always has a default value and allows null.
						nullable: true,
						hasDefaultValue: $hasDefaultValue,
						defaultValue: $defaultValue,
					);

					$parameters[] = new ControllerMethodParameter($context, $definitions, $name, $type);
				}
			}
		}

		$attributeRequestHeaders = [];
		/** @var AttributeGroup $attrGroup */
		foreach ($method->attrGroups as $attrGroup) {
			foreach ($attrGroup->attrs as $attr) {
				if ($attr->name->getLast() === 'RequestHeader') {
					$args = [];
					foreach ($attr->args as $key => $arg) {
						$attrName = $arg->name?->name;
						if ($attrName === null) {
							$attrName = match ($key) {
								0 => 'name',
								1 => 'description',
								2 => 'indirect',
								default => throw new RuntimeException('Should not happen.'),
							};
						}

						$args[$attrName] = match ($attrName) {
							'name', 'description' => $arg->value->value,
							'indirect' => $arg->value->name->name === 'true',
							default => throw new RuntimeException('Should not happen.'),
						};
					}

					$headerName = self::cleanHeaderName($args['name']);
					if ($headerName !== $args['name']) {
						Logger::error($context, 'Request header "' . $args['name'] . '" should be "' . $headerName . '".');
					}

					self::checkCustomHeaderName($context, $headerName);

					if (array_key_exists($headerName, $attributeRequestHeaders)) {
						Logger::error($context, 'Request header "' . $headerName . '" already documented.');
					}

					$attributeRequestHeaders[$headerName] = $args['description'];
					if ($args['indirect'] ?? false) {
						$codeRequestHeaders[] = $headerName;
					}
				}
			}
		}

		$undocumentedRequestHeaders = array_diff($codeRequestHeaders, array_keys($attributeRequestHeaders));
		if ($undocumentedRequestHeaders !== []) {
			Logger::warning($context, 'Undocumented request headers (use the RequestHeader attribute): ' . implode(', ', $undocumentedRequestHeaders));
			foreach ($undocumentedRequestHeaders as $header) {
				$attributeRequestHeaders[$header] = null;
			}
		}

		$unusedRequestHeaders = array_diff(array_keys($attributeRequestHeaders), $codeRequestHeaders);
		if ($unusedRequestHeaders !== []) {
			Logger::error($context, 'Unused request header descriptions: ' . implode(', ', $unusedRequestHeaders));
		}

		return new ControllerMethod($parameters, $attributeRequestHeaders, $responses, $responseDescriptions, $methodDescription, $methodSummary, $isDeprecated);
	}

	private static function cleanHeaderName(string $header): string {
		return str_replace('_', '-', strtolower($header));
	}

	private static function checkCustomHeaderName(string $context, string $header): void {
		if (in_array($header, self::HTTP_STANDARD_HEADERS, true)) {
			return;
		}

		if (!str_starts_with($header, 'x-')) {
			Logger::warning($context, 'Request header "' . $header . '" should start with "x-" to denote a custom header.');
		}
	}
}
