<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Notifications;

use OCP\Capabilities\ICapability;

/**
 * @psalm-import-type NotificationsSchemaOnlyInCapabilities from ResponseDefinitions
 */
class Capabilities implements ICapability {
	/**
	 * @return array{test: array{a: int, b: NotificationsSchemaOnlyInCapabilities}}
	 */
	public function getCapabilities(): array {
		return [];
	}
}
