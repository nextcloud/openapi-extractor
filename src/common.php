<?php

namespace OpenAPIExtractor;

function generateReadableAppID(string $appID): string {
	return implode("", array_map(fn(string $s) => ucfirst($s), explode("_", $appID)));
}

function jsonFlags(): int {
	return JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES;
}