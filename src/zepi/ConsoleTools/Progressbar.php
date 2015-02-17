<?php
/**
 * A better eczConsoleProgressbar. This class allows you to change the 
 * number of maximum entries on the fly and redraws the progressbar
 * correctly.
 * 
 * @package zepi\ConsoleTools
 * @author Matthias Zobrist <matthias.zobrist@zepi.net>
 * @copyright Copyright (c) 2015 zepi (http://www.zepi.net)
 */

namespace zepi\ConsoleTools;

/**
 * A better eczConsoleProgressbar. This class allows you to change the 
 * number of maximum entries on the fly and redraws the progressbar
 * correctly.
 * 
 * @author Matthias Zobrist <matthias.zobrist@zepi.net>
 * @copyright Copyright (c) 2015 zepi (http://www.zepi.net)
 */
class Progressbar extends \ezcConsoleProgressbar
{
  public function increaseMaximum($additionalNumberOfEntries)
  {
    // Increase the number of entries
    $this->max += $additionalNumberOfEntries;
    
    // Restore the cursor
    $this->output->restorePos();
    
    // Reset the arrays
    $this->valueMap = array( 
      'bar'       => '',
      'fraction'  => '',
      'act'       => '',
      'max'       => '',
    );
    $this->measures =  array( 
      'barSpace'          => 0,
      'fractionSpace'     => 0,
      'actSpace'          => 0,
      'maxSpace'          => 0,
      'fixedCharSpace'    => 0,
    );
    
    // Calculate the measures
    $this->calculateMeasures();
    
    // Restart the progressbar
    $this->output->storePos();
    $this->started = true;
  }
}
