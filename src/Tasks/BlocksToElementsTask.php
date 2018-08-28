<?php

namespace Dynamic\BlockMigration\Tasks;

use DNADesign\Elemental\Models\ElementalArea;
use Dynamic\BlockMigration\Tools\BlockElementTranslator;
use Dynamic\BlockMigration\Tools\DataManipulator;
use Dynamic\BlockMigration\Traits\BlockMigrationConfigurationTrait;
use Dynamic\ClassNameUpdate\BuildTasks\DatabaseClassNameUpdateTask;
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
     * @var string
     */
    private static $segment = 'MigrateBlocksTask';

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
        /*$mappings = $this->getBlockClassMapping();
        DatabaseClassNameUpdateTask::singleton()
            ->run(Controller::curr()->getRequest(), $mappings);*/

        $migrationMapping = $this->config()->get('migration_mapping');

        /*foreach ($migrationMapping as $block => $mapping) {
            foreach($mapping['Relations'] as $key => $data){
                $manipulator = new DataManipulator($data['MigrateOptionFromTable'], $data['BlockRelatedObject']);
                $manipulator->migrateData();
            }
        }*/

        foreach ($migrationMapping as $block => $mapping) {
            self::write_it("Migrating {$block} to {$mapping['Element']}");
            $relations = isset($mapping['Relations']) ? $mapping['Relations'] : null;
            //$callable = isset($mapping['CustomMethod']) ? $mapping['CustomMethod'] : null;
            $this->processBlockRecords($block::get(), $mapping['Element'], $relations);
        }
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
                        foreach ($page->getElementalRelations() as $relation) {
                            $areaID = $relation . 'ID';
                            if (!$page->$areaID) {
                                $area = ElementalArea::create();
                                $area->OwnerClassName = $page->ClassName;
                                $area->write();
                                $page->$areaID = $area->ID;
                                $page->write();
                            } elseif ($area = ElementalArea::get()->filter('ID', $page->$areaID)->first()) {
                                $area->write();
                            }
                        }
                        $area = $page->ElementalArea();

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
     * @param string $message
     * @param bool $indent
     */
    protected static function write_it($message = '', $indent = true)
    {
        if (Director::is_cli()) {
            if ($indent) {
                echo "\t";
            }
            echo "{$message}\n";
        } else {
            if ($indent) {
                echo "<p style='margin-left: 25px;'>{$message}</p>";
            } else {
                echo "<p>{$message}</p>";
            }
        }
    }
}
