<?php

namespace Dynamic\BlockMigration\Tasks;

use DNADesign\Elemental\Models\ElementalArea;
use Dynamic\BlockMigration\Tools\BlockElementTranslator;
use Dynamic\BlockMigration\Tools\DataManipulator;
use Dynamic\BlockMigration\Tools\ElementalAreaGenerator;
use Dynamic\BlockMigration\Tools\Message;
use Dynamic\BlockMigration\Traits\BlockMigrationConfigurationTrait;
use Dynamic\Elements\Accordion\Elements\ElementAccordion;
use Dynamic\Jasna\Pages\HomePage;
use SheaDawson\Blocks\BlockManager;
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
use SilverStripe\ORM\ManyManyList;
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

        /**
         * BlockManager used to get legacy block areas of a particular class.
         */
        $manager = BlockManager::singleton();

        /**
         * array used to track what we know about classes and their areas.
         */
        $mappedAreas = [];

        /**
         * @param $page a page to process related blocks to elements
         */
        $processPage = function ($page) use (&$manager, &$mappedAreas, &$migrationMapping) {
            //if (!class_exists($page->ClassName)) continue;
            $class = $page->ClassName;

            if ($page->getObsoleteClassName()) $page->ClassName = \Page::class;

            if ($class != 'GoogleSiteSearchPage') {
                $properPage = $class::get()->byID($page->ID);

                if (!isset($mappedAreas[$properPage->ClassName])) {
                    $mappedAreas[$properPage->ClassName] = $manager->getAreasForPageType($properPage->ClassName);
                }

                foreach ($mappedAreas[$properPage->ClassName] as $area => $title) {
                    $this->processBlockRecords($properPage, $area, $page->getBlockList($area), $migrationMapping);
                }//*/
            }
        };

        foreach ($this->yieldPages() as $page) {
            $processPage($page);
        }

        Message::terminal("Fin.");
    }

    protected function yieldPages()
    {
        foreach (SiteTree::get()->sort('ID') as $page) {
            yield $page;
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
     * @param $page The page owning the existing blocks and the new elements
     * @param $area The name of the ElementalArea relation (i.e. ElementalArea, Sidebar)
     * @param $records The legacy block records to migrate to elements
     * @param $mapping The block to element mapping array
     * @throws \SilverStripe\ORM\ValidationException
     */
    protected function processBlockRecords($page, $area, $records, $mapping)
    {
        $area = ElementalAreaGenerator::find_or_make_elemental_area($page, $area);

        foreach ($records as $record) {
            Message::terminal("Migrating {$record->ClassName} - {$record->ID} for page {$page->ClassName} - {$page->ID}.");

            if (isset($mapping[$record->ClassName])) {
                if (!isset($mapping[$record->ClassName]) || !isset($mapping[$record->ClassName]['NewObject'])) {
                    Message::terminal('dang');
                    return;
                }

                if ($page->hasMethod('getElementalRelations')) {
                    $relations = (isset($mapping[$record->ClassName]['Relations'])) ? $mapping[$record->ClassName]['Relations'] : false;
                    $element = BlockElementTranslator::translate_block($record, $mapping[$record->ClassName]['NewObject'], $relations);

                    $element->ParentID = $area->ID;
                    $element->LegacyID = $record->ID;
                    $element->write();//*/
                } else {
                    Message::terminal('dang 2');
                    return;
                }
            }

            Message::terminal("End migrating {$record->ClassName}.\n\n");
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
