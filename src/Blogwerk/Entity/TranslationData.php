<?php
/**
 * Entity TranslationData to representate the link between
 * the different posts in the different languages.
 * 
 * @package Blogwerk\Entity
 * @author Matthias Zobrist <matthias.zobrist@zepi.net>
 * @copyright Copyright (c) 2015 zepi (http://www.zepi.net)
 */
 
namespace Blogwerk\Entity;

/**
 * Entity TranslationData to representate the link between
 * the different posts in the different languages.
 * 
 * @author Matthias Zobrist <matthias.zobrist@zepi.net>
 * @copyright Copyright (c) 2015 zepi (http://www.zepi.net)
 */
class TranslationData
{
  /**
   * @var integer
   */
  protected $_id;
  
  /**
   * @var array
   */
  protected $_languageData = array();
  
  /**
   * Constructs the object
   * 
   * @param integer $id
   */
  public function __construct($id)
  {
    $this->_id = $id; 
  }
  
  /**
   * Returns true if the given language and post id is saved
   * in the translation data.
   * 
   * @param string $language
   * @param integer $id
   * @return boolean
   */
  public function hasLanguage($language, $id)
  {
    foreach ($this->_languageData as $data) {
      if ($data['language'] === $language && $data['postId'] === $id) {
        return true;
      }
    }
    
    return false;
  }
  
  /**
   * Adds the language and id combination to the translation data.
   * 
   * @param string $language
   * @param integer $id
   */
  public function addLanguage($language, $id)
  {
    if ($this->hasLanguage($language, $id)) {
      return;
    }
    
    $this->_languageData[] = array('language' => $language, 'postId' => $id);
  }
  
  /**
   * Returns all language data (combination of language code and post id).
   * 
   * @return array
   */
  public function getLanguageData()
  {
    return $this->_languageData;
  }
}
