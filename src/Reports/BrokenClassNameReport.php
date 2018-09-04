<?php

namespace Dynamic\Jasna\Reports;

use Dynamic\BlockMigration\Tasks\BlocksToElementsTask;
use phpDocumentor\Reflection\DocBlock\Tags\Var_;
use SilverStripe\Core\ClassInfo;
use SilverStripe\Dev\Debug;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldButtonRow;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldExportButton;
use SilverStripe\Forms\GridField\GridFieldPaginator;
use SilverStripe\Forms\GridField\GridFieldPrintButton;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\ORM\ArrayList;
use SilverStripe\ORM\DataObject;
use SilverStripe\Reports\Report;
use SilverStripe\View\ArrayData;

/**
 * Class BrokenClassNameReport
 * @package Dynamic\Jasna\Reports
 */
class BrokenClassNameReport extends Report
{
    /**
     * @var string
     */
    protected $title = 'Broken ClassName Report';

    /**
     * @var string
     */
    protected $description = 'A report of tables containing broken ClassNames.';

    /**
     * @var string
     */
    protected $dataClass = DataObject::class;

    /**
     * @param array $params
     * @param null $sort
     * @param null $limit
     * @return \SilverStripe\ORM\DataList|\SilverStripe\ORM\SS_List|void
     */
    public function sourceRecords($params = [], $sort = null, $limit = null)
    {
        $results = ArrayList::create();
        if ($mapping = BlocksToElementsTask::singleton()->config()->get('mappings')) {
            foreach ($mapping as $old => $new) {
                $count = 0;

                $subclasses = ClassInfo::getValidSubClasses($new);

                foreach ($this->yieldRecords($new::get()->exclude('ClassName', $new)) as $record) {
                    if (($record->ClassName != $new || !class_exists($record->ClassName)) && !in_array($record->ClassName, $subclasses)) {
                        $count++;
                    }
                }

                if ($count) {
                    $results->push(ArrayData::create([
                        'Title' => $new::singleton()->singular_name(),
                        'LegacyClassName' => $old,
                        'FQN' => $new,
                        'RecordsToUpdate' => $count,
                    ]));
                }
            }
        }
        return $results;
    }

    /**
     * @param $records
     * @return \Generator
     */
    protected function yieldRecords($records)
    {
        foreach ($records as $record) {
            yield $record;
        }
    }

    /**
     * Return a field, such as a {@link GridField} that is
     * used to show and manipulate data relating to this report.
     *
     * Generally, you should override {@link columns()} and {@link records()} to make your report,
     * but if they aren't sufficiently flexible, then you can override this method.
     *
     * @return \SilverStripe\Forms\FormField subclass
     */
    public function getReportField()
    {
        $items = $this->sourceRecords();

        $gridFieldConfig = GridFieldConfig::create()->addComponents(
            new GridFieldButtonRow('before'),
            new GridFieldPrintButton('buttons-before-left'),
            new GridFieldExportButton('buttons-before-left'),
            new GridFieldSortableHeader(),
            new GridFieldDataColumns(),
            new GridFieldPaginator()
        );
        $gridField = new GridField('Report', null, $items, $gridFieldConfig);
        $columns = $gridField->getConfig()->getComponentByType(GridFieldDataColumns::class);

        $displayFields['Title'] = 'Object Name';
        $displayFields['LegacyClassName'] = 'Legacy ClassName';
        $displayFields['FQN'] = 'New ClassName (FQN)';
        $displayFields['RecordsToUpdate'] = 'Records To Update';

        $columns->setDisplayFields($displayFields);

        return $gridField;
    }
}