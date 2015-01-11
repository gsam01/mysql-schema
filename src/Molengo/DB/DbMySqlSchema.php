<?php

namespace Molengo\DB;

use PDO;

class DbMySqlSchema
{

    protected $db;

    /**
     *
     * @param array $params
     * @return boolean
     */
    public function connect($strDsn)
    {
        $arrDsn = explode(';', $strDsn);
        foreach ($arrDsn as $value) {
            list($k, $v) = explode('=', $value);
            $arrDsn[strtolower($k)] = trim($v);
        }
        // open connection
        $this->db = new PDO($strDsn, $arrDsn['username'], $arrDsn['password'], array(PDO::MYSQL_ATTR_INIT_COMMAND => 'SET NAMES utf8'));
        $this->db->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->db->setAttribute(PDO::ATTR_ORACLE_NULLS, PDO::NULL_TO_STRING);
        $this->db->setAttribute(PDO::ATTR_CASE, PDO::CASE_LOWER);
        return true;
    }

    /**
     *
     * @return type
     */
    protected function getDb()
    {
        return $this->db;
    }

    protected function query($sql, $fields = null)
    {
        $db = $this->getDb();
        $stm = $db->prepare($sql);
        if ($fields === null) {
            $stm->execute();
        } else {
            $stm->execute($fields);
        }
        $result = $stm->fetchAll(PDO::FETCH_ASSOC);
        return $result;
    }

    public function getTables()
    {
        $sql = "SELECT *
                FROM information_schema.tables
                WHERE table_schema = database();";
        $result = $this->query($sql);
        return $result;
    }

    /**
     * Returns all columns in a table
     *
     * @param string $table
     * @return array
     */
    public function getTableColumns($table)
    {
        $sql = 'SELECT
            column_name,
            column_default,
            is_nullable,
            data_type,
            character_maximum_length,
            character_octet_length,
            numeric_precision,
            numeric_scale,
            character_set_name,
            collation_name,
            column_type,
            column_key,
            extra,
            `privileges`,
            column_comment
            FROM information_schema.columns
            WHERE table_schema = DATABASE()
            AND table_name = :table;';

        $fields = array(
            ':table' => $table
        );

        $result = $this->query($sql, $fields);
        return $result;
    }

    /**
     * Returns all columns in a table
     *
     * @param string $table
     * @return array
     */
    public function getTableContraints($table)
    {
        $sql = 'SELECT *
            FROM
              `information_schema`.`KEY_COLUMN_USAGE`
            WHERE
            CONSTRAINT_SCHEMA =  DATABASE()
            AND TABLE_NAME=:table;';

        $fields = array(
            ':table' => $table
        );

        $result = $this->query($sql, $fields);
        return $result;
    }

    public function getTableSchemas()
    {
        $return = array();
        $tables = $this->getTables();
        foreach ($tables as $table) {
            $cols = $this->getTableColumns($table['table_name']);
            $return[$table['table_name']] = $cols;
        }
        return $return;
    }

    public function getHtml($params = array())
    {
        $return = '';
        $tables = $this->getTables();
        if (empty($tables)) {
            return $return;
        }
        $return .= $this->getHtmlCss() . "\n";
        foreach ($tables as $table) {
            $cols = $this->getTableColumns($table['table_name']);
            $contraints = $this->getTableContraints($table['table_name']);
            $return .= "\n" . $this->getHtmlTable($table, $cols, $contraints, $params);
        }
        return $return;
    }

    public function getCsv($params = array())
    {
        $return = '';
        $nl = "\n";
        $tables = $this->getTables();
        if (empty($tables)) {
            return $return;
        }
        $mapping = !empty($params['mapping']);
        $exportCols = $this->getExportColumns($mapping);
        $row = array();
        foreach ($exportCols as $col) {
            $row[] = $this->escCsv($col);
        }
        $return .= implode(";", $row) . $nl;

        foreach ($tables as $table) {
            $cols = $this->getTableColumns($table['table_name']);
            $contraints = $this->getTableContraints($table['table_name']);
            $return .= $this->getCsvTable($table, $cols, $contraints);
        }
        return $return;
    }

    public function saveCsvFile($filename)
    {
        $csv = $this->getCsv();
        return file_put_contents($filename, $csv);
    }

    protected function getCsvTable($table, $cols, $contraints, $params = array())
    {
        $nl = "\n";
        $csv = '';
        //$row = array(
        //    $this->escCsv("Table"),
        //    $this->escCsv($table['table_name'])
        //);
        //$csv .= implode(";", $row) . $nl;
        //if (!empty($table['table_comment'])) {
        //    $row = array(
        //        $this->escCsv("Table comment"),
        //        $this->escCsv($table['table_comment'])
        //    );
        //    $csv .= implode(";", $row) . $nl;
        //}
        //$csv .= $nl;

        $colContraints = array();
        if (!empty($contraints)) {
            foreach ($contraints as $const) {
                if (empty($const['referenced_table_name'])) {
                    continue;
                }
                $text = '';
                $text .= $const['referenced_table_name'] . '.';
                $text .= $const['referenced_column_name'];
                //$const['table_column'] = $text;
                $colContraints[$const['column_name']][] = $text;
            }
        }

        foreach ($cols as $col) {
            $colname = $col['column_name'];
            $fks = '';
            if (isset($colContraints[$colname])) {
                $fks = implode(",", $colContraints[$colname]);
            }
            $row = array(
                $this->escCsv($table['table_name']),
                $this->escCsv($col['column_name']),
                $this->escCsv($col['column_type']),
                $this->escCsv($col['is_nullable']),
                $this->escCsv($col['column_key']),
                $this->escCsv($col['extra']),
                $this->escCsv($col['column_default']),
                $this->escCsv($fks),
                $this->escCsv($col['column_comment'])
            );
            $csv .= implode(";", $row) . $nl;
        }
        return $csv;
    }

    /**
     * Escape/quote as Excel CSV string
     * @param string $value
     */
    public function escCsv($value)
    {
        if ($value === null || $value === '') {
            return '""';
        }
        $return = '"' . str_replace('"', '""', $value) . '"';
        $return = $this->encodeIso($return);
        return $return;
    }

    /**
     * Returns a ISO-8859-1 encoded string or array
     *
     * @param mixed $mix
     * @return mixed
     */
    protected function encodeIso($mix)
    {
        if ($mix === null || $mix === '') {
            return $mix;
        }
        if (is_array($mix)) {
            foreach ($mix as $str_key => $str_val) {
                $mix[$str_key] = encode_iso($str_val);
            }
            return $mix;
        } else {
            if (mb_check_encoding($mix, 'UTF-8')) {
                return mb_convert_encoding($mix, 'ISO-8859-1', 'auto');
            } else {
                return $mix;
            }
        }
    }

    /**
     *
     * @param bool $mapping
     * @return string
     */
    protected function getExportColumns($mapping)
    {
        $return = array();
        $return['{database}'] = 'Database';
        $return['{column}'] = 'Column';
        $return['{column_type}'] = 'Type';
        $return['{is_nullable}'] = 'Null';
        $return['{column_key}'] = 'Key';
        $return['{extra}'] = 'Extra';
        $return['{column_default}'] = 'Default';
        $return['{fk}'] = 'Foreign keys';
        $return['{column_comment}'] = 'Comment';
        if ($mapping) {
            $return['{mapping_table}'] = 'Mapping table';
            $return['{mapping_columns}'] = 'Mapping column';
            $return['{mapping_type}'] = 'Mapping type';
            $return['{mapping_comment}'] = 'Mapping comment';
        }
        return $return;
    }

    public function h($str)
    {
        return htmlentities($str);
    }

    public function getHtmlTable($table, $cols, $contraints, $params = array())
    {
        $mapping = !empty($params['mapping']);
        $html = '';
        $html .= '<div><h1>' . $this->h($table['table_name']) . ' </h1>';

        if (!empty($table['table_comment'])) {
            $html .= '<table style="width: 100%; border:0px"><tbody><tr>';
            $html .= '<td style="width: 10%; border:0px">' . $this->h('Table comment') . '</td>';
            $html .= '<td style="border:0px">' . $this->h($table['table_comment']) . '</td>';
            $html .= '</tbody></table>';
            $html .= '<br>';
        }

        $html .= '<table style="width: 100%;"><thead><tr>';
        $exportCols = $this->getExportcolumns($mapping);
        unset($exportCols['{database}']);
        foreach ($exportCols as $col) {
            $html .= '<th>' . $this->h($col) . '</th>' . "\n";
        }
        $html .= '</tr></thead><tbody>';

        $colContraints = array();
        if (!empty($contraints)) {
            foreach ($contraints as $const) {
                if (empty($const['referenced_table_name'])) {
                    continue;
                }
                $text = '';
                //$text .= $const['column_name'] . ' -> ';
                $text .= $const['referenced_table_name'] . '.';
                $text .= $const['referenced_column_name'];
                $anchorname = $const['referenced_table_name'] . '_' . $const['referenced_column_name'];
                $text = $this->h($text);
                $link = sprintf('<a href="#%s">%s</a>', $anchorname, $text);
                $colContraints[$const['column_name']][] = $link;
            }
        }

        foreach ($cols as $col) {
            $colname = $col['column_name'];
            $anchorname = $table['table_name'] . '_' . $col['column_name'];
            $fks = '';
            if (isset($colContraints[$colname])) {
                $fks = implode("<br>\n", $colContraints[$colname]);
            }

            $html .= strtr('<tr>
                <td>{column}&nbsp;<a name="{anchorname}"></a></td>
                <td>{column_type}&nbsp;<bdo dir="ltr"></bdo></td>
                <td>{is_nullable}&nbsp;</td>
                <td>{column_key}&nbsp;</td>
                <td>{extra}&nbsp;</td>
                <td>{column_default}&nbsp;</td>
                <td>{fk}&nbsp;</td>
                <td>{column_comment}&nbsp;</td>', array(
                '{column}' => $this->h($col['column_name']),
                '{anchorname}' => $this->h($anchorname),
                '{column_type}' => $this->h($col['column_type']),
                '{is_nullable}' => $this->h($col['is_nullable']),
                '{column_key}' => $this->h($col['column_key']),
                '{extra}' => $this->h($col['extra']),
                '{column_default}' => $this->h($col['column_default']),
                '{fk}' => $fks,
                '{column_comment}' => $this->h($col['column_comment'])
            ));
            if ($mapping) {
                $html .= '<td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>
                <td>&nbsp;</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</tbody></table></div>';
        return $html;
    }

    public function getHtmlCss()
    {
        return '<style>' . '

            *{
                font-family: Trebuchet MS, Calibri, sans-serif;
            }

            .nowrap {
                white-space: nowrap;
            }

            .hide {
                display: none;
            }

            body, table, th, td {
                color:             #000;
                background-color:  #fff;
            }

            img {
                border: 0;
            }

            table, th, td {
                border: 1px solid #ccc;
            }

            table {
                border-collapse:   collapse;
                border-spacing:    0;
            }

            th, td {
                padding:           0.2em;
            }

            th {
                font-weight:       bold;
                background-color:  #e5e5e5;
            }

            th.vtop, td.vtop {
                vertical-align: top;
            }

            th.vbottom, td.vbottom {
                vertical-align: bottom;
            }

            @media print {
                .print_ignore {
                    display: none;
                }

                .nowrap {
                    white-space: nowrap;
                }

                .hide {
                    display: none;
                }

                body, table, th, td {
                    color:             #000;
                    background-color:  #fff;
                }

                img {
                    border: 0;
                }

                table, th, td {
                    border: .1em solid #000;
                }

                table {
                    border-collapse:   collapse;
                    border-spacing:    0;
                }

                th, td {
                    padding:           0.2em;
                }

                th {
                    font-weight:       bold;
                    background-color:  #e5e5e5;
                }

                th.vtop, td.vtop {
                    vertical-align: top;
                }

                th.vbottom, td.vbottom {
                    vertical-align: bottom;
                }
            }' . '</style>';
    }

}
