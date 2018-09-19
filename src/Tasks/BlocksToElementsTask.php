<?php

namespace Dynamic\BlockMigration\Tasks;

use DNADesign\Elemental\Models\ElementalArea;
use Dynamic\BlockMigration\Tools\BlockElementTranslator;
use Dynamic\BlockMigration\Tools\DataManipulator;
use Dynamic\BlockMigration\Tools\ElementalAreaGenerator;
use Dynamic\BlockMigration\Tools\Message;
use Dynamic\BlockMigration\Traits\BlockMigrationConfigurationTrait;
use Dynamic\Elements\Accordion\Elements\ElementAccordion;
use SheaDawson\Blocks\Model\Block;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Control\Controller;
use SilverStripe\Control\Director;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
use SilverStripe\Dev\Debug;
use SilverStripe\ORM\DataObject;
use SilverStripe\ORM\Queries\SQLSelect;

/**
 * Class BlocksToElementsTask
 * @package Dynamic\BlockMigration\Tasks
 */
class BlocksToElementsTask extends BuildTask
{
    use BlockMigrationConfigurationTrait;

    /**
     * @var string $title Shown in the overview on the {@link TaskRunner}
     * HTML or CLI interface. Should be short and concise, no HTML allowed.
     */
    protected $title = 'SilverStripe Blocks to SilverStripe Elemental Migration Task';

    /**
     * @var string $description Describe the implications the task has,
     * and the changes it makes. Accepts HTML formatting.
     */
    protected $description = 'A task for migrating data from SilverStripe Blocks to SilverStripe Elemental';

    /**
     * @var string /dev/tasks/migration-block-task
     */
    private static $segment = 'migration-block-task';

    /**
     * @var bool Should the ClassName Update Task run first?
     */
    private static $class_name_migration = false;

    /**
     * @var
     */
    private $block_class_mapping;

    /**
     * @var
     */
    private $migration_mapping;

    /**
     * @param \SilverStripe\Control\HTTPRequest $request
     */
    public function run($request)
    {
        $migrationMapping = $this->config()->get('migration_mapping');

        foreach ($migrationMapping as $block => $mapping) {
            if (isset($mapping['MigrateOptionFromTable'])) {
                foreach ($mapping['MigrateOptionFromTable'] as $relationName => $tableData) {
                    if (isset($mapping['Relations']) && isset($mapping['Relations'][$relationName])) {
                        $manipulationConfig = $this->getManipulationConfig($tableData);

                        $manipulator = new DataManipulator($manipulationConfig);
                        $manipulator->migrateData();
                    }
                }
            }
        }

        foreach ($migrationMapping as $currentClass => $mapping) {
            Message::terminal("Migrating {$currentClass} to {$mapping['NewObject']}");
            $relations = isset($mapping['Relations']) ? $mapping['Relations'] : [];
            $this->processBlockRecords($currentClass::get(), $mapping['NewObject'], $relations);
        } //*/
    }

    /**
     * @return mixed
     */
    public function getBlockClassMapping()
    {
        if (!$this->block_class_mapping) {
            $this->setBlockClassMapping();
        }

        return $this->block_class_mapping;
    }

    /**
     * @return $this
     */
    public function setBlockClassMapping()
    {
        $this->block_class_mapping = $this->parseMapping('mappings');

        return $this;
    }

    protected function parseMapping($key = '')
    {
        return $this->config()->get($key);
    }

    /**
     * @param $configInformation
     */
    protected function updateBlocksClassName($configInformation)
    {
        foreach ($this->yieldBlocks($configInformation['LegacyName']) as $block) {
            $block->ClassName = $configInformation['NewName'];
            $block->write();
        }
    }

    /**
     * @param $className
     * @return \Generator
     */
    protected function yieldBlocks($className)
    {
        foreach (Block::get()->filter('ClassName', $className) as $record) {
            yield $record;
        }
    }

    /**
     * @param $records Legacy Block Records
     * @param $elementType
     */
    protected function processBlockRecords($records, $elementType, $relations)
    {
        foreach ($records as $record) {
            if ($record->Pages()->exists()) {
                foreach ($this->yieldBlockPages($record) as $page) {
                    if ($page->hasMethod('getElementalRelations')) {
                        $area = ElementalAreaGenerator::find_or_make_elemental_area($page);
                        $element = BlockElementTranslator::translate_block($record, $elementType, $relations);

                        $element->ParentID = $area->ID;
                        $element->LegacyID = $record->ID;
                        $element->write();
                    }
                }
            } else {
                $element = BlockElementTranslator::translate_block($record, $elementType, $relations);
            }
        }
    }

    /**
     * @param Block $block
     * @return \Generator
     */
    protected function yieldBlockPages(Block $block)
    {
        foreach ($block->Pages() as $page) {
            yield $page;
        }
    }

    /**
     * @param $tableData
     * @return array
     */
    protected function getManipulationConfig($tableData)
    {
        $config = [];
        foreach ($tableData as $key => $val) {
            if ($key != 'UseParentTable') {
                $config['SourceTable'] = $key;
                $config['ToClass'] = $val;
            } else {
                $config['ParentTable'] = true;
            }
        }

        return $config;
    }
}
