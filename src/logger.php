<?php

namespace OpenAPIExtractor;

use Exception;

class Logger {
	static bool $exitOnError = true;

	protected static function log(string $level, string $context, string $text): void {
		print(self::format($level, $context, $text));
	}

	protected static function format(string $level, string $context, string $text): string {
		return $level . ": " . $context . ": " . $text . "\n";
	}

	public static function info(string $context, string $text): void {
		self::log("Info", $context, $text);
	}

	public static function warning(string $context, string $text): void {
		self::log("Warning", $context, $text);
	}

	public static function error(string $context, string $text): void {
		if (self::$exitOnError) {
			throw new Exception(self::format("Error", $context, $text));
		} else {
			self::log("Error", $context, $text);
		}
	}

	public static function panic(string $context, string $text): void {
		throw new Exception(self::format("Panic", $context, $text));
	}
}
