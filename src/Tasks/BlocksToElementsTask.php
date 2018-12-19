<?php

namespace Dynamic\BlockMigration\Tasks;

use Dynamic\BlockMigration\Tools\PageProcessor;
use SilverStripe\CMS\Model\SiteTree;
use SilverStripe\Dev\BuildTask;

/**
 * Class BlocksToElementsTask
 * @package Dynamic\BlockMigration\Tasks
 */
class BlocksToElementsTask extends BuildTask
{
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
     * @var string /dev/tasks/migration-block-task
     */
    private static $segment = 'migration-block-task';

    /**
     * @param \SilverStripe\Control\HTTPRequest $request
     */
    public function run($request)
    {
        $pageProcessor = PageProcessor::create();

        foreach ($this->yieldPages() as $page) {
            $pageProcessor->processPage($page);
        }
    }

    /**
     * @return \Generator
     */
    protected function yieldPages()
    {
        foreach (SiteTree::get()->sort('ID') as $page) {
            yield $page;
        }
    }
}
