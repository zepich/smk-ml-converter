<?php
/**
 * Database handler class.
 * Wraps PDO to work around connecting to the database in PDO constructor.
 * Will lazy initialize DB connection on first request.
 *
 * @package zepi_wrapper
 * @author Matthias Zobrist <matthias.zobrist@zepi.org>
 * @copyright Copyright (c) 2011 zepi (http://www.zepi.org)
 */
namespace zepi\Wrapper;

/**
 * Database handler class.
 * Wraps PDO to work around connecting to the database in PDO constructor.
 * Will lazy initialize DB connection on first request.
 *
 * @author Matthias Zobrist <matthias.zobrist@zepi.org>
 * @copyright Copyright (c) 2011 zepi (http://www.zepi.org)
 */
class Pdo
{
  /**
   * @var \PDO
   */
  protected $_pdo;
  
  /**
   * @var string
   */
  protected $_dsn;
  
  /**
   * @var string
   */
  protected $_username;
  
  /**
   * @var string
   */
  protected $_password;
  
  /**
   * @var integer
   */
  protected $_connectionTime;
  
  /**
   * @var array
   */
  protected $_options = array();
  
  /**
   * @var boolean
   */
  protected $_dryRun;
  
  /**
   * Constructs the object.
   * Parameters are the same as in the original PDO class.
   *
   * @param string $_dsn
   * @param string $_username
   * @param string $_password
   * @param array $_options
   * @param boolean $dryRun
   * @return null
   */
  public function __construct($dsn, $username = '', $password = '', $options = array(), $dryRun = false)
  {
    $this-> _dsn = $dsn;
    $this-> _username = $username;
    $this-> _password = $password;
    $this-> _options = $options;
    $this-> _dryRun = $dryRun;
  }
  /**
   * Delegates all method calls to the PDO object, lazy initializing it on demand.
   *
   * @param string $method
   * @param array $parameters
   * @return mixed
   */
  public function __call($method, $parameters)
  {
    if ($this-> _connectionTime < (time() - 300)) {
      $this-> _pdo = null;
    }
    
    if ($this-> _pdo === null) {
      try {
        $this-> _pdo = new \PDO($this-> _dsn, $this-> _username, $this-> _password, $this-> _options);
      } catch (\Exception $e) {
        \Blogwerk\Output::output('Cannot connect to database server!', 'error');
        exit;
      }
      
      $this-> _connectionTime = time();
    }
    
    if ($method === 'exec' && $this->_dryRun) {
      \Blogwerk\Output::output('Cachted an execution of the method "exec" because dryRun is true.', 'dry');
      return;
    }
    
    return call_user_func_array(array($this-> _pdo, $method), $parameters);
  }
}
