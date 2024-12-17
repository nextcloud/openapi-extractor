<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2024 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OCA\Notifications;

use OCP\Capabilities\IPublicCapability;

class PublicCapabilities implements IPublicCapability {
	/**
	 * @return array{test: array{b: string}}
	 */
	public function getCapabilities(): array {
		return [];
	}
}
