<?php

namespace OpenAPIExtractor;

use Exception;

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
		public bool $isPublic,
	) {
	}
}

function parseRoutes(string $path): array {
	$content = file_get_contents($path);
	if (str_contains($content, "return ")) {
		return include($path);
	} elseif (str_contains($content, "registerRoutes")) {
		preg_match("/registerRoutes\(\\\$this, (\[[^;]*)\);/", $content, $matches);

		$tmpPath = tempnam(sys_get_temp_dir(), "routes-");
		file_put_contents($tmpPath, "<?php\nreturn " . $matches[1] . ";");
		$routes = include($tmpPath);
		unlink($tmpPath);

		return $routes;
	} else {
		throw new Exception("Unknown routes.php format");
	}
}
