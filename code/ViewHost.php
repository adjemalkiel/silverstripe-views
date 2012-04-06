<?php

/**
 * A ViewHost is a DataObjectDecorator that can be added to DataObjects to
 * allow them to have view definitions added to them.  With the default module
 * configuration all SiteTree nodes have the ViewHost DOD added to them.
 *
 * @todo test adding this to SiteConfig to allow for the definition of site-
 *       wide views available from all pages.  The view traversal code will
 *       need to be modified to look at SiteConfig after exhausting all other
 *       options (and look at both translations of SiteConfig like it does for
 *       pages)
 *
 * @author Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @copyright (c) 2012 Jeremy Thomerson <jeremy@thomersonfamily.com>
 * @package silverstripe-views
 * @subpackage code
 */
class ViewHost extends DataObjectDecorator {

   /**
    * @see DataObjectDecorator->extraStatics()
    */
   function extraStatics() {
      return array(
         'has_one' => array(
            'ViewCollection' => 'ViewCollection',
         ),
      );
   }

   /**
    * Accessor for retrieving all views attached to the owning data object.
    */
   public function Views() {
      $coll = $this->owner->ViewCollection();
      if (is_null($coll)) {
         return null;
      }

      return $coll->Views();
   }

   /**
    * Used by templates in a control block to retrieve a view by name.
    * Additionally, a boolean can be passed in to indicate whether or not the
    * hierarchy should be traversed to find the view on translations and
    * parents (default: true).
    *
    * @param string $name the name of the view to find
    * @param int $resultsPerPage (optional, default 0) - zero for unlimited results, otherwise how many to show per page
    * @param string $paginationURLParam the query string key to use for pagination (default: start)
    * @param boolean $traverse traverse hierarchy looking for view? (default: true)
    * @return View the found view or null if not found
    */
   public function GetView($name, $resultsPerPage = 0, $paginationURLParam = 'start', $traverse = true) {
      // ATTEMPT 1: Do I have the view on this page?
      $view = $this->owner->getViewWithoutTraversal($name);

      if ($view == null && $traverse) {
         $defaultLocale = class_exists('Translatable') ? Translatable::default_locale() : null;

         // ATTEMPT 2: if we're translatable get the page of the default locale and see if it has the view
         if ($this->owner->hasExtension('Translatable') && $this->owner->Locale != $defaultLocale) {
            $master = $this->owner->getTranslation($defaultLocale);
            $view = ($master != null && $master->hasExtension('ViewHost')) ? $master->getViewWithoutTraversal($name) : null;
         }

         // ATTEMPT 3: go to my parent page and try to get the view (and allow it to continue traversing)
         if ($view == null && $this->owner->ParentID != 0 && ($parent = $this->owner->Parent()) != null && $parent->hasExtension('ViewHost')) {
            return $parent->GetView($name, $resultsPerPage, $paginationURLParam, $traverse);
         }
      }

      return $view->setTransientPaginationConfig($resultsPerPage, $paginationURLParam);
   }

   /**
    * Internal function used by GetView to actually implement the non-recursive
    * portion of the view searching functionality.  This function checks only
    * its owner object to see if it contains the given view.
    *
    * NOTE: even though this is an internal function it must be declared public
    * because other functions in this class call
    * $ownerObject->getViewWithoutTraversal.  Since they are calling the
    * function on the owner object and not directly on this class it must be
    * public.
    *
    * @see GetView()
    * @param string $name the name of the view to find
    * @return View the found view or null if not found
    */
   public function getViewWithoutTraversal($name) {
      $allViews = $this->Views();
      if ($allViews == null) {
         return null;
      }
      foreach ($allViews as $view) {
         if ($view->Name == $name) {
            return $view;
         }
      }
      return null;
   }

   /**
    * Used by templates in a conditional block to see if there is a view with a
    * given name defined on this page (or, if traversing, a translation or
    * parent)
    *
    * @param string $name the name of the view to find
    * @param boolean $traverse traverse hierarchy looking for view? (default: true)
    * @return View the found view or null if not found
    */
   public function HasView($name, $traverse = true) {
      return ($this->GetView($name, $traverse) != null);
   }

   /**
    * Used by templates in a conditional block to see if there is a view with a
    * given name defined on this page (or, if traversing, a translation or
    * parent) AND the view has results.
    *
    * @param string $name the name of the view to find
    * @param boolean $traverse traverse hierarchy looking for view? (default: true)
    * @return View the found view or null if not found
    */
   public function HasViewWithResults($name, $traverse = true) {
      $view = $this->GetView($name, $traverse);
      if ($view == null) {
         return false;
      }

      $results = $view->Results();
      return is_null($results) ? false : ($results->Count() > 0);
   }

   /**
    * Used by templates in a conditional block to see if there is a view with a
    * given name defined on this page (or, if traversing, a translation or
    * parent) AND the view has results in the language of the page that is
    * being viewed.
    *
    * @param string $name the name of the view to find
    * @param boolean $traverse traverse hierarchy looking for view? (default: true)
    * @return View the found view or null if not found
    */
   public function HasViewWithTranslatedResults($name, $traverse = true) {
      $view = $this->GetView($name, $traverse);
      if ($view == null) {
         return false;
      }

      $results = $view->TranslatedResults();
      return is_null($results) ? false : ($results->Count() > 0);
   }

   /**
    * @see DataObjectDecorator->updateCMSFields()
    */
   public function updateCMSFields(FieldSet &$fields) {
      // TODO: make this show more than 10 results (it's paginated)
      // TODO: make this not show the checkboxes since we're limiting it to the views on this page
      $viewCollection = $this->owner->ViewCollection();
      $viewsTable = new HasManyComplexTableField(
         $viewCollection,
         'Views',
         'View',
         array(
            'ReadOnlySummary' => 'Name',
         ),
         'getCMSFields',
         sprintf('"View"."ViewCollectionID" = %d', $viewCollection->ID)
      );

      // use our custom form for add/edit:
      $viewsTable->popupClass = 'AddViewFormPopup';

      $fields->addFieldToTab('Root.Views', $viewsTable);
   }

}
