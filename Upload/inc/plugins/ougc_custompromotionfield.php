<?php

/***************************************************************************
 *
 *    OUGC Custom Promotion Field plugin (/inc/plugins/ougc_custompromotionfield.php)
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

declare(strict_types=1);

// Die if IN_MYBB is not defined, for security reasons.
defined('IN_MYBB') or die('This file cannot be accessed directly.');

global $plugins;

// Add our hooks
if (defined('IN_ADMINCP')) {
    $plugins->add_hook(
        'admin_formcontainer_output_row',
        ['OUGC_CustomPromotionField', 'admin_formcontainer_output_row']
    );
    $plugins->add_hook(
        'admin_user_group_promotions_add',
        ['OUGC_CustomPromotionField', 'admin_user_group_promotions_add']
    );
    $plugins->add_hook(
        'admin_user_group_promotions_edit',
        ['OUGC_CustomPromotionField', 'admin_user_group_promotions_add']
    );
    $plugins->add_hook(
        'admin_user_group_promotions_add_commit',
        ['OUGC_CustomPromotionField', 'admin_user_group_promotions_add_commit']
    );
    $plugins->add_hook(
        'admin_user_group_promotions_edit_commit',
        ['OUGC_CustomPromotionField', 'admin_user_group_promotions_add_commit']
    );
}

$plugins->add_hook('task_promotions', ['OUGC_CustomPromotionField', 'task_promotions']);

$plugins->add_hook('task_promotions', ['OUGC_CustomPromotionField', 'task_promotions_finalize'], 90);

defined('PLUGINLIBRARY') || define('PLUGINLIBRARY', MYBB_ROOT . 'inc/plugins/pluginlibrary.php');

function ougc_custompromotionfield_info()
{
    global $lang;
    OUGC_CustomPromotionField::lang_load();

    return [
        'name' => 'OUGC Custom Promotion Field',
        'description' => $lang->ougc_custompromotionfield_desc,
        'website' => 'https://ougc.network',
        'author' => 'Omar G.',
        'authorsite' => 'https://ougc.network',
        'version' => '1.8.37',
        'versioncode' => 1837,
        'compatibility' => '18*',
        'codename' => 'ougc_custompromotionfield',
        'pl' => [
            'version' => 13,
            'url' => 'https://community.mybb.com/mods.php?action=view&pid=573'
        ]
    ];
}

function ougc_custompromotionfield_activate()
{
    global $lang, $PL, $cache, $db;

    OUGC_CustomPromotionField::meets_requirements();

    foreach (OUGC_CustomPromotionField::_db_columns() as $table => $columns) {
        foreach ($columns as $field => $definition) {
            if (!$db->field_exists($field, $table)) {
                $db->add_column($table, $field, $definition);
            } else {
                $db->modify_column($table, $field, $definition);
            }
        }
    }

    $plugins = $cache->read('ougc_plugins');

    if (!$plugins) {
        $plugins = [];
    }

    $info = ougc_custompromotionfield_info();

    if (isset($plugins['custompromotionfield'])) {
        OUGC_CustomPromotionField::_db_verify_columns();
    }

    $plugins['custompromotionfield'] = $info['versioncode'];

    $cache->update('ougc_plugins', $plugins);
}

function ougc_custompromotionfield_is_installed()
{
    global $db;

    $installed = false;

    foreach (OUGC_CustomPromotionField::_db_columns() as $table => $columns) {
        foreach ($columns as $field => $definition) {
            if ($db->field_exists($field, $table)) {
                $installed = true;

                break;
            }
        }
    }

    return $installed;
}

function ougc_custompromotionfield_uninstall()
{
    global $db, $PL;

    OUGC_CustomPromotionField::meets_requirements();

    foreach (OUGC_CustomPromotionField::_db_columns() as $table => $columns) {
        foreach ($columns as $field => $definition) {
            if ($db->field_exists($field, $table)) {
                $db->drop_column($table, $field);
            }
        }
    }

    global $cache;

    $plugins = (array)$cache->read('ougc_plugins');

    if (isset($plugins['custompromotionfield'])) {
        unset($plugins['custompromotionfield']);
    }

    if ($plugins) {
        $cache->update('ougc_plugins', $plugins);
    } else {
        $PL->cache_delete('ougc_plugins');
    }
}

class OUGC_CustomPromotionField
{
    // TODO: as listed @ https://mariadb.com/kb/en/string-functions/
    private static $stringFunctions = [
        'LIKE',
        'NOT LIKE',
        'LOWER',
        'UPPER',
    ];

    // TODO: as listed @ https://mariadb.com/kb/en/comparison-operators/
    private static $comparisonOperators = [
        '>',
        '>=',
        '=',
        '!=',
        '<=',
        '<',
        '<=>',
        'IN',
        'NOT IN',
    ];

    private static $comparisonOperatorsCore = [
        'hours',
        'days',
        'weeks',
        'months',
        'years',
    ];

    // TODO: as listed @ https://mariadb.com/kb/en/aggregate-functions/
    private static $aggregateFunctions = [
        'COUNT' => [],
        'MAX' => [],
        'MIN' => [],
        'SUM' => [],
        'GROUP_CONCAT' => [
            'DISTINCT' => '',
            'SEPARATOR' => '\n',
        ],
    ];

    // TODO: as listed @ https://mariadb.com/kb/en/logical-operators/
    private static $logicalOperators = [
        'AND',
        'OR',
        'XOR',
    ];

    private static $allowedEvaluationFunctions = [
        'str_word_count',
        'substr_count',
    ];

    private static $allowedEvaluationResultFunctions = [
        'version_compare',
    ];

    public static function _db_columns(): array
    {
        return [
            'promotions' => [
                'ougc_custompromotionfield_table' => "varchar(50) NOT NULL DEFAULT ''",
                'ougc_custompromotionfield_field' => "varchar(50) NOT NULL DEFAULT ''",
                'ougc_custompromotionfield_value' => "varchar(50) NOT NULL DEFAULT '0'",
                'ougc_custompromotionfield_type' => "varchar(10) NOT NULL DEFAULT ''",
                'ougc_custompromotionfield_script' => 'text NULL',
            ],
        ];
    }

    public static function _db_verify_columns(): bool
    {
        global $db;

        foreach (self::_db_columns() as $table => $columns) {
            foreach ($columns as $field => $definition) {
                if ($db->field_exists($field, $table)) {
                    $db->modify_column($table, "`{$field}`", $definition);
                } else {
                    $db->add_column($table, $field, $definition);
                }
            }
        }

        return true;
    }

    public static function lang_load(): bool
    {
        global $lang;

        isset($lang->ougc_custompromotionfield) || $lang->load('users_ougc_custompromotionfield');

        return true;
    }

    public static function meets_requirements(): bool
    {
        global $PL, $lang;

        if (file_exists(PLUGINLIBRARY)) {
            $PL || require_once PLUGINLIBRARY;
        }

        self::lang_load();

        $info = ougc_custompromotionfield_info();

        if (!file_exists(PLUGINLIBRARY) || $PL->version < $info['pl']['version']) {
            flash_message(
                $lang->sprintf($lang->ougc_custompromotionfield_pl, $info['pl']['url'], $info['pl']['version']),
                'error'
            );
            admin_redirect('index.php?module=config-plugins');

            return false;
        }

        return true;
    }

    public static function admin_formcontainer_output_row(array &$args): array
    {
        global $lang, $form_container, $form, $mybb, $promotion;

        self::lang_load();

        if (!empty($lang->promo_requirements_desc) && $args['description'] == $lang->promo_requirements_desc) {
            $selected = '';

            foreach ($mybb->get_input('requirements', MyBB::INPUT_ARRAY) as $requirement) {
                if ($requirement == 'custompromotionfield') {
                    $selected = ' selected="selected"';
                }
            }

            $args['content'] = str_replace(
                '</select',
                "<option value=\"custompromotionfield\"{$selected}>{$lang->ougc_custompromotionfield_select}</option></select",
                $args['content']
            );
        }

        if (!empty($lang->orig_user_group_desc) && $args['description'] == $lang->orig_user_group_desc) {
            foreach (self::_db_columns() as $table => $columns) {
                foreach ($columns as $field => $definition) {
                    if (!isset($mybb->input[$field]) && isset($promotion[$field])) {
                        $mybb->input[$field] = $promotion[$field];
                    }
                }
            }

            $options = [
                '>' => '(>) ' . $lang->greater_than,
                '>=' => '(>=) ' . $lang->greater_than_or_equal_to,
                '=' => '(=) ' . $lang->equal_to,
                '!=' => '(!=) ' . $lang->ougc_custompromotionfield_type_notequal_to,
                '<=' => '(<=) ' . $lang->less_than_or_equal_to,
                '<' => '(<) ' . $lang->less_than,
                'hours' => $lang->hours,
                'days' => $lang->days,
                'weeks' => $lang->weeks,
                'months' => $lang->months,
                'years' => $lang->years,
            ];

            $form_container->output_row(
                $lang->ougc_custompromotionfield_select_table,
                $lang->ougc_custompromotionfield_select_table_desc,
                $form->generate_text_box(
                    'ougc_custompromotionfield_table',
                    $mybb->get_input('ougc_custompromotionfield_table'),
                    ['style' => '" placeholder="users']
                ),
                'ougc_custompromotionfield_table'
            );

            $form_container->output_row(
                $lang->ougc_custompromotionfield_select_column,
                $lang->ougc_custompromotionfield_select_column_desc,
                $form->generate_text_box(
                    'ougc_custompromotionfield_field',
                    $mybb->get_input('ougc_custompromotionfield_field'),
                    ['style' => '" placeholder="lastpost']
                ),
                'ougc_custompromotionfield_column'
            );

            $form_container->output_row(
                $lang->ougc_custompromotionfield_select_value,
                $lang->ougc_custompromotionfield_select_value_desc,
                $form->generate_numeric_field(
                    'ougc_custompromotionfield_value',
                    $mybb->get_input('ougc_custompromotionfield_value', MyBB::INPUT_STRING),
                    ['min' => 0]
                ) . ' ' .
                $form->generate_select_box(
                    'ougc_custompromotionfield_type',
                    $options,
                    $mybb->get_input('ougc_custompromotionfield_type')
                ),
                'ougc_custompromotionfield_value'
            );

            $form_container->output_row(
                $lang->ougc_custompromotionfield_select_script,
                $lang->ougc_custompromotionfield_select_script_desc,
                $form->generate_text_area(
                    'ougc_custompromotionfield_script',
                    $mybb->get_input('ougc_custompromotionfield_script'),
                    [
                        'style' => '" placeholder="' . str_replace(
                                '"',
                                '&quot;',
                                $lang->ougc_custompromotionfield_placeholder_script
                            )
                    ]
                ),
                'ougc_custompromotionfield_script'
            );
        }

        return $args;
    }

    public static function admin_user_group_promotions_add(): bool
    {
        global $db, $pid, $mybb, $plugins;

        $editAction = $plugins->current_hook === 'admin_user_group_promotions_edit';

        if (!$mybb->get_input('ougc_custompromotionfield_script')) {
            return false;
        }

        if (json_decode($mybb->get_input('ougc_custompromotionfield_script')) === null) {
            global $lang, $errors;

            self::lang_load();

            $errors[] = $lang->ougc_custompromotionfield_error_script;
        }

        return true;
    }

    public static function admin_user_group_promotions_add_commit(): bool
    {
        global $db, $pid, $mybb, $plugins;

        $editAction = $plugins->current_hook === 'admin_user_group_promotions_edit_commit';

        if ($editAction) {
            global $update_promotion;

            $fields = &$update_promotion;
        } else {
            $fields = [];
        }

        foreach (self::_db_columns() as $table => $columns) {
            foreach ($columns as $field => $definition) {
                $fields[$field] = $db->escape_string($mybb->get_input($field));
            }
        }

        if (!$editAction) {
            $db->update_query('promotions', $fields, "pid='{$pid}'");
        }

        return true;
    }

    public static function task_promotions(array &$promotionArguments): array
    {
        global $promotion, $db;

        if (my_strpos($promotionArguments['promotion']['requirements'], 'custompromotionfield') === false) {
            return $promotionArguments;
        }

        foreach (
            [
                'postnum',
                'threadnum',
                'reputation',
                'referrals',
                'warningpoints',
                'regdate',
                'timeonline',
                'usergroup',
                'additionalgroups'
            ] as $usersDefaultColumn
        ) {
            $promotionArguments['sql_where'] = str_replace(
                $usersDefaultColumn,
                "u.{$usersDefaultColumn}",
                $promotionArguments['sql_where']
            );
        }

        $promotionScripts = json_decode($promotionArguments['promotion']['ougc_custompromotionfield_script'], true);

        if (!is_array($promotionScripts)) {
            $promotionScripts = [];
        }

        $conditionalTable = $promotionArguments['promotion']['ougc_custompromotionfield_table'];

        $conditionalColumn = $promotionArguments['promotion']['ougc_custompromotionfield_field'];

        $conditionalValue = $promotionArguments['promotion']['ougc_custompromotionfield_value'];

        $conditionalOperator = $promotionArguments['promotion']['ougc_custompromotionfield_type'];

        if (
            (!empty($conditionalTable) && $db->table_exists($conditionalTable)) &&
            (!empty($conditionalColumn) && $db->field_exists(
                    $conditionalColumn,
                    $conditionalTable
                ))
        ) {
            switch ($conditionalOperator) {
                case 'hours':
                    $conditionalOperator = '>=';

                    $conditionalValue = TIME_NOW - ($conditionalValue * 60 * 60);
                    break;
                case 'days':
                    $conditionalOperator = '>=';

                    $conditionalValue = TIME_NOW - ($conditionalValue * 60 * 60 * 24);
                    break;
                case 'weeks':
                    $conditionalOperator = '>=';

                    $conditionalValue = TIME_NOW - ($conditionalValue * 60 * 60 * 24 * 7);
                    break;
                case 'months':
                    $conditionalOperator = '>=';

                    $conditionalValue = TIME_NOW - ($conditionalValue * 60 * 60 * 24 * 30);
            }

            $promotionScripts['whereClauses'][] = [
                'tableName' => $conditionalTable,
                'columnName' => $conditionalColumn,
                'columnValue' => $conditionalValue,
                'columnOperator' => $conditionalOperator
            ];
        }

        $additionalTables = $additionalClauses = $additionalFields = $aggregateHavings = [];

        $anonymousFunction = function (&$whereClause) use (
            $db,
            &$additionalTables,
            &$additionalClauses,
            &$additionalFields,
            &$aggregateHavings,
            $promotionScripts
        ): bool {
            $tableName = &$whereClause['tableName'];

            $columnName = &$whereClause['columnName'];

            if (!empty($whereClause['finalizingClause'])) {
                $columnValues = &$whereClause['evaluationValue'];
            } else {
                $columnValues = &$whereClause['columnValue'];
            }

            if (!empty($whereClause['finalizingClause'])) {
                $columnOperator = &$whereClause['evaluationOperator'];
            } else {
                $columnOperator = &$whereClause['columnOperator'];
            }

            if (!empty($whereClause['aggregateFunction'])) {
                $aggregateFunction = &$whereClause['aggregateFunction'];
            } else {
                $aggregateFunction = null;
            }

            $aggregateAlias = '';

            if (isset($whereClause['aggregateAlias']) && ctype_alnum($whereClause['aggregateAlias'])) {
                $aggregateAlias = $whereClause['aggregateAlias'];
            } elseif (!empty($whereClause['finalizingClause'])) {
                $aggregateAlias = $columnName;
            }

            if (!is_array($columnValues)) {
                $columnValues = [$columnValues];
            }

            static $comparisonOperators = null;

            if ($comparisonOperators === null) {
                $comparisonOperators = array_merge(self::$comparisonOperators, self::$stringFunctions);
            }

            if (
                !ctype_alnum($tableName) ||
                !$db->table_exists($tableName) ||
                (empty($aggregateFunction) && ctype_alnum($columnName) && !$db->field_exists(
                        $columnName,
                        $tableName
                    )) ||
                //empty($columnValues) ||
                !in_array($columnOperator, $comparisonOperators) ||
                (!empty($aggregateFunction) && !in_array(
                        $aggregateFunction,
                        array_keys(self::$aggregateFunctions)
                    )) ||
                (!empty($aggregateFunction) && empty($aggregateAlias) && empty($whereClause['finalizingClause']))
            ) {
                return false;
            }

            if (!empty($whereClause['finalizingClause'])) {
                global $ougcCustomPromotionField;

                is_array($ougcCustomPromotionField) || $ougcCustomPromotionField = [];

                isset($ougcCustomPromotionField['logicalOperator']) || $ougcCustomPromotionField['logicalOperator'] = $promotionScripts['logicalOperator'];

                isset($ougcCustomPromotionField['finalizingClauses']) || $ougcCustomPromotionField['finalizingClauses'] = [];

                $ougcCustomPromotionField['finalizingClauses'][] = $whereClause;
            }

            $aggregateAlias = "ougcCustomPromotionFieldColumn_{$aggregateAlias}";

            foreach ($columnValues as &$columnValue) {
                if (is_float($columnValue)) {
                    $columnValue = (float)$columnValue;
                } elseif (is_numeric($columnValue)) {
                    $columnValue = (int)$columnValue;
                } else {
                    $columnValue = $db->escape_string($columnValue);
                }
            }

            unset($columnValue);

            if (in_array($columnOperator, ['IN', 'NOT IN', 'LOWER', 'UPPER'])) {
                $conditionalValue = implode("','", $columnValues);

                $conditionalValue = "('{$conditionalValue}')";
            } elseif (in_array($columnOperator, ['LIKE', 'NOT LIKE'])) {
                $conditionalValue = implode('', $columnValues);

                $conditionalValue = "\"%{$conditionalValue}%\"";
            } else {
                $conditionalValue = implode("','", $columnValues);

                $conditionalValue = "'{$conditionalValue}'";
            }

            $conditionalTablePrefix = "ougcCustomPromotionFieldTable_{$tableName}";

            if (isset($whereClause['relationMainField']) && preg_match('/[\w.]+/', $whereClause['relationMainField'])) {
                $relationMainField = $whereClause['relationMainField'];
            } else {
                $relationMainField = 'u.uid';
            }

            if (isset($whereClause['relationSecondaryField']) && preg_match(
                    '/[\w.]+/',
                    $whereClause['relationSecondaryField']
                )) {
                $relationSecondaryField = $whereClause['relationSecondaryField'];
            } else {
                $relationSecondaryField = 'uid';
            }

            $additionalTables["{$tableName} AS {$conditionalTablePrefix} ON ({$conditionalTablePrefix}.{$relationSecondaryField}={$relationMainField})"] = 1;

            if (!empty($aggregateFunction) && in_array($aggregateFunction, array_keys(self::$aggregateFunctions))) {
                if (empty($whereClause['finalizingClause'])) {
                    $hasAggregateFunction = true;
                }

                $aggregateFunctionOptions = [];

                /*foreach (self::$aggregateFunctions[$aggregateFunction] as $aggregateFunctionOptionKey => $aggregateFunctionOptionValue) {
                    $aggregateFunctionOptions[] = "{$aggregateFunctionOptionKey} '{$aggregateFunctionOptionValue}'";
                }

                foreach (self::$aggregateFunctions[$aggregateFunction] as $aggregateFunctionOptionKey => $aggregateFunctionOptionValue) {
                    if (empty($aggregateFunctionOptionValue)) {
                        continue;
                    }

                    $aggregateFunctionOptions[] = "{$aggregateFunctionOptionKey} '{$aggregateFunctionOptionValue}'";
                }*/

                if (!empty($whereClause['aggregateFunctionOptions'])) {
                    foreach ($whereClause['aggregateFunctionOptions'] as $aggregateFunctionOptionKey => $aggregateFunctionOptionValue) {
                        if (isset(self::$aggregateFunctions[$aggregateFunction][$aggregateFunctionOptionKey])) {
                            if ($aggregateFunctionOptionKey !== 'DISTINCT') {
                                $aggregateFunctionOptions[] = "{$aggregateFunctionOptionKey} {$aggregateFunctionOptionValue}";
                            }
                        }
                    }
                }

                $aggregateFunctionOptions = implode(' ', $aggregateFunctionOptions);

                $distinctOption = '';

                if (isset($whereClause['aggregateFunctionOptions']['DISTINCT'])) {
                    $distinctOption = 'DISTINCT';
                }

                $additionalFields["{$aggregateFunction}({$distinctOption} {$conditionalTablePrefix}.{$columnName} {$aggregateFunctionOptions}) AS {$aggregateAlias}"] = 1;
            }

            if (empty($aggregateFunction)) {
                $dataColum = "{$conditionalTablePrefix}.{$columnName}";
            } else {
                $dataColum = $aggregateAlias;
            }

            if (!empty($hasAggregateFunction)) {
                $aggregateHavings["{$dataColum} {$columnOperator} {$conditionalValue}"] = 1;
            } elseif (empty($whereClause['finalizingClause'])) {
                $additionalClauses["{$dataColum} {$columnOperator} {$conditionalValue}"] = 1;
            }

            return false;
        };

        foreach ($promotionScripts['whereClauses'] as $whereClause) {
            $anonymousFunction($whereClause);
        }

        if (in_array($promotionScripts['logicalOperator'], self::$logicalOperators)) {
            $logicalOperator = $promotionScripts['logicalOperator'];
        } else {
            $logicalOperator = 'AND';
        }

        $additionalTables = implode(" LEFT JOIN {$db->table_prefix}", array_keys($additionalTables));

        $additionalTables = $additionalTables ? " LEFT JOIN {$db->table_prefix}{$additionalTables}" : '';

        if (!empty($additionalClauses)) {
            $additionalClauses = implode(" {$logicalOperator} ", array_keys($additionalClauses));

            $promotionArguments['sql_where'] .= " {$promotionArguments['and']} ({$additionalClauses})";
        }

        $additionalFields = implode(', ', array_keys($additionalFields));

        $additionalFields = $additionalFields ? ", {$additionalFields}" : '';

        if (empty($aggregateHavings)) {
            $aggregateHavings = '';
        } else {
            $aggregateHavings = implode(',', array_keys($aggregateHavings));

            $aggregateHavings = "HAVING {$aggregateHavings}";
        }

        control_db(
            'function simple_select($table, $fields = "*", $conditions = "", $options = array())
{
    global $db;
    
    static $controlDone = false;
    
    if(
        !$controlDone &&
        my_strpos($table, "users") !== false &&
        my_strpos($fields, "uid,' . $promotionArguments['usergroup_select'] . '") !== false
    )
    {
        //$GLOBALS["someDoneVariable"] = true;
        
        //$controlDone = false;
    
        $table = "users AS u ' . $additionalTables . '";
    
        $fields = "u.uid,u.' . $promotionArguments['usergroup_select'] . $additionalFields . '";
        
        $options["group_by"] = "u.uid ' . $aggregateHavings . '";
    }
    
    return parent::simple_select($table, $fields, $conditions, $options);
}'
        );

        return $promotionArguments;
    }

    public static function task_promotions_finalize(array &$promotionArguments): array
    {
        global $db;
        global $ougcCustomPromotionField;

        if (empty($ougcCustomPromotionField['finalizingClauses'])) {
            return $promotionArguments;
        }

        $dbQuery = $db->simple_select(
            'users',
            "uid,{$promotionArguments['usergroup_select']}",
            $promotionArguments['sql_where'],
            ['group_by' => 'uid']
        );

        $userIDs = [];

        while ($userData = $db->fetch_array($dbQuery)) {
            $evaluationResultMain = true;

            foreach ($ougcCustomPromotionField['finalizingClauses'] as $whereClause) {
                $columnName = &$whereClause['columnName'];

                $aggregateAlias = $columnName;

                $aggregateAlias = "ougcCustomPromotionFieldColumn_{$aggregateAlias}";

                if (
                    !isset($whereClause['evaluationFunction']) ||
                    !isset($whereClause['evaluationFunctionArguments']) ||
                    !($whereClause['evaluationFunctionArguments']) ||
                    !function_exists($whereClause['evaluationFunction']) ||
                    !function_exists($whereClause['evaluationResultFunction']) ||
                    !in_array($whereClause['evaluationFunction'], self::$allowedEvaluationFunctions) ||
                    !in_array($whereClause['evaluationResultFunction'], self::$allowedEvaluationResultFunctions) ||
                    !isset($userData[$aggregateAlias])
                ) {
                    continue;
                }

                if (isset($whereClause['stripTags'])) {
                    $userData[$aggregateAlias] = self::stripTags(
                        $userData[$aggregateAlias],
                        (array)$whereClause['stripTags']
                    );
                }

                $userData[$aggregateAlias] = my_strtolower($userData[$aggregateAlias]);

                if (($mainArgumentKey = array_search(
                        $whereClause['columnName'],
                        $whereClause['evaluationFunctionArguments'],
                        true
                    )) !== false) {
                    $whereClause['evaluationFunctionArguments'][$mainArgumentKey] = $userData[$aggregateAlias];
                }

                $evaluationValue = $whereClause['evaluationFunction'](...$whereClause['evaluationFunctionArguments']);

                $evaluationResult = true;

                foreach ($whereClause['evaluationValue'] as $expectedEvaluationValueKey => $expectedEvaluationValueValue) {
                    $evaluationResult = $evaluationResult && $whereClause['evaluationResultFunction'](
                            (string)$evaluationValue,
                            (string)$expectedEvaluationValueValue,
                            $whereClause['evaluationOperator']
                        );
                }

                switch ($ougcCustomPromotionField['logicalOperator']) {
                    case 'AND':
                        $evaluationResultMain = $evaluationResultMain && $evaluationResult;
                        break;
                    case 'OR':
                        $evaluationResultMain = $evaluationResultMain || $evaluationResult;
                        break;
                    //case 'XOR':
                    //$evaluationResultMain = $evaluationResultMain xor $evaluationResult;
                    //break;
                }
            }

            if ($evaluationResultMain) {
                $userIDs[(int)$userData['uid']] = 1;
            }
        }

        $userIDs = implode("','", array_keys($userIDs));

        $promotionArguments['sql_where'] .= " {$promotionArguments['and']} u.uid IN ('{$userIDs}')";

        return $promotionArguments;
    }

    /**
     * Strips tags and special code off a string
     * Based off Zinga Burga's "Thread Tooltip Preview" plugin threadtooltip_getpreview() function.
     *
     * @param string $message Message to strip tags off from.
     * @return string Parsed message
     */
    private static function stripTags(
        string $message,
        array $parserOptions = []
    ): string {
        // Attempt to remove any quotes
        $message = preg_replace_callback(array(
            '#\[quote=([\"\']|&quot;|)(.*?)(?:\\1)(.*?)(?:[\"\']|&quot;)?\](.*?)\[/quote\](\r\n?|\n?)#si',
            '#\[quote\](.*?)\[\/quote\](\r\n?|\n?)#si',
            '#\[quote\]#si',
            '#\[\/quote\]#si'
        ), function ($matches) {
            return '';
        }, $message);

        global $parser;

        if (!is_object($parser)) {
            require_once MYBB_ROOT . 'inc/class_parser.php';

            $parser = new postParser();
        }

        $message = $parser->parse_message($message, array_merge([
            'allow_html' => 0,
            'allow_mycode' => 1,
            'allow_smilies' => 0,
            'allow_imgcode' => 1,
            'filter_badwords' => 1,
            'nl2br' => 0
        ], $parserOptions));

        // before stripping tags, try converting some into spaces
        $message = preg_replace([
            '~\<(?:img|hr).*?/\>~si',
            '~\<li\>(.*?)\</li\>~si'
        ], [' ', "\n* $1"], $message);

        $message = unhtmlentities(strip_tags($message));

        // convert \xA0 to spaces (reverse &nbsp;)
        $message = trim(
            preg_replace(array('~ {2,}~', "~\n{2,}~"),
                array(' ', "\n"),
                strtr($message, array("\xA0" => ' ', "\r" => '', "\t" => ' ')))
        );

        // newline fix for browsers which don't support them
        return preg_replace("~ ?\n ?~", " \n", $message);
    }
}

// control_object by Zinga Burga from MyBBHacks ( mybbhacks.zingaburga.com )
if (!function_exists('control_object')) {
    function control_object(&$obj, $code)
    {
        static $cnt = 0;
        $newname = '_objcont_' . (++$cnt);
        $objserial = serialize($obj);
        $classname = get_class($obj);
        $checkstr = 'O:' . strlen($classname) . ':"' . $classname . '":';
        $checkstr_len = strlen($checkstr);
        if (substr($objserial, 0, $checkstr_len) == $checkstr) {
            $vars = array();
            // grab resources/object etc, stripping scope info from keys
            foreach ((array)$obj as $k => $v) {
                if ($p = strrpos($k, "\0")) {
                    $k = substr($k, $p + 1);
                }
                $vars[$k] = $v;
            }
            if (!empty($vars)) {
                $code .= '
					function ___setvars(&$a) {
						foreach($a as $k => &$v)
							$this->$k = $v;
					}
				';
            }
            eval('class ' . $newname . ' extends ' . $classname . ' {' . $code . '}');
            $obj = unserialize('O:' . strlen($newname) . ':"' . $newname . '":' . substr($objserial, $checkstr_len));
            if (!empty($vars)) {
                $obj->___setvars($vars);
            }
        }
        // else not a valid object or PHP serialize has changed
    }
}

if (!function_exists('control_db')) {
    // explicit workaround for PDO, as trying to serialize it causes a fatal error (even though PHP doesn't complain over serializing other resources)
    if ($GLOBALS['db'] instanceof AbstractPdoDbDriver) {
        $GLOBALS['AbstractPdoDbDriver_lastResult_prop'] = new ReflectionProperty('AbstractPdoDbDriver', 'lastResult');
        $GLOBALS['AbstractPdoDbDriver_lastResult_prop']->setAccessible(true);
        function control_db($code)
        {
            global $db;
            $linkvars = array(
                'read_link' => $db->read_link,
                'write_link' => $db->write_link,
                'current_link' => $db->current_link,
            );
            unset($db->read_link, $db->write_link, $db->current_link);
            $lastResult = $GLOBALS['AbstractPdoDbDriver_lastResult_prop']->getValue($db);
            $GLOBALS['AbstractPdoDbDriver_lastResult_prop']->setValue($db, null); // don't let this block serialization
            control_object($db, $code);
            foreach ($linkvars as $k => $v) {
                $db->$k = $v;
            }
            $GLOBALS['AbstractPdoDbDriver_lastResult_prop']->setValue($db, $lastResult);
        }
    } elseif ($GLOBALS['db'] instanceof DB_SQLite) {
        function control_db($code)
        {
            global $db;
            $oldLink = $db->db;
            unset($db->db);
            control_object($db, $code);
            $db->db = $oldLink;
        }
    } else {
        function control_db($code)
        {
            control_object($GLOBALS['db'], $code);
        }
    }
}