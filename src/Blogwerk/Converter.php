<?php
/**
 * The Converter class is the frontcontroller which will execute
 * all operations for the converter.
 * 
 * @package Blogwerk
 * @author Matthias Zobrist <matthias.zobrist@zepi.net>
 * @copyright Copyright (c) 2015 zepi (http://www.zepi.net)
 */

namespace Blogwerk;

/**
 * The Converter class is the frontcontroller which will execute
 * all operations for the converter.
 * 
 * @author Matthias Zobrist <matthias.zobrist@zepi.net>
 * @copyright Copyright (c) 2015 zepi (http://www.zepi.net)
 */
class Converter
{
  /**
   * @var \Blogwerk\CliCore
   */
  protected $_cliCore; 
 
  /**
   * @var \Blogwerk\Configuration
   */
  protected $_configuration;
  
  /**
   * @var \zepi\Wrapper\zepi
   */
  protected $_pdo;
  
  /**
   * @var \Comotive\Converter\SmkToWordPress
   */
  protected $_smkConverter;
  
  /**
   * Constructs the object
   * 
   * @param \Blogwerk\CliCore $cliCore
   * @param \Blogwerk\Configuration $configuration
   */
  public function __construct(CliCore $cliCore, Configuration $configuration)
  {
    $this->_cliCore = $cliCore;
    $this->_configuration = $configuration;
  }
  
  /**
   * Executes the convert functionality
   */
  public function convert()
  {
    Output::output('Start the SMK ML Converter...', 'main');
    
    // Display a hint if the command is executed as dry run
    if ($this->_cliCore->isDryRun()) {
      Output::output('!!! DRY RUN is active. No actions are executed on the database!', 'dry');
    }
    
    if ($this->_cliCore->getMode() === CliCore::MODE_PREPARE) {
      /**
       * Prepare the database for a normal WordPress instance
       */
      
      Output::output('Mode: PREPARE the database for WordPress', 'main');
      
      // Execute the smk converter to prepare the database
      $this->_executeSmkConverter();
    } else if ($this->_cliCore->getMode() === CliCore::MODE_RESET) {
      /**
       * Reset the admin login
       */
      
      Output::output('Mode: RESET the admin login', 'main');
      
      // Verify the WordPress installation
      Output::output('Verify WordPress...', 'main');
      $this->_verifyEnvironment(false);
      
      // Reset the admin password
      Output::output('Reset the passwort for the user "admin".', 'main');
      $this->_resetPassword();
    } else if ($this->_cliCore->getMode() === CliCore::MODE_CONVERT) {
      /**
       * Convert the language data
       */
      
      Output::output('Mode: CONVERT the multilanguage data', 'main');
      
      // Verify the WordPress installation
      Output::output('Verify WordPress & Polylang...', 'main');
      $this->_verifyEnvironment(true);
      
      // Translate posts
      Output::output('Converting the posts...', 'main');
      $this->_convertPosts();
      
      // Translate terms
      Output::output('Converting the terms...', 'main');
      $this->_convertTerms();
      
      // SMK data cleanup
      Output::output('Cleaning up the SMK data...', 'main');
      //$this->_cleanupSmkData();
    }
  }
  
  /**
   * Creates the pdo database connection
   * 
   * @return \zepi\Wrapper\Pdo
   */
  protected function _getDatabaseConnection()
  {
    if ($this->_pdo === null) {
      $dryRun = $this->_cliCore->isDryRun();
      
      $this->_pdo = new \zepi\Wrapper\Pdo(
        'mysql:host=' . $this->_configuration->get('database', 'dbHost') . ';dbname=' . $this->_configuration->get('database', 'dbName'),
        $this->_configuration->get('database', 'dbUser'),
        $this->_configuration->get('database', 'dbPassword'),
        array(\PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8"),
        $dryRun
      );
    }

    return $this->_pdo;
  }
  
  /**
   * Execute the SMK converter
   */
  protected function _executeSmkConverter()
  {
    // Executes the smk converter
    $smkConverter = $this->_getSmkConverter();
    $smkConverter->run();
    
    // Output the howto to convert the multilanguage data
    $text = 'The database is prepared for a normal WordPress instance. Please execute these steps manually:' . PHP_EOL
          . PHP_EOL
          . '  1. Download WordPress and extract the content of WordPress package to a directory.' . PHP_EOL
          . '  2. Create manually a wp-config.php file and insert the required data to connect to the database.' . PHP_EOL
          . '  3. Reset the admin password with this tool Use the following command: "php converter.php ' . CliCore::MODE_RESET . '"';
          
    Output::output($text, 'main');
  }
  
  /**
   * Verify the WordPress environment
   * 
   * @param boolean $validatePolylang
   */
  protected function _verifyEnvironment($validatePolylang = false)
  {
    $path = $this->_configuration->get('wordpress', 'pathToRoot');
    
    // Verify the path
    if (!file_exists($path) || !is_readable($path)) {
      Output::output('The path to WordPress is not valid or is not readable ("' . $path . '").', 'error');
      exit;
    }

    // Check for the wp-config.php file
    if (!file_exists($path . '/wp-config.php') || !is_readable($path . '/wp-config.php')) {
      Output::output('There is no wp-config.php or the file is not readable in the WordPress path ("' . $path . '").', 'error');
      exit;
    }
    
    // Check for the wp-load.php file
    if (!file_exists($path . '/wp-load.php') || !is_readable($path . '/wp-load.php')) {
      Output::output('There is no wp-load.php or the file is not readable in the WordPress path ("' . $path . '").', 'error');
      exit;
    }
    
    // Load WordPress
    Output::output('Load WordPress...', 'info');
    include_once($path . '/wp-load.php');
    
    // Verify the installation
    if (get_option('siteurl') == false) {
      Output::output('WordPress is not working. Please verify the installation.', 'error');
      exit;
    }
    
    // If we don't have to validate polylang then we need to return here.
    if (!$validatePolylang) {
      return true;
    }
    
    // Is the Polylang plugin installed and activated?
    include_once(ABSPATH . 'wp-admin/includes/plugin.php');
    if (!is_plugin_active('polylang/polylang.php')) {
      Output::output('The Polylang plugin is not activated. Please activate and configure the plugin.', 'error');
      exit;
    }

    // Yeah. WordPress is installed and Polylang is active!
    Output::output('WordPress is loaded and ready to convert the language data.', 'main');
  }

  /**
   * Resets the password of the admin user
   */
  protected function _resetPassword()
  {
    $pdo = $this->_getDatabaseConnection();
    
    $usersTable = $this->_configuration->get('dbconverter', 'newPrefix') . 'users';
    $data = $pdo->query('SELECT ID FROM ' . $usersTable . ' WHERE user_login = "admin" ORDER BY ID ASC LIMIT 0,1')->fetch();
    
    // If there is no admin user, we cannot reset the password.
    if ($data === false) {
      Output::output('There is no user with the username "admin". We cannot reset the password.', 'error');
      exit;
    }
    
    // Set the new password for the admin user
    $pdo->exec(
      'UPDATE ' . $usersTable . ' SET ' .
      'user_pass = ' . $pdo->quote(wp_hash_password('Converter2015')) . ' ' .
      'WHERE ID = ' . $data['ID']
    );
    
    // Output the login data
    Output::output('The password is changed.', 'notice');
    Output::outputLine();
    Output::output('   Username:    admin', 'notice');
    Output::output('   Password:    Converter2015', 'notice');
    Output::outputLine();
    
    // Output the howto text
    $text = 'The password is reset for the admin user and you should now be able to login as admin. Please execute this steps manually:' . PHP_EOL
          . PHP_EOL
          . '  1. Download Polylang from the WordPress plugin repository.' . PHP_EOL
          . '  2. Extract the content of the Polylang plugin to the WordPress plugins directory.' . PHP_EOL
          . '  3. Login to WordPress and activate Polylang.' . PHP_EOL
          . '  4. Configure the languages in the backend of WordPress. Please add all required languages.' . PHP_EOL
          . '  5. Execute this tool again with the following command "php convert.php ' . CliCore::MODE_CONVERT . '"' . PHP_EOL;
          
    Output::output($text, 'main');
  }
  
  /**
   * Convert the posts
   */
  protected function _convertPosts()
  {
    $postConverter = new \Blogwerk\Converter\Post($this->_cliCore, $this->_getDatabaseConnection(), $this->_configuration);
    $postConverter->convertPosts();
  }
  
  /**
   * Convert the terms
   */
  protected function _convertTerms()
  {
    $termConverter = new \Blogwerk\Converter\Term($this->_cliCore, $this->_getDatabaseConnection(), $this->_configuration);
    $termConverter->convertTermsOfTaxonomies();
  }
  
  /**
   * Cleanups the smk data and removes, if enabled, the smk language
   * data.
   */
  protected function _cleanupSmkData()
  {
    $smkConverter = $this->_getSmkConverter();
    
    if ($this->_configuration->get('smkconverter', 'removeLanguageField')) {
      $smkConverter->removeLanguageField();
    }
  }
  
  /**
   * Creates a Comotive SMK to WordPress converter
   * 
   * @return \Comotive\Converter\SmkToWordPress
   */
  protected function _getSmkConverter()
  {
    if ($this->_smkConverter === null) {
      $oldPrefix = $this->_configuration->get('dbconverter', 'oldPrefix');
      $newPrefix = $this->_configuration->get('dbconverter', 'newPrefix');
      $userPrefix = $this->_configuration->get('dbconverter', 'userPrefix');
      
      $this->_smkConverter = new \Comotive\Converter\SmkToWordPress(
        $this->_getDatabaseConnection(), 
        $oldPrefix, 
        $newPrefix, 
        $userPrefix
      );
    }
    
    return $this->_smkConverter;
  }
}
