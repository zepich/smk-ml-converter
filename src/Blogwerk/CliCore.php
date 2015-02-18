<?php
/**
 * The CliCore is the mail part to manage the cli options and 
 * arguments.
 * 
 * @package Blogwerk
 * @author Matthias Zobrist <matthias.zobrist@zepi.net>
 * @copyright Copyright (c) 2015 zepi (http://www.zepi.net)
 */
 
namespace Blogwerk;

/**
 * The CliCore is the mail part to manage the cli options and 
 * arguments.
 * 
 * @author Matthias Zobrist <matthias.zobrist@zepi.net>
 * @copyright Copyright (c) 2015 zepi (http://www.zepi.net)
 */
class CliCore
{
  const MODE_PREPARE = 'prepare';
  const MODE_RESET = 'reset';
  const MODE_CONVERT = 'convert';
  
  /**
   * @var string
   */
  protected $_configFilePath;
  
  /**
   * @var boolean
   */
  protected $_dryRun = false;
  
  /**
   * @var string
   */
  protected $_mode;
  
  /**
   * Returns the path to the selected configuration
   * file.
   * 
   * @return string
   */
  public function getConfigurationFilePath()
  {
    return $this->_configFilePath;
  }
  
  /**
   * Returns true if the tool is executed as a dry run
   * 
   * @return boolean
   */
  public function isDryRun()
  {
    return ($this->_dryRun);
  }
  
  /**
   * Returns the execution mode.
   * 
   * @return string
   */
  public function getMode()
  {
    return $this->_mode;
  }
  
  /**
   * Declares the cli arguments
   * 
   * @access public
   */
  public function declareArguments()
  {
    $input = new \ezcConsoleInput();
    
    /**
     * Define the mode argument
     */
    $consoleArguments = new \ezcConsoleArguments();
    $consoleArguments[] = new \ezcConsoleArgument(
      'mode',
      \ezcConsoleInput::TYPE_STRING,
      'Execution mode, use "' . self::MODE_PREPARE . '" to prepare the database for WordPress, "' . self::MODE_RESET . '" to reset the admin login or "' . self::MODE_CONVERT . '" to convert the language data',
      'Execution mode, use "' . self::MODE_PREPARE . '" to prepare the database for WordPress, "' . self::MODE_RESET . '" to reset the admin login or "' . self::MODE_CONVERT . '" to convert the language data',
      false
    );
    
    $input->argumentDefinition = $consoleArguments;
    
    /**
     * Help option
     */
    $helpOption = $input->registerOption( 
      new \ezcConsoleOption( 
        'h',
        'help',
        null,
        null,
        false,
        'This help text.',
        'This help text.', 
        array(),
        array(), 
        true,
        false,
        true
      )
    );
    
    /**
     * Configuration file
     */
    $configOption = $input->registerOption( 
      new \ezcConsoleOption( 
        'c',
        'config',
        \ezcConsoleInput::TYPE_STRING,
        null,
        false,
        'Configuration file for the translator.'
      )
    );
    
    /**
     * Don't save any data, test only
     */
    $dryOption = $input->registerOption( 
      new \ezcConsoleOption( 
        'd',
        'dry',
        null,
        null,
        false,
        'Test everything but don\'t execute anything.'
      )
    );
    
    /**
     * Parse the arguments. Displays an error message if something is wrong
     */
    try {
      $input->process();
    } catch (\ezcConsoleOptionException $e) {
      die('ERROR: ' . $e->getMessage() . PHP_EOL);
    }
    
    /**
     * Verify the help option
     */
    if ($helpOption->value !== false) {
      echo $input->getHelpText('Translates the Social Media Kit Mutli Language data to Polylang.');
      exit;
    }

    /**
     * Verify the config file argument
     */
    if ($configOption->value === false) {
      echo $input->getHelpText('Translates the Social Media Kit Mutli Language data to Polylang.');
      exit;
    }
    
    $this->_configFilePath = $configOption->value;
    $this->_dryRun = $dryOption->value;
    
    /**
     * Verify the mode argument
     */
    $arguments = $input->getArguments();
    
    if (!is_array($arguments) || !isset($arguments[0])) {
      echo $input->getHelpText('Translates the Social Media Kit Mutli Language data to Polylang.');
      exit;
    }
    
    $modeArgument = $arguments[0];
    if (!in_array($modeArgument, array(self::MODE_PREPARE, self::MODE_RESET, self::MODE_CONVERT))) {
      echo $input->getHelpText('Translates the Social Media Kit Mutli Language data to Polylang.');
      exit;
    }
    
    $this->_mode = $modeArgument;
  }
}
