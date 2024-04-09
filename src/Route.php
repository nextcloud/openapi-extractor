<?php

namespace OpenAPIExtractor;

class Route {
	public function __construct(
		public string $name,
		public array $tags,
		public string $operationId,
		public string $verb,
		public string $url,
		public array $requirements,
		public array $defaults,
		public ControllerMethod $controllerMethod,
		public bool $isOCS,
		public bool $isCORS,
		public bool $isCSRFRequired,
		public bool $isPublic,
	) {
	}

	public static function parseRoutes(string $path): array {
		$content = file_get_contents($path);
		if (str_contains($content, 'return ')) {
			if (str_contains($content, '$this')) {
				preg_match('/return ([^;]*);/', $content, $matches);
				return self::includeRoutes("<?php\nreturn ${matches[1]};");
			}
			return include($path);
		} elseif (str_contains($content, 'registerRoutes')) {
			preg_match_all('/registerRoutes\(.*?\$this,.*?(\[[^;]*)\);/s', $content, $matches);
			return array_merge(...array_map(fn (string $match) => self::includeRoutes("<?php\nreturn $match;"), $matches[1]));
		}

		Logger::warning('Routes', 'Unknown routes.php format');
		return [];
	}

	private static function includeRoutes(string $code): array {
		$tmpPath = tempnam(sys_get_temp_dir(), "routes-");
		file_put_contents($tmpPath, $code);
		$routes = include($tmpPath);
		unlink($tmpPath);

		return $routes;
	}
}
