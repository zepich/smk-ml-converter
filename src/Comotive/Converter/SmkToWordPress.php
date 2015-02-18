<?php

namespace Comotive\Converter;

/**
 * This migration makes a SMK DB useable for wordpress
 * @author Michael Sebel <michael@comotive.ch>
 */
class SmkToWordPress
{
  /**
   * @var \zepi\Wrapper\Pdo
   */
  protected $_pdo;
  
  /**
   * @var string
   */
  protected $_oldPrefix;
  
  /**
   * @var string
   */
  protected $_newPrefix;
  
  /**
   * @var string
   */
  protected $_userPrefix;
  
  /**
   * @var boolean
   */
  protected $_isDryRun;

  /**
   * Prepares the migration, instantiates DB Connection
   * 
   * @param \zepi\Wrapper\Pdo $pdo
   */
  public function __construct(\zepi\Wrapper\Pdo $pdo, $oldPrefix, $newPrefix, $userPrefix)
  {
    $this->_pdo = $pdo;
    
    $this->_oldPrefix = $oldPrefix;
    $this->_newPrefix = $newPrefix;
    $this->_userPrefix = $userPrefix;
  }
  
  /**
   * Activates the dry run. This means, no sql statement will be
   * executed.
   * 
   * @param boolean $isDryRun
   */
  public function activateDryRun($isDryRun)
  {
    $this->_isDryRun = $isDryRun;
  }
  
  /**
   * Runs the whole migration
   */
  public function run()
  {
    $this->printLine('Starting migration');
    
    // Remove smk tables that aren't used anymore
    $this->removeSmkTables();
    
    // Rename user tables and all wordpress tables
    $this->renameTables();
    
    // Change user meta prefixes and capabilites
    $this->fixUserCapabilities();
    
    // Change the site and home url to the new host
    $this->modifyOptions();
    
    // Remove spam from comments
    $this->removeSpam();
    $this->printLine('Finished migration');
  }

  /**
   * Removes all SMK tables
   */
  protected function removeSmkTables()
  {
    $tables = array(
      $this->_oldPrefix . 'blwns_subs',
      $this->_oldPrefix . 'f3_field',
      $this->_oldPrefix . 'f3_form',
      $this->_oldPrefix . 'log',
      $this->_oldPrefix . 'newsletters',
      $this->_oldPrefix . 'newsletter_entries',
      $this->_oldPrefix . 'newsletter_templates',
      $this->_oldPrefix . 'seo_forwarder',
      $this->_oldPrefix . 'sm_attachments',
      $this->_oldPrefix . 'sm_cvs',
      $this->_oldPrefix . 'sm_cvs_meta',
      $this->_oldPrefix . 'sm_label',
      $this->_oldPrefix . 'sm_labels',
      $this->_oldPrefix . 'sm_label_relationships',
      $this->_oldPrefix . 'sm_msgs',
      $this->_oldPrefix . 'sm_msgs_label',
      $this->_oldPrefix . 'sm_pics',
      $this->_oldPrefix . 'sm_msgs_meta',
      $this->_oldPrefix . 'wf_action',
      $this->_oldPrefix . 'wf_event',
      $this->_oldPrefix . 'wf_roomitem',
      $this->_oldPrefix . 'wf_running_workflow',
      $this->_oldPrefix . 'wf_workflow',
      $this->_oldPrefix . 'wf_workflow_action'
    );

    // Remove all the tables
    foreach ($tables as $table) {
      $this->printLine('Deleting table ' . $table);
      $this->_pdo->exec('DROP TABLE ' . $table . ';');
    }
  }

  /**
   * Rename existing tables
   */
  protected function renameTables()
  {
    $tables = array(
      $this->_userPrefix . 'users' => $this->_newPrefix . 'users',
      $this->_userPrefix . 'usermeta' => $this->_newPrefix . 'usermeta',
      $this->_oldPrefix . 'comments' => $this->_newPrefix . 'comments',
      $this->_oldPrefix . 'commentmeta' => $this->_newPrefix . 'commentmeta',
      $this->_oldPrefix . 'links' => $this->_newPrefix . 'links',
      $this->_oldPrefix . 'options' => $this->_newPrefix . 'options',
      $this->_oldPrefix . 'postmeta' => $this->_newPrefix . 'postmeta',
      $this->_oldPrefix . 'posts' => $this->_newPrefix . 'posts',
      $this->_oldPrefix . 'terms' => $this->_newPrefix . 'terms',
      $this->_oldPrefix . 'term_relationships' => $this->_newPrefix . 'term_relationships',
      $this->_oldPrefix . 'term_taxonomy' => $this->_newPrefix . 'term_taxonomy'
    );

    // Remove all the tables
    foreach ($tables as $oldName => $newName) {
      $this->printLine('Renaming table ' . $oldName . ' to ' . $newName);
      $this->_pdo->exec('RENAME TABLE ' . $oldName . ' TO ' . $newName . ';');
    }
  }

  /**
   * Remove spam from comments and unused meta data
   */
  protected function removeSpam()
  {
    $this->printLine('Removing spam comments');
    $table = $this->_newPrefix . 'comments';
    $this->_pdo->exec('DELETE FROM ' . $table . ' WHERE comment_approved = "spam"');
    $this->_pdo->exec('DELETE FROM ' . $table . ' WHERE comment_approved = 0 AND comment_type = "trackback"');
    $this->_pdo->exec('DELETE FROM ' . $table . ' WHERE comment_approved = 0 AND comment_type = "pingback"');

    $this->printLine('Removing unused comment meta data');
    $table = $this->_newPrefix . 'commentmeta';
    $this->_pdo->exec('
      DELETE FROM ' . $table . ' WHERE
      meta_key IN(
        "_notification_sent",
        "editable_comments_validation",
        "editable_comments_edit_time"
      )
    ');
  }

  /**
   * @param string $message
   */
  protected function printLine($message, $type = 'info')
  {
    \Blogwerk\Output::output($message, $type);
  }

  /**
   * Remove the language field
   */
  public function removeLanguageField()
  {
    $this->printLine('Removing language field from wp_posts');
    $table = $this->_newPrefix . 'posts';
    $this->_pdo->exec('ALTER TABLE ' . $table . ' DROP post_lang');
  }

  /**
   * Fix the user capabilities for each user
   */
  protected function fixUserCapabilities()
  {
    // Preset some variables
    $options = $this->_newPrefix . 'options';
    $usermeta = $this->_newPrefix . 'usermeta';
    $oldRolesKey = $this->_oldPrefix . 'user_roles';
    $newRolesKey = $this->_newPrefix . 'user_roles';
    $oldUserCapsKey = $this->_oldPrefix . 'capabilities';
    $newUserCapsKey = $this->_newPrefix . 'capabilities';
    $oldUserLevelKey = $this->_oldPrefix . 'user_level';
    $newUserLevelKey = $this->_newPrefix . 'user_level';
    $oldUserDescKey = $this->_oldPrefix . 'user_description';
    $newUserDescKey = 'description';

    // Get the capabilities array(wpX_user_roles)
    $stmt = $this->_pdo->query('SELECT option_value FROM ' . $options . ' WHERE option_name = "' . $oldRolesKey . '"');
    $stmt->execute();
    $data = $stmt->fetch();
    $stmt->closeCursor();
    $roles = unserialize($data[0]);

    // Rename the "smk1editor" to "Redaktor"
    $roles['smk1editor']['name'] = 'Redaktor';
    // Remove the "smk3superuser"
    unset($roles['smk3superuser']);
    // Save the capabilities with new prefix, delete old one
    $this->printLine('Saving new roles to ' . $newRolesKey);
    $this->_pdo->exec('DELETE FROM ' . $options . ' WHERE option_name = "' . $newRolesKey . '"');
    $this->_pdo->exec('
      INSERT INTO ' . $options . ' (option_name, option_value, autoload)
      VALUES ("' . $newRolesKey . '", "' . $this->escapeSerialize($roles) . '", "yes");
    ');

    // Reassign the administrator role to all smk3superuser
    $this->printLine('Changing smk3superusers to real administrators');
    $stmt = $this->_pdo->query('
      SELECT umeta_id, meta_key, meta_value FROM ' . $usermeta . '
      WHERE meta_key = "' . $oldUserCapsKey . '" AND meta_value LIKE "%smk3superuser%"
    ');
    $stmt->execute();

    foreach ($stmt->fetchAll() as $userMeta) {
      $newMeta = unserialize($userMeta['meta_value']);
      unset($newMeta['smk3superuser']);
      $newMeta['administrator'] = true;
      $this->_pdo->exec('
        UPDATE ' . $usermeta . '
        SET meta_value = "' . $this->escapeSerialize($newMeta) . '"
        WHERE umeta_id = ' . $userMeta['umeta_id']
      );
    }

    // Rename all the matching cap prefixes
    $this->printLine('Change cap keys ' . $oldUserCapsKey . ' to ' . $newUserCapsKey);
    $this->_pdo->exec('
      UPDATE ' . $usermeta . '
      SET meta_key = "' . $newUserCapsKey . '"
      WHERE meta_key = "' . $oldUserCapsKey . '"
    ');

    // Rename all the matching user level
    $this->printLine('Change user level keys ' . $oldUserLevelKey . ' to ' . $newUserLevelKey);
    $this->_pdo->exec('
      UPDATE ' . $usermeta . '
      SET meta_key = "' . $newUserLevelKey . '"
      WHERE meta_key = "' . $oldUserLevelKey . '"
    ');

    // Rename all the user desc keys
    $this->printLine('Change user desc keys ' . $oldUserDescKey . ' to ' . $newUserDescKey);
    $this->_pdo->exec('
      UPDATE ' . $usermeta . '
      SET meta_key = "' . $newUserDescKey . '"
      WHERE meta_key = "' . $oldUserDescKey . '"
    ');
  }

  /**
   * Updates the options table and writes the new value for
   * home and site url in it.
   */
  protected function modifyOptions()
  {
    $this->printLine('Change the site and home url in the options table.');
    
    // Define the new value
    $value = 'http://' . $_SERVER['HTTP_HOST'];
    
    // Set the new home and site url
    $optionsTable = $this->_newPrefix . 'options';
    $this->_pdo->exec('
      UPDATE ' . $optionsTable . '
      SET option_value = "' . $value . '"
      WHERE option_name IN ("home", "siteurl")
    ');
  }

  /**
   * @param array $value
   * @return string serialized escaped string
   */
  protected function escapeSerialize($value)
  {
    return addslashes(serialize($value));
  }
}
