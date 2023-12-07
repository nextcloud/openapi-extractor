<?php

namespace OpenAPIExtractor;

use Exception;

class Logger {
	static bool $exitOnError = true;

	protected static function log(LoggerLevel $level, string $context, string $text): void {
		print(self::format($level, $context, $text));
	}

	protected static function format(LoggerLevel $level, string $context, string $text): string {
		$colorCode = match ($level) {
			LoggerLevel::Info => "",
			LoggerLevel::Warning => "\e[33m",
			LoggerLevel::Error => "\e[91m",
		};
		return $colorCode . $level->value . ": " . $context . ": " . $text . "\n\e[0m";
	}

	public static function info(string $context, string $text): void {
		self::log(LoggerLevel::Info, $context, $text);
	}

	public static function warning(string $context, string $text): void {
		self::log(LoggerLevel::Warning, $context, $text);
	}

	public static function error(string $context, string $text): void {
		if (self::$exitOnError) {
			throw new LoggerException(LoggerLevel::Error, $context, $text);
		} else {
			self::log(LoggerLevel::Error, $context, $text);
		}
	}

	public static function panic(string $context, string $text): void {
		throw new LoggerException(LoggerLevel::Error, $context, $text);
	}
}
