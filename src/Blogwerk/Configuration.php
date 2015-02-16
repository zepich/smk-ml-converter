<?php
/**
 * The Configuration object manages the config file access and
 * parsing.
 * 
 * @package Blogwerk
 * @author Matthias Zobrist <matthias.zobrist@zepi.net>
 * @copyright Copyright (c) 2015 zepi (http://www.zepi.net)
 */
 
namespace Blogwerk;

/**
 * The Configuration object manages the config file access and
 * parsing.
 * 
 * @author Matthias Zobrist <matthias.zobrist@zepi.net>
 * @copyright Copyright (c) 2015 zepi (http://www.zepi.net)
 */
class Configuration
{
  /**
   * @var \Blogwerk\CliCore
   */
  protected $_cliCore;
  
  /**
   * @var array
   */
  protected $_data;
  
  /**
   * Constructs the object
   * 
   * @param \Blogwerk\CliCore $cliCore
   */
  public function __construct(CliCore $cliCore)
  {
    $this->_cliCore = $cliCore;
  }
  
  /**
   * Reads and parses a configuration file
   * 
   * @access public
   * @return boolean
   */
  public function readFromFile()
  {
    $file = $this->_cliCore->getConfigurationFilePath();
    
    if (!file_exists($file)) {
      Output::output('The given configuration file "' . $file . '" does not exist.', 'error');
      exit;
    }
    
    if (!is_readable($file)) {
      Output::output('Cannot access the given configuration file "' . $file . '".', 'error');
      exit;
    }
    
    $this->_data = parse_ini_file($file, true);
    
    if (!is_array($this->_data)) {
      Output::output('Cannot parse the given configuration file "' . $file . '" as an ini file.', 'error');
      exit;
    }
    
    return true;
  }
  
  /**
   * Returns the value for the given key or returns false
   * if the given key does not exists
   * 
   * @access public
   * @param string $section
   * @param string $key
   * @return mixed
   */
  public function get($section, $key)
  {
    if (!isset($this->_data[$section][$key])) {
      return false;
    }
    
    return $this->_data[$section][$key];
  }
}
