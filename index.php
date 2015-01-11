<?php

require_once __DIR__ . '/config.php';

main();

function main()
{
    $dbs = new Molengo\DB\DbMySqlSchema();
    $dbs->connect(Config::get('db.dsn'));

    // index.php?format=csv&mapping=0
    $strFormat = gv($_GET, 'format', 'html');
    $boolMapping = gv($_GET, 'mapping', '0') == 1 ? true : false;

    //$tables = $dbs->getTables();
    //print_r($tables);
    //$cols = $dbs->getTableColumns('files');
    //print_r($cols);
    //$schema = $dbs->getTableSchemas();
    //print_r($schema);

    if ($strFormat == 'html') {
        echo $dbs->getHtml(array(
            'mapping' => $boolMapping
        ));
    }
    if ($strFormat == 'csv') {
        echo $dbs->getCsv(array(
            'mapping' => $boolMapping
        ));
    }
}
