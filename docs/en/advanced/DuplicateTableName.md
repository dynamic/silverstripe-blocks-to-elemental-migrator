# Duplicate `$table_name`

### Summary
There are some caveots that can pop up while doing a migration. One of which that we have seen is duplicate table names when upgrading from SilverStripe 3 to SilverStripe 4. This particular issue was two fold, we first need to resolve the table names, then we likely need to move data from the legacy table to the new table. This will be illustrated below.

### Duplicate `$table_name` Example

In this example we'll be looking at Dynamic's Accordion Block and Accordion Element. This particular instance has a table name collision in the SS4 versions of each module (the static `$table_name` config has the same value for both objects).

We will look at how we configure the migration tool to handle the data:

#### Current Configuration

__*AccordionPanel.php (Blocks Module Implementation)*__

```php
<?php

namespace Dynamic\DynamicBlocks\Model;

/* === use statements === */

class AccordionPanel extends DataObject
{
    /**
     * @var string
     */
    private static $singular_name = 'Accordion Panel';

    /**
     * @var string
     */
    private static $plural_name = 'Accordion Panels';

    /**
     * @var string
     */
    private static $description = 'A panel for a Accordion widget';

    /* == various relation and db statics == */

    /**
     * @var string
     */
    private static $table_name = 'AccordionPanel';

    /* == remaining code == */
}
```

__*AccordionPanel.php (Element Module Implementation)*__

```php
<?php

namespace Dynamic\Elements\Accordion\Model;

/* === use statements === */

/**
 * Class AccordionPanel
 * @package Dynamic\Elements\Accordion\Model
 *
 * @property int $Sort
 *
 * @property int AccordionID
 * @method ElementAccordion Accordion()
 */
class AccordionPanel extends BaseElementObject
{
    /**
     * @var string
     */
    private static $singular_name = 'Accordion Panel';

    /**
     * @var string
     */
    private static $plural_name = 'Accordion Panels';

    /**
     * @var string
     */
    private static $description = 'A panel for a Accordion widget';

    /* == various relation and db statics == */

    /**
     * @var string Database table name, default's to the fully qualified name
     */
    private static $table_name = 'AccordionPanel';

    /* == remaining code == */
}
```

As you can see, both classes use the same value for the `$table_name` private static. Our resolution was renaming the `Dynamic\DynamicBlocks\Model\AccordionPanel` variable to something like `BlockAccordionPanel` as we are ultimately mirgrating the data from the block module to the element module.

This leads to the following structure:

- Class: `Dynamic\DynamicBlocks\Model\AccordionPanel`
  - SS3 Class: `AccordionPanel`
  - SS3 Table: `AccordionPanel`
  - SS4 Table: `BlockAccordionPanel`
- Class: `Dynamic\Elements\Accordion\Model\AccordionPanel`
  - SS3 Class: NA (In this instance, we didn't use the SS3 version of Elemental as blocks was our preferred module)
  - SS3 Table: NA
  - SS4 Table: `AccordionPanel`

The result of this structure means the data from the SS3 site is in the table for the Elemental Accordion Panel, rather than the new table for the Block Accordion Panel. By configuring that information, the table holding the SS3 data and the class the data belongs to, the migrator will query the data and move it to the appropriate table.


### Configuration
In order for the migration tool to be aware that the `AccordionPanel` table holds "Block Data" for SS3, while currently being the table for the new `Dynamic\Elements\Accordion\Model\AccordionPanel` class, we need to add the `MigrateOptionFromTable` configuration to our block migration configuration file. Below is the pattern accepted by the migration tool at this time:

```yml
Dynamic\BlockMigration\Tasks\BlocksToElementsTask:
  migration_mapping:
    Dynamic\DynamicBlocks\Block\AccordionBlock: # The block class we're migrating away from
      Element: Dynamic\Elements\Accordion\Elements\ElementAccordion # The element class we're migrating to
      Relations: # Relation mapping (relation keys only)*
        # BlockRelationName: 'ElementRelationName'
        Panels: 'Panels'
      MigrateOptionFromTable: # These relations need data moved before migrating from a block to an element
        Panels: # Relation Name
          # TableNameWithData: ClassTheDataBelongsTo
          AccordionPanel: Dynamic\DynamicBlocks\Model\AccordionPanel
```

_* Relation keys would include anything on the left side of a `$has_one`, or `$has_many`, `$many_many` relation_

