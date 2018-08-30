<?php

namespace Dynamic\BlockMigration\Tools;

use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Dev\Debug;
use SilverStripe\ORM\DB;
use SilverStripe\ORM\Queries\SQLAssignmentRow;
use SilverStripe\ORM\Queries\SQLDelete;
use SilverStripe\ORM\Queries\SQLInsert;
use SilverStripe\ORM\Queries\SQLSelect;
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
     * @var string The classname of the object that needs the data.
     */
    private $to_class;

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
     * @var bool Whether we are querying a parent table as the DataObject doesn't have it's own table. This is fairly basic and doesn't support multi-level hierarchy at this time.
     */
    private $use_parent_table = false;

    /**
     * @var array
     */
    private $delete_records = [];

    /**
     * @var array
     */
    private static $default_title;

    /**
     * DataManipulator constructor.
     * @param string $fromTable
     * @param string $toClass
     */
    public function __construct($configuration)
    {
        $toClass = $configuration['ToClass'];

        $this->setFromTable($configuration['SourceTable']);
        $this->setToClass($toClass);

        if (isset($configuration['ParentTable']) && $configuration['ParentTable']) {
            $this->setParentTable($configuration['ParentTable']);
        }

        $this->setToTable($toClass::getSchema()->tableName($configuration['ToClass']));
        $this->setSchema(DB::getConfig()['database'], $configuration['SourceTable']);
    }

    /**
     * @param string $toClass
     * @return $this
     */
    public function setToClass($toClass)
    {
        $this->to_class = $toClass;

        return $this;
    }

    /**
     * @return string
     */
    public function getToClass()
    {
        return $this->to_class;
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
     * @param $parentTable
     * @return $this
     */
    public function setParentTable($parentTable)
    {
        $this->use_parent_table = $parentTable;

        return $this;
    }

    /**
     * @return bool
     */
    public function getParentTable()
    {
        return $this->use_parent_table;
    }

    /**
     * @param string $database
     * @param string $table
     */
    public function setSchema($database, $table)
    {
        $results = $this->getDBQuery("SELECT * FROM INFORMATION_SCHEMA.COLUMNS WHERE TABLE_NAME = N'{$table}' AND TABLE_SCHEMA = '{$database}'");

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
        $class = $this->getToClass();
        $table = $this->getFromTable();

        $where = ($this->getParentTable())
            ? ['ClassName' => $class]
            : null;

        $results = $this->getResults($table, '*', $where);

        foreach ($results as $result) {
            Message::terminal("Migrating {$class::singleton()->singular_name()}: {$result['ID']}");
            $record = $class::create();
            foreach ($this->prepareKeys($table, $result) as $key => $val) {
                if ($key != 'ClassName') {
                    $record->$key = $val;
                }
            }

            if (!$record->Title) {
                $record->Title = $this->getDefaultTitle($class);
            }

            //$this->extend('updateMigrationRecord', $record, $result);

            if ($record->hasExtension(Versioned::class)) {
                $published = $record->isPublished();
            }

            $record->write();

            if (isset($published)) {
                $record->writeToStage(Versioned::DRAFT);
                if ($published) {
                    $record->publishRecursive();
                }
            }

            Message::terminal("\tRecord migrated");

            $this->delete_records[$table][$result['ID']] = [$result['ID']];
        }

        $this->deleteLegacyData();
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
    protected function getResults($from, $select, $where)
    {
        $query = new SQLSelect();
        $query->addFrom($from);
        if ($where !== null) {
            $query->addWhere($where);
        }

        $this->extend('updateResultsQuery', $query);

        return $query->execute();
    }

    /**
     * @param $query
     * @return \SilverStripe\ORM\Connect\Query
     */
    protected function getDBQuery($query)
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
            $title = "Migrated {$class::singleton()->singular_name()} record";
        } else {
            $config[$class];
        }

        return $title;
    }

    /**
     *
     */
    protected function deleteLegacyData()
    {
        foreach ($this->delete_records as $table => $ids) {
            foreach ($ids as $key => $val) {
                //todo this doesn't consistently delete the data from the previous table as needed
                $where = ["\"{$table}\".\"ID\"" => $val];
                $deleteQuery = SQLDelete::create()
                    ->setFrom($table)
                    ->setWhere($where);
                $deleteQuery->execute();

                unset($deleteQuery);
            }
        }
    }
}