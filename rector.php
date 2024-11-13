<?php

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

declare(strict_types=1);

use Rector\Config\RectorConfig;

return RectorConfig::configure()
	->withPaths([__DIR__])
	->withSkipPath(__DIR__ . '/vendor')
	->withPhpSets()
	->withPreparedSets(
		deadCode: true,
		codeQuality: true,
		typeDeclarations: true,
		strictBooleans: true,
	);
