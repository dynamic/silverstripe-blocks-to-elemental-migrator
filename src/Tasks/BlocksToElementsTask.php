<?php

namespace Dynamic\BlockMigration\Tasks;

use DNADesign\Elemental\Models\ElementalArea;
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
        $mappings = $this->getBlockClassMapping();
        DatabaseClassNameUpdateTask::singleton()
            ->run(Controller::curr()->getRequest(), $mappings);

        $migrationMapping = $this->config()->get('migration_mapping');

        foreach ($migrationMapping as $block => $mapping) {
            $this->processBlockRecords($block::get(), $mapping['Element'], $mapping['Relations']);
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
            foreach ($this->yieldBlockPages($record) as $page) {
                $this->generateBlockElement($page, $record, $elementType, $relations);
            }
        }
    }

    /**
     * @param SiteTree $page
     * @param Block $record
     * @param $elementType
     * @param $relations
     * @throws \SilverStripe\ORM\ValidationException
     */
    protected function generateBlockElement(SiteTree $page, Block $record, $elementType, $relations)
    {
        if ($record) {
            if (array_search(DataObject::class, ClassInfo::ancestry($elementType))) {
                $element = $record->newClassInstance($elementType);
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
                $element->ParentID = $area->ID;
                $element->write();

                static::write_it("Element of type {$elementType::singleton()->getType()} created with Title: \"{$element->Title}\".", false);

                foreach ($relations as $relationType => $relationConfiguration) {
                    foreach ($relationConfiguration as $relation) {
                        switch ($relationType) {
                            case 'ManyMany':
                            case 'HasMany':
                                $this->processManyRelation($element, $record, $relations['HasMany']);
                                break;
                            case 'HasOne':
                                $this->processOneRelation($element, $record, $relations['HasOne']);
                                break;
                        }

                    }
                }
            } else {
                static::write_it("Couldn't migrate {$record->Title} to an Element of type {$elementType}. Please make sure you have the proper mapping and module installed to support that element type", false);
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
     * @param $element
     * @param $legacyRecord
     * @param array $relation
     */
    protected function processManyRelation($element, $legacyRecord, $relation = [])
    {
        if (isset($relation['LegacyObsolete'])) {
            $this->migrateObsoleteData($relation);
        }

        $newRelationName = $relation['ElementRelationName'];
        $newRelationList = $element->$newRelationName();

        $legacyRelationName = $relation['BlockRelationName'];
        $legacyRelationList = $legacyRecord->$legacyRelationName();

        foreach ($legacyRelationList as $legacyObject) {
            if (!$object = $relation['ElementRelationObject']::get()->filter('ID', $legacyObject->ID)->first()) {
                $object = $legacyObject->newClassInstance($relation['ElementRelationObject']);
                $object->write();
            }
            $newRelationList->add($object);
            static::write_it("New {$relation['ElementRelationObject']::singleton()->singular_name()} created with the Title: \"{$object->Title}\" and linked to Element with the Title: \"{$element->Title}\".");
        }

    }

    /**
     * @param $element
     * @param $legacyRecord
     * @param array $relation
     */
    protected function processOneRelation($element, $legacyRecord, $relation = [])
    {
        if (isset($relation['LegacyObsolete'])) {
            $this->migrateObsoleteData($relation);
        }
    }

    /**
     * @param $relation
     */
    protected function migrateObsoleteData($relation)
    {
        $table = "_obsolete_{$relation['BlockLegacyRelationObjectClassName']}";

        $query = new SQLSelect();
        $query->setFrom("\"{$table}\"");
        $results = $query->execute();

        foreach ($results as $result) {
            $newObject = Injector::inst()->create($relation['BlockRelatedObject']);
            foreach ($result as $key => $val) {
                $newObject->$key = $val;
            }
            $newObject->ClassName = $relation['BlockRelatedObject'];
            $newObject->write();
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
