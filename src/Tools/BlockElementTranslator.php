<?php

namespace Dynamic\BlockMigration\Tools;

use Dynamic\Elements\Features\Elements\ElementFeatures;
use Dynamic\Elements\Features\Model\FeatureObject;
use Dynamic\Elements\Promos\Elements\ElementPromos;
use Dynamic\Elements\Promos\Model\PromoObject;
use InvalidArgumentException;
use Dynamic\BlockMigration\Traits\Translator;
use SheaDawson\Blocks\Model\Block;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Core\Config\Configurable;
use SilverStripe\Core\Extensible;
use SilverStripe\Core\Injector\Injectable;
use SilverStripe\Core\Injector\Injector;
use SilverStripe\Dev\Debug;
use SilverStripe\Versioned\Versioned;

/**
 * Class BlockElementTranslator
 * @package Dynamic\BlockMigration\Tools
 */
class BlockElementTranslator
{
    use Extensible {
        defineMethods as extensibleDefineMethods;
    }
    use Injectable;
    use Configurable;

    /**
     * Explicitly traverse $db fields and migrate field to field.
     *
     * @var bool
     */
    private static $explicit_data_transfer = false;

    /**
     * @param $block
     * @param $elementType
     * @param $areaID
     * @param $relations
     * @return mixed
     */
    public static function translate_block($block, $elementType, $areaID, $relations)
    {
        if ($block->exists()) {
            $element = Injector::inst()->create($elementType, $block->toMap(), false);

            $element->setClassName($elementType);
            $element->populateDefaults();
            $element->ID = null;
            $element->forceChange();

            self::singleton()->extend('updateNewElementInstance', $element);

            $element->ParentID = $areaID;
            $element->write();

            if (!empty($relations)) {
                static::duplicateRelations($block, $element, $relations);
            }

            if ($block->hasMethod('isPublished')) {
                $element->writeToStage(Versioned::DRAFT);

                if ($block->isPublished()) {
                    $element->publishRecursive();
                }
            }

            return $element;
        }
    }

    /**
     * Copies the given relations from this object to the destination.
     * This method was adopted from DataObject to support cross-object relation migrations.
     *
     * @param DataObject $sourceObject the source object to duplicate from
     * @param DataObject $destinationObject the destination object to populate with the duplicated relations
     * @param array $relations List of relations
     */
    protected static function duplicateRelations($sourceObject, $destinationObject, $relations)
    {
        // Get list of duplicable relation types
        $manyMany = $sourceObject->manyMany();
        $hasMany = $sourceObject->hasMany();
        $hasOne = $sourceObject->hasOne();
        $belongsTo = $sourceObject->belongsTo();

        // Duplicate each relation based on type
        foreach ($relations as $blockRelation => $elementRelation) {
            switch (true) {
                case array_key_exists($blockRelation, $manyMany):
                    {
                        static::duplicateManyManyRelation(
                            $sourceObject,
                            $destinationObject,
                            $blockRelation,
                            $elementRelation
                        );
                        break;
                    }
                case array_key_exists($blockRelation, $hasMany):
                    {
                        static::duplicateHasManyRelation(
                            $sourceObject,
                            $destinationObject,
                            $blockRelation,
                            $elementRelation
                        );
                        break;
                    }
                case array_key_exists($blockRelation, $hasOne):
                    {
                        static::duplicateHasOneRelation(
                            $sourceObject,
                            $destinationObject,
                            $blockRelation,
                            $elementRelation
                        );
                        break;
                    }
                case array_key_exists($blockRelation, $belongsTo):
                    {
                        static::duplicateBelongsToRelation(
                            $sourceObject,
                            $destinationObject,
                            $blockRelation,
                            $elementRelation
                        );
                        break;
                    }//*/
                default:
                    {
                        $sourceType = get_class($sourceObject);
                        throw new InvalidArgumentException(
                            "Cannot duplicate unknown relation {$relation} on parent type {$sourceType}"
                        );
                    }
            }
        }
    }

    /**
     * Copies the many_many and belongs_many_many relations from one object to another instance of the name of object.
     *
     * @deprecated 4.1...5.0 Use duplicateRelations() instead
     * @param DataObject $sourceObject the source object to duplicate from
     * @param DataObject $destinationObject the destination object to populate with the duplicated relations
     * @param bool|string $filter
     */
    protected static function duplicateManyManyRelations($sourceObject, $destinationObject, $filter)
    {
        Deprecation::notice('5.0', 'Use duplicateRelations() instead');

        // Get list of relations to duplicate
        if ($filter === 'many_many' || $filter === 'belongs_many_many') {
            $relations = $sourceObject->config()->get($filter);
        } elseif ($filter === true) {
            $relations = $sourceObject->manyMany();
        } else {
            throw new InvalidArgumentException("Invalid many_many duplication filter");
        }
        foreach ($relations as $manyManyName => $type) {
            static::duplicateManyManyRelation($sourceObject, $destinationObject, $manyManyName);
        }
    }

    /**
     * Duplicates a single many_many relation from one object to another.
     *
     * @param DataObject $sourceObject
     * @param DataObject $destinationObject
     * @param string $relation
     */
    protected static function duplicateManyManyRelation(
        $sourceObject,
        $destinationObject,
        $blockRelation,
        $elementRelation
    ) {
        // Copy all components from source to destination
        $source = $sourceObject->getManyManyComponents($blockRelation);
        $dest = $destinationObject->getManyManyComponents($elementRelation);

        $destClass = $dest->dataClass();

        if ($source instanceof ManyManyList) {
            $extraFieldNames = $source->getExtraFields();
        } else {
            $extraFieldNames = [];
        }

        Message::terminal("attempting to migrate {$source->count()} records of type {$source->dataClass()} to {$destClass}");

        foreach ($source as $item) {
            // Merge extra fields
            $extraFields = [];
            foreach ($extraFieldNames as $fieldName => $fieldType) {
                $extraFields[$fieldName] = $item->getField($fieldName);
            }

            if ($item->ClassName != $destClass) {
                $relatedObject = static::find_or_make_object($destClass, $item);

                if ($relatedObject->hasExtension(Versioned::class)) {
                    $relatedObject->writeToStage(Versioned::DRAFT);
                    $relatedObject->publishRecursive();
                } else {
                    $relatedObject->write();
                }

                Message::terminal("new {$destClass} created from {$item->ClassName}:{$item->ID}");

                $dest->add($relatedObject, $extraFields);
            } else {
                $dest->add($item, $extraFields);
            }
        }
    }

    /**
     * Duplicates a single many_many relation from one object to another.
     *
     * @param DataObject $sourceObject
     * @param DataObject $destinationObject
     * @param string $relation
     */
    protected static function duplicateHasManyRelation(
        $sourceObject,
        $destinationObject,
        $blockRelation,
        $elementRelation
    ) {
        // Copy all components from source to destination
        $source = $sourceObject->getComponents($blockRelation);
        $dest = $destinationObject->getComponents($elementRelation);

        $newInstance = static::get_require_new_instance(
            $sourceObject,
            $destinationObject,
            $blockRelation,
            $elementRelation
        );

        Message::terminal("attempting to migrate {$source->count()} records of type {$source->dataClass()} to {$newInstance}");

        /** @var DataObject $item */
        foreach ($source as $item) {
            // Don't write on duplicate; Wait until ParentID is available later.
            // writeRelations() will eventually write these records when converting
            // from UnsavedRelationList
            $clonedItem = ($newInstance !== false) ? static::new_object($newInstance, $item) : $item;

            if ($clonedItem !== false) {
                if ($clonedItem->hasExtension(Versioned::class)) {
                    $clonedItem->writeToStage(Versioned::DRAFT);
                    $clonedItem->publishRecursive();
                } else {
                    $clonedItem->write();
                }

                Message::terminal("new {$newInstance} created from {$clonedItem->ClassName}:{$clonedItem->ID}");

                $dest->add($clonedItem);
            } else {
                Message::terminal("Error attempting to create item for {$sourceObject->singular_name()}'s {$blockRelation} relation'");
            }
        }
    }

    /**
     * Duplicates a single has_one relation from one object to another.
     * Note: Child object will be force written.
     *
     * @param DataObject $sourceObject
     * @param DataObject $destinationObject
     * @param string $relation
     */
    protected static function duplicateHasOneRelation(
        $sourceObject,
        $destinationObject,
        $blockRelation,
        $elementRelation
    ) {
        // Check if original object exists
        $item = $sourceObject->getComponent($blockRelation);
        if (!$item->isInDB()) {
            return;
        }

        $newInstance = static::get_require_new_instance(
            $sourceObject,
            $destinationObject,
            $blockRelation,
            $elementRelation
        );
        $clonedItem = (!$newInstance) ? $item : $item->newClassInstance($elementRelation);

        $destinationObject->setComponent($elementRelation, $clonedItem);
    }

    /**
     * Duplicates a single belongs_to relation from one object to another.
     * Note: This will force a write on both parent / child objects.
     *
     * @param DataObject $sourceObject
     * @param DataObject $destinationObject
     * @param string $relation
     */
    protected static function duplicateBelongsToRelation(
        $sourceObject,
        $destinationObject,
        $blockRelation,
        $elementRelation
    ) {
        // Check if original object exists
        $item = $sourceObject->getComponent($blockRelation);
        if (!$item->isInDB()) {
            return;
        }

        $newInstance = static::get_require_new_instance(
            $sourceObject,
            $destinationObject,
            $blockRelation,
            $elementRelation
        );

        if (!$newInstance) {
            $clonedItem = $item->duplicate(false);
        } else {
            $clonedItem = $item->newClassInstance($elementRelation);
        }

        $destinationObject->setComponent($elementRelation, $clonedItem);
        // After $clonedItem is assigned the appropriate FieldID / FieldClass, force write
        // @todo Write this component in onAfterWrite instead, assigning the FieldID then
        // https://github.com/silverstripe/silverstripe-framework/issues/7818
        $clonedItem->write();
    }

    /**
     * @param $sourceObject
     * @param $destinationObject
     * @param $blockRelation
     * @param $elementRelation
     * @return bool|string
     */
    protected static function get_require_new_instance(
        &$sourceObject,
        &$destinationObject,
        &$blockRelation,
        &$elementRelation
    ) {
        return ($sourceObject->getRelationClass($blockRelation) == $destinationObject->getRelationClass($elementRelation))
            ? false
            : $destinationObject->getRelationClass($elementRelation);
    }

    /**
     * @param $newClass
     * @param $legacyObject
     * @return mixed
     */
    protected static function find_or_make_object($newClass, $legacyObject)
    {
        if ($newClass === false) {
            return $legacyObject;
        }

        if (!class_exists($newClass)) {
            return false;
        }

        if (!$newClass::singleton()->hasField('LegacyID')) {
            $newObject = static::new_object($newClass, $legacyObject);
        } elseif (!$newObject = $newClass::get()->filter('LegacyID', $legacyObject->ID)->first()) {
            $newObject = static::new_object($newClass, $legacyObject);
        }

        return $newObject;
    }

    /**
     * @param $newClass
     * @param $legacyObject
     * @return mixed
     */
    protected static function new_object($newClass, $legacyObject)
    {
        /** @var DataObject $newInstance */
        $newObject = Injector::inst()->create($newClass, $legacyObject->toMap(), false);
        if ($newObject->hasField('LegacyID')) {
            $newObject->LegacyID = $newObject->ID;
        }

        $newObject->ID = null;

        $newObject->setClassName($newClass);
        $newObject->populateDefaults();
        $newObject->forceChange();

        return $newObject;
    }

    /**
     * @param $item
     * @param $clonedItem
     * @return mixed
     */
    protected static function set_explicit($item, $clonedItem)
    {
        foreach ($item->db() as $field) {
            $clonedItem->$field = $item->$field;
        }

        return $clonedItem;
    }
}
