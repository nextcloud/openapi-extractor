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
	->get
	->responses
	->{'200'}
	->content
	->{'application/json'}
	->schema
	->properties
	->ocs
	->properties
	->data
	->properties
	->capabilities
	->anyOf
		= array_map(static fn (string $capability): array => ['$ref' => '#/components/schemas/' . $capability], $capabilities);

function loadSpec(string $path): object {
	return rewriteRefs(json_decode(file_get_contents($path), false, 512, JSON_THROW_ON_ERROR));
}

function getAppID(object $spec): string {
	return explode('-', $spec->info->title)[0];
}

function rewriteRefs(object $spec): object {
	$readableAppID = Helpers::generateReadableAppID(getAppID($spec));
	object_walk_recursive($spec, static function (mixed &$item, string $key) use ($readableAppID): void {
		if ($key === '$ref' && $item !== '#/components/schemas/OCSMeta') {
			$item = str_replace('#/components/schemas/', '#/components/schemas/' . $readableAppID, $item);
		}
	});
	return $spec;
}

function object_walk_recursive(object $object, \Closure $callback): void {
	foreach (array_keys(get_object_vars($object)) as $key) {
		$callback($object->{$key}, $key);
		if (is_object($object->{$key})) {
			object_walk_recursive($object->{$key}, $callback);
		}
		if (is_array($object->{$key})) {
			foreach ($object->{$key} as $item) {
				if (is_object($item)) {
					object_walk_recursive($item, $callback);
				}
			}
		}
	}
}

function collectCapabilities(object $spec): array {
	$capabilities = [];
	$readableAppID = Helpers::generateReadableAppID(getAppID($spec));
	foreach (array_keys(get_object_vars($spec->components->schemas)) as $name) {
		if ($name === 'Capabilities' || $name === 'PublicCapabilities') {
			$capabilities[] = $readableAppID . $name;
		}
	}

	return $capabilities;
}

function rewriteSchemaNames(object $spec): array {
	$schemas = get_object_vars($spec->components->schemas);
	$readableAppID = Helpers::generateReadableAppID(getAppID($spec));
	return array_combine(
		array_map(static fn (string $key): string => $key === 'OCSMeta' ? $key : $readableAppID . $key, array_keys($schemas)),
		array_values($schemas),
	);
}

function rewriteTags(object $spec): array {
	return array_map(static function (object $tag) use ($spec) {
		$tag->name = getAppID($spec) . '/' . $tag->name;
		return $tag;
	}, $spec->tags);
}

function rewriteOperations(object $spec): array {
	global $firstStatusCode;

	foreach (array_keys(get_object_vars($spec->paths)) as $path) {
		foreach (array_keys(get_object_vars($spec->paths->{$path})) as $method) {
			if (!in_array($method, ['delete', 'get', 'post', 'put', 'patch', 'options'])) {
				continue;
			}
			$operation = &$spec->paths->{$path}->{$method};
			if (property_exists($operation, 'operationId')) {
				$operation->operationId = getAppID($spec) . '-' . $operation->operationId;
			}
			if (property_exists($operation, 'tags')) {
				$operation->tags = array_map(static fn (string $tag): string => getAppID($spec) . '/' . $tag, $operation->tags);
			} else {
				$operation->tags = [getAppID($spec)];
			}
			if ($firstStatusCode && property_exists($operation, 'responses')) {
				/** @var string $value */
				$value = array_key_first(get_object_vars($operation->responses));
				$response = $operation->responses->{$value};
				$operation->responses = new stdClass();
				$operation->responses->{$value} = $response;
			}
		}
	}
	return get_object_vars($spec->paths);
}

file_put_contents($mergedSpecPath, json_encode($data, Helpers::jsonFlags()) . "\n");

if (Logger::$errorCount > 0) {
	Logger::panic('app', 'Encountered ' . Logger::$errorCount . ' errors that need to be fixed!');
}
