#!/usr/bin/env php
<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OpenAPIExtractor;

foreach ([__DIR__ . '/../../autoload.php', __DIR__ . '/vendor/autoload.php'] as $file) {
	if (file_exists($file)) {
		require_once $file;
		break;
	}
}

use Ahc\Cli\Input\Command;
use DirectoryIterator;
use PhpParser\Node\AttributeGroup;
use PhpParser\Node\Expr\ClassConstFetch;
use PhpParser\Node\Expr\New_;
use PhpParser\Node\Name;
use PhpParser\Node\Stmt\Class_;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Throw_;
use PhpParser\NodeFinder;
use PhpParser\ParserFactory;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTagNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\ReturnTagValueNode;
use PHPStan\PhpDocParser\Ast\PhpDoc\TypeAliasTagValueNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\ConstExprParser;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;
use PHPStan\PhpDocParser\Parser\TypeParser;
use PHPStan\PhpDocParser\ParserConfig;
use RecursiveDirectoryIterator;
use RecursiveIteratorIterator;
use stdClass;

$command = new Command('generate-spec.php', 'Extract OpenAPI specs from the Nextcloud source code');
$command
	->arguments('dir out')
	->option('--first-status-code', 'Only output the first status code')
	->option('--first-content-type', 'Only output the first content type')
	->option('--allow-missing-docs', 'Allow missing documentation fields')
	->option('--no-tags', 'Use no tags')
	->option('--openapi-version', 'OpenAPI version to use', null, '3.0.3')
	->option('--verbose', 'Verbose logging')
	->parse($_SERVER['argv']);

$dir = $command->dir ?? '';
$out = $command->out ?? '';
$firstStatusCode = $command->firstStatusCode ?? false;
$firstContentType = $command->firstContentType ?? false;
$allowMissingDocs = $command->allowMissingDocs ?? false;
$useTags = $command->tags ?? true;
Logger::$verbose = $command->verbose ?? false;
$openapiVersion = $command->openapiVersion ?? '3.0.3';

if ($dir == '') {
	$dir = '.';
}
if ($out == '') {
	$out = 'openapi.json';
}

$astParser = (new ParserFactory())->createForNewestSupportedVersion();
$nodeFinder = new NodeFinder;

$config = new ParserConfig(usedAttributes: ['lines' => true, 'indexes' => true, 'comments' => true]);
$lexer = new Lexer($config);
$constExprParser = new ConstExprParser($config);
$typeParser = new TypeParser($config, $constExprParser);
$phpDocParser = new PhpDocParser($config, $typeParser, $constExprParser);

$infoXMLPath = $dir . '/appinfo/info.xml';

if (file_exists($infoXMLPath)) {
	$xml = simplexml_load_string(file_get_contents($infoXMLPath));
	if ($xml === false) {
		Logger::panic('appinfo', 'info.xml file at ' . $infoXMLPath . ' is not parsable');
	}

	$appIsCore = false;
	$appID = (string)$xml->id;
	$readableAppID = $xml->namespace ? (string)$xml->namespace : Helpers::generateReadableAppID($appID);
	$appSummary = (string)$xml->summary;
	$appVersion = (string)$xml->version;
	$appLicence = (string)$xml->licence;
} else {
	$versionPHPPath = $dir . '/../version.php';

	if (!file_exists($versionPHPPath)) {
		Logger::panic('appinfo', 'Neither ' . $infoXMLPath . ' nor ' . $versionPHPPath . ' exists');
	}

	// Includes https://github.com/nextcloud/server/blob/master/version.php when running inside https://github.com/nextcloud/server/tree/master/core
	include($versionPHPPath);
	if (!isset($OC_VersionString)) {
		Logger::panic('appinfo', 'Unable to figure out core version');
	}

	$appIsCore = true;
	$appID = 'core';
	$readableAppID = 'Core';
	$appSummary = 'Core functionality of Nextcloud';
	$appVersion = $OC_VersionString;
	$appLicence = 'agpl';
}

$sourceDir = $appIsCore ? $dir : $dir . '/lib';
$appinfoDir = $appIsCore ? $dir : $dir . '/appinfo';

$openapi = [
	'openapi' => $openapiVersion,
	'info' => [
		'title' => $appID,
		'version' => '0.0.1', // This marks the document version and not the implementation version
		'description' => $appSummary,
		'license' => Helpers::license($openapiVersion, $appLicence),
	],
	'components' => [
		'securitySchemes' => Helpers::securitySchemes(),
		'schemas' => [],
	],
	'paths' => [],
];

Logger::info('app', 'Extracting OpenAPI spec for ' . $appID . ' ' . $appVersion);

$schemas = [];
$tags = [];

$definitions = [];
$definitionsPath = $sourceDir . '/ResponseDefinitions.php';
if (file_exists($definitionsPath)) {
	foreach ($nodeFinder->findInstanceOf($astParser->parse(file_get_contents($definitionsPath)), Class_::class) as $node) {
		$doc = $node->getDocComment()?->getText();
		if ($doc != null) {
			$docNodes = $phpDocParser->parse(new TokenIterator($lexer->tokenize($doc)))->children;
			foreach ($docNodes as $docNode) {
				if ($docNode instanceof PhpDocTagNode && $docNode->value instanceof TypeAliasTagValueNode) {
					if (!str_starts_with($docNode->value->alias, $readableAppID)) {
						Logger::error('Response definitions', "Type alias '" . $docNode->value->alias . "' has to start with '" . $readableAppID . "'");
					}
					$definitions[$docNode->value->alias] = $docNode->value->type;
				}
			}
		}
	}
	foreach (array_keys($definitions) as $name) {
		$schemas[Helpers::cleanSchemaName($name)] = OpenApiType::resolve('Response definitions: ' . $name, $definitions, $definitions[$name])->toArray();
	}
} else {
	Logger::debug('Response definitions', 'No response definitions were loaded');
}

$capabilities = null;
$publicCapabilities = null;
$capabilitiesFiles = [];
$capabilitiesDirs = [$sourceDir];
if ($appIsCore) {
	$capabilitiesDirs[] = $sourceDir . '/../lib/private';
}
foreach ($capabilitiesDirs as $dir) {
	$iterator = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($dir));
	foreach ($iterator as $file) {
		$path = $file->getPathname();
		if (str_ends_with((string)$path, 'Capabilities.php')) {
			$capabilitiesFiles[] = $path;
		}
	}
}
sort($capabilitiesFiles);
foreach ($capabilitiesFiles as $path) {
	/**
	 * @var Class_ $node
	 */
	foreach ($nodeFinder->findInstanceOf($astParser->parse(file_get_contents($path)), Class_::class) as $node) {
		$implementsCapability = array_filter($node->implements, fn (Name $name): bool => $name->getLast() === 'ICapability') !== [];
		$implementsPublicCapability = array_filter($node->implements, fn (Name $name): bool => $name->getLast() === 'IPublicCapability') !== [];
		if (!$implementsCapability && !$implementsPublicCapability) {
			continue;
		}

		$capabilitiesMethod = null;
		/** @var ClassMethod $classMethod */
		foreach ($nodeFinder->findInstanceOf($node->stmts, ClassMethod::class) as $classMethod) {
			if ($classMethod->name == 'getCapabilities') {
				$capabilitiesMethod = $classMethod;
				break;
			}
		}
		if ($capabilitiesMethod == null) {
			Logger::error($path, 'Unable to read capabilities method');
			continue;
		}

		$doc = $capabilitiesMethod->getDocComment()?->getText();
		if ($doc == null) {
			Logger::error($path, 'Unable to read capabilities docs');
			continue;
		}

		$type = null;
		$docNodes = $phpDocParser->parse(new TokenIterator($lexer->tokenize($doc)))->children;
		foreach ($docNodes as $docNode) {
			if ($docNode instanceof PhpDocTagNode && $docNode->value instanceof ReturnTagValueNode) {
				$type = OpenApiType::resolve($path, $definitions, $docNode->value->type);
				break;
			}
		}
		if ($type == null) {
			Logger::error($path, 'No return value');
			continue;
		}

		$schema = $type->toArray();

		if ($implementsPublicCapability) {
			$publicCapabilities = $publicCapabilities == null ? $schema : Helpers::mergeSchemas([$publicCapabilities, $schema]);
		} else {
			$capabilities = $capabilities == null ? $schema : Helpers::mergeSchemas([$capabilities, $schema]);
		}
	}
}
if ($capabilities != null) {
	$schemas['Capabilities'] = $capabilities;
}
if ($publicCapabilities != null) {
	$schemas['PublicCapabilities'] = $publicCapabilities;
}
if ($capabilities == null && $publicCapabilities == null) {
	Logger::debug('Capabilities', 'No capabilities were loaded');
}

$parsedRoutes = file_exists($appinfoDir . '/routes.php') ? Route::parseRoutes($appinfoDir . '/routes.php') : [];

$controllers = [];
$controllersDir = $sourceDir . '/Controller';
if (file_exists($controllersDir)) {
	$dir = new DirectoryIterator($controllersDir);
	$controllerFiles = [];
	foreach ($dir as $file) {
		$filePath = $file->getPathname();
		if (!str_ends_with($filePath, 'Controller.php')) {
			continue;
		}
		$controllerFiles[] = $filePath;
	}
	sort($controllerFiles);

	foreach ($controllerFiles as $filePath) {
		$controllers[basename($filePath, 'Controller.php')] = $astParser->parse(file_get_contents($filePath));
	}
}

$routes = [];
foreach ($controllers as $controllerName => $stmts) {
	$controllerClass = null;
	/** @var Class_ $class */
	foreach ($nodeFinder->findInstanceOf($stmts, Class_::class) as $class) {
		if ($class->name->name === $controllerName . 'Controller') {
			$controllerClass = $class;
			break;
		}
	}
	if ($controllerClass === null) {
		Logger::error($controllerName, "Controller '$controllerName' not found");
		continue;
	}

	/** @var ClassMethod $classMethod */
	foreach ($nodeFinder->findInstanceOf($controllerClass->stmts, ClassMethod::class) as $classMethod) {
		$name = substr($class->name->name, 0, -strlen('Controller')) . '#' . $classMethod->name->name;

		/** @var AttributeGroup $attrGroup */
		foreach ($classMethod->attrGroups as $attrGroup) {
			foreach ($attrGroup->attrs as $attr) {
				if ($attr->name->getLast() !== 'Route' && $attr->name->getLast() !== 'ApiRoute' && $attr->name->getLast() !== 'FrontpageRoute') {
					continue;
				}

				$key = match ($attr->name->getLast()) {
					'Route' => null,
					'ApiRoute' => 'ocs',
					'FrontpageRoute' => 'routes',
				};
				$args = [
					'name' => $name,
				];
				for ($i = 0, $iMax = count($attr->args); $i < $iMax; $i++) {
					$arg = $attr->args[$i];

					if ($arg->name !== null) {
						$argName = $arg->name->name;
					} else {
						$argNames = ['verb', 'url', 'requirements', 'defaults', 'root', 'postfix'];
						if ($attr->name->getLast() === 'Route') {
							array_unshift($argNames, 'type');
						}
						$argName = $argNames[$i];
					}

					if ($argName === 'type' && $arg->value instanceof ClassConstFetch) {
						$type = $arg->value->name->name;
						$key = match ($type) {
							'TYPE_API' => 'ocs',
							'TYPE_FRONTPAGE' => 'routes',
							default => Logger::panic($name, 'Unknown Route type: ' . $type),
						};
						continue;
					}

					$args[$argName] = Helpers::exprToValue($name, $arg->value);
				}

				$parsedRoutes[$key] ??= [];
				$parsedRoutes[$key][] = $args;
			}
		}
	}
}

if ($parsedRoutes === []) {
	Logger::warning('Routes', 'No routes were loaded');
}

$operationIds = [];

foreach ($parsedRoutes as $key => $value) {
	if ($key === 'resources') {
		Logger::warning('Routes', 'Routes of type "resources" are not currently supported.');
		continue;
	}

	$pathPrefix = match ($key) {
		'ocs' => '/ocs/v2.php',
		'routes' => '/index.php',
		default => throw new \InvalidArgumentException('Unknown routes key "' . $key . '"'),
	};

	foreach ($value as $route) {
		$routeName = $route['name'];

		$postfix = $route['postfix'] ?? null;
		$verb = array_key_exists('verb', $route) ? $route['verb'] : 'GET';
		$requirements = array_key_exists('requirements', $route) ? $route['requirements'] : [];
		$defaults = array_key_exists('defaults', $route) ? $route['defaults'] : [];
		$root = array_key_exists('root', $route) ? $route['root'] : ($appIsCore ? '' : '/apps/' . $appID);
		$url = $route['url'];
		if (!str_starts_with((string)$url, '/')) {
			$url = '/' . $url;
		}
		if (str_ends_with((string)$url, '/')) {
			$url = substr((string)$url, 0, -1);
		}
		$url = $pathPrefix . $root . $url;

		$methodName = lcfirst(str_replace('_', '', ucwords(explode('#', (string)$routeName)[1], '_')));
		if ($methodName === 'preflightedCors') {
			continue;
		}

		$controllerName = ucfirst(str_replace('_', '', ucwords(explode('#', (string)$routeName)[0], '_')));
		$controllerClass = null;
		/** @var Class_ $class */
		foreach ($nodeFinder->findInstanceOf($controllers[$controllerName] ?? [], Class_::class) as $class) {
			if ($class->name == $controllerName . 'Controller') {
				$controllerClass = $class;
				break;
			}
		}
		if ($controllerClass == null) {
			Logger::error($routeName, "Controller '" . $controllerName . "' not found");
			continue;
		}

		$parentControllerClass = $controllerClass->extends?->name ?? '';
		$parentControllerClass = explode('\\', $parentControllerClass);
		$parentControllerClass = end($parentControllerClass);
		// This is very ugly, but since we do not parse the entire source code we can not say with certainty which controller type is used.
		// To still allow apps to use custom controllers that extend OCSController, we only check the suffix and have the warning if the controller type can not be detected.
		$isOCS = str_ends_with($parentControllerClass, 'OCSController');
		if ($parentControllerClass !== 'Controller' && $parentControllerClass !== 'ApiController' && $parentControllerClass !== 'OCSController' && !$isOCS) {
			Logger::warning($routeName, 'You are extending a custom controller class. Make sure that it ends with "OCSController" if it extends "OCSController" itself.');
		} elseif ($isOCS !== ($pathPrefix === '/ocs/v2.php')) {
			Logger::warning($routeName, 'Do not mix OCS/non-OCS routes and non-OCS/OCS controllers!');
		}

		$controllerScopes = Helpers::getOpenAPIAttributeScopes($controllerClass, $routeName);
		if (Helpers::classMethodHasAnnotationOrAttribute($controllerClass, 'IgnoreOpenAPI')) {
			if ($controllerScopes === [] || (in_array('ignore', $controllerScopes, true) && count($controllerScopes) === 1)) {
				Logger::debug($routeName, "Controller '" . $controllerName . "' ignored because of IgnoreOpenAPI attribute");
				continue;
			}

			Logger::panic($routeName, "Controller '" . $controllerName . "' is marked as ignore but also has other scopes");
		}

		if (in_array('ignore', $controllerScopes, true)) {
			if (count($controllerScopes) === 1) {
				Logger::debug($routeName, "Controller '" . $controllerName . "' ignored because of OpenAPI attribute");
				continue;
			}

			Logger::panic($routeName, "Controller '" . $controllerName . "' is marked as ignore but also has other scopes");
		}

		$tagName = implode('_', array_map(fn (string $s) => strtolower($s), Helpers::splitOnUppercaseFollowedByNonUppercase($controllerName)));
		$doc = $controllerClass->getDocComment()?->getText();
		if ($doc != null && count(array_filter($tags, fn (array $tag): bool => $tag['name'] === $tagName)) == 0) {
			$classDescription = [];

			$docNodes = $phpDocParser->parse(new TokenIterator($lexer->tokenize($doc)))->children;
			foreach ($docNodes as $docNode) {
				if ($docNode instanceof PhpDocTextNode) {
					$block = Helpers::cleanDocComment($docNode->text);
					if ($block === '') {
						continue;
					}
					$classDescription[] = $block;
				}
			}

			if ($classDescription !== []) {
				$tags[] = [
					'name' => $tagName,
					'description' => implode("\n", $classDescription),
				];
			}
		}

		$methodFunction = null;
		/** @var ClassMethod $classMethod */
		foreach ($nodeFinder->findInstanceOf($controllerClass->stmts, ClassMethod::class) as $classMethod) {
			if ($classMethod->name == $methodName) {
				$methodFunction = $classMethod;
				break;
			}
		}
		if ($methodFunction == null) {
			Logger::panic($routeName, 'Missing controller method');
		}

		$isNoCSRFRequired = Helpers::classMethodHasAnnotationOrAttribute($methodFunction, 'NoCSRFRequired');
		$isCORS = Helpers::classMethodHasAnnotationOrAttribute($methodFunction, 'CORS');
		$isPublic = Helpers::classMethodHasAnnotationOrAttribute($methodFunction, 'PublicPage');
		$isAdmin = !Helpers::classMethodHasAnnotationOrAttribute($methodFunction, 'NoAdminRequired') && !$isPublic;
		$isDeprecated = Helpers::classMethodHasAnnotationOrAttribute($methodFunction, 'deprecated');
		$isIgnored = Helpers::classMethodHasAnnotationOrAttribute($methodFunction, 'IgnoreOpenAPI');
		$isPasswordConfirmation = Helpers::classMethodHasAnnotationOrAttribute($methodFunction, 'PasswordConfirmationRequired');
		$isExApp = Helpers::classMethodHasAnnotationOrAttribute($methodFunction, 'ExAppRequired');
		$isCORS = Helpers::classMethodHasAnnotationOrAttribute($methodFunction, 'CORS');
		$scopes = Helpers::getOpenAPIAttributeScopes($classMethod, $routeName);

		if ($isIgnored) {
			if ($scopes === [] || (in_array('ignore', $scopes, true) && count($scopes) === 1)) {
				Logger::debug($routeName, 'Route ignored because of IgnoreOpenAPI attribute');
				continue;
			}

			Logger::panic($routeName, 'Route is marked as ignore but also has other scopes');
		}

		if ($scopes === []) {
			if ($controllerScopes !== []) {
				$scopes = $controllerScopes;
			} elseif (!$isOCS) {
				$scopes = ['ignore'];
			} elseif ($isExApp) {
				$scopes = ['ex_app'];
			} elseif ($isAdmin) {
				$scopes = ['administration'];
			} else {
				$scopes = ['default'];
			}
		}

		if (in_array('ignore', $scopes, true)) {
			if (count($scopes) === 1) {
				Logger::debug($routeName, 'Route ignored because of OpenAPI attribute');
				continue;
			}

			Logger::panic($routeName, 'Route is marked as ignore but also has other scopes');
		}

		$routeTags = Helpers::getOpenAPIAttributeTagsByScope($classMethod, $routeName, $tagName, reset($scopes));

		if ($isOCS && !array_key_exists('OCSMeta', $schemas)) {
			$schemas['OCSMeta'] = [
				'type' => 'object',
				'required' => [
					'status',
					'statuscode',
				],
				'properties' => [
					'status' => ['type' => 'string'],
					'statuscode' => ['type' => 'integer'],
					'message' => ['type' => 'string'],
					'totalitems' => ['type' => 'string'],
					'itemsperpage' => ['type' => 'string'],
				],
			];
		}

		$classMethodInfo = ControllerMethod::parse($routeName, $definitions, $methodFunction, $isAdmin, $isDeprecated, $isPasswordConfirmation, $isCORS);
		if ($classMethodInfo->returns !== []) {
			Logger::error($routeName, 'Returns an invalid response');
			continue;
		}
		if (count($classMethodInfo->responses) == 0) {
			Logger::error($routeName, 'Returns no responses');
			continue;
		}

		$codeStatusCodes = [];
		/* @var Throw_ $throwStatement */
		foreach ($nodeFinder->findInstanceOf($methodFunction->stmts, Throw_::class) as $throwStatement) {
			if ($throwStatement->expr instanceof New_ && $throwStatement->expr->class instanceof Name) {
				$type = $throwStatement->expr->class->getLast();
				$statusCode = StatusCodes::resolveException($routeName, $type);
				if ($statusCode != null) {
					$codeStatusCodes[] = $statusCode;
				}
			}
		}

		$docStatusCodes = array_map(fn (ControllerMethodResponse $response): int => $response->statusCode, array_filter($classMethodInfo->responses, fn (?ControllerMethodResponse $response): bool => $response != null));
		$missingDocStatusCodes = array_unique(array_filter(array_diff($codeStatusCodes, $docStatusCodes), fn (int $code): bool => $code < 500));

		if ($missingDocStatusCodes !== []) {
			Logger::error($routeName, 'Returns undocumented status codes: ' . implode(', ', $missingDocStatusCodes));
			continue;
		}

		$operationId = [
			$tagName,
			...Helpers::splitOnUppercaseFollowedByNonUppercase($methodName)
		];
		if ($postfix !== null) {
			$operationId[] = $postfix;
		}
		$operationId = strtolower(implode('-', $operationId));

		if (in_array($operationId, $operationIds, true)) {
			Logger::panic($routeName, 'Route is not unique! If you want to have two routes pointing to the same controller method you must specify a postfix on at least one of the routes.');
		}
		$operationIds[] = $operationId;

		foreach ($scopes as $scope) {
			$routes[$scope] ??= [];
			$routes[$scope][] = new Route(
				$routeName,
				$routeTags[$scope] ?? [$tagName],
				$operationId,
				$verb,
				$url,
				$requirements,
				$defaults,
				$classMethodInfo,
				$isOCS,
				$isCORS,
				$isNoCSRFRequired,
				$isPublic,
			);
		}

		Logger::debug($routeName, 'Route generated');
	}
}

$tagNames = [];
if ($useTags) {
	foreach ($routes as $scopeRoutes) {
		foreach ($scopeRoutes as $route) {
			foreach ($route->tags as $tag) {
				if (!in_array($tag, $tagNames)) {
					$tagNames[] = $tag;
				}
			}
		}
	}
}

$scopePaths = [];

foreach ($routes as $scope => $scopeRoutes) {
	foreach ($scopeRoutes as $route) {
		$pathParameters = [];
		$urlParameters = [];

		preg_match_all('/{[^}]*}/', $route->url, $urlParameters);
		$urlParameters = array_map(fn (string $name): string => substr($name, 1, -1), $urlParameters[0]);

		$unusedRequirements = array_diff(array_keys($route->requirements), $urlParameters);
		if ($unusedRequirements !== []) {
			Logger::error($route->name, 'Unused requirements: ' . implode(',', $unusedRequirements));
		}

		foreach ($urlParameters as $urlParameter) {
			$matchingParameters = array_filter($route->controllerMethod->parameters, fn (ControllerMethodParameter $param): bool => $param->name == $urlParameter);
			$requirement = $route->requirements[$urlParameter] ?? null;
			if (count($matchingParameters) == 1) {
				$parameter = $matchingParameters[array_keys($matchingParameters)[0]];
				if ($parameter?->methodParameter == null && ($route->requirements == null || !array_key_exists($urlParameter, $route->requirements))) {
					Logger::error($route->name, "Unable to find parameter for '" . $urlParameter . "'");
					continue;
				}

				$schema = $parameter->type->toArray(true);
				$description = $parameter?->docType != null && $parameter->docType->description != '' ? Helpers::cleanDocComment($parameter->docType->description) : null;
			} else {
				$schema = [
					'type' => 'string',
				];
				$description = null;
			}

			if ($requirement != null) {
				if (!str_starts_with((string)$requirement, '^')) {
					$requirement = '^' . $requirement;
				}
				if (!str_ends_with((string)$requirement, '$')) {
					$requirement .= '$';
				}
			}

			if ($schema['type'] == 'string') {
				if ($urlParameter == 'apiVersion') {
					if ($requirement == null) {
						Logger::error($route->name, 'Missing requirement for apiVersion');
						continue;
					}
					preg_match("/^\^\(([v0-9-.|]*)\)\\$$/m", (string)$requirement, $matches);
					if (count($matches) == 2) {
						$enum = explode('|', $matches[1]);
					} else {
						Logger::error($route->name, 'Invalid requirement for apiVersion');
						continue;
					}
					$schema['enum'] = $enum;
					$schema['default'] = end($enum);
				} elseif ($requirement != null) {
					$schema['pattern'] = $requirement;
				}
			}

			if (array_key_exists($urlParameter, $route->defaults)) {
				$schema['default'] = $route->defaults[$urlParameter];
			}

			$parameter = [
				'name' => $urlParameter,
				'in' => 'path',
			];
			if ($description !== null) {
				$parameter['description'] = $description;
			}
			$parameter['required'] = true;
			$parameter['schema'] = $schema;

			$pathParameters[] = $parameter;
		}

		$queryParameters = [];
		$bodyParameters = [];
		foreach ($route->controllerMethod->parameters as $parameter) {
			$alreadyInPath = false;
			foreach ($pathParameters as $pathParameter) {
				if ($pathParameter['name'] == $parameter->name) {
					$alreadyInPath = true;
					break;
				}
			}
			if (!$alreadyInPath) {
				if (in_array(strtolower($route->verb), ['put', 'post', 'patch'])) {
					$bodyParameters[] = $parameter;
				} else {
					$queryParameters[] = $parameter;
				}
			}
		}

		$mergedResponses = [];
		foreach (array_unique(array_map(fn (ControllerMethodResponse $response): int => $response->statusCode, array_filter($route->controllerMethod->responses, fn (?ControllerMethodResponse $response): bool => $response != null))) as $statusCode) {
			if ($firstStatusCode && $mergedResponses !== []) {
				break;
			}

			$statusCodeResponses = array_filter($route->controllerMethod->responses, fn (?ControllerMethodResponse $response): bool => $response != null && $response->statusCode == $statusCode);
			$headers = [];
			foreach ($statusCodeResponses as $response) {
				if ($response->headers !== null) {
					$headers = array_merge($headers, $response->headers);
				}
			}

			$mergedContentTypeResponses = [];
			foreach (array_unique(array_map(fn (ControllerMethodResponse $response): ?string => $response->contentType, array_filter($statusCodeResponses, fn (ControllerMethodResponse $response): bool => $response->contentType != null))) as $contentType) {
				if ($firstContentType && $mergedContentTypeResponses !== []) {
					break;
				}

				/** @var ControllerMethodResponse[] $contentTypeResponses */
				$contentTypeResponses = array_values(array_filter($statusCodeResponses, fn (ControllerMethodResponse $response): bool => $response->contentType == $contentType));

				$hasEmpty = array_filter($contentTypeResponses, fn (ControllerMethodResponse $response): bool => $response->type == null) !== [];
				$uniqueResponses = array_values(array_intersect_key($contentTypeResponses, array_unique(array_map(fn (ControllerMethodResponse $response): array|\stdClass => $response->type->toArray(), array_filter($contentTypeResponses, fn (ControllerMethodResponse $response): bool => $response->type != null)), SORT_REGULAR)));
				if (count($uniqueResponses) == 1) {
					if ($hasEmpty) {
						$mergedContentTypeResponses[$contentType] = [];
					} else {
						$schema = Helpers::cleanEmptyResponseArray($contentTypeResponses[0]->type->toArray());
						$mergedContentTypeResponses[$contentType] = ['schema' => Helpers::wrapOCSResponse($route, $contentTypeResponses[0], $schema)];
					}
				} else {
					$mergedContentTypeResponses[$contentType] = [
						'schema' => [
							[$hasEmpty ? 'anyOf' : 'oneOf' => array_map(function (ControllerMethodResponse $response) use ($route): \stdClass|array {
								$schema = Helpers::cleanEmptyResponseArray($response->type->toArray());
								return Helpers::wrapOCSResponse($route, $response, $schema);
							}, $uniqueResponses)],
						],
					];
				}
			}

			$response = [
				'description' => array_key_exists($statusCode, $route->controllerMethod->responseDescription) ? $route->controllerMethod->responseDescription[$statusCode] : '',
			];
			if ($headers !== []) {
				$response['headers'] = array_combine(
					array_keys($headers),
					array_map(
						fn (OpenApiType $type): array => [
							'schema' => $type->toArray(),
						],
						array_values($headers),
					),
				);
			}
			if ($mergedContentTypeResponses !== []) {
				$response['content'] = $mergedContentTypeResponses;
			}
			$mergedResponses[$statusCode] = $response;
		}

		$security = [];
		if ($route->isPublic) {
			// Add empty authentication, meaning that it's optional. We can't know if there is a difference in behaviour for authenticated vs. unauthenticated access on public pages (e.g. capabilities)
			$security[] = new stdClass();
		}
		if (!$route->isCORS) {
			// Bearer auth is not allowed on CORS routes
			$security[] = ['bearer_auth' => []];
		}
		// Add basic auth last, so it's only fallback if bearer is available
		$security[] = ['basic_auth' => []];

		$operation = [
			'operationId' => $route->operationId,
		];
		if ($route->controllerMethod->summary !== null) {
			$operation['summary'] = $route->controllerMethod->summary;
		}
		if ($route->controllerMethod->description !== []) {
			$operation['description'] = implode("\n", $route->controllerMethod->description);
		}
		if ($route->controllerMethod->isDeprecated) {
			$operation['deprecated'] = true;
		}
		if ($useTags) {
			$operation['tags'] = $route->tags;
		}
		$operation['security'] = $security;

		if ($bodyParameters !== []) {
			$requiredBodyParameters = [];

			foreach ($bodyParameters as $bodyParameter) {
				$required = !$bodyParameter->type->nullable && !$bodyParameter->type->hasDefaultValue;
				if ($required) {
					$requiredBodyParameters[] = $bodyParameter->name;
				}
			}

			$required = $requiredBodyParameters !== [];

			$schema = [
				'type' => 'object',
			];
			if ($required) {
				$schema['required'] = $requiredBodyParameters;
			}
			$schema['properties'] = [];
			foreach ($bodyParameters as $bodyParameter) {
				$schema['properties'][$bodyParameter->name] = $bodyParameter->type->toArray();
			}

			$operation['requestBody'] = [
				'required' => $required,
				'content' => [
					'application/json' => [
						'schema' => $schema,
					],
				],
			];
		}

		$parameters = $pathParameters;
		foreach ($queryParameters as $queryParameter) {
			$parameter = [
				'name' => $queryParameter->name . ($queryParameter->type->type === 'array' ? '[]' : ''),
				'in' => 'query',
			];
			if ($queryParameter->docType !== null && $queryParameter->docType->description !== '') {
				$parameter['description'] = Helpers::cleanDocComment($queryParameter->docType->description);
			}
			if (!$queryParameter->type->nullable && !$queryParameter->type->hasDefaultValue) {
				$parameter['required'] = true;
			}
			if ($queryParameter->type->deprecated) {
				$parameter['deprecated'] = true;
			}
			$parameter['schema'] = $queryParameter->type->toArray(true);

			$parameters[] = $parameter;
		}
		if ($route->isOCS || !$route->isNoCSRFRequired) {
			$parameters[] = [
				'name' => 'OCS-APIRequest',
				'in' => 'header',
				'description' => 'Required to be true for the API request to pass',
				'required' => true,
				'schema' => [
					'type' => 'boolean',
					'default' => true,
				],
			];
		}
		if ($parameters !== []) {
			$operation['parameters'] = $parameters;
		}

		$operation['responses'] = $mergedResponses;

		$scopePaths[$scope] ??= [];
		$scopePaths[$scope][$route->url] ??= [];

		if (!array_key_exists($route->url, $openapi['paths'])) {
			$openapi['paths'][$route->url] = [];
		}

		$verb = strtolower($route->verb);
		if (array_key_exists($verb, $scopePaths[$scope][$route->url])) {
			Logger::error($route->name, "Operation '" . $route->verb . "' already set for path '" . $route->url . "'");
		}

		$scopePaths[$scope][$route->url][$verb] = $operation;
	}
}

if ($appIsCore) {
	$schemas['Status'] = [
		'type' => 'object',
		'required' => [
			'installed',
			'maintenance',
			'needsDbUpgrade',
			'version',
			'versionstring',
			'edition',
			'productname',
			'extendedSupport'
		],
		'properties' => [
			'installed' => [
				'type' => 'boolean',
			],
			'maintenance' => [
				'type' => 'boolean',
			],
			'needsDbUpgrade' => [
				'type' => 'boolean',
			],
			'version' => [
				'type' => 'string',
			],
			'versionstring' => [
				'type' => 'string',
			],
			'edition' => [
				'type' => 'string',
			],
			'productname' => [
				'type' => 'string',
			],
			'extendedSupport' => [
				'type' => 'boolean',
			],
		],
	];
	$scopePaths['default']['/status.php'] = [
		'get' => [
			'operationId' => 'get-status',
			'responses' => [
				200 => [
					'description' => 'Status returned',
					'content' => [
						'application/json' => [
							'schema' => [
								'$ref' => '#/components/schemas/Status'
							],
						],
					],
				],
			],
		],
	];
}

if (count($schemas) == 0 && count($routes) == 0) {
	Logger::error('app', 'No spec generated');
}

ksort($schemas);

if ($useTags) {
	$openapi['tags'] = $tags;
}

$hasSingleScope = count($scopePaths) <= 1;
$fullScopePathArrays = [];

if (!$hasSingleScope) {
	$scopePaths['full'] = [];
} elseif ($scopePaths === []) {
	if (isset($schemas['Capabilities']) || isset($schemas['PublicCapabilities'])) {
		Logger::debug('app', 'Generating default scope without routes to populate capabilities');
		$scopePaths['default'] = [];
	} else {
		Logger::panic('app', 'No routes or capabilities defined');
	}
}

$usedSchemas = ['Capabilities', 'PublicCapabilities'];

foreach (glob(dirname($out) . '/openapi*.json') as $path) {
	unlink($path);
}

foreach ($scopePaths as $scope => $paths) {
	$openapiScope = $openapi;

	$scopeSuffix = ($hasSingleScope || $scope === 'default') ? '' : '-' . $scope;
	$openapiScope['info']['title'] .= $scopeSuffix;
	$openapiScope['paths'] = $paths;

	if ($scope !== 'full' && !$hasSingleScope) {
		$fullScopePathArrays[] = $paths;
	}

	if ($scope === 'full') {
		foreach ($fullScopePathArrays as $fullScopePaths) {
			foreach ($fullScopePaths as $fullScopePath => $operations) {
				$openapiScope['paths'][$fullScopePath] ??= [];
				foreach ($operations as $method => $operation) {
					// Don't need to check if we overwrite an existing operation,
					// as we already check for collisions when validating the routes.
					$openapiScope['paths'][$fullScopePath][$method] = $operation;
				}
			}
		}
		$openapiScope['components']['schemas'] = $schemas;
	} else {
		$usedRefs = [];
		foreach ($paths as $urlRoutes) {
			foreach ($urlRoutes as $routeData) {
				foreach ($routeData['responses'] as $responseData) {
					if (isset($responseData['content']) && $responseData['content'] !== []) {
						$usedRefs[] = Helpers::collectUsedRefs($responseData['content']);
					}
				}
				if (isset($routeData['requestBody']['content']) && $routeData['requestBody']['content'] !== []) {
					$usedRefs[] = Helpers::collectUsedRefs($routeData['requestBody']['content']);
				}
			}
		}

		$usedRefs = array_merge(...$usedRefs);

		$scopedSchemas = [];
		while ($usedRef = array_shift($usedRefs)) {
			if (!str_starts_with((string)$usedRef, '#/components/schemas/')) {
				continue;
			}

			$schemaName = substr((string)$usedRef, strlen('#/components/schemas/'));

			if (!isset($schemas[$schemaName])) {
				Logger::error('app', "Schema $schemaName used by scope $scope is not defined");
			}

			$newRefs = Helpers::collectUsedRefs($schemas[$schemaName]);
			foreach ($newRefs as $newRef) {
				if (!isset($scopedSchemas[substr((string)$newRef, strlen('#/components/schemas/'))])) {
					$usedRefs[] = $newRef;
				}
			}

			$scopedSchemas[$schemaName] = $schemas[$schemaName];
			$usedSchemas[] = $schemaName;
		}

		if (isset($schemas['Capabilities'])) {
			$scopedSchemas['Capabilities'] = $schemas['Capabilities'];
		}
		if (isset($schemas['PublicCapabilities'])) {
			$scopedSchemas['PublicCapabilities'] = $schemas['PublicCapabilities'];
		}

		if ($scopedSchemas === []) {
			$scopedSchemas = new stdClass();
		} else {
			ksort($scopedSchemas);
		}

		$openapiScope['components']['schemas'] = $scopedSchemas;
	}

	$pathsCount = count($openapiScope['paths']);
	if ($pathsCount === 0) {
		// Make sure the paths array is always a dictionary
		$openapiScope['paths'] = new stdClass();
	}

	$startExtension = strrpos($out, '.');
	if ($startExtension !== false) {
		// Path + filename (without extension)
		$path = substr($out, 0, $startExtension);
		// Extension
		$extension = substr($out, $startExtension);
		$scopeOut = $path . $scopeSuffix . $extension;
	} else {
		$scopeOut = $out . $scopeSuffix;
	}

	file_put_contents($scopeOut, json_encode($openapiScope, Helpers::jsonFlags()) . "\n");

	Logger::info('app', 'Generated scope ' . $scope . ' with ' . $pathsCount . ' routes!');
}

$unusedSchemas = array_diff(array_keys($schemas), $usedSchemas);
if ($unusedSchemas !== []) {
	Logger::error('app', 'Unused schemas: ' . implode(', ', $unusedSchemas));
}

if (Logger::$errorCount > 0) {
	Logger::panic('app', 'Encountered ' . Logger::$errorCount . ' errors that need to be fixed!');
}
