<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OpenAPIExtractor;

use PhpParser\Node\Stmt\Enum_;
use PhpParser\Node\Stmt\EnumCase;
use PHPStan\PhpDocParser\Ast\PhpDoc\PhpDocTextNode;
use PHPStan\PhpDocParser\Lexer\Lexer;
use PHPStan\PhpDocParser\Parser\PhpDocParser;
use PHPStan\PhpDocParser\Parser\TokenIterator;

/** Resolves a backed enum's fully qualified class name into its OpenAPI representation. */
class EnumResolver {
	/** @var array<string, OpenApiType|false> */
	private array $cache = [];

	public function __construct(
		private readonly ClassResolver $classResolver,
		private readonly PhpDocParser $phpDocParser,
		private readonly Lexer $lexer,
	) {
	}

	public function resolve(string $fqcn): ?OpenApiType {
		$fqcn = ltrim($fqcn, '\\');
		if (!array_key_exists($fqcn, $this->cache)) {
			$this->cache[$fqcn] = $this->load($fqcn) ?? false;
		}

		$enum = $this->cache[$fqcn];
		return $enum !== false ? $enum : null;
	}

	private function load(string $fqcn): ?OpenApiType {
		$node = $this->classResolver->resolve($fqcn);
		if (!$node instanceof Enum_) {
			return null;
		}

		$path = $node->getAttribute('sourceFile', $fqcn);

		if ($node->scalarType === null) {
			Logger::debug($path, "Enum '" . $fqcn . "' is not backed and can therefore not be used as an OpenAPI type. Use 'enum " . $node->name->name . ": string' or 'enum " . $node->name->name . ": int' instead.");
			return null;
		}

		$values = [];
		foreach ($node->stmts as $stmt) {
			if ($stmt instanceof EnumCase && $stmt->expr !== null) {
				$values[] = Helpers::exprToValue($path . ': ' . $fqcn . '::' . $stmt->name->name, $stmt->expr);
			}
		}

		$description = null;
		$doc = $node->getDocComment()?->getText();
		if ($doc != null) {
			$descriptionLines = [];
			$docNodes = $this->phpDocParser->parse(new TokenIterator($this->lexer->tokenize($doc)))->children;
			foreach ($docNodes as $docNode) {
				if ($docNode instanceof PhpDocTextNode) {
					$block = Helpers::cleanDocComment($docNode->text);
					if ($block !== '') {
						$descriptionLines[] = $block;
					}
				}
			}
			if ($descriptionLines !== []) {
				$description = implode("\n", $descriptionLines);
			}
		}

		return new OpenApiType(
			context: $path,
			type: $node->scalarType->name === 'int' || $node->scalarType->name === 'integer' ? 'integer' : 'string',
			format: $node->scalarType->name === 'int' || $node->scalarType->name === 'integer' ? 'int64' : null,
			description: $description,
			enum: $values,
		);
	}
}
