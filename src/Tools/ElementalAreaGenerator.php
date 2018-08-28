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
    public static function find_or_make_elemental_area($page)
    {
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

        return $area;
    }
}