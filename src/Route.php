<?php

namespace OpenAPIExtractor;

class Route {
	public function __construct(
		public string $name,
		public string $tag,
		public string $controllerName,
		public string $methodName,
		public ?string $postfix,
		public string $verb,
		public string $url,
		public array $requirements,
		public ControllerMethod $controllerMethod,
		public bool $isOCS,
		public bool $isCORS,
		public bool $isCSRFRequired,
		public bool $isPublic,
	) {
	}

	static function parseRoutes(string $path): array {
		$content = file_get_contents($path);
		if (str_contains($content, "return ")) {
			if (str_contains($content, "\$this")) {
				preg_match("/return ([^;]*);/", $content, $matches);
				return self::includeRoutes("<?php\nreturn " . $matches[1] . ";");
			}
			return include($path);
		} elseif (str_contains($content, "registerRoutes")) {
			preg_match("/registerRoutes\(.*?\\\$this,.*?(\[[^;]*)\);/s", $content, $matches);
			return self::includeRoutes("<?php\nreturn " . $matches[1] . ";");
		} else {
			Logger::panic("Routes", "Unknown routes.php format");
		}
	}

	private static function includeRoutes(string $code) {
		$tmpPath = tempnam(sys_get_temp_dir(), "routes-");
		file_put_contents($tmpPath, $code);
		$routes = include($tmpPath);
		unlink($tmpPath);

		return $routes;
	}
}
