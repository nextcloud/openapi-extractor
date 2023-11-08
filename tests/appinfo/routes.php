<?php

declare(strict_types=1);

/**
 * @copyright Copyright (c) 2023, Joas Schilling <coding@schilljs.com>
 * @copyright Copyright (c) 2016, ownCloud, Inc.
 *
 * @author Joas Schilling <coding@schilljs.com>
 *
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

return [
	'ocs' => [
		['name' => 'Settings#federationByController', 'url' => '/api/{apiVersion}/controller-scope', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#ignoreByMethod', 'url' => '/api/{apiVersion}/ignore-method', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#defaultScope', 'url' => '/api/{apiVersion}/settings', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#adminScope', 'url' => '/api/{apiVersion}/admin', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#doubleScope', 'url' => '/api/{apiVersion}/double', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],

		['name' => 'Settings2#defaultAdminScopeOverwritten', 'url' => '/api/{apiVersion}/default-admin-overwritten', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings2#defaultAdminScope', 'url' => '/api/{apiVersion}/default-admin', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
	],
];
