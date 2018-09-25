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
            case 'AfterContent':
            default:
                $areaName = 'ElementalArea';
                break;
            case 'Sidebar':
                $areaName = 'Sidebar';
                break;
        }

        $areaID = $areaName . 'ID';
        if (!$page->$areaID) {
            $area = ElementalArea::create();
            $area->OwnerClassName = $page->ClassName;
            $area->write();
            $page->$areaID = $area->ID;
            if (!class_exists($page->ClassName)) {
                $page->ClassName = \Page::class;
            }
            $page->write();
        }

        return ElementalArea::get()->filter('ID', $page->$areaID)->first();
    }
}