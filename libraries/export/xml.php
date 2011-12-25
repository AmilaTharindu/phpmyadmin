<?php
/* vim: set expandtab sw=4 ts=4 sts=4: */
/**
 * Set of functions used to build XML dumps of tables
 *
 * @todo
 * @package phpMyAdmin-Export-XML
 */
if (! defined('PHPMYADMIN')) {
    exit;
}

if (strlen($GLOBALS['db'])) { /* Can't do server export */

if (isset($plugin_list)) {
    $plugin_list['xml'] = array(
        'text' => __('XML'),
        'extension' => 'xml',
        'mime_type' => 'text/xml',
        'options' => array(
            array('type' => 'begin_group', 'name' => 'general_opts'),
            array('type' => 'hidden', 'name' => 'structure_or_data'),
            array('type' => 'end_group')
            ),
        'options_text' => __('Options')
        );

    /* Export structure */
    $plugin_list['xml']['options'][] =
        array('type' => 'begin_group', 'name' => 'structure', 'text' => __('Object creation options (all are recommended)'));
    $plugin_list['xml']['options'][] =
        array('type' => 'bool', 'name' => 'export_struc', 'text' => __('Structure Schemas'));
    $plugin_list['xml']['options'][] =
        array('type' => 'bool', 'name' => 'export_functions', 'text' => __('Functions'));
    $plugin_list['xml']['options'][] =
        array('type' => 'bool', 'name' => 'export_procedures', 'text' => __('Procedures'));
    $plugin_list['xml']['options'][] =
        array('type' => 'bool', 'name' => 'export_tables', 'text' => __('Tables'));
    $plugin_list['xml']['options'][] =
        array('type' => 'bool', 'name' => 'export_triggers', 'text' => __('Triggers'));
    $plugin_list['xml']['options'][] =
        array('type' => 'bool', 'name' => 'export_views', 'text' => __('Views'));
    $plugin_list['xml']['options'][] = array('type' => 'end_group');

    /* Data */
    $plugin_list['xml']['options'][] =
        array('type' => 'begin_group', 'name' => 'data', 'text' => __('Data dump options'));
    $plugin_list['xml']['options'][] =
        array('type' => 'bool', 'name' => 'export_contents', 'text' => __('Export contents'));
    $plugin_list['xml']['options'][] = array('type' => 'end_group');
} else {

/**
 * Outputs comment
 *
 * @param   string      Text of comment
 *
 * @return  bool        Whether it suceeded
 */
function PMA_exportComment($text) {
    return PMA_exportOutputHandler('<!-- ' . $text . ' -->' . $GLOBALS['crlf']);
}

/**
 * Outputs export footer
 *
 * @return  bool        Whether it suceeded
 *
 * @access  public
 */
function PMA_exportFooter() {
    $foot = '</pma_xml_export>';

    return PMA_exportOutputHandler($foot);
}

/**
 * Returns Query for CREATE TABLE Definitions
 *
 * @param   string   the SHOW CREATE TABLE query statement result
 * @param   string   the table name
 * @param   string   the end of line sequence
 *
 * @return  string   resulting definition
 *
 * @access  public
 */
function PMA_getTableDef($table_def, $table_name, $crlf) {
    $create_query = '';
    $create_query .= '            <pma:table name="' . $table_name . '">' . $crlf;

    $table_def = "                " . $table_def;
    $table_def = str_replace("\n", "\n                ", $table_def);

    $create_query .= $table_def . ';' . $crlf;
    $create_query .= '            </pma:table>' . $crlf;
    return $create_query;
}

/**
 * Returns a Actual View Definition
 *
 * @param   string   the Database name
 * @param   string   the SHOW CREATE TABLE query statement result
 * @param   string   the view name
 * @param   string   the end of line sequence
 *
 * @return  string   resulting definition
 *
 * @access  public
 */
function PMA_getViewDef($db, $view_def, $view_name, $crlf) {
    $create_query = '';
    $create_query .= '            <pma:view name="' . $view_name . '">' . $crlf;
    $view_def = "                " . $view_def;
    $view_def = str_replace("\n", "\n                ", $view_def);

    // drop database name from VIEW creation.
    $create_query .= $view_def . ';' . $crlf;
    $create_query = preg_replace('/' . PMA_backquote($db) . '\./', '', $create_query);

    $create_query .= '            </pma:view>' . $crlf;
    return $create_query;
}

/**
 * Outputs export header
 *
 * @return  bool        Whether it suceeded
 *
 * @access  public
 */
function PMA_exportHeader() {
    global $crlf;
    global $cfg;
    global $what;
    global $db;
    global $table;
    global $tables;

    $export_struct = isset($GLOBALS[$what . '_export_struc']) ? true : false;
    $export_data = isset($GLOBALS[$what . '_export_contents']) ? true : false;

    if ($GLOBALS['output_charset_conversion']) {
        $charset = $GLOBALS['charset_of_file'];
    } else {
        $charset = $GLOBALS['charset'];
    }

    $head  =  '<?xml version="1.0" encoding="' . $charset . '"?>' . $crlf
           .  '<!--' . $crlf
           .  '- phpMyAdmin XML Dump' . $crlf
           .  '- version ' . PMA_VERSION . $crlf
           .  '- http://www.phpmyadmin.net' . $crlf
           .  '-' . $crlf
           .  '- ' . __('Host') . ': ' . $cfg['Server']['host'];
    if (!empty($cfg['Server']['port'])) {
         $head .= ':' . $cfg['Server']['port'];
    }
    $head .= $crlf
           .  '- ' . __('Generation Time') . ': ' . PMA_localisedDate() . $crlf
           .  '- ' . __('Server version') . ': ' . substr(PMA_MYSQL_INT_VERSION, 0, 1) . '.' . (int) substr(PMA_MYSQL_INT_VERSION, 1, 2) . '.' . (int) substr(PMA_MYSQL_INT_VERSION, 3) . $crlf
           .  '- ' . __('PHP Version') . ': ' . phpversion() . $crlf
           .  '-->' . $crlf . $crlf;

    $head .= '<pma_xml_export version="1.0"' . (($export_struct) ? ' xmlns:pma="http://www.phpmyadmin.net/some_doc_url/"' : '') . '>' . $crlf;

    if ($export_struct) {
        $result = PMA_DBI_fetch_result('SELECT `DEFAULT_CHARACTER_SET_NAME`, `DEFAULT_COLLATION_NAME` FROM `information_schema`.`SCHEMATA` WHERE `SCHEMA_NAME` = \''.$db.'\' LIMIT 1');
        $db_collation = $result[0]['DEFAULT_COLLATION_NAME'];
        $db_charset = $result[0]['DEFAULT_CHARACTER_SET_NAME'];

        $head .= '    <!--' . $crlf;
        $head .= '    - Structure schemas' . $crlf;
        $head .= '    -->' . $crlf;
        $head .= '    <pma:structure_schemas>' . $crlf;
        $head .= '        <pma:database name="' . $db . '" collation="' . $db_collation . '" charset="' . $db_charset . '">' . $crlf;

    /***** Exporting functions first *****/

        if (isset($GLOBALS[$what . '_export_functions']) && $GLOBALS[$what . '_export_functions']) {
            // Export functions
            $functions = PMA_DBI_get_procedures_or_functions($db, 'FUNCTION');
            if ($functions) {
                foreach ($functions as $function) {
                    $head .= '            <pma:function name="' . $function . '">' . $crlf;

                    // Do some formatting
                    $sql = PMA_DBI_get_definition($db, 'FUNCTION', $function);
                    $sql = rtrim($sql);
                    $sql = "                " . $sql;
                    $sql = str_replace("\n", "\n                ", $sql);

                    $head .= $sql . $crlf;
                    $head .= '            </pma:function>' . $crlf;
                }

                unset($create_func);
                unset($function);
                unset($functions);
            }
        }

    /****** Exporting Procedures second ******/

    if (isset($GLOBALS[$what . '_export_procedures']) && $GLOBALS[$what . '_export_procedures']) {
            // Export procedures
            $procedures = PMA_DBI_get_procedures_or_functions($db, 'PROCEDURE');
            if ($procedures) {
                foreach ($procedures as $procedure) {
                    $head .= '            <pma:procedure name="' . $procedure . '">' . $crlf;

                    // Do some formatting
                    $sql = PMA_DBI_get_definition($db, 'PROCEDURE', $procedure);
                    $sql = rtrim($sql);
                    $sql = "                " . $sql;
                    $sql = str_replace("\n", "\n                ", $sql);

                    $head .= $sql . $crlf;
                    $head .= '            </pma:procedure>' . $crlf;
                }

                unset($create_proc);
                unset($procedure);
                unset($procedures);
            }
        }

        /****** Exporting Tables & views structure ******/

        if (count($tables) == 0) {
            $tables[] = $table;
        }

        foreach ($tables as $table) {
            // Export tables and views
            $result = PMA_DBI_fetch_result('SHOW CREATE TABLE ' . PMA_backquote($db) . '.' . PMA_backquote($table), 0);
            $table_def =  $result[$table][1];

            $is_view = PMA_isView($db, $table);

            if ($is_view) {
                $type = 'view';
            } else {
                $type = 'table';
            }

            if ($is_view && ! isset($GLOBALS[$what . '_export_views'])) {
                continue;
            }

            if (! $is_view && ! isset($GLOBALS[$what . '_export_tables'])) {
                continue;
            }

            if ($is_view &&  isset($GLOBALS[$what . '_export_views'])) {
                $view_create[$table] = PMA_getViewDef($db, $table_def, $table, $crlf);
                continue;
            }

            $head .= PMA_getTableDef($table_def, $table, $crlf);

            if (isset($GLOBALS[$what . '_export_triggers']) && $GLOBALS[$what . '_export_triggers']) {
                // Export triggers
                $triggers = PMA_DBI_get_triggers($db, $table);
                if ($triggers) {
                    foreach ($triggers as $trigger) {
                        $code = $trigger['create'];
                        $head .= '            <pma:trigger name="' . $trigger['name'] . '">' . $crlf;

                        // Do some formatting
                        $code = substr(rtrim($code), 0, -3);
                        $code = "                " . $code;
                        $code = str_replace("\n", "\n                ", $code);

                        $head .= $code . $crlf;
                        $head .= '            </pma:trigger>' . $crlf;
                    }

                    unset($trigger);
                    unset($triggers);
                }
            }
        }

        foreach ($view_create as $view => $definition) {
            $head .= $definition;
        }

        /****** Exporting Events ******/

        if (isset($GLOBALS[$what . '_export_events']) && $GLOBALS[$what . '_export_events']) {

            if (PMA_MYSQL_INT_VERSION > 50100) {
                $event_names = PMA_DBI_fetch_result('SELECT EVENT_NAME FROM information_schema.EVENTS WHERE EVENT_SCHEMA= \'' . PMA_sqlAddslashes($db, true) . '\';');
                if ($event_names) {
                    foreach($event_names as $event_name) {
                        $head .= '            <pma:event name="' . $event_name . '">' . $crlf;
                        $sql = PMA_DBI_get_definition($db, 'EVENT', $event_name);
                        $sql = '                ' . $sql;
                        $sql = str_replace("\n", "\n                ", $sql);
                        $head .= $sql . $crlf;
                        $head .= '            </pma:event>' . $crlf;
                    }
                }
            }
        }

        unset($result);

        $head .= '        </pma:database>' . $crlf;
        $head .= '    </pma:structure_schemas>' . $crlf;

        if ($export_data) {
            $head .= $crlf;
        }
    }

    return PMA_exportOutputHandler($head);
}

/**
 * Outputs database header
 *
 * @param   string      Database name
 *
 * @return  bool        Whether it suceeded
 *
 * @access  public
 */
function PMA_exportDBHeader($db) {
    global $crlf;
    global $what;

    if (isset($GLOBALS[$what . '_export_contents']) && $GLOBALS[$what . '_export_contents']) {
        $head = '    <!--' . $crlf
              . '    - ' . __('Database') . ': ' . (isset($GLOBALS['use_backquotes']) ? PMA_backquote($db) : '\'' . $db . '\''). $crlf
              . '    -->' . $crlf
              . '    <database name="' . $db . '">' . $crlf;

        return PMA_exportOutputHandler($head);
    }
    else
    {
        return TRUE;
    }
}

/**
 * Outputs database footer
 *
 * @param   string      Database name
 *
 * @return  bool        Whether it suceeded
 *
 * @access  public
 */
function PMA_exportDBFooter($db) {
    global $crlf;
    global $what;

    if (isset($GLOBALS[$what . '_export_contents']) && $GLOBALS[$what . '_export_contents']) {
        return PMA_exportOutputHandler('    </database>' . $crlf);
    }
    else
    {
        return TRUE;
    }
}

/**
 * Outputs create database database
 *
 * @param   string      Database name
 *
 * @return  bool        Whether it suceeded
 *
 * @access  public
 */
function PMA_exportDBCreate($db) {
    return TRUE;
}


/**
 * Outputs the content of a table
 *
 * @param   string      the database name
 * @param   string      the table name
 * @param   string      the end of line sequence
 * @param   string      the url to go back in case of error
 * @param   string      SQL query for obtaining data
 *
 * @return  bool        Whether it suceeded
 *
 * @access  public
 */
function PMA_exportData($db, $table, $crlf, $error_url, $sql_query) {
    global $what;

    if (isset($GLOBALS[$what . '_export_contents']) && $GLOBALS[$what . '_export_contents']) {
        $result      = PMA_DBI_query($sql_query, null, PMA_DBI_QUERY_UNBUFFERED);

        $columns_cnt = PMA_DBI_num_fields($result);
        for ($i = 0; $i < $columns_cnt; $i++) {
            $columns[$i] = stripslashes(str_replace(' ', '_', PMA_DBI_field_name($result, $i)));
        }
        unset($i);

        $buffer      = '        <!-- ' . __('Table') . ' ' . $table . ' -->' . $crlf;
        if (!PMA_exportOutputHandler($buffer)) {
            return FALSE;
        }

        while ($record = PMA_DBI_fetch_row($result)) {
            $buffer         = '        <table name="' . htmlspecialchars($table) . '">' . $crlf;
            for ($i = 0; $i < $columns_cnt; $i++) {
                // If a cell is NULL, still export it to preserve the XML structure
                if (!isset($record[$i]) || is_null($record[$i])) {
                    $record[$i] = 'NULL';
                }
                $buffer .= '            <column name="' . $columns[$i] . '">' . htmlspecialchars((string)$record[$i])
                        .  '</column>' . $crlf;
            }
            $buffer         .= '        </table>' . $crlf;

            if (!PMA_exportOutputHandler($buffer)) {
                return FALSE;
            }
        }
        PMA_DBI_free_result($result);
    }

    return TRUE;
} // end of the 'PMA_getTableXML()' function
}
}
?>
