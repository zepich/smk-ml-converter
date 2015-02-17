<?php
/**
 * The Output class is a utility class to make the display
 * of text and some other things very easy.
 * 
 * @package Blogwerk
 * @author Matthias Zobrist <matthias.zobrist@zepi.net>
 * @copyright Copyright (c) 2015 zepi (http://www.zepi.net)
 */

namespace Blogwerk;

/**
 * The Output class is a utility class to make the display
 * of text and some other things very easy.
 * 
 * @author Matthias Zobrist <matthias.zobrist@zepi.net>
 * @copyright Copyright (c) 2015 zepi (http://www.zepi.net)
 */
class Output
{
  /**
   * @static
   * @var eczConsoleOutput
   */
  static protected $_eczConsoleOutput;
  
  /**
   * Sends the given message to the std output. The message will printed in the 
   * color for the given type
   * 
   * @static
   * @param string $message
   * @param string $type
   */
  static public function output($message, $type)
  {
    self::initializeEczConsoleOutput();
    
    $modifiedMessage = date('Y-m-d H:i:s') . ' - ' . $message . PHP_EOL;
    self::$_eczConsoleOutput->outputText($modifiedMessage, $type);
  }
  
  /**
   * Outputs an empty line
   * 
   * @static
   */
  static public function outputLine()
  {
    self::initializeEczConsoleOutput();
    
    self::$_eczConsoleOutput->outputLine();
  }
  
  /**
   * Starts a new progressbar for the given amount of entries
   * 
   * @static
   * @param integer $numberOfEntries
   * @return \eczConsoleProgressbar
   */
  static public function startProgressBar($numberOfEntries)
  {
    self::initializeEczConsoleOutput();
    
    //$bar = new \ezcConsoleProgressbar(self::$_eczConsoleOutput, $numberOfEntries);
    $bar = new \zepi\ConsoleTools\Progressbar(self::$_eczConsoleOutput, $numberOfEntries);
    
    return $bar;
  }
  
  /**
   * Initializes the eczConsoleOutput object
   * 
   * @static
   */
  static protected function initializeEczConsoleOutput()
  {
    if (self::$_eczConsoleOutput === null) {
      self::$_eczConsoleOutput = new \ezcConsoleOutput();
    
      self::$_eczConsoleOutput->formats->info->color = 'blue';
      self::$_eczConsoleOutput->formats->error->color = 'red';
      self::$_eczConsoleOutput->formats->dry->color = 'yellow';
      self::$_eczConsoleOutput->formats->main->color = 'green';
      self::$_eczConsoleOutput->formats->notice->color = 'cyan';
      self::$_eczConsoleOutput->options->autobreak    = 100;
    }
  }
}
