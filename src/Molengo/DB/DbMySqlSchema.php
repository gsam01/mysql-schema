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
        $exportCols = $this->getCsvColumns($mapping);
        $row = array();
        foreach ($exportCols as $col) {
            $row[] = $this->escCsv($col);
        }
        $return .= implode(";", $row) . $nl;

        foreach ($tables as $table) {
            $cols = $this->getTableColumns($table['table_name']);
            $contraints = $this->getTableContraints($table['table_name']);
            $return .= $this->getCsvTable($table, $cols, $contraints);
            $return .= $nl;
        }
        return $return;
    }

    public function getXsd()
    {
        $return = '';
        $tables = $this->getTables();
        if (empty($tables)) {
            return $return;
        }
        #print_r($tables);
        $arrTables = array();
        foreach ($tables as $table) {

            $strTableName = $table['table_name'];
            //$strTableName2 = substr($strTableName, 0, strlen($strTableName) - 1);
            $strTableName2 = \Inflector::singularize($strTableName);
            $strTableComment = $table['table_comment'];
            echo $strTableName . "\n";

            $arrTable = array(
                '@attributes' => array(
                    'name' => $strTableName,
                    'minOccurs' => '0'
                )
            );

            if (!empty($strTableComment)) {
                $arrTable['xs:annotation'] = array(
                    'xs:documentation' => $strTableComment
                );
            }

            $arrTableCols = $this->getTableColumns($table['table_name']);
            $arrColSchema = $this->getColXsdElements($arrTableCols);

            $arrTableSchema = array();
            $arrTableSchema[] = array(
                'xs:element' => array(
                    '@attributes' => array(
                        'name' => $strTableName2,
                        'minOccurs' => '0',
                        'maxOccurs' => 'unbounded'
                    ),
                    'xs:annotation' => array(
                        'xs:documentation' => ''
                    ),
                    'xs:complexType' => array(
                        'xs:sequence' => $arrColSchema
                    )
                )
            );
            $arrTable['xs:complexType'] = array(
                'xs:sequence' => $arrTableSchema
            );
            $arrTables['xs:element'][] = $arrTable;
        }

        $arrXml = array(
            '@attributes' => array(
                'xmlns:xs' => 'http://www.w3.org/2001/XMLSchema',
                'xmlns:vc' => 'http://www.w3.org/2007/XMLSchema-versioning',
                'elementFormDefault' => 'qualified',
                'attributeFormDefault' => 'unqualified',
                'vc:minVersion' => '1.1'
            ),
            'xs:element' => array(
                array(
                    '@attributes' => array(
                        'name' => 'root'
                    ),
                    'xs:annotation' => array(
                        'xs:documentation' => 'Root element'
                    ),
                    'xs:complexType' => array(
                        'xs:sequence' => $arrTables
                    )
                ),
            )
        );

        $xml = new \Molengo\XmlUtil();
        $xml = $xml->convertArrayToXml('xs:schema', $arrXml);
        $strReturn = $xml->saveXML();
        //file_put_contents('c:\test.xsd', $strReturn);

        return $strReturn;
    }

    public function getColXsdElements($arrCols)
    {
        $arrReturn = array();

        foreach ($arrCols as $arrCol) {
            #print_r($arrCol);
            $strName = $arrCol['column_name'];
            $strNillable = ($arrCol['is_nullable'] == 'NO') ? 'false' : 'true';

            $arrEl = array(
                '@attributes' => array(
                    'name' => $strName,
                    'minOccurs' => '0',
                    'nillable' => $strNillable
                ),
            );
            if (!empty($arrCol['column_comment'])) {
                $arrEl['xs:annotation'] = array(
                    'xs:documentation' => $arrCol['column_comment']
                );
            }

            // datatype
            $arrEl['xs:simpleType'] = $this->getColumnXsType($arrCol);

            $arrReturn['xs:element'][] = $arrEl;
        }

        /*
          [column_name] => id
          [column_default] =>
          [is_nullable] => NO
          [data_type] => int
          [character_maximum_length] =>
          [character_octet_length] =>
          [numeric_precision] => 10
          [numeric_scale] => 0
          [character_set_name] =>
          [collation_name] =>
          [column_type] => int(11)
          [column_key] => PRI
          [extra] => auto_increment
          [privileges] => select,insert,update,references
          [column_comment] =>
         */

        return $arrReturn;
    }

    public function getColumnXsType($arrCol)
    {
        $arrReturn = array();

        $strColumnType = $arrCol['column_type'];
        $arrMatch = array();
        preg_match('/^([a-z]+)(\((.*)\))?$/', $strColumnType, $arrMatch);
        //print_r($arrMatch);
        $numMin = 0;
        $numMax = 0;
        $strType = $arrMatch[1];
        $strLength = isset($arrMatch[3]) ? $arrMatch[3] : null;

        // string
        if ($strType == 'varchar' || $strType == 'longtext') {
            if ($strType == 'longtext') {
                $strLength = '2147483647';
            }
            // @todo mediumtext
            $arrReturn['xs:restriction'] = array(
                '@attributes' => array(
                    'base' => 'xs:string'
                ),
                'xs:maxLength' => array(
                    '@attributes' => array(
                        'value' => $strLength
                    )
                )
            );
        }
        if ($strType == 'int') {
            if ($strLength == 11) {
                //$numMin = '-2147483648';
                $numMin = '0';
                $numMax = '2147483647';
            }
            $arrReturn['xs:restriction'] = array(
                '@attributes' => array(
                    'base' => 'xs:int'
                ),
                'xs:minInclusive' => array(
                    '@attributes' => array(
                        'value' => $numMin
                    )
                ),
                'xs:maxInclusive' => array(
                    '@attributes' => array(
                        'value' => $numMax
                    )
                )
            );
        }
        if ($strType == 'datetime') {

            $boolClassic = false;
            if ($boolClassic) {
                $arrReturn['xs:restriction'] = array(
                    '@attributes' => array(
                        'base' => 'xs:dateTime'
                    ),
                    'xs:minInclusive' => array(
                        '@attributes' => array(
                            'value' => '0000-00-00T00:00:00'
                        )
                    ),
                    'xs:maxInclusive' => array(
                        '@attributes' => array(
                            'value' => '9999-12-31T23:59:59'
                        )
                    ),
                    'xs:pattern' => array(
                        '@attributes' => array(
                            'value' => '\p{Nd}{4}-\p{Nd}{2}-\p{Nd}{2}T\p{Nd}{2}:\p{Nd}{2}:\p{Nd}{2}'
                        )
                    )
                );
            } else {
                // iso datetime as string
                $arrReturn['xs:restriction'] = array(
                    '@attributes' => array(
                        'base' => 'xs:string'
                    ),
                    'xs:length' => array(
                        '@attributes' => array(
                            'value' => '19'
                        )
                    ),
                    'xs:pattern' => array(
                        '@attributes' => array(
                            'value' => '\p{Nd}{4}-\p{Nd}{2}-\p{Nd}{2}\s\p{Nd}{2}:\p{Nd}{2}:\p{Nd}{2}'
                        )
                    )
                );
            }
        }
        if ($strType == 'tinyint') {
            if ($strLength == '1') {
                $numMin = '0';
                $numMax = '1';
            } else {
                $this->setMinMaxSigned($strLength, $numMin, $numMax);
            }

            $arrReturn['xs:restriction'] = array(
                '@attributes' => array(
                    'base' => 'xs:byte'
                ),
                'xs:minInclusive' => array(
                    '@attributes' => array(
                        'value' => $numMin
                    )
                ),
                'xs:maxInclusive' => array(
                    '@attributes' => array(
                        'value' => $numMax
                    )
                )
            );
        }
        if ($strType == 'smallint') {
            $this->setMinMaxSigned($strLength, $numMin, $numMax);
            $numMin = '0'; // '-32768';
            $numMax = '32767';
            $arrReturn['xs:restriction'] = array(
                '@attributes' => array(
                    'base' => 'xs:short'
                ),
                'xs:minInclusive' => array(
                    '@attributes' => array(
                        'value' => $numMin
                    )
                ),
                'xs:maxInclusive' => array(
                    '@attributes' => array(
                        'value' => $numMax
                    )
                )
            );
        }
        if ($strType == 'bigint') {
            //$this->setMinMaxSigned($strLength, $numMin, $numMax);
            //$numMin = '-9223372036854775808';
            $numMin = '0';
            $numMax = '9223372036854775807';
            $arrReturn['xs:restriction'] = array(
                '@attributes' => array(
                    'base' => 'xs:long'
                ),
                'xs:minInclusive' => array(
                    '@attributes' => array(
                        'value' => $numMin
                    )
                ),
                'xs:maxInclusive' => array(
                    '@attributes' => array(
                        'value' => $numMax
                    )
                )
            );
        }
        //identity_card_required


        if ($strType == 'decimal') {

            $arrMatchLen = array();
            preg_match('/^([0-9]+)*,?([0-9]+)?$/', $strLength, $arrMatchLen);

            //$this->setMinMaxSigned($strLength, $numMin, $numMax);
            $arrReturn['xs:restriction'] = array(
                '@attributes' => array(
                    'base' => 'xs:decimal'
            ));
            if (isset($arrMatchLen[1])) {
                $numTotalDigits = $arrMatchLen[1];

                $arrReturn['xs:restriction']['xs:totalDigits'] = array(
                    '@attributes' => array(
                        'value' => $numTotalDigits
                ));

                //$arrReturn['xs:restriction']['xs:minInclusive'] = array(
                //    '@attributes' => array(
                //        'value' => $numMin
                //));
            }
            if (isset($arrMatchLen[2])) {
                $numFractionDigits = $arrMatchLen[2];

                $arrReturn['xs:restriction']['xs:fractionDigits'] = array(
                    '@attributes' => array(
                        'value' => $numFractionDigits
                ));

                //$arrReturn['xs:restriction']['xs:maxInclusive'] = array(
                //    '@attributes' => array(
                //        'value' => $numMax
                //    )
                //);
            }
        }

        if (empty($arrReturn['xs:restriction'])) {
            print_r($arrCol);
            throw new \Exception('No type found for ' . $strType . '(' . $strLength . ')');
        }

        return $arrReturn;
    }

    public function setMinMaxSigned($numBytes, &$numMin, &$numMax)
    {
        $numBytes = (int) $numBytes;
        // 8 bits (one byte) --> 2^8 (2x2x2x2x2x2x2x2) = 256 possibilities
        $numBits = $numBytes * 8;
        $numPossible = pow(2, $numBits);
        $numMin = ($numPossible / 2) * -1;
        $numMax = ($numPossible / 2) - 1;
        return $numPossible;
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
                $this->escCsv(encode_iso($col['column_comment']))
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
    protected function getHtmlColumns($mapping)
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

    /**
     *
     * @param bool $mapping
     * @return string
     */
    protected function getCsvColumns($mapping)
    {
        $return = array();
        //$return['{database}'] = 'Database';

        $return['{table_name}'] = 'Table';
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
        $exportCols = $this->getHtmlcolumns($mapping);
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
                font-size: 12px;
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
