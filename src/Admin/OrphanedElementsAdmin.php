<?php

namespace Dynamic\BlockMigration\Admin;

use DNADesign\Elemental\Models\BaseElement;
use SilverStripe\Admin\ModelAdmin;
use SilverStripe\Dev\Debug;

/**
 * Class OrphanedElementsAdmin
 * @package Dynamic\BlockMigration\Admin
 */
class OrphanedElementsAdmin extends ModelAdmin
{
    /**
     * @var string
     */
    private static $url_segment = 'orphaned-elements-admin';

    /**
     * @var string
     */
    private static $menu_title = 'Orphaned Elements';

    /**
     * @var array
     */
    private static $managed_models = [
        BaseElement::class,
    ];

    /**
     * @return \SilverStripe\ORM\ArrayList|\SilverStripe\ORM\DataList
     */
    public function getList()
    {
        $list = parent::getList();

        $class = $list->dataClass();

        $list2 = $list->exclude('ParentID:GreaterThan', 0);

        return $list2;
    }
}