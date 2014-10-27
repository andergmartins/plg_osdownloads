<?php
/**
 * @package     com_osdownloads
 * @author      Akema
 * @copyright   Copyright
 * @license     License, for example GNU/GPL
 */

defined('_JEXEC') or die('Restricted access');

require_once JPATH_SITE . '/components/com_osdownloads/router.php';

/**
 * All functions need to get wrapped in a class
 *
 */
class PlgSearchOSDownloads extends JPlugin {

  /**
   * Constructor
   *
   * @access      protected
   * @param       object $subject The object to observe
   * @param       array $config An array that holds the plugin configuration
   * @since       1.6
   */
  public function __construct(& $subject, $config) {
    parent::__construct($subject, $config);
    $this->loadLanguage();
  }

  function onContentSearchAreas() {
    static $areas = array(
        'osdownloads' => 'OSDownloads'
    );

    return $areas;
  }

  // The real function has to be created. The database connection should be made.
  // The function will be closed with an } at the end of the file.
  /**
   * The sql must return the following fields that are used in a common display
   * routine: href, title, section, created, text, browsernav
   *
   * @param string Target search string
   * @param string mathcing option, exact|any|all
   * @param string ordering option, newest|oldest|popular|alpha|category
   * @param mixed An array if the search it to be restricted to areas, null if search all
   * @return array|mixed
   */
  function onContentSearch($text, $phrase = '', $ordering = '', $areas = null) {
    $db = JFactory::getDBO();
    $user = JFactory::getUser();
    $groups = implode(',', $user->getAuthorisedViewLevels());

    // If the array is not correct, return it:
    if(is_array($areas)) {
      if(!array_intersect($areas, array_keys($this->onContentSearchAreas()))) {
        return array();
      }
    }

    // Now retrieve the plugin parameters like this:
    //$nameofparameter = $this->params->get('nameofparameter', defaultsetting);
    $limit = $this->params->get('search_limit', '50' );

    // Use the PHP function trim to delete spaces in front of or at the back of the searching terms
    $text = trim($text);

    // Return Array when nothing was filled in.
    if($text == '') {
      return array();
    }

    // After this, you have to add the database part. This will be the most difficult part, because this changes per situation.
    // In the coding examples later on you will find some of the examples used by Joomla! 3.1 core Search Plugins.
    //It will look something like this.
    $wheres = array();
    switch($phrase) {

      // Search exact
      case 'exact':
        $text = $db->Quote('%' . $db->escape($text, true) . '%', false);
        $wheres2 = array();
        $wheres2[] = 'LOWER(a.name) LIKE ' . $text;
        $wheres2[] = 'LOWER(a.brief) LIKE ' . $text;
        $wheres2[] = 'LOWER(a.description_1) LIKE ' . $text;
        $wheres2[] = 'LOWER(a.description_2) LIKE ' . $text;
        $wheres2[] = 'LOWER(a.description_3) LIKE ' . $text;
        $where = '(' . implode(') OR (', $wheres2) . ')';
        break;

      // Search all or any
      case 'all':
      case 'any':

        // Set default
      default:
        $words = explode(' ', $text);
        $wheres = array();
        foreach($words as $word) {
          $word = $db->Quote('%' . $db->escape($word, true) . '%', false);
          $wheres2 = array();
          $wheres2[] = 'LOWER(a.name) LIKE ' . $word;
          $wheres2[] = 'LOWER(a.brief) LIKE ' . $word;
          $wheres2[] = 'LOWER(a.description_1) LIKE ' . $word;
          $wheres2[] = 'LOWER(a.description_2) LIKE ' . $word;
          $wheres2[] = 'LOWER(a.description_3) LIKE ' . $word;
          $wheres[] = implode(' OR ', $wheres2);
        }
        $where = '(' . implode(($phrase == 'all' ? ') AND (' : ') OR ('), $wheres) . ')';
        break;
    }

    // Ordering of the results
    switch($ordering) {

      //Alphabetic, ascending
      case 'alpha':
        $order = 'a.name ASC';
        break;

      // Oldest first
      case 'oldest':

        // Popular first
      case 'popular':

        // Newest first
      case 'newest':

        // Default setting: alphabetic, ascending
      default:
        $order = 'a.name ASC';
    }

    $section = JText::_('OSDownloads');
    $query = $db->getQuery(true);
    $query->select('m.id AS idm');
    $query->from('#__menu AS m');
    $query->where(' m.link = "index.php?option=com_osdownloads&view=downloads" AND m.published > 0 ');
    $db->setQuery($query);
    $idMenu = $db->loadResult();

    // The database query; differs per situation! It will look something like this (example from newsfeed search plugin):
    $query = $db->getQuery(true);
    $query->select('a.name AS title, a.brief AS text, a.id AS eventID, c.alias AS catslug, "" AS created, a.alias AS slug');
    $query->select($query->concatenate(array($db->Quote($section), 'c.title'), " / ") . ' AS section');
    $query->select('"0" AS browsernav');
    $query->from('#__osdownloads_documents AS a');
    $query->innerJoin('#__categories as c ON c.id = a.cate_id');
    $query->where('(' . $where . ')' . ' AND a.published = 1 AND c.published = 1 AND c.access IN (' . $groups . ')');
    $query->order($order);

    // Set query
    $db->setQuery($query, 0, $limit);
    $rows = $db->loadObjectList();

    foreach($rows as $key => $row) {
      $rows[ $key ]->href = 'index.php?option=com_osdownloads&view=item&id=' . $row->eventID . '&Itemid=' . $idMenu;
    }

    //Return the search results in an array
    return $rows;
  }
}