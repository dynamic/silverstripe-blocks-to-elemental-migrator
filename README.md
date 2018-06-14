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
Configuration supports mapping Blocks and their relations to DataObjects to Elements and their relations to DataObjects. Below is a sample configuration migrating `PageSectionBlock`, `PromoBlock` and `ImageBlock` to `ElementFeatures`, `ElementPromos` and `ElementImage` respectively.


**blockmigration.yml**

```yml
Dynamic\BlockMigration\Tasks\BlocksToElementsTask:
  mappings:
    PageSectionBlock:  Dynamic\DynamicBlocks\Block\PageSectionBlock
    PromoBlock: Dynamic\DynamicBlocks\Block\PromoBlock
    ImageBlock: Dynamic\DynamicBlocks\Block\ImageBlock
    PageSectionObject: Dynamic\DynamicBlocks\Model\PageSectionObject
    PromoObject: Dynamic\DynamicBlocks\Model\PromoObject

  migration_mapping:
    Dynamic\DynamicBlocks\Block\PageSectionBlock:
      Element: Dynamic\Elements\Features\Elements\ElementFeatures
      Relations:
        HasMany:
          BlockRelationName: 'Sections'
          BlockRelationInverseID: 'PageSectionBlockID'
          BlockRelatedObject: Dynamic\DynamicBlocks\Model\PageSectionObject
          BlockLegacyRelationObjectClassName: 'PageSectionObject'
          ElementRelationName: 'Features'
          ElementRelationObject: Dynamic\Elements\Features\Model\FeatureObject
    Dynamic\DynamicBlocks\Block\PromoBlock:
      Element: Dynamic\Elements\Promos\Elements\ElementPromos
      Relations:
        HasMany:
          BlockRelationName: 'Promos'
          BlockRelationInverseID: 'PageSectionBlockID'
          BlockRelatedObject: Dynamic\DynamicBlocks\Model\PromoObject
          BlockLegacyRelationObjectClassName: 'PromoObject'
          ElementRelationName: 'Promos'
          ElementRelationObject: Dynamic\BaseObject\Model\BaseElementObject
          LegacyObsolete: true
    Dynamic\DynamicBlocks\Block\ImageBlock:
      Element: Dynamic\Elements\Features\Elements\ElementImage
      Relations:
        HasOne:
          BlockRelationName: 'Image'
          BlockRelatedObject: SilverStripe\Assets\Image
          ElementRelationName: 'Image'
          ElementRelationObject: SilverStripe\Assets\Image
```

### ToDo

- Version table migration support
