<?php

/**
 * Matomo - free/libre analytics platform
 *
 * @link    https://matomo.org
 * @license https://www.gnu.org/licenses/gpl-3.0.html GPL v3 or later
 */
namespace Piwik\Updater\Migration\Db;

use Piwik\Db\Schema;
/**
 * @see Factory::createTable()
 * @ignore
 */
class CreateTable extends \Piwik\Updater\Migration\Db\Sql
{
    /**
     * Constructor.
     * @param string $table Prefixed table name
     * @param string|string[] $columnNames array(columnName => columnValue)
     * @param string|string[] $primaryKey one or multiple columns that define the primary key
     */
    public function __construct($table, $columnNames, $primaryKey)
    {
        $columns = array();
        foreach ($columnNames as $column => $type) {
            $columns[] = sprintf('`%s` %s', $column, $type);
        }
        if (!empty($primaryKey)) {
            $columns[] = sprintf('PRIMARY KEY ( `%s` )', implode('`, `', $primaryKey));
        }
        $sql = sprintf('CREATE TABLE `%s` (%s) %s', $table, implode(', ', $columns), Schema::getInstance()->getTableCreateOptions());
        parent::__construct($sql, static::ERROR_CODE_TABLE_EXISTS);
    }
}
