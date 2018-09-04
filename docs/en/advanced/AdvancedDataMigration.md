# Advanced Data Migration

### Summary
You may find that in some cases you had additional relations or fields applied to a Block, or a field name has changed from your old block to a new element. This is best resolved using SilverStripe's [DataExtension](https://github.com/silverstripe/silverstripe-framework/blob/4/src/ORM/DataExtension.php).

#### Example

_**MyBlock.php**_

```php
<?php

namespace Foo\Bar;

class MyBlock extends Block
{
    private static $db = [
        'MyUniqueField' => 'Boolean',
    ];
}
```

_**MyElement.php**_

```php
<?php

namespace Foo\Baz;

class MyElement extends BaseElement
{
    private static $db = [
        'MyNewUniqueField' => 'Boolean',
    ];
}
```

In this example `MyUniqueField` is now `MyNewUniqueField`. The migration tool isn't inherently aware of this change, however, we can use a `DataExtension` to handle this:

```php
<?php

namespace Foo\Baz;

class MyDataExtension extends DataExtension
{
    private static $db = [
        'MyUniqueField' => 'Boolean',
    ];

    public function onBeforeWrite() {
        parent::onBeforeWrite();
    
        $this->owner->MyNewUniqueField = $this->owner->MyUniqueField;
    }
}
```

Applying the above `DataExtension` to `MyElement` will allow it to access the legacy field as it is re-implemented in the `DataExtension`'s `$db` fields. We then us an `onBeforeWrite()` to move the value from the old field to the new field. After the migration is complete, this `DataExtension` could be removed if the legacy field is no longer needed, or at minimum, updating or removing the `onBeforeWrite()` so as to not overwrite any data after the migration is complete.