<?php

namespace Dynamic\BlockMigration\Traits;

/**
 * Trait BlockMigrationConfigurationTrait
 * @package Dynamic\BlockMigration\Traits
 */
trait BlockMigrationConfigurationTrait
{
    /**
     * Configuration needs to take into account the legacy block class name. This may not be a FQN.
     *
     * @return array
     */
    protected function getBlockTypes()
    {
        return [];
    }
}
