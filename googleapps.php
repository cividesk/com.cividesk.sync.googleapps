<?php

require_once 'googleapps.civix.php';

/**
 * Implementation of hook_civicrm_config
 */
function googleapps_civicrm_config(&$config) {
  _googleapps_civix_civicrm_config($config);
  $extRoot = dirname( __FILE__ ) . DIRECTORY_SEPARATOR;
  if (is_dir($extRoot . 'packages')) {
    set_include_path($extRoot . 'packages' . PATH_SEPARATOR . get_include_path());
  }
}

/**
 * Implementation of hook_civicrm_xmlMenu
 *
 * @param $files array(string)
 */
function googleapps_civicrm_xmlMenu(&$files) {
  _googleapps_civix_civicrm_xmlMenu($files);
}

/**
 * Implementation of hook_civicrm_install
 */
function googleapps_civicrm_install() {
  // required to define the CONST below
  googleapps_civicrm_config();
  require_once 'CRM/Sync/BAO/GoogleApps.php';
  // Create sync queue table if not exists
  $query = "
    CREATE TABLE IF NOT EXISTS `" . CRM_Sync_BAO_GoogleApps::GOOGLEAPPS_QUEUE_TABLE_NAME . "` (
          `id` int(10) NOT NULL AUTO_INCREMENT,
          `civicrm_contact_id` int(10) NOT NULL,
          `google_contact_id` varchar(32) DEFAULT NULL,
          `first_name` varchar(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
          `last_name` varchar(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
          `organization` varchar(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
          `job_title` varchar(255) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
          `email` varchar(64) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
          `email_location_id` int(10) DEFAULT NULL,
          `phone` varchar(32) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
          `phone_ext` varchar(32) CHARACTER SET utf8 COLLATE utf8_unicode_ci DEFAULT NULL,
          `phone_location_id` int(10) DEFAULT NULL,
          `is_deleted` tinyint(1) NOT NULL DEFAULT '0',
      PRIMARY KEY (`id`),
      KEY `civicrm_contact_id` (`civicrm_contact_id`),
      KEY `google_contact_id` (`google_contact_id`)
    ) ENGINE=InnoDB  DEFAULT CHARSET=utf8 COLLATE=utf8_unicode_ci;";
  CRM_Core_DAO::executeQuery($query);
  return _googleapps_civix_civicrm_install();
}

/**
 * Implementation of hook_civicrm_uninstall
 */
function googleapps_civicrm_uninstall() {
  // required to define the CONST below
  googleapps_civicrm_config();
  require_once 'CRM/Sync/BAO/GoogleApps.php';
  // Delete scheduled job
  $scheduledJob = CRM_Sync_BAO_GoogleApps::get_scheduledJob();
  $scheduledJob->delete();
  // Delete custom group & fields
  $custom_group = CRM_Sync_BAO_GoogleApps::get_customGroup();
  $custom_group['version'] = 3;
  $result = civicrm_api('CustomGroup', 'delete', $custom_group);
  // Drop sync queue table
  $query = "DROP TABLE IF EXISTS `" . CRM_Sync_BAO_GoogleApps::GOOGLEAPPS_QUEUE_TABLE_NAME . "`;";
  CRM_Core_DAO::executeQuery($query);
  // Delete all settings
  CRM_Core_BAO_Setting::deleteItem(CRM_Sync_BAO_GoogleApps::GOOGLEAPPS_PREFERENCES_NAME);
  return _googleapps_civix_civicrm_uninstall();
}

/**
 * Implementation of hook_civicrm_enable
 */
function googleapps_civicrm_enable() {
  // Create and enable custom group
  $params = CRM_Sync_BAO_GoogleApps::get_customGroup();
  $params['version'] = 3;
  $params['is_active'] = 1;
  $result = civicrm_api('CustomGroup', 'create', $params);
  // Create custom fields in this group
  $custom_fields = CRM_Sync_BAO_GoogleApps::get_customFields($params['id']);
  return _googleapps_civix_civicrm_enable();
}

/**
 * Implementation of hook_civicrm_disable
 */
function googleapps_civicrm_disable() {
  // Disable custom group
  $params = CRM_Sync_BAO_GoogleApps::get_customGroup();
  $params['version'] = 3;
  $params['is_active'] = 0;
  $result = civicrm_api('CustomGroup', 'create', $params);
  return _googleapps_civix_civicrm_disable();
}

/**
 * Implementation of hook_civicrm_upgrade
 *
 * @param $op string, the type of operation being performed; 'check' or 'enqueue'
 * @param $queue CRM_Queue_Queue, (for 'enqueue') the modifiable list of pending up upgrade tasks
 *
 * @return mixed  based on op. for 'check', returns array(boolean) (TRUE if upgrades are pending)
 *                for 'enqueue', returns void
 */
function googleapps_civicrm_upgrade($op, CRM_Queue_Queue $queue = NULL) {
  return _googleapps_civix_civicrm_upgrade($op, $queue);
}

/**
 * Implementation of hook_civicrm_managed
 *
 * Generate a list of entities to create/deactivate/delete when this module
 * is installed, disabled, uninstalled.
 */
function googleapps_civicrm_managed(&$entities) {
  return _googleapps_civix_civicrm_managed($entities);
}

/**
 * Implementation of hook_civicrm_navigationMenu
 */
function googleapps_civicrm_navigationMenu( &$params ) {
  googleapps_civicrm_config();
  // Add menu entry for extension administration page
  _googleapps_insert_navigationMenu($params, 'Administer/System Settings', array(
    'name'       => 'Cividesk sync for Google Apps',
    'url'        => 'civicrm/admin/sync/googleapps',
    'permission' => 'administer CiviCRM',
  ));

  // Do we need to add Google Apps navigation menu?
  $settings = CRM_Sync_BAO_GoogleApps::getSettings();
  if (CRM_Utils_Array::value('domain', $settings)) {
    // Add menu entry for More submenu
    $ok = _googleapps_insert_navigationMenu($params, '', array(
      'name'       => 'More',
    ));
    // And then all childs
    if ($ok) {
      _googleapps_insert_navigationMenu($params, 'More', array(
        'name'      => 'Google Mail',
        'url'       => 'http://mail.google.com/a/'.$settings['domain'],
        'target'    => 'gmail',
      ));
      _googleapps_insert_navigationMenu($params, 'More', array(
        'name'      => 'Google Calendar',
        'url'       => 'http://www.google.com/calendar/hosted/'.$settings['domain'],
        'target'    => 'gcalendar',
      ));
      _googleapps_insert_navigationMenu($params, 'More', array(
        'name'      => 'Google Docs',
        'url'       => 'http://docs.google.com/a/'.$settings['domain'],
        'target'    => 'gdocs',
      ));
      _googleapps_insert_navigationMenu($params, 'More', array(
        'name'      => 'Google Contacts',
        'url'       => 'http://www.google.com/contacts/a/'.$settings['domain'],
        'target'    => 'gcontacts',
      ));
    }
  }
}

function _googleapps_insert_navigationMenu(&$menu, $path, $item, $parentId = null) {
  static $navId;

  // If we are done going down the path, insert menu
  if (empty($path)) {
    if (!$navId) $navId = CRM_Core_DAO::singleValueQuery("SELECT max(id) FROM civicrm_navigation");
    $navId ++;
    $menu[$navId] = array (
      'attributes' => array_merge($item, array(
        'label'      => CRM_Utils_Array::value('name', $item),
        'active'     => 1,
        'parentID'   => $parentId,
        'navID'      => $navId,
      ))
    );
    return true;
  } else {
    // Find an recurse into the next level down
    $found = false;
    $path = explode('/', $path);
    $first = array_shift($path);
    foreach ($menu as $key => &$entry) {
      if ($entry['attributes']['label'] == $first) {
        if (!$entry['child']) $entry['child'] = array();
        $found = _googleapps_insert_navigationMenu($entry['child'], implode('/', $path), $item, $key);
      }
    }
    return $found;
  }
}