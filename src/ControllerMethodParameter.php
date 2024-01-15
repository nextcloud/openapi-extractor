<?php

namespace OpenAPIExtractor;

use PhpParser\Node\Param;

class ControllerMethodParameter {
	public OpenApiType $type;

	public function __construct(string $context, array $definitions, public string $name, public Param $methodParameter, public ?OpenApiType $docType) {
		if ($docType != null) {
			$this->type = $this->docType;
		} else {
			$this->type = OpenApiType::resolve($context, $definitions, $methodParameter->type);
		}
		if ($methodParameter->default != null) {
			try {
				$this->type->defaultValue = Helpers::exprToValue($context, $methodParameter->default);
				$this->type->hasDefaultValue = true;
			} catch (UnsupportedExprException $e) {
				Logger::debug($context, $e);
			}
		}
	}
}
