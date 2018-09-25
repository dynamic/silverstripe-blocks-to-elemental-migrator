<?php

namespace Dynamic\BlockMigration\Tools;

use DNADesign\Elemental\Models\ElementalArea;
use Dynamic\BlockMigration\Tasks\BlocksToElementsTask;
use SilverStripe\Versioned\Versioned;

/**
 * Class ElementalAreaGenerator
 * @package Dynamic\BlockMigration\Tools
 */
class ElementalAreaGenerator
{
    /**
     * @param $page
     * @return mixed
     */
    public static function find_or_make_elemental_area($page, $area)
    {
        $areaMappging = BlocksToElementsTask::singleton()->config()->get('area_assignments');

        if (isset($areaMappging[$area])) {
            $areaName = $areaMappging[$area];
        } else {
            $areaName = static::get_default_area_by_page($page);
        }

        Message::terminal("Attempting to resolve {$area} area for {$page->ClassName} - {$page->ID} with {$areaName}");

        $areaID = $areaName . 'ID';
        if (!$page->$areaID) {
            Message::terminal("No area currently set, attempting to create");
            $elementalArea = ElementalArea::create();
            $elementalArea->OwnerClassName = $page->ClassName;
            $elementalArea->write();
            $elementalArea->exists() ? Message::terminal("{$elementalArea->ClassName} created for {$page->ClassName} - {$page->ID}") : Message::terminal("Area could not be created.");
            $page->$areaID = $elementalArea->ID;
            if (!class_exists($page->ClassName)) {
                $page->ClassName = \Page::class;
            }
            $isPublished = $page->isPublished();

            $page->write();
            $page->writeToStage(Versioned::DRAFT);

            if ($isPublished) {
                $page->publishRecursive();
            }

            $page->$areaID > 0 ? Message::terminal("Area successfully related to page.") : Message::terminal("Area unsuccessfully related to page.");
        } else {
            Message::terminal("An area already exists for that page.");
        }

        $resultingArea = ElementalArea::get()->filter('ID', $page->$areaID)->first();

        Message::terminal("Resolved with area {$resultingArea->ClassName} - {$resultingArea->ID}.\n\n");

        return $resultingArea;
    }

    /**
     * @param $page
     * @return mixed
     */
    protected static function get_default_area_by_page($page)
    {
        $config = BlocksToElementsTask::singleton()->config();
        $defaults = $config->get('default_areas');

        if (isset($defaults[$page->ClassName])) {
            return $defaults[$page->ClassName];
        }

        return $config->get('default_area');
    }
}