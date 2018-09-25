<?php

namespace Dynamic\BlockMigration\Tools;

use DNADesign\Elemental\Models\ElementalArea;

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
        switch ($area) {
            case 'HomeContent':
                $areaName = 'HomePageArea';
                break;
            case 'Sidebar':
                $areaName = 'Sidebar';
                break;
            case 'Footer':
                $areaName = 'FooterArea';
                break;
            case 'AfterContent':
            default:
                $areaName = 'ElementalArea';
                break;
        }

        Message::terminal("Attempting to resolve {$area} area for {$page->ClassName} - {$page->ID}");

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
            $page->write();
            $page->$areaID > 0 ? Message::terminal("Area successfully related to page.") : Message::terminal("Area unsuccessfully related to page.");
        } else {
            Message::terminal("An area already exists for that page.");
        }

        $resultingArea = ElementalArea::get()->filter('ID', $page->$areaID)->first();

        Message::terminal("Resolved with area {$resultingArea->ClassName} - {$resultingArea->ID}.\n\n");

        return $resultingArea;
    }
}