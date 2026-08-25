<?php

/**
 * SPDX-FileCopyrightText: 2026 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OpenAPIExtractor;

use PhpParser\Node\Stmt\ClassLike;
use PhpParser\NodeFinder;
use PhpParser\NodeTraverser;
use PhpParser\Parser;

/**
 * Lazily resolves a class, interface, trait or enum by its fully qualified name,
 * by mapping namespace prefixes to source directories, PSR-4 style.
 */
class ClassResolver {
	/** @var array<string, ClassLike|false> */
	private array $cache = [];

	/** @var array<string, string> Namespace prefix => source directory */
	private readonly array $namespaceRoots;

	/** @param array<string, string> $namespaceRoots Namespace prefix => source directory */
	public function __construct(
		private readonly Parser $astParser,
		private readonly NodeTraverser $nodeTraverser,
		private readonly NodeFinder $nodeFinder,
		array $namespaceRoots,
	) {
		$this->namespaceRoots = array_combine(
			array_map(static fn (string $prefix): string => trim($prefix, '\\'), array_keys($namespaceRoots)),
			array_values($namespaceRoots),
		);
	}

	/** Returns null if the class can not be found, e.g. because it is outside of the known namespace roots. */
	public function resolve(string $fqcn): ?ClassLike {
		$fqcn = ltrim($fqcn, '\\');
		if (!array_key_exists($fqcn, $this->cache)) {
			$this->cache[$fqcn] = $this->load($fqcn) ?? false;
		}

		$node = $this->cache[$fqcn];
		return $node !== false ? $node : null;
	}

	private function load(string $fqcn): ?ClassLike {
		$path = $this->findFile($fqcn);
		if ($path === null || !is_file($path)) {
			return null;
		}

		$contents = file_get_contents($path);
		if ($contents === false) {
			return null;
		}

		/** @var ClassLike $node */
		foreach ($this->nodeFinder->findInstanceOf($this->nodeTraverser->traverse($this->astParser->parse($contents)), ClassLike::class) as $node) {
			if ($node->namespacedName?->toString() === $fqcn) {
				$node->setAttribute('sourceFile', $path);
				return $node;
			}
		}

		return null;
	}

	/** Maps the class name to a file path via its longest matching namespace prefix. */
	private function findFile(string $fqcn): ?string {
		$bestPrefix = null;
		foreach (array_keys($this->namespaceRoots) as $prefix) {
			if (!str_starts_with($fqcn . '\\', $prefix . '\\')) {
				continue;
			}
			if ($bestPrefix === null || strlen($prefix) > strlen($bestPrefix)) {
				$bestPrefix = $prefix;
			}
		}

		if ($bestPrefix === null) {
			return null;
		}

		$relativeName = substr($fqcn, strlen($bestPrefix) + 1);
		return $this->namespaceRoots[$bestPrefix] . '/' . str_replace('\\', '/', $relativeName) . '.php';
	}
}
