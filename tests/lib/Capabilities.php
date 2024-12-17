<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Notifications;

use OCP\Capabilities\ICapability;

class Capabilities implements ICapability {
	/**
	 * @return array{test: array{a: int}}
	 */
	public function getCapabilities(): array {
		return [];
	}
}
