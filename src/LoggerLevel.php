<?php

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-License-Identifier: AGPL-3.0-or-later
 */

namespace OpenAPIExtractor;

enum LoggerLevel: string {
	case Debug = 'Debug';
	case Info = 'Info';
	case Warning = 'Warning';
	case Error = 'Error';
}
