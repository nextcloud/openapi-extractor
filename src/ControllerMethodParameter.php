<?php

namespace OpenAPIExtractor;


use PhpParser\Node\Expr;
use PhpParser\Node\Expr\Array_;
use PhpParser\Node\Expr\ArrayItem;
use PhpParser\Node\Expr\ConstFetch;
use PhpParser\Node\Expr\UnaryMinus;
use PhpParser\Node\Param;
use PhpParser\Node\Scalar\LNumber;
use PhpParser\Node\Scalar\String_;
use PHPStan\PhpDocParser\Ast\PhpDoc\ParamTagValueNode;

class ControllerMethodParameter {
	public OpenApiType $type;

	public function __construct(string $context, array $definitions, public string $name, public Param $methodParameter, public ?OpenApiType $docType) {
		if ($docType != null) {
			$this->type = $this->docType;
		} else {
			$this->type = OpenApiType::resolve($context, $definitions, $methodParameter->type);
		}
		if ($methodParameter->default != null) {
			$this->type->hasDefaultValue = true;
			$this->type->defaultValue = self::exprToValue($context, $methodParameter->default);
		}
	}

	private static function exprToValue(string $context, Expr $expr): mixed {
		if ($expr instanceof ConstFetch) {
			$value = $expr->name->getLast();
			return match ($value) {
				"null" => null,
				"true" => true,
				"false" => false,
				default => Logger::panic($context, "Unable to evaluate constant value '" . $value . "'"),
			};
		}
		if ($expr instanceof String_) {
			return $expr->value;
		}
		if ($expr instanceof LNumber) {
			return intval($expr->value);
		}
		if ($expr instanceof UnaryMinus) {
			return -self::exprToValue($context, $expr->expr);
		}
		if ($expr instanceof Array_) {
			$values = array_map(fn(ArrayItem $item) => self::exprToValue($context, $item), $expr->items);
			$filteredValues = array_filter($values, fn(mixed $value) => $value !== null);
			if (count($filteredValues) != count($values)) {
				return null;
			}
			return $values;
		}
		if ($expr instanceof ArrayItem) {
			return self::exprToValue($context, $expr->value);
		}
		if ($expr instanceof Expr\ClassConstFetch || $expr instanceof Expr\BinaryOp) {
			// Not supported
			return null;
		}

		Logger::panic($context, "Unable to evaluate expression '" . get_class($expr) . "'");
	}
}
