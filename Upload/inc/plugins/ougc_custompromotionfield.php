<?php

/***************************************************************************
 *
 *	OUGC Custom Promotion Field plugin (/inc/plugins/ougc_custompromotionfield.php)
 *	Author: Omar Gonzalez
 *	Copyright: © 2020 Omar Gonzalez
 *
 *	Website: https://ougc.network
 *
 *	Allow administrators to select custom table fields to consider for group promotions.
 *
 ***************************************************************************
 
****************************************************************************
	This program is free software: you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation, either version 3 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program.  If not, see <http://www.gnu.org/licenses/>.
****************************************************************************/

// Die if IN_MYBB is not defined, for security reasons.
defined('IN_MYBB') or die('This file cannot be accessed directly.');

// Add our hooks
if(defined('IN_ADMINCP'))
{
	$plugins->add_hook('admin_formcontainer_output_row', array('OUGC_CustomPromotionField', 'admin_formcontainer_output_row'));
	$plugins->add_hook('admin_user_group_promotions_add_commit', array('OUGC_CustomPromotionField', 'admin_user_group_promotions_add_commit'));
	$plugins->add_hook('admin_user_group_promotions_edit_commit', array('OUGC_CustomPromotionField', 'admin_user_group_promotions_edit_commit'));
}

$plugins->add_hook('task_promotions', array('OUGC_CustomPromotionField', 'task_promotions'));

// PLUGINLIBRARY
defined('PLUGINLIBRARY') or define('PLUGINLIBRARY', MYBB_ROOT.'inc/plugins/pluginlibrary.php');

// Plugin API
function ougc_custompromotionfield_info()
{
	global $lang;
	OUGC_CustomPromotionField::lang_load();

	return array(
		'name'          => 'OUGC Custom Promotion Field',
		'description'   => $lang->ougc_custompromotionfield_desc,
		'website'		=> 'https://ougc.network',
		'author'		=> 'Omar G.',
		'authorsite'	=> 'https://ougc.network',
		'version'		=> '1.8.22',
		'versioncode'	=> 1822,
		'compatibility'	=> '18*',
		'codename'		=> 'ougc_custompromotionfield',
		'pl'			=> array(
			'version'	=> 13,
			'url'		=> 'https://community.mybb.com/mods.php?action=view&pid=573'
		)
	);
}

// _activate function
function ougc_custompromotionfield_activate()
{
	global $lang, $PL, $cache;

	OUGC_CustomPromotionField::meets_requirements();

	// Insert version code into cache
	$plugins = $cache->read('ougc_plugins');

	if(!$plugins)
	{
		$plugins = array();
	}

	$info = ougc_custompromotionfield_info();

	if(isset($plugins['custompromotionfield']))
	{
		OUGC_CustomPromotionField::_db_verify_columns();
	}

	$plugins['custompromotionfield'] = $info['versioncode'];

	$cache->update('ougc_plugins', $plugins);
}

// _is_installed function
function ougc_custompromotionfield_is_installed()
{
	global $db;

	$installed = false;

	foreach(OUGC_CustomPromotionField::_db_columns() as $table => $columns)
	{
		foreach($columns as $field => $definition)
		{
			if($db->field_exists($field, $table))
			{
				$installed = true;

				break;
			}
		}
	}

	return $installed;
}

// _uninstall function
function ougc_custompromotionfield_uninstall()
{
	global $db, $PL;

	OUGC_CustomPromotionField::meets_requirements();

	foreach(OUGC_CustomPromotionField::_db_columns() as $table => $columns)
	{
		foreach($columns as $field => $definition)
		{
			if($db->field_exists($field, $table))
			{
				$db->drop_column($table, $field);
			}
		}
	}

	global $cache;

	// Remove version code from cache
	$plugins = (array)$cache->read('ougc_plugins');

	if(isset($plugins['custompromotionfield']))
	{
		unset($plugins['custompromotionfield']);
	}

	if($plugins)
	{
		$cache->update('ougc_plugins', $plugins);
	}
	else
	{
		$PL->cache_delete('ougc_plugins');
	}
}

// Our awesome class
class OUGC_CustomPromotionField
{
	public $options = array();

	function __construct()
	{
		
	}

	// List of columns
	function _db_columns()
	{
		$tables = array(
			'promotions'	=> array(
				'ougc_custompromotionfield_table'	=> "varchar(50) NOT NULL DEFAULT ''",
				'ougc_custompromotionfield_field'	=> "varchar(50) NOT NULL DEFAULT ''",
				'ougc_custompromotionfield_value'	=> "smallint(5) NOT NULL DEFAULT '0'",
				'ougc_custompromotionfield_type'	=> "varchar(5) NOT NULL DEFAULT ''",
			),
		);

		return $tables;
	}

	// Verify DB columns
	function _db_verify_columns()
	{
		global $db;

		foreach(self::_db_columns() as $table => $columns)
		{
			foreach($columns as $field => $definition)
			{
				if($db->field_exists($field, $table))
				{
					$db->modify_column($table, "`{$field}`", $definition);
				}
				else
				{
					$db->add_column($table, $field, $definition);
				}
			}
		}
	}

	// Load our language file if neccessary
	function lang_load()
	{
		global $lang;

		isset($lang->ougc_custompromotionfield) || $lang->load('users_ougc_custompromotionfield');
	}

	// Check PL requirements
	function meets_requirements()
	{
		global $PL, $lang;

		if(file_exists(PLUGINLIBRARY))
		{
			$PL || require_once PLUGINLIBRARY;
		}

		self::lang_load();

		$info = ougc_custompromotionfield_info();

		if(!file_exists(PLUGINLIBRARY) || $PL->version < $info['pl']['version'])
		{
			flash_message($lang->sprintf($lang->ougc_custompromotionfield_pl, $info['pl']['url'], $info['pl']['version']), 'error');
			admin_redirect('index.php?module=config-plugins');

			return false;
		}

		return true;
	}

	function admin_formcontainer_output_row(&$args)
	{
		global $lang, $form_container, $form, $mybb, $promotion;

		self::lang_load();

		if($args['description'] == $lang->promo_requirements_desc && !empty($lang->promo_requirements_desc))
		{
			foreach($mybb->get_input('requirements', MyBB::INPUT_ARRAY) as $requirement)
			{
				if($requirement == 'custompromotionfield')
				{
					$selected = ' selected="selected"';
				}
			}

			$args['content'] = str_replace('</select', "<option value=\"custompromotionfield\"{$selected}>{$lang->ougc_custompromotionfield_select}</option></select", $args['content']);
		}

		if($args['description'] == $lang->orig_user_group_desc && !empty($lang->orig_user_group_desc))
		{
			foreach(self::_db_columns() as $table => $columns)
			{
				foreach($columns as $field => $definition)
				{
					isset($mybb->input[$field]) || $mybb->input[$field] = $promotion[$field];
				}
			}

			$options = array(
				">" => $lang->greater_than,
				">=" => $lang->greater_than_or_equal_to,
				"=" => $lang->equal_to,
				"<=" => $lang->less_than_or_equal_to,
				"<" => $lang->less_than,
				"hours" => $lang->hours,
				"days" => $lang->days,
				"weeks" => $lang->weeks,
				"months" => $lang->months,
				"years" => $lang->years
			);

			$form_container->output_row(
				$lang->ougc_custompromotionfield_select,
				$lang->ougc_custompromotionfield_select_desc,
				$lang->ougc_custompromotionfield_table.$form->generate_text_box('ougc_custompromotionfield_table', $mybb->get_input('ougc_custompromotionfield_table'), array('id' => 'ougc_custompromotionfield_table', 'style' => 'max-width: 10em;')).' '.
				$lang->ougc_custompromotionfield_field.$form->generate_text_box('ougc_custompromotionfield_field', $mybb->get_input('ougc_custompromotionfield_field'), array('id' => 'ougc_custompromotionfield_field', 'style' => 'max-width: 10em;')).'<hr />'.
				$form->generate_numeric_field('ougc_custompromotionfield_value', $mybb->get_input('ougc_custompromotionfield_value', MyBB::INPUT_INT), array('id' => 'ougc_custompromotionfield_value', 'min' => 0))." ".
				$form->generate_select_box('ougc_custompromotionfield_type', $options, $mybb->get_input('ougc_custompromotionfield_type'), array('id' => 'ougc_custompromotionfield_type')), 'ougc_custompromotionfield_type');
		}
	}

	function admin_user_group_promotions_add_commit()
	{
		global $db, $pid, $mybb;

		$fields = array();

		foreach(self::_db_columns() as $table => $columns)
		{
			foreach($columns as $field => $definition)
			{
				$fields[$field] = $db->escape_string($mybb->get_input($field));
			}
		}

		$db->update_query('promotions', $fields, "pid='{$pid}'");
	}

	function admin_user_group_promotions_edit_commit()
	{
		global $db, $update_promotion, $mybb;

		foreach(self::_db_columns() as $table => $columns)
		{
			foreach($columns as $field => $definition)
			{
				$update_promotion[$field] = $db->escape_string($mybb->get_input($field));
			}
		}
	}

	function task_promotions(&$args)
	{
		global $promotion, $db;

		$requirements = explode(',', $args['promotion']['requirements']);

		foreach($requirements as $requirement)
		{
			$table = (string)$args['promotion']['ougc_custompromotionfield_table'];
			$field = (string)$args['promotion']['ougc_custompromotionfield_field'];
			$value = (int)$args['promotion']['ougc_custompromotionfield_value'];

			if(
				$value < 1 ||
				$requirement != 'custompromotionfield' ||
				!$db->table_exists($table) ||
				!$db->field_exists($field, $table)
			)
			{
				continue;
			}

			$prefix = '';
			if($table != 'users' || 1)
			{
				$prefix = 'cf.';

				foreach(array('postnum', 'threadnum', 'reputation', 'referrals', 'warningpoints', 'regdate', 'timeonline', 'usergroup', 'additionalgroups') as $uf)
				{
					$args['sql_where'] = str_replace($uf, 'u.'.$uf, $args['sql_where']);
				}

				control_object($db, '
					function simple_select($table, $fields="*", $conditions="", $options=array())
					{
						static $done = false;

						if(!$done && my_strpos($fields, "uid,'.$args['usergroup_select'].'") !== false)
						{
							$fields = "u.uid,u.'.$args['usergroup_select'].'";
							$table = $table." u LEFT JOIN '.TABLE_PREFIX.$table.' cf ON (u.uid=cf.uid)";

							$done = true;
						}

						return parent::simple_select($table, $fields, $conditions, $options);
					}
				');
			}

			$operator = $args['promotion']['ougc_custompromotionfield_type'];

			if(!in_array($args['promotion']['ougc_custompromotionfield_type'], array('>', '>=', '=', '<=', '<')))
			{
				$operator = '<=';
			}

			switch($args['promotion']['ougc_custompromotionfield_type'])
			{
				case "hours":
					$value = TIME_NOW-($value*60*60);
					break;
				case "days":
					$value = TIME_NOW-($value*60*60*24);
					break;
				case "weeks":
					$value = TIME_NOW-($value*60*60*24*7);
					break;
				case "months":
					$value = TIME_NOW-($value*60*60*24*30);
					break;
				case "years":
					$value = TIME_NOW-($value*60*60*24*365);
					break;
			}

			$args['sql_where'] .= "{$args['and']}{$prefix}{$field} {$operator} '{$value}'";
		}
	}
}

// control_object by Zinga Burga from MyBBHacks ( mybbhacks.zingaburga.com ), 1.62
if(!function_exists('control_object'))
{
	function control_object(&$obj, $code)
	{
		static $cnt = 0;
		$newname = '_objcont_'.(++$cnt);
		$objserial = serialize($obj);
		$classname = get_class($obj);
		$checkstr = 'O:'.strlen($classname).':"'.$classname.'":';
		$checkstr_len = strlen($checkstr);
		if(substr($objserial, 0, $checkstr_len) == $checkstr)
		{
			$vars = array();
			// grab resources/object etc, stripping scope info from keys
			foreach((array)$obj as $k => $v)
			{
				if($p = strrpos($k, "\0"))
				{
					$k = substr($k, $p+1);
				}
				$vars[$k] = $v;
			}
			if(!empty($vars))
			{
				$code .= '
					function ___setvars(&$a) {
						foreach($a as $k => &$v)
							$this->$k = $v;
					}
				';
			}
			eval('class '.$newname.' extends '.$classname.' {'.$code.'}');
			$obj = unserialize('O:'.strlen($newname).':"'.$newname.'":'.substr($objserial, $checkstr_len));
			if(!empty($vars))
			{
				$obj->___setvars($vars);
			}
		}
		// else not a valid object or PHP serialize has changed
	}
}
