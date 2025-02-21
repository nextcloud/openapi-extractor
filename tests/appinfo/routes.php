<?php

declare(strict_types=1);

/**
 * SPDX-FileCopyrightText: 2023 Nextcloud GmbH and Nextcloud contributors
 * SPDX-FileCopyrightText: 2016 ownCloud, Inc.
 * SPDX-License-Identifier: AGPL-3.0-only
 */

return [
	'resources' => [],
	'ocs' => [
		['name' => 'AdminSettings#adminScopeImplicitFromAdminRequired', 'url' => '/api/{apiVersion}/default-admin', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'AdminSettings#movedToDefaultScope', 'url' => '/api/{apiVersion}/default-admin-overwritten', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'AdminSettings#movedToSettingsTag', 'url' => '/api/{apiVersion}/moved-with-tag', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'AdminSettings#movedToSettingsTagUnnamed', 'url' => '/api/{apiVersion}/moved-with-unnamed-tag', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],

		['name' => 'ExAppSettings#exAppScopeAttribute', 'url' => '/api/{apiVersion}/ex-app-attribute', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'ExAppSettings#exAppScopeOverride', 'url' => '/api/{apiVersion}/ex-app-override', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],

		['name' => 'Federation#federationByController', 'url' => '/api/{apiVersion}/controller-scope', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Federation#movedToDefaultScope', 'url' => '/api/{apiVersion}/default-scope', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],

		['name' => 'ReturnArrays#stringArray', 'url' => '/api/{apiVersion}/return-arrays/string', 'verb' => 'GET', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'ReturnArrays#nonEmptyStringArray', 'url' => '/api/{apiVersion}/return-arrays/non-empty-string', 'verb' => 'GET', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'ReturnArrays#lowercaseStringArray', 'url' => '/api/{apiVersion}/return-arrays/lowercase-string', 'verb' => 'GET', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'ReturnArrays#nonEmptyLowercaseStringArray', 'url' => '/api/{apiVersion}/return-arrays/non-empty-lowercase-string', 'verb' => 'GET', 'requirements' => ['apiVersion' => '(v2)']],

		['name' => 'Settings#ignoreByDeprecatedAttributeOnMethod', 'url' => '/api/{apiVersion}/ignore-openapi-attribute', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#ignoreByScopeOnMethod', 'url' => '/api/{apiVersion}/ignore-method-scope', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#ignoreByUnnamedScopeOnMethod', 'url' => '/api/{apiVersion}/ignore-method-scope-unnamed', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#movedToAdminScope', 'url' => '/api/{apiVersion}/admin-scope', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#defaultAndAdminScope', 'url' => '/api/{apiVersion}/default-and-admin-scope', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#nestedSchemas', 'url' => '/api/{apiVersion}/nested-schemas', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#listSchemas', 'url' => '/api/{apiVersion}/list-schemas', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#listOfIntParameters', 'url' => '/api/{apiVersion}/list-of-int', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#intParameterWithMinAndMax', 'url' => '/api/{apiVersion}/min-max', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#intParameterWithMin', 'url' => '/api/{apiVersion}/min', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#intParameterWithMax', 'url' => '/api/{apiVersion}/max', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#intParameterNonNegative', 'url' => '/api/{apiVersion}/non-negative', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#intParameterPositive', 'url' => '/api/{apiVersion}/positive', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#intParameterNegative', 'url' => '/api/{apiVersion}/negative', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#intParameterNonPositive', 'url' => '/api/{apiVersion}/non-positive', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#listOfIntStringAndOneBool', 'url' => '/api/{apiVersion}/mixed-list-one', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#listOfIntStringAndAllBools', 'url' => '/api/{apiVersion}/mixed-list-all', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#booleanParameterRequired', 'url' => '/api/{apiVersion}/boolean', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#booleanParameterDefaultFalse', 'url' => '/api/{apiVersion}/boolean-default-false', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#booleanParameterDefaultTrue', 'url' => '/api/{apiVersion}/boolean-default-true', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#booleanTrueParameter', 'url' => '/api/{apiVersion}/boolean-true', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#booleanFalseParameter', 'url' => '/api/{apiVersion}/boolean-false', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#booleanTrueFalseParameter', 'url' => '/api/{apiVersion}/boolean-true-false', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#trueFalseParameter', 'url' => '/api/{apiVersion}/true-false', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#stringValueParameter', 'url' => '/api/{apiVersion}/string-value', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#intValueParameter', 'url' => '/api/{apiVersion}/int-value', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#numericParameter', 'url' => '/api/{apiVersion}/numeric', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#arrayListParameter', 'url' => '/api/{apiVersion}/array-list', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#arrayKeyedParameter', 'url' => '/api/{apiVersion}/array-keyed', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#throwingOCS', 'url' => '/api/{apiVersion}/throwing/ocs', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#throwingOther', 'url' => '/api/{apiVersion}/throwing/other', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#empty204', 'url' => '/api/{apiVersion}/204', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#empty304', 'url' => '/api/{apiVersion}/304', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#passwordConfirmationAnnotation', 'url' => '/api/{apiVersion}/passwordConfirmationAnnotation', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#passwordConfirmationAttribute', 'url' => '/api/{apiVersion}/passwordConfirmationAttribute', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#oneOf', 'url' => '/api/{apiVersion}/oneOf', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#anyOf', 'url' => '/api/{apiVersion}/anyOf', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#floatDouble', 'url' => '/api/{apiVersion}/floatDouble', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#emptyArray', 'url' => '/api/{apiVersion}/emptyArray', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#parameterRequestBody', 'url' => '/api/{apiVersion}/parameterGET', 'verb' => 'GET', 'postfix' => 'GET', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#parameterRequestBody', 'url' => '/api/{apiVersion}/parameterHEAD', 'verb' => 'HEAD', 'postfix' => 'HEAD', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#parameterRequestBody', 'url' => '/api/{apiVersion}/parameterPOST', 'verb' => 'POST', 'postfix' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#parameterRequestBody', 'url' => '/api/{apiVersion}/parameterPUT', 'verb' => 'PUT', 'postfix' => 'PUT', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#parameterRequestBody', 'url' => '/api/{apiVersion}/parameterDELETE', 'verb' => 'DELETE', 'postfix' => 'DELETE', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#parameterRequestBody', 'url' => '/api/{apiVersion}/parameterPATCH', 'verb' => 'PATCH', 'postfix' => 'PATCH', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#objectDefaults', 'url' => '/api/{apiVersion}/objectDefaults', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#whitespace', 'url' => '/api/{apiVersion}/whitespace', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#withCorsAnnotation', 'url' => '/api/{apiVersion}/cors/annotation', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#withCorsAttribute', 'url' => '/api/{apiVersion}/cors/attribute', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#enumNotAliased', 'url' => '/api/{apiVersion}/enum/not-aliased', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
		['name' => 'Settings#enumAliased', 'url' => '/api/{apiVersion}/enum/aliased', 'verb' => 'POST', 'requirements' => ['apiVersion' => '(v2)']],
	],
];
