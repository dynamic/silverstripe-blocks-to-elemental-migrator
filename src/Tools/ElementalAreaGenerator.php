<?php

namespace Dynamic\BlockMigration\Tools;

use DNADesign\Elemental\Models\ElementalArea;
use Dynamic\BlockMigration\Tasks\BlocksToElementsTask;
use Dynamic\Jasna\Pages\HomePage;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\Debug;
use SilverStripe\ORM\DB;
use SilverStripe\Versioned\Versioned;

/**
 * Class ElementalAreaGenerator
 * @package Dynamic\BlockMigration\Tools
 */
class ElementalAreaGenerator
{
    /**
     * @param $object Page or DataObject with an Elemental Area (previously Block Area)
     * @param $area The Block Area name
     * @return \SilverStripe\ORM\DataObject
     * @throws \SilverStripe\ORM\ValidationException
     */
    public static function find_or_make_elemental_area($object, $area)
    {
        $areaMappging = BlocksToElementsTask::singleton()->config()->get('area_assignments');

        if (isset($areaMappging[$area])) {
            $areaName = $areaMappging[$area];
        } else {
            $areaName = static::get_default_area_by_page($object);
        }

        Message::terminal("Attempting to resolve {$area} area for {$object->singular_name()} with {$areaName}");

        $areaID = $areaName . 'ID';
        
        if ($object->$areaID == 0) {
            Message::terminal("No area currently set, attempting to create");
            $elementalArea = ElementalArea::create();
            $elementalArea->OwnerClassName = $object->ClassName;
            $elementalArea->write();
            $elementalArea->exists() ? Message::terminal("{$elementalArea->ClassName} created for {$object->ClassName} - {$object->ID}") : Message::terminal("Area could not be created.");

            // To preserve draft state for current pages that have a live version, we should set the has_one relation via SQL to prevent data disruption
            static::set_relations($object, $areaID, $elementalArea);

            $class = $object->ClassName;

            $object = $class::get()->byID($object->ID);

            $object->$areaID > 0 ? Message::terminal("Area successfully related to page.") : Message::terminal("Area unsuccessfully related to page.");
        } else {
            Message::terminal("An area already exists for that page.");
        }

        $resultingArea = ElementalArea::get()->filter('ID', $object->$areaID)->first();

        Message::terminal("Resolved with an ElementalArea of ID {$resultingArea->ID}.\n\n");

        return $resultingArea;
    }

    /**
     * @param $object
     * @param $relationColumn
     * @param $elementalArea
     */
    protected static function set_relations($object, $relationColumn, $elementalArea)
    {
        if ($object instanceof SiteTree) {
            $baseTable = $object->getSchema()->tableForField($object->ClassName, $relationColumn);

            if ($baseTable && $baseTable != '') {
                DB::prepared_query("UPDATE `{$baseTable}` SET `{$relationColumn}` = ? WHERE ID = ?",
                    [$elementalArea->ID, $object->ID]);
                DB::prepared_query("UPDATE `{$baseTable}_Live` SET `{$relationColumn}` = ? WHERE ID = ?",
                    [$elementalArea->ID, $object->ID]);
                DB::prepared_query("UPDATE `{$baseTable}_Versions` SET `{$relationColumn}` = ? WHERE RecordID = ?",
                    [$elementalArea->ID, $object->ID]);
                
                return $object::get()->byID($object->ID);
            } else {
                Message::terminal("Couldn't update relation for {$object->ClassName} - {$object->ID}, Area {$relationColumn} - {$elementalArea->ID}");
            }
        } else {
            Message::terminal("{$object->ClassName} is not a decendant of SiteTree");
            Debug::show($object);
            die;
        }
    }

    /**
     * @param $page
     * @return mixed
     */
    protected static function get_default_area_by_page($page)
    {
        $config = BlocksToElementsTask::config();
        $defaults = $config->get('default_areas');

        if (isset($defaults[$page->ClassName])) {
            return $defaults[$page->ClassName];
        }

        if ($config->get('default_block_area')) {
            return $config->get('default_block_area');
        }

        return $config->get('default_area');
    }
}
