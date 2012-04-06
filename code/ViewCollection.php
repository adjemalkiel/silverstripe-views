<?php

/**
 * A container for many views.  Since ViewHost is a DataObjectDecorator, it
 * does not work for it to directly had a has_many to Views because SS requires
 * a has_one from the other end of the relationship (in this case, View would
 * be required to have a has_one back to the thing that the ViewHost DOD was
 * added to).
 *
 * Since has_one relationships work fine on DataObjectDecorators, ViewHost has
 * a has_one relationship to ViewCollection, which in turn has the has_many
 * relationship to views.
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage code
 */
class ViewCollection extends DataObject {

   static $has_many = array(
      'Views' => 'View',
   );

}
