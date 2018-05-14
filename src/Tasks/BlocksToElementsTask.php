<?php

namespace Dynamic\BlockMigration\Tasks;

use Dynamic\BlockMigration\Traits\BlockMigrationConfigurationTrait;
use SheaDawson\Blocks\Model\Block;
use SilverStripe\Control\Director;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\BuildTask;
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
     * @param \SilverStripe\Control\HTTPRequest $request
     */
    public function run($request)
    {
        foreach ($this->getBlockTypes() as $blockType => $configInformation) {
            $this->updateBlocksClassName($configInformation);

            $this->processBlockRecords($configInformation['NewName']::get(), $configInformation['Element'], $configInformation['Relations']);
        }
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
            $element = $record->newClassInstance($elementType);
            $element->write();

            static::write_it("Element of type {$elementType::singleton()->getType()} created with Title: \"{$element->Title}\".", false);

            foreach ($relations as $relationType => $relationConfiguration) {
                foreach ($relationConfiguration as $relation) {
                    switch ($relationType) {
                        case 'ManyMany':
                        case 'HasMany':
                            $this->processManyRelation($element, $record, $relation);
                            break;
                        case 'HasOne':
                            $this->processOneRelation($element, $record, $relation);
                            break;
                    }

                }
            }
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

        $newRelationName = $relation['NewRelationName'];
        $newRelationList = $element->$newRelationName();

        $legacyRelationName = $relation['LegacyRelationName'];
        $legacyRelationList = $legacyRecord->$legacyRelationName();

        foreach ($legacyRelationList as $legacyObject) {
            if (!$object = $relation['NewObject']::get()->filter('ID', $legacyObject->ID)->first()) {
                $object = $legacyObject->newClassInstance($relation['NewObject']);
                $object->write();
            }
            $newRelationList->add($object);
            static::write_it("New {$relation['NewObject']::singleton()->singular_name()} created with the Title: \"{$object->Title}\" and linked to Element with the Title: \"{$element->Title}\".");
        }

    }

    /**
     * @param $element
     * @param $legacyRecord
     * @param array $relation
     */
    protected function processOneRelation($element, $legacyRecord, $relation = [])
    {
        if(isset($relation['LegacyObsolete'])){
            $this->migrateObsoleteData($relation);
        }
    }

    /**
     * @param $relation
     */
    protected function migrateObsoleteData($relation)
    {
        $table = "_obsolete_{$relation['LegacyObjectClassName']}";

        $query = new SQLSelect();
        $query->setFrom("\"{$table}\"");
        $results = $query->execute();

        foreach ($results as $result) {
            $newObject = Injector::inst()->create($relation['LegacyObject']);
            foreach ($result as $key => $val) {
                $newObject->$key = $val;
            }
            $newObject->ClassName = $relation['LegacyObject'];
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
