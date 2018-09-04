# SilverStripe Blocks to Elemental Migrator

### Summary
SilverStripe 3 saw the creation of new way to manage content. One of these ways was the Blocks module. With the release of SilverStripe 4, Elemental is now the preferred "Block" type module for managing sets of flexible content. This module aims to make migrating from the Blocks module to Elemental a little easier.

This module provides a base task that is customisable to allow for additional blocks you may have created to be migrated to existing elements, or new elements you have created.

## Requirements

* SilverStripe ^4.0
* SilverStripe Elemental ^2.0
* SilverStripe Blocks ^2.0

## Installation

`composer require dynamic/silverstripe-blocks-to-elemental-migrator`

## Usage

### Configuration
Configuration supports mapping Blocks and their relations to DataObjects to Elements and their relations to DataObjects. Below is a sample configuration migrating `AccordionBlock `, `ImageBlock ` and `RecentBlogPostsBlock ` to `ElementAccordion `, `ElementImage ` and `ElementBlogPosts ` respectively.


**blockmigration.yml**

```yml
Dynamic\BlockMigration\Tasks\BlocksToElementsTask:
  mappings:
    AccordionBlock: Dynamic\DynamicBlocks\Block\AccordionBlock
    AccordionPanel: Dynamic\DynamicBlocks\Model\AccordionPanel
    ImageBlock: Dynamic\DynamicBlocks\Block\ImageBlock
    RecentBlogPostsBlock: Dynamic\DynamicBlocks\Block\RecentBlogPostsBlock

  migration_mapping:
    ##Accordion
    Dynamic\DynamicBlocks\Block\AccordionBlock:
      Element: Dynamic\Elements\Accordion\Elements\ElementAccordion
      Relations:
        Panels: 'Panels'
      MigrateOptionFromTable:
        Panels:
          AccordionPanel: Dynamic\DynamicBlocks\Model\AccordionPanel
    ##Image
    Dynamic\DynamicBlocks\Block\ImageBlock:
      Element: Dynamic\Elements\Image\Elements\ElementImage
      Relations:
        Image: 'Image'
    ##Recent Blog Posts
    Dynamic\DynamicBlocks\Block\RecentBlogPostsBlock:
      Element: Dynamic\Elements\Blog\Elements\ElementBlogPosts
      Relations:
        Blog: 'Blog'
```

You may run into some snags depending on your project. Check out the [Advanced Configuration](docs/en/advanced/AdvancedUsage.md) for additional options and suggestions.