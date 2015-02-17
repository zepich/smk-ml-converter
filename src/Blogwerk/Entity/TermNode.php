<?php
/**
 * Entity TermNode to representate a TermNode in the term
 * tree.
 * 
 * @package Blogwerk\Entity
 * @author Matthias Zobrist <matthias.zobrist@zepi.net>
 * @copyright Copyright (c) 2015 zepi (http://www.zepi.net)
 */
 
namespace Blogwerk\Entity;

/**
 * Entity TermNode to representate a TermNode in the term
 * tree.
 * 
 * @author Matthias Zobrist <matthias.zobrist@zepi.net>
 * @copyright Copyright (c) 2015 zepi (http://www.zepi.net)
 */
class TermNode
{
  /**
   * @var integer
   */
  protected $_termId;
  
  /**
   * @var \StdClass
   */
  protected $_termData;
  
  /**
   * @var \Blogwerk\Entity\TermNode
   */
  protected $_parent = null;
  
  /**
   * @var array
   */
  protected $_children = array();
  
  /**
   * @var array
   */
  protected $_languageTermIds = array();
  
  /**
   * Constructs the object
   * 
   * @param integer $termId
   * @param \StdClass $termData
   * @param \Blogwerk\Entity\TermNode $parent
   */
  public function __construct($termId, \StdClass $termData, TermNode $parent = null)
  {
    $this->_termId = $termId;
    $this->_termData = $termData;
    $this->_parent = $parent;
  }
  
  /**
   * Returns the term id
   * 
   * @return integer
   */
  public function getTermId()
  {
    return $this->_termId;
  }
  
  /**
   * Returns the raw term data
   * 
   * @return \StdClass
   */
  public function getTermData()
  {
    return $this->_termData;
  }
  
  /**
   * Returns the parent term node
   * 
   * @return \Blogwerk\Entity\TermNode
   */
  public function getParent()
  {
    return $this->_parent;
  }
  
  /**
   * Returns true if the term node has children
   * 
   * @return boolean
   */
  public function hasChildren()
  {
    return (count($this->_children) > 0);
  }
  
  /**
   * Returns the children of the term node
   * 
   * @return array
   */
  public function getChildren()
  {
    return $this->_children;
  }
  
  /**
   * Sets the children of the term node
   * 
   * @param array $children
   */
  public function setChildren($children)
  {
    if (!is_array($children)) {
      return;
    }
    
    $this->_children = $children;
  }
  
  /**
   * Returns true if the term node has a term id for the given
   * language code
   * 
   * @param string $languageCode
   * @return boolean
   */
  public function hasLanguageTermId($languageCode)
  {
    return (isset($this->_languageTermIds[$languageCode]));
  }
  
  /**
   * Adds a language and term id combination to the term 
   * node.
   * 
   * @param string $languageCode
   * @param integer $termId
   */
  public function addLanguageTermId($languageCode, $termId)
  {
    $this->_languageTermIds[$languageCode] = $termId;
  }
  
  /**
   * Returns the term id for the given language code
   * 
   * @param string $languageCode
   * @return integer
   */
  public function getLanguageTermId($languageCode)
  {
    if (!isset($this->_languageTermIds[$languageCode])) {
      return false;
    }
    
    return $this->_languageTermIds[$languageCode];
  }
}
