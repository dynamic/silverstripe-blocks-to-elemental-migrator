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

### Assumptions
- You have upgraded your site to SilverStripe 4
- You have installed the SilverStripe 4 compatible Blocks module
- You have installed the Elemental module
- you have installed any additional Element modules needed for your project

### Configuration
Configuration supports mapping Blocks and their relations to DataObjects to Elements and their relations to DataObjects. Below is a sample configuration migrating `PageSectionBlock`, `PromoBlock` and `ImageBlock` to `ElementFeatures`, `ElementPromos` and `ElementImage` respectively.

```php

namespace Your\Name\Space;

use Dynamic\BlockMigration\Tasks\BlocksToElementsTask;
use Dynamic\DynamicBlocks\Block\ImageBlock;
use Dynamic\DynamicBlocks\Block\PageSectionBlock;
use Dynamic\DynamicBlocks\Block\PromoBlock;
use Dynamic\DynamicBlocks\Model\PageSectionObject;
use Dynamic\DynamicBlocks\Model\PromoObject as LegacyObject;
use Dynamic\Elements\Features\Elements\ElementFeatures;
use Dynamic\Elements\Features\Model\FeatureObject;
use Dynamic\Elements\Image\Elements\ElementImage;
use Dynamic\Elements\Promos\Elements\ElementPromos;
use Dynamic\Elements\Promos\Model\PromoObject;
use SilverStripe\Assets\Image;

class MyMigrationBuildTask extends BlocksToElementsTask
{
	 /**
     * @return array
     */
    public function getBlockTypes()
    {
        return [
            PageSectionBlock::class => [
                'LegacyName' => 'PageSectionBlock',
                'NewName' => PageSectionBlock::class,
                'Element' => ElementFeatures::class,
                'Relations' => [
                    'HasMany' => [
                        [
                            'LegacyRelationName' => 'Sections',
                            'LegacyInverseID' => 'PageSectionBlockID',
                            'LegacyObject' => PageSectionObject::class,
                            'LegacyObjectClassName' => 'PageSectionObject',
                            'NewRelationName' => 'Features',
                            'NewObject' => FeatureObject::class,
                        ],
                    ],
                ],
            ],
            PromoBlock::class => [
                'LegacyName' => 'PromoBlock',
                'NewName' => PromoBlock::class,
                'Element' => ElementPromos::class,
                'Relations' => [
                    'ManyMany' => [
                        [
                            'LegacyRelationName' => 'Promos',
                            'LegacyObject' => LegacyObject::class,
                            'LegacyObjectClassName' => 'PromoObject',
                            'LegacyObsolete' => true,
                            'NewRelationName' => 'Promos',
                            'NewObject' => PromoObject::class,
                        ],
                    ],
                ],
            ],
            ImageBlock::class => [
                'LegacyName' => 'ImageBlock',
                'NewName' => ImageBlock::class,
                'Element' => ElementImage::class,
                'Relations' => [
                    'HasOne' => [
                        [
                            'LegacyRelationName' => 'Image',
                            'LegacyObject' => Image::class,
                            'LegacyObjectClassName' => 'Image',
                            'NewRelationName' => 'Image',
                            'NewObject' => Image::class,
                        ],
                    ],
                ],
            ],
        ];
    }
}
```

#### Configuration Key

**Block with HasMany to Element with HasMany**

```
PageSectionBlock::class => [ // the SS4 block classname
    'LegacyName' => 'PageSectionBlock', // the SS3 block classname stored in the ClassName column of the database
    'NewName' => PageSectionBlock::class, // the SS4 block classname
    'Element' => ElementFeatures::class, // the SS4 Element classname we're migrating to
    'Relations' => [ // Optional relation mapping
        'HasMany' => [ // Relation type
            [ // relation mapping, allows for multiple relations to be processed
                'LegacyRelationName' => 'Sections',// Relation name in Blocks
                'LegacyInverseID' => 'PageSectionBlockID', // Inverse HasOne relation column (Relation Name with ID, only required for HasMany relation migration)
                'LegacyObject' => PageSectionObject::class, // Related object in Blocks (FQN)
                'LegacyObjectClassName' => 'PageSectionObject', // Related object value in ClassName column
                'NewRelationName' => 'Features', // Element relation name
                'NewObject' => FeatureObject::class, // Element related object classname
            ],
        ],
    ],
]
```

**Block with ManyMany to Element with ManyMany**

```
PromoBlock::class => [
    'LegacyName' => 'PromoBlock',
    'NewName' => PromoBlock::class,
    'Element' => ElementPromos::class,
    'Relations' => [
        'ManyMany' => [
            [
                'LegacyRelationName' => 'Promos',
                'LegacyObject' => LegacyObject::class,
                'LegacyObjectClassName' => 'PromoObject',
                'LegacyObsolete' => true,
                'NewRelationName' => 'Promos',
                'NewObject' => PromoObject::class,
            ],
        ],
    ],
]
```

**Block with HasOne to Element with HasOne**

```
ImageBlock::class => [
    'LegacyName' => 'ImageBlock',
    'NewName' => ImageBlock::class,
    'Element' => ElementImage::class,
    'Relations' => [
        'HasOne' => [
            [
                'LegacyRelationName' => 'Image',
                'LegacyObject' => Image::class,
                'LegacyObjectClassName' => 'Image',
                'NewRelationName' => 'Image',
                'NewObject' => Image::class,
            ],
        ],
    ],
]
```

### ToDo

- Documentation on handling table name collisions (supported at this time, not documented on process)
- 
- Version table migration support
