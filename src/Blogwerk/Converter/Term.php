<?php
/**
 * Term converter to convert terms and reassign posts to
 * the new correct term.
 * 
 * @package Blogwerk\Converter
 * @author Matthias Zobrist <matthias.zobrist@zepi.net>
 * @copyright Copyright (c) 2015 zepi (http://www.zepi.net)
 */
 
namespace Blogwerk\Converter;

use \Blogwerk\CliCore;
use \Blogwerk\Configuration;
use \Blogwerk\Entity\TranslationData;
use \Blogwerk\Entity\TermNode;
use \Blogwerk\Output;

/**
 * Term converter to convert terms and reassign posts to
 * the new correct term.
 * 
 * @author Matthias Zobrist <matthias.zobrist@zepi.net>
 * @copyright Copyright (c) 2015 zepi (http://www.zepi.net)
 */
class Term
{
  /**
   * @var \Blogwerk\CliCore
   */
  protected $_cliCore;
  
  /**
   * @var \zepi\Wrapper\Pdo
   */
  protected $_pdo;
  
  /**
   * @var \Blogwerk\Configuration
   */
  protected $_configuration;
  
  /**
   * @var \eczConsoleProgressbar
   */
  protected $_bar;
  
  /**
   * @var integer
   */
  protected $_dryRunCounter = 0;
  
  /**
   * Constructs the object
   * 
   * @param \Blogwerk\CliCore $cliCore
   * @param \zepi\Wrapper\Pdo $pdo
   * @param \Blogwerk\Configuration $configuration
   */
  public function __construct(CliCore $cliCore, \zepi\Wrapper\Pdo $pdo, Configuration $configuration)
  {
    $this->_cliCore = $cliCore;
    $this->_pdo = $pdo;
    $this->_configuration = $configuration;
  }
  
  /**
   * This is a filter to manipulate the term query in polylang. We need
   * all terms and not only the translated.
   * 
   * @param array $taxonomies
   * @return array
   */
  public function resetTaxonomiesForPolylang($taxonomies)
  {
    return array();
  }
  
  /**
   * Converts terms of all registred taxonomies to multilanguage terms.
   */
  public function convertTermsOfTaxonomies()
  {
    // Add a filter which will reset all taxonomies to nothing
    add_filter('pll_get_taxonomies', array($this, 'resetTaxonomiesForPolylang'));
    
    /**
     * 
     * 1. Get all taxonomies
     * 2. Translate all terms of each taxonomy (create new terms and save the id's)
     * 3. Loop trough all taxonomies, get all posts and move the posts to the right new term
     * 
     */
     
    $taxonomies = get_taxonomies();
    foreach ($taxonomies as $taxonomy) {
      Output::output('Convert all terms of the taxonomy "' . $taxonomy . '"...', 'info');
      
      // Convert all terms into multiple translated terms
      $terms = $this->_convertTaxonomyAndTerms($taxonomy);
       
      // Convert all posts to the new terms
      $this->_convertPosts($taxonomy, $terms);
    }
  }
  
  /**
   * Converts all terms for the given taxonomy
   * 
   * @param string $taxonomy
   * @return array
   */
  protected function _convertTaxonomyAndTerms($taxonomy)
  {
    // Get the terms
    $terms = get_terms($taxonomy, 'hide_empty=0');
    
    // Build tree
    Output::output('Create the term tree...', 'info');
    $this->_bar = Output::startProgressBar(count($terms));
    $tree = $this->_generateTree($terms);
    $this->_bar->finish();
    Output::outputLine();
    
    $this->_dryRunCounter = 0;
    
    // Convert the terms into multilanguage terms
    Output::output('Convert each term into a multilanguage term...', 'info');
    $this->_bar = Output::startProgressBar(count($terms));
    $this->_convertTerms($taxonomy, $tree);
    $this->_bar->finish();
    Output::outputLine();
  }
  
  /**
   * Generates a term tree with the given terms.
   * 
   * @param array $terms
   * @param integer $parent
   * @param \Blogwerk\Entity\TermNode $parentTermNode
   * @return array 
   */
  protected function _generateTree($terms, $parent = 0, TermNode $parentTermNode = null)
  {
    $nodes = array();
    
    foreach ($terms as $term) {
      if ($term->parent == $parent && $term->term_id > 0) {
        // Create a term node object
        $termNode = new TermNode($term->term_id, $term, $parentTermNode);
        
        // Get the children for this term
        $children = $this->_generateTree($terms, $term->term_id, $termNode);
        $termNode->setChildren($children);
        
        // Add the term node to the nodes array
        $nodes[] = $termNode;
        
        $this->_bar->advance();
      }
    }
    
    return $nodes;
  }
  
  /**
   * Loops trough the whole term tree and converts each term to a multilanguage term.
   * The original term will be assigned to the default language. We will create
   * new terms for each additional language.
   * 
   * @param string $taxonomy
   * @param array $tree
   * @param \Blogwerk\Entity\TermNode
   */
  protected function _convertTerms($taxonomy, $tree, TermNode $parentTermNode = null)
  {
    $defaultLanguage = pll_default_language();
    
    foreach ($tree as $node) {
      $translations = $this->_parseSmkTranslationString($node->getTermData()->name);
      
      // If the name isn't a translated string we do not work with this term
      if (!$translations) {
        continue;
      }
      
      $languageIdData = array();
      foreach ($translations as $languageCode => $translation) {
        // If the translation for this language is empty we use the
        // translation for the default language
        if ($translation == '') {
          $translation = $translations[$defaultLanguage];
        }

        // If the language is the default language we update the term
        if ($languageCode === $defaultLanguage) {
          $termId = $node->getTermId();
          
          if (!$this->_cliCore->isDryRun()) {
            $result = wp_update_term($termId, $taxonomy, array(
              'name' => $translation
            ));
          } else {
            $this->_dryRunCounter++;
          }
        } else {
          $parentId = null;
          if ($parentTermNode !== null) {
            $parentId = $parentTermNode->getLanguageTermId($languageCode);
          }
          
          // If the language code not is the default language we add a new term
          if (!$this->_cliCore->isDryRun()) {
            $termData = wp_insert_term($translation, $taxonomy, array(
              'name' => $translation,
              'description' => $node->getTermData()->description,
              'parent' => $parentId
            ));
            
            if ($termData instanceof \WP_Error) {
              $termId = 0;
            } else {
              $termId = $termData['term_id'];
            }
          } else {
            $this->_dryRunCounter++;
          }
        }
        
        // We update the term and set the language for the ther
        if (!$this->_cliCore->isDryRun()) {
          pll_set_term_language($termId, $languageCode);
        } else {
          $this->_dryRunCounter++;
        }
        
        $node->addLanguageTermId($languageCode, $termId);
        
        $languageIdData[$languageCode] = $termId;
      }
      
      // Save the translation data for the term
      if (!$this->_cliCore->isDryRun()) {
        pll_save_term_translations($languageIdData);
      } else {
        $this->_dryRunCounter++;
      }
      
      // Convert all children
      if ($node->hasChildren()) {
        $this->_convertTerms($taxonomy, $node->getChildren(), $node);
      }
      
      $this->_bar->advance();
    }
  }

  /**
   * Parses an SMK Translation string and returns an array
   * with all possible translations.
   * 
   * @param string $string
   * @return array
   */
  protected function _parseSmkTranslationString($string)
  {
    /**
     * Check if the string is a translated string 
     */
    $translations = array();
    if (preg_match('#\[(de|fr|it|en)\](.*?)\[/\1\]#', $string)) {
      preg_match_all('#\[(de|fr|it|en)\](.*?)\[/\1\]#', $string, $matches, PREG_SET_ORDER);
    
      foreach ($matches as $match) {
        $translations[$match[1]] = trim($match[2]);
      }
      
      return $translations;
    }

    return false;
  }
}
