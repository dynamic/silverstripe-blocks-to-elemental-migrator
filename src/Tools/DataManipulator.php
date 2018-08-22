<?php

namespace Dynamic\BlockMigration\Tools;

use Dynamic\DynamicBlocks\Model\AccordionPanel;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Dev\Debug;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLAssignmentRow;
use SilverStripe\ORM\Queries\SQLDelete;
use SilverStripe\ORM\Queries\SQLInsert;
use SilverStripe\Versioned\Versioned;

/**
 * Class DataManipulator
 * @package Dynamic\BlockMigration\Tools
 */
class DataManipulator
{
    use Extensible {
        defineMethods as extensibleDefineMethods;
    }
    use Injectable;
    use Configurable;

    /**
     * @var string This is the name of the table that is now used by another class.
     */
    private $from_table;

    /**
     * @var string This is the name of the table the legacy class now uses.
     */
    private $to_table;

    /**
     * @var
     */
    private $schema;

    /**
     * @var
     */
    private $singleton;

    /**
     * @var array
     */
    private static $default_title;

    /**
     * DataManipulator constructor.
     * @param string $fromTable
     * @param string $toTable
     */
    public function __construct($fromTable, $toTable)
    {
        $this->setFromTable($fromTable);
        $this->setToTable($toTable);
        $this->setSchema(DB::getConfig()['database'], 'AccordionPanel');
    }

    /**
     * @param string $table
     * @return $this
     */
    public function setFromTable($table)
    {
        $this->from_table = $table;
        return $this;
    }

    /**
     * @return string
     */
    public function getFromTable()
    {
        return $this->from_table;
    }

    /**
     * @param string $table
     * @return $this
     */
    public function setToTable($table)
    {
        $this->to_table = $table;
        return $this;
    }

    /**
     * @return string
     */
    public function getToTable()
    {
        return $this->to_table;
    }

    /**
     * @param string $database
     * @param string $table
     */
    public function setSchema($database, $table)
    {
        $results = $this->getResults("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = N'{$table}' AND TABLE_SCHEMA = '{$database}'");

        $this->schema = static::results_to_array($results);
        return $this;
    }

    /**
     * @param $results
     * @return array
     */
    protected static function results_to_array($results)
    {
        $schema = [];
        foreach ($results as $key => $result) {
            $schema[$result['COLUMN_NAME']] = $result['COLUMN_NAME'];
        }

        return $schema;
    }

    public function migrateData()
    {
        $class = AccordionPanel::class;
        $table = $this->getFromTable();
        $results = $this->getResults("SELECT * FROM {$this->getFromTable()}");

        foreach ($results as $result) {
            Message::terminal("Migrating {$class::singleton()->singular_name()}: {$result['ID']}");
            $record = $class::create();
            foreach ($this->prepareKeys($table, $result) as $key => $val) {
                $record->$key = $val;
            }

            if (!$record->Title) {
                $record->Title = $this->getDefaultTitle($class);
            }

            //$this->extend('updateMigrationRecord', $record);

            if ($record->hasExtension(Versioned::class)) {
                $record->writeToStage(Versioned::DRAFT);
            } else {
                $record->write();
            }

            Message::terminal("\tRecord migrated");

            //todo this doesn't consistently delete the data from the previous table as needed
            $where = ["\"{$table}\".\"ID\"" => $result['ID']];
            $deleteQuery = SQLDelete::create()
                ->setFrom($table)
                ->setWhere($where);
            $deleteQuery->execute();
        }
    }

    /**
     * @param $result
     * @return array
     */
    protected function prepareKeys($table, $result)
    {
        $data = [];
        foreach ($result as $key => $val) {
            $data[$key] = $val;
        }

        return $data;
    }

    /**
     * @param $query
     * @return \SilverStripe\ORM\Connect\Query
     */
    protected function getResults($query)
    {
        return DB::query($query);
    }

    /**
     * @param string $class
     * @return string
     */
    protected function getDefaultTitle($class)
    {
        $config = $this->config()->get('default_title');
        if (!is_array($config) || is_null($config) || !isset($config[$class])) {
            $title = "Migrated {$record->singular_name()} record";
        } else {
            $config[$class];
        }

        return $title;
    }
}