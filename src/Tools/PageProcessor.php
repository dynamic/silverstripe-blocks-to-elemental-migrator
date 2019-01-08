<?php

namespace Dynamic\BlockMigration\Tools;

use DNADesign\Elemental\Models\BaseElement;
use DNADesign\Elemental\Models\ElementalArea;
use Dynamic\BlockMigration\Tasks\BlocksToElementsTask;
use Dynamic\DynamicBlocks\Block\PageSectionBlock;
use SheaDawson\Blocks\BlockManager;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Config;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Dev\Debug;
use SilverStripe\ORM\DataObject;
use SilverStripe\Versioned\Versioned;

class PageProcessor
{
    use Injectable;
    use Configurable;
    use Extensible;

    /**
     * @var bool
     */
    private static $publish_none_area = true;

    /**
     * @var BlockManager
     */
    private $block_manager;

    /**
     * @var array
     */
    private $mapped_areas = [];

    /**
     * @var
     */
    private $page;

    /**
     * @var array
     */
    private $migration_mapping = [];

    /**
     * PageProcessor constructor.
     */
    public function __construct()
    {
        $this->setBlockManager();
        $this->setMigrationMapping(Config::inst()->get(BlocksToElementsTask::class, 'migration_mapping'));
    }

    /**
     * @return $this
     */
    protected function setBlockManager()
    {
        $this->block_manager = BlockManager::singleton();

        return $this;
    }

    /**
     * @return BlockManager
     */
    protected function getBlockManager()
    {
        return $this->block_manager;
    }

    /**
     * @param $class
     * @return $this
     */
    protected function setMappedArea($class)
    {
        if (!isset($this->mapped_areas[$class])) {
            $this->mapped_areas[$class] = $this->getBlockManager()->getAreasForPageType($class);
        }

        return $this;
    }

    /**
     * @return array
     */
    protected function getMappedAreas()
    {
        return $this->mapped_areas;
    }

    /**
     * @param $page
     * @return $this
     */
    protected function setPage($page)
    {
        $this->page = $page;

        return $this;
    }

    /**
     * @return mixed
     */
    protected function getPage()
    {
        return $this->page;
    }

    /**
     * @param $mapping
     * @return $this
     */
    protected function setMigrationMapping($mapping = [])
    {
        $this->migration_mapping = $mapping;

        return $this;
    }

    /**
     * @return array
     */
    protected function getMigrationMapping()
    {
        if (empty($this->migration_mapping)) {
            $this->setMigrationMapping(Config::inst()->get(BlocksToElementsTask::class, 'migration_mapping'));
        }

        return $this->migration_mapping;
    }

    /**
     * @param $class
     * @return mixed
     */
    protected function getMappedAreasByClass($class)
    {
        if (!isset($this->getMappedAreas()[$class])) {
            $this->setMappedArea($class);
        }

        return $this->getMappedAreas()[$class];
    }

    /**
     * @param SiteTree $page
     * @throws \SilverStripe\ORM\ValidationException
     */
    public function processPage(SiteTree $page)
    {
        Message::terminal("Processing page {$page->Title}");

        $original = $updated = BlockManager::singleton()->config()->get('options');
        $updated['use_blocksets'] = false;
        $class = $page->ClassName;

        Config::modify()->set(BlockManager::class, 'options', $updated);

        if ($page = $class::get()->byID($page->ID)) {
            $this->setPage($page);

            foreach ($this->getMappedAreasByClass($page->ClassName) as $area => $title) {
                $this->processBlockRecords($page::get()->byID($page->ID), $area,
                    $this->getPageBlocksByArea($page, $area));
            }

            if (count($this->getPageBlocksByArea($page::get()->byID($page->ID), null))) {
                $default = $this->getDefaultBlockArea($page);

                echo "\n\n\n\n\n\n========== Additional unassigned blocks on page ==========\n\n\n";
                Message::terminal("\$this->processBlockRecords({$page->ID}, {$default}, \$this->getPageBlocksByArea({$page->ID}, null))");

                $this->processBlockRecords($page::get()->byID($page->ID), $default,
                    $this->getPageBlocksByArea($page, null), $this->config()->get('publish_none_area'));

                echo "\n\n\n========== End additional unassigned blocks on page ==========\n\n\n\n\n\n";
            }
        }

        Config::modify()->set(BlockManager::class, 'options', $original);
    }

    /**
     * @param $record
     * @return bool|mixed
     */
    protected function getDefaultBlockArea($record)
    {
        $config = BlocksToElementsTask::config();
        $classDefaults = $config->get('default_block_areas');

        if ($classDefaults && is_array($classDefaults)) {
            if (array_key_exists($record->ClassName, $classDefaults)) {
                return $classDefaults[$record->ClassName];
            }
        }

        if ($config->get('default_block_area')) {
            return $config->get('default_block_area');
        }

        return null;
    }

    /**
     * @param $page
     * @param $area
     * @return mixed
     */
    protected function getPageBlocksByArea($page, $area)
    {
        if ($area !== null) {
            return $page->getBlockList($area);
        }

        return $page->Blocks()->filter('BlockArea', $area);
    }

    /**
     * @param $page The page owning the existing blocks and the new elements
     * @param $areaName The name of the BlockArea the records belong to
     * @param $records The legacy block records to migrate to elements
     * @param bool $publish Publish the new element records if available
     * @throws \SilverStripe\ORM\ValidationException
     */
    protected function processBlockRecords($page, $areaName, $records, $publish = true)
    {
        $area = ElementalAreaGenerator::find_or_make_elemental_area($page, $areaName);
        $mapping = $this->getMigrationMapping();

        /**
         * var $record Block record
         */
        foreach ($this->yieldSingle($records) as $record) {
            Message::terminal("Migrating block type: {$record->ClassName} - {$record->ID}.");

            if ($this->isMapped($record->ClassName) && $this->hasNewObject($record->ClassName)) {
                if ($page->hasMethod('getElementalRelations')) {
                    $relations = $this->hasRelations($record->ClassName)
                        ? $mapping[$record->ClassName]['Relations']
                        : false;

                    $element = BlockElementTranslator::translate_block(
                        $record,
                        $mapping[$record->ClassName]['NewObject'],
                        $area->ID,
                        $relations
                    );

                    if ($element instanceof BaseElement && $area instanceof ElementalArea && $record instanceof DataObject) {
                        $element->ParentID = $area->ID;
                        $element->LegacyID = $record->ID;
                    } else {
                        Message::terminal("Something is a non-object");
                    }

                    if ($record->hasMethod('isPublished')) {
                        $element->writeToStage(Versioned::DRAFT);

                        //don't publish if they were in the "None" block area
                        if ($record->isPublished() && $publish) {
                            $element->publishRecursive();
                        }
                    } else {
                        $element->write();
                    }
                } else {
                    Message::terminal("{$record->ClassName} is not mapped. This class may not exist or needs to be added to the mapping.");
                }
            }

            Message::terminal("End migrating {$record->ClassName}.\n\n");
        }//*/
    }

    /**
     * @param $class
     * @return bool
     */
    private function isMapped($class)
    {
        return isset($this->getMigrationMapping()[$class]);
    }

    /**
     * @param $class
     * @return bool
     */
    private function hasNewObject($class)
    {
        return isset($this->getMigrationMapping()[$class]['NewObject']);
    }

    /**
     * @param $class
     * @return bool
     */
    private function hasRelations($class)
    {
        return isset($this->getMigrationMapping()[$class]['Relations']);
    }

    /**
     * @param $records
     * @return \Generator
     */
    protected function yieldSingle($records)
    {
        foreach ($records as $record) {
            yield $record;
        }
    }

    /**
     * @param $records
     * @return \Generator
     */
    protected function yieldMulti($records)
    {
        foreach ($records as $key => $val) {
            yield $key => $val;
        }
    }
}
