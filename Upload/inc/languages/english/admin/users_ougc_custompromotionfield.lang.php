<?php

/***************************************************************************
 *
 *    OUGC Custom Promotion Field plugin (/inc/languages/english/admin/users_ougc_custompromotionfield.lang.php)
 *    Author: Omar Gonzalez
 *    Copyright: © 2020 Omar Gonzalez
 *
 *    Website: https://ougc.network
 *
 *    Allow administrators to select custom table fields to consider for group promotions.
 *
 ***************************************************************************
 ****************************************************************************
 * This program is free software: you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.  If not, see <http://www.gnu.org/licenses/>.
 ****************************************************************************/

$l['ougc_custompromotionfield'] = 'OUGC Custom Promotion Field';
$l['ougc_custompromotionfield_desc'] = 'Allow administrators to select custom table fields to consider for group promotions.';

$l['ougc_custompromotionfield_pl'] = 'This plugin requires <a href="{1}">PluginLibrary</a> version {2} or later. Please uplaod the necessary files.';

$l['ougc_custompromotionfield_select'] = 'Custom Field(s)';
$l['ougc_custompromotionfield_select_table'] = 'Custom Field: Table';
$l['ougc_custompromotionfield_select_table_desc'] = 'Select a custom table to consider for this promotion. This table has to exist within the forum table.';
$l['ougc_custompromotionfield_select_column'] = 'Custom Field: Column';
$l['ougc_custompromotionfield_select_column_desc'] = 'Select a custom column to consider for this promotion. This column has to exist within the selected table above.';
$l['ougc_custompromotionfield_select_value'] = 'Custom Field: Value';
$l['ougc_custompromotionfield_select_value_desc'] = 'Select the value to compare against for the selected column above.';
$l['ougc_custompromotionfield_select_script'] = 'Custom Field: JSON Script';
$l['ougc_custompromotionfield_select_script_desc'] = 'JSON compatible script to set complex promotion verifications. <a href="https://github.com/OUGC-Network/OUGC-Custom-Promotion-Field" target="_blank">Read the documentation</a> for more on this.';
$l['ougc_custompromotionfield_error_script'] = 'The value for "Custom Field: JSON Script" is not a valid JSON format string. Leave empty to ignore this setting.';

$l['ougc_custompromotionfield_table'] = 'Table: ';
$l['ougc_custompromotionfield_field'] = 'Field: ';

$l['ougc_custompromotionfield_type_notequal_to'] = 'Not equal to';

$l['ougc_custompromotionfield_placeholder_script'] = '{
	"whereClauses":[
		{
			"tableName":"threads",
			"columnName":"tid",
			"columnValue":3,
			"columnOperator":">=",
			"aggregateFunction":"COUNT",
			"aggregateAlias":"totalThreads"
		},
		{
			"tableName":"threads",
			"columnName":"visible",
			"columnValue":1,
			"columnOperator":"="
		},
		{
			"tableName":"forums",
			"columnName":"fid",
			"columnValue":[
				2,
				30
			],
			"columnOperator":"IN",
			"relationMainField":"ougcCustomPromotionFieldTable_threads.fid",
			"relationSecondaryField":"fid"
		}
	],
	"logicalOperator":"AND"
}';