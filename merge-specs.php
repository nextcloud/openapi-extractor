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
use stdClass;

$command = new Command('merge-specs.php', 'Merge multiple Nextcloud OpenAPI specs into one');
$command
	->option('--merged <merged>')
	->option('--core <core>')
	->option('--openapi-version', 'OpenAPI version to use', null, '3.0.3')
	->option('--first-status-code', 'Only output the first status code')
	->argument('<[specs...]>')
	->parse($_SERVER['argv']);

/**
 * @var string $mergedSpecPath
 */
$mergedSpecPath = $command->values()['<merged>'];
/**
 * @var string $coreSpecPath
 */
$coreSpecPath = $command->values()['<core>'];
$openapiVersion = $command->openapiVersion ?? '3.0.3';
$firstStatusCode = $command->firstStatusCode ?? false;
/**
 * @var string[] $otherSpecPaths
 */
$otherSpecPaths = $command->specs;

$coreSpec = loadSpec($coreSpecPath);

$capabilities = collectCapabilities($coreSpec);

$data = [
	'openapi' => $openapiVersion,
	'info' => [
		'title' => 'nextcloud',
		'description' => 'Nextcloud APIs',
		'license' => Helpers::license($openapiVersion, 'agpl'),
		'version' => '0.0.1',
	],
	'security' => [
		[
			'basic_auth' => [],
		],
		[
			'bearer_auth' => [],
		],
	],
	'tags' => rewriteTags($coreSpec),
	'components' => [
		'securitySchemes' => Helpers::securitySchemes(),
		'schemas' => rewriteSchemaNames($coreSpec),
	],
	'paths' => rewriteOperations($coreSpec),
];

foreach ($otherSpecPaths as $specPath) {
	if ($specPath === $coreSpecPath) {
		continue;
	}
	$spec = loadSpec($specPath);
	$data['components']['schemas'] = array_merge($data['components']['schemas'], rewriteSchemaNames($spec));
	$data['tags'] = array_merge($data['tags'], rewriteTags($spec));
	$data['paths'] = array_merge($data['paths'], rewriteOperations($spec));
	$capabilities = array_merge($capabilities, collectCapabilities($spec));
}

$data
['paths']
['/ocs/v2.php/cloud/capabilities']
['get']
['responses']
['200']
['content']
['application/json']
['schema']
['properties']
['ocs']
['properties']
['data']
['properties']
['capabilities']
['anyOf']
	= array_map(fn (string $capability): array => ['$ref' => '#/components/schemas/' . $capability], $capabilities);

function loadSpec(string $path): array {
	return rewriteRefs(json_decode(file_get_contents($path), true));
}

function getAppID(array $spec): string {
	return explode('-', $spec['info']['title'])[0];
}

function rewriteRefs(array $spec): array {
	$readableAppID = Helpers::generateReadableAppID(getAppID($spec));
	array_walk_recursive($spec, function (mixed &$item, string $key) use ($readableAppID): void {
		if ($key === '$ref' && $item !== '#/components/schemas/OCSMeta') {
			$item = str_replace('#/components/schemas/', '#/components/schemas/' . $readableAppID, $item);
		}
	});
	return $spec;
}

function collectCapabilities(array $spec): array {
	$capabilities = [];
	$readableAppID = Helpers::generateReadableAppID(getAppID($spec));
	foreach (array_keys($spec['components']['schemas']) as $name) {
		if ($name == 'Capabilities' || $name == 'PublicCapabilities') {
			$capabilities[] = $readableAppID . $name;
		}
	}

	return $capabilities;
}

function rewriteSchemaNames(array $spec): array {
	$schemas = $spec['components']['schemas'];
	$readableAppID = Helpers::generateReadableAppID(getAppID($spec));
	return array_combine(
		array_map(fn (string $key): string => $key === 'OCSMeta' ? $key : $readableAppID . $key, array_keys($schemas)),
		array_values($schemas),
	);
}

function rewriteTags(array $spec): array {
	return array_map(function (array $tag) use ($spec) {
		$tag['name'] = getAppID($spec) . '/' . $tag['name'];
		return $tag;
	}, $spec['tags']);
}

function rewriteOperations(array $spec): array {
	global $firstStatusCode;

	foreach (array_keys($spec['paths']) as $path) {
		foreach (array_keys($spec['paths'][$path]) as $method) {
			if (!in_array($method, ['delete', 'get', 'post', 'put', 'patch', 'options'])) {
				continue;
			}
			$operation = &$spec['paths'][$path][$method];
			if (array_key_exists('operationId', $operation)) {
				$operation['operationId'] = getAppID($spec) . '-' . $operation['operationId'];
			}
			if (array_key_exists('tags', $operation)) {
				$operation['tags'] = array_map(fn (string $tag): string => getAppID($spec) . '/' . $tag, $operation['tags']);
			} else {
				$operation['tags'] = [getAppID($spec)];
			}
			if ($firstStatusCode && array_key_exists('responses', $operation)) {
				/** @var string $value */
				$value = array_key_first($operation['responses']);
				$operation['responses'] = [$value => $operation['responses'][$value]];
			}
			if (array_key_exists('security', $operation)) {
				$counter = count($operation['security']);
				for ($i = 0; $i < $counter; $i++) {
					if (count($operation['security'][$i]) == 0) {
						$operation['security'][$i] = new stdClass(); // When reading {} will be converted to [], so we have to fix it
					}
				}
			}
		}
	}
	return $spec['paths'];
}

file_put_contents($mergedSpecPath, json_encode($data, Helpers::jsonFlags()) . "\n");

if (Logger::$errorCount > 0) {
	Logger::panic('app', 'Encountered ' . Logger::$errorCount . ' errors that need to be fixed!');
}
