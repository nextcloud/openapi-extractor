<?php

namespace OpenAPIExtractor;

use cebe\openapi\spec\SecurityRequirement;

function generateReadableAppID(string $appID): string {
	return implode("", array_map(fn(string $s) => ucfirst($s), explode("_", $appID)));
}

function securitySchemes(): array {
	return [
		"basic_auth" => [
			"type" => "http",
			"scheme" => "basic",
		],
		"bearer_auth" => [
			"type" => "http",
			"scheme" => "bearer",
		],
	];
}

function securityRequirements(): array {
	return [
		[
			"basic_auth" => [],
		],
		[
			"bearer_auth" => [],
		],
	];
}

function jsonFlags(): int {
	return JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
}