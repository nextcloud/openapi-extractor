<?php

namespace OpenAPIExtractor;

use Exception;

enum LoggerColor {
	case Green;
	case Yellow;
	case Red;
}

class Logger {
	static bool $exitOnError = true;

	protected static function log(LoggerColor $color, string $level, string $context, string $text): void {
		print(self::format($color, $level, $context, $text));
	}

	protected static function format(LoggerColor $color, string $level, string $context, string $text): string {
		$colorCode = match ($color) {
			LoggerColor::Green => "",
			LoggerColor::Yellow => "\e[33m",
			LoggerColor::Red => "\e[91m",
		};
		return $colorCode . $level . ": " . $context . ": " . $text . "\n\e[0m";
	}

	public static function info(string $context, string $text): void {
		self::log(LoggerColor::Green, "Info", $context, $text);
	}

	public static function warning(string $context, string $text): void {
		self::log(LoggerColor::Yellow, "Warning", $context, $text);
	}

	public static function error(string $context, string $text): void {
		if (self::$exitOnError) {
			throw new Exception(self::format(LoggerColor::Red, "Error", $context, $text));
		} else {
			self::log(LoggerColor::Red, "Error", $context, $text);
		}
	}

	public static function panic(string $context, string $text): void {
		throw new Exception(self::format(LoggerColor::Red, "Panic", $context, $text));
	}
}
