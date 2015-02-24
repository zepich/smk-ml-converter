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
   * @var integer
   */
  protected $_errorCounter = 0;
  
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
   * Converts terms of all registred taxonomies to multilanguage terms.
   */
  public function convertTermsOfTaxonomies()
  {
    // Load the excluded taxonomies from the configuration file
    $excludedTaxonomiesString = $this->_configuration->get('termsconverter', 'excludedTaxonomies');
    $excludedTaxonomies = explode(',', $excludedTaxonomiesString);
     
    $taxonomies = get_taxonomies();
    foreach ($taxonomies as $taxonomy) {
      Output::outputLine();
      
      Output::output('Taxonomy: "' . $taxonomy . '"', 'main');
      
      // If this is a excluded taxonomy we do nothing and continue
      if (in_array($taxonomy, $excludedTaxonomies)) {
        Output::output('The taxonomy "' . $taxonomy . '" is excluded by the configuration file.', 'notice');
        continue;
      }
      
      // Convert all terms into multiple translated terms
      $termTree = $this->_convertTaxonomyAndTerms($taxonomy);
       
      $this->_resetCounter();
       
      // Convert all posts to the new terms
      Output::output('Search all posts for all terms and reassign them...', 'info');
      $this->_bar = Output::startProgressBar(count($termTree));
      $this->_convertPosts($taxonomy, $termTree);
      $this->_bar->finish();
      Output::outputLine();
      
      // Analyse the counter
      $this->_analyseErrorCounter($taxonomy, 'convert posts to term');
      $this->_analyseDryRunCounter($taxonomy, 'convert posts to term');
    }

    Output::outputLine();
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
    $terms = $this->_getTerms($taxonomy);
    
    // If we have no terms for the taxonomy we have nothing to do...
    if (count($terms) == 0) {
      Output::output('There are no terms for the taxonomy "' . $taxonomy . '".', 'info');
      return false;
    }
    
    $this->_resetCounter();
    
    // Build the term tree tree
    Output::output('Create the term tree...', 'info');
    $this->_bar = Output::startProgressBar(count($terms));
    $tree = $this->_generateTree($terms);
    $this->_bar->finish();
    Output::outputLine();
    
    // Analyse the counter
    $this->_analyseErrorCounter($taxonomy, 'generate tree');
    $this->_analyseDryRunCounter($taxonomy, 'generate tree');
    
    // Count all nodes in the term tree
    $nodeCount = $this->_countTreeNodes($tree);

    // If the tree is empty we have nothing to do for this taxonomy
    if ($nodeCount == 0) {
      Output::output('The generated tree for the taxonomy "' . $taxonomy . '" is empty.', 'info');
      return false;
    }
    
    $this->_resetCounter();
    
    // Convert the terms into multilanguage terms
    Output::output('Convert each term into a multilanguage term...', 'info');
    $this->_bar = Output::startProgressBar($nodeCount);
    $this->_convertTerms($taxonomy, $tree);
    $this->_bar->finish();
    Output::outputLine();
    
    // Analyse the counter
    $this->_analyseErrorCounter($taxonomy, 'convert terms');
    $this->_analyseDryRunCounter($taxonomy, 'convert terms');

    return $tree;
  }
  
  /**
   * Returns all terms for the given taxonomy
   * 
   * @param string $taxonomy
   * @return array
   */
  protected function _getTerms($taxonomy)
  {
    $termsTable = $this->_configuration->get('dbconverter', 'newPrefix') . 'terms';
    $termsTaxonomyTable = $this->_configuration->get('dbconverter', 'newPrefix') . 'term_taxonomy';
    $query = 'SELECT t.name, t.slug, tt.* '
         . 'FROM ' . $termsTable . ' AS t '
         . 'INNER JOIN ' . $termsTaxonomyTable . ' tt '
         . 'ON t.term_id = tt.term_id '
         . 'WHERE tt.taxonomy = \'' . $taxonomy . '\' '
         . 'ORDER BY tt.parent';
    $data = $this->_pdo->query($query)->fetchAll(\PDO::FETCH_CLASS, 'stdClass');
    
    return $data;
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
      $termNode = null;
      
      if ($term->parent == $parent && $term->term_id > 0) {
        // If the term is translated we would search for the term node
        // of the same term in another language and use this term node.
        $termLanguage = pll_get_term_language($term->term_id);
        if ($termLanguage !== false) {
          $termTranslationData = $this->_pll_get_term_translations($term->term_id);
          
          $termNode = $this->_searchExistingTermNode($nodes, $termTranslationData);
        }
        
        // Create a term node object if there is not an term node yet
        if ($termNode === null || $termNode === false) {
          $termNode = new TermNode(intval($term->term_id), $term, $parentTermNode);
        }
        
        // Get the children for this term
        $children = $this->_generateTree($terms, intval($term->term_id), $termNode);
        $termNode->setChildren($children);
        
        // If the term is translated fill the translation information for it
        if ($termLanguage !== false) {
          $termNode->addLanguageTermId($termLanguage, intval($term->term_id));
        }
        
        // Add the term node to the nodes array
        $nodes[] = $termNode;
        
        $this->_bar->advance();
      }
    }
    
    return $nodes;
  }

  /**
   * Returns the array with the translations data for the given term id
   * 
   * @param integer $termId
   * @return array
   */
  protected function _pll_get_term_translations($termId)
  {
    global $polylang;
    
    if (!isset($polylang)) {
      return array();
    }
    
    return $polylang->get_translations('term', $termId);
  }
  
  /**
   * Iterates trough all created nodes and searches for one of the 
   * translated nodes. Returns the found node or false if no node were
   * found.
   * 
   * @param array $nodes
   * @param array $termTranslationData
   * @return boolean|TermNode 
   */
  protected function _searchExistingTermNode($nodes, $termTranslationData)
  {
    foreach ($nodes as $node) {
      foreach ($termTranslationData as $languageCode => $termId) {
        if ($node->getTermId() == $termId) {
          return $node;
        }
      }
    }
    
    return false;
  }
  
  /**
   * Counts all nodes in the term tree and returns the number of nodes
   * 
   * @param array $tree
   * @return integer
   */
  protected function _countTreeNodes($tree)
  {
    $counter = 0;
    
    foreach ($tree as $node) {
      $counter++;
      
      if ($node->hasChildren()) {
        $counter += $this->_countTreeNodes($node->getChildren());
      }
    }
    
    return $counter;
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
      $mainTermLanguage = $this->_detectMainTermLanguage($node->getTermData()->name, $node->getTermData()->slug, $defaultLanguage);
      
      $languageIdData = array();
      foreach ($translations as $languageCode => $translation) {
        // If the node has the translation for this language we don't need to do anything
        if ($node->hasLanguageTermId($languageCode)) {
          $node->addLanguageTermId($languageCode, $node->getTermId());
          $languageIdData[$languageCode] = $node->getTermId();
          
          continue;
        }
        
        // If the translation for this language is empty we use the
        // translation for the default language
        if ($translation == '') {
          $translation = $this->_getBestTranslation($defaultLanguage, $translations);
        }
        
        // If the translation is written the same as in the default language we need
        // to change the slug for the new term
        $slug = $this->_searchSlug($node->getTermData()->term_taxonomy_id, $languageCode);
        if ($slug == '') {
          $slug = $this->_getBestSlug($defaultLanguage, $translation, $translations, $languageCode, $taxonomy);
        }

        // If the language is the default language we update the term
        if ($languageCode === $mainTermLanguage) {
          $termId = $node->getTermId();
          
          // Update the term or count the dry run
          if (!$this->_cliCore->isDryRun()) {
            $termData = wp_update_term($termId, $taxonomy, array(
              'name' => $translation
            ));
          } else {
            $this->_dryRunCounter++;
            $termId = mt_rand(100, 1000000);
          }
        } else {
          // Get the parent id if the term node has a parent node
          $parentId = null;
          if ($parentTermNode !== null) {
            $parentId = $parentTermNode->getLanguageTermId($languageCode);
          }

          // If the language code not is the default language we add a new term
          if (!$this->_cliCore->isDryRun()) {
            $termData = wp_insert_term($translation, $taxonomy, array(
              'name' => $translation,
              'slug' => $slug,
              'description' => $node->getTermData()->description,
              'parent' => $parentId
            ));
            
            // If the function wp_insert_term returned an error,
            // we need to count the error and use 0 as term id
            if ($termData instanceof \WP_Error) {
              $this->_errorCounter++;
              $termId = 0;
            } else {
              $termId = $termData['term_id'];
            }
          } else {
            $this->_dryRunCounter++;
            $termId = mt_rand(100, 1000000);
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
  protected function _parseSmkTranslationString($string, $autoFill = true)
  {
    $translations = array();
    
    // Create the array with all the languages manually as fallback
    foreach (pll_languages_list() as $language) {
      $translations[$language] = false;
    }
    
    // Parse the string if this is a multilanguage string
    if (preg_match('#\[(de|fr|it|en)\](.*?)\[/\1\]#', $string)) {
      preg_match_all('#\[(de|fr|it|en)\](.*?)\[/\1\]#', $string, $matches, PREG_SET_ORDER);
    
      foreach ($matches as $match) {
        $translations[$match[1]] = trim($match[2]);
      }
    }
    
    // If there are values like false then we need the best available value
    if ($autoFill) {
      foreach ($translations as $languageCode => $value) {
        if ($value === false) {
          $translations[$languageCode] = $this->_getBestValue($translations, $string);
        }
      }
    }

    return $translations;
  }
  
  /**
   * Returns the main language for the given original string and the available translations
   * 
   * @param string $originalString
   * @param string $originalSlug
   * @param string $defaultLanguage
   * @return string
   */
  protected function _detectMainTermLanguage($originalString, $originalSlug, $defaultLanguage)
  {
    $realTranslations = $this->_parseSmkTranslationString($originalString);
    $availableLanguages = array();
    
    foreach ($realTranslations as $languageCode => $translation) {
      if ($translation != false) {
        $availableLanguages[$languageCode] = $translation;
      }
    }
    
    // If there is only one translation for the term we can use this
    // language as main term language
    if (count($availableLanguages) == 1) {
      return key($availableLanguages);
    }
    
    foreach ($availableLanguages as $languageCode => $translation) {
      $translatedSlug = sanitize_title($translation);
      
      if ($translatedSlug === $originalSlug) {
        return $languageCode;
      }
    }
    
    return $defaultLanguage;
  }
  
  /**
   * Returns the best available value for the translation string
   * 
   * @param array $translations
   * @param string $string
   * @return string
   */
  protected function _getBestValue($translations, $string)
  {
    $defaultLanguage = pll_default_language();
    
    if (isset($translations[$defaultLanguage]) && $translations[$defaultLanguage] !== false) {
      return $translations[$defaultLanguage];
    }
    
    foreach ($translations as $language => $value) {
      if ($value !== false) {
        return $value;
      }
    }
    
    return $string;
  }
  
  /**
   * Returns the best available translation for the given default language and available 
   * translations.
   * 
   * @param string $defaultLanguage
   * @param array $translation
   * @return string
   */
  protected function _getBestTranslation($defaultLanguage, $translations)
  {
    if (isset($translations[$defaultLanguage]) && $translations[$defaultLanguage] != '') {
      return $translations[$defaultLanguage];
    }
    
    foreach ($translations as $languageCode => $translation) {
      if ($translation != '') {
        return $translation;
      }
    }
    
    return 'IMPORT-ERROR - NO TRANSLATION FOUND';
  }
  
  /**
   * Returns an available slug for the given data
   * 
   * @param string $defaultLanguage
   * @param string $translation
   * @param array $translations
   * @param string $languageCode
   * @param string $taxonomy
   * @return string
   */
  protected function _getBestSlug($defaultLanguage, $translation, $translations, $languageCode, $taxonomy)
  {
    $slug = $translation;
    
    $bestTranslation = $this->_getBestTranslation($defaultLanguage, $translations);
    if ($bestTranslation === $translation) {
      $slug = $translation . '-' . $languageCode;
    }
    
    if (term_exists($slug, $taxonomy)) {
      $slug .= '-' . time();
    }
    
    return $slug;
  }
  
  /**
   * Returns the slug for the given term taxonomy id and language code
   * 
   * @param integer $termTaxonomyId
   * @param string $languageCode
   * @return string
   */
  protected function _searchSlug($termTaxonomyId, $languageCode)
  {
    $termTaxonomyMetaTable = $this->_configuration->get('dbconverter', 'oldPrefix') . 'term_taxonomymeta';
    $query = 'SELECT meta_value '
         . 'FROM ' . $termTaxonomyMetaTable . ' '
         . 'WHERE term_taxonomy_id = \'' . $termTaxonomyId . '\' '
         . 'AND meta_key = \'translated_slug_' . $languageCode . '\'';
    $data = $this->_pdo->query($query)->fetch();
    
    if (count($data) === 0) {
      return '';
    }

    return $data['meta_value'];
  }
  
  /**
   * Iterates trough the whole term tree. The function will then
   * load the posts for each term. The old term of each post will
   * be removed and the translated term will be added to the 
   * post.
   * 
   * @param string $taxonomy
   * @param array $tree
   */
  protected function _convertPosts($taxonomy, $tree)
  {
    if (!is_array($tree)) {
      return;
    }
    
    foreach ($tree as $node) {
      $postIds = $this->_getPostsForTermId($taxonomy, $node->getTermData()->term_id);
      
      // Increase the number of items in the progressbar
      $this->_bar->increaseMaximum(count($postIds));
      
      foreach ($postIds as $postId) {
        $postLanguage = pll_get_post_language($postId);
        $oldTermId = intval($node->getTermData()->term_id);
        $newTermId = $node->getLanguageTermId($postLanguage);
        
        if ($newTermId !== false && $newTermId != $oldTermId && !has_term($newTermId, $taxonomy, $postId)) {
          // Remove the old term
          if (!$this->_cliCore->isDryRun()) {
            wp_remove_object_terms($postId, $oldTermId, $taxonomy);
          } else {
            $this->_dryRunCounter++;
          }
          
          // Add the new term
          if (!$this->_cliCore->isDryRun()) {
            wp_add_object_terms($postId, $newTermId, $taxonomy);
          } else {
            $this->_dryRunCounter++;
          }
        }
        
        $this->_bar->advance();
      }
      
      // Iterate trough the children - if the node has any chlidren
      if ($node->hasChildren()) {
        $this->_convertPosts($taxonomy, $node->getChildren());
      }
      
      $this->_bar->advance();
    }
  }
  
  /**
   * Returns an array with all post ids for the given
   * taxonomy and term id
   * 
   * @param string $taxonomy
   * @param integer $termId
   * @return array
   */
  protected function _getPostsForTermId($taxonomy, $termId)
  {
    global $post;
    
    $args = array(
      'post_type' => 'any',
      'posts_per_page' => -1,
      'tax_query' => array(
        array(
          'taxonomy' => $taxonomy,
          'field'    => 'id',
          'terms'    => $termId,
        ),
      ),
    );
    $query = new \WP_Query($args);
    
    // If there are no posts available we return an empty array
    if (!$query->have_posts()) {
      return array();
    }
    
    // Get all the post ids
    $postIds = array();
    while ($query->have_posts()) {
      $query->the_post();
      
      $postIds[] = $post->ID;
    }
    
    // Destroy the query and the used space
    unset($query);
    
    return $postIds;
  }
  
  /**
   * Displays the error counter if the error counter is higher than zero.
   * 
   * @param string $taxonomy
   * @param string $action
   */
  protected function _analyseErrorCounter($taxonomy, $action)
  {
    if ($this->_errorCounter > 0) {
      Output::output('Taxonomy "' . $taxonomy . '": A total of ' . $this->_errorCounter . ' errors happend while we executed the action "' . $action . '".', 'error');
    }
  }
  
  /**
   * Analyses the dry run counter and displays it if needed
   * 
   * @param string $taxonomy
   * @param string $action
   */
  protected function _analyseDryRunCounter($taxonomy, $action)
  {
    if ($this->_dryRunCounter > 0) {
      Output::output('Taxonomy "' . $taxonomy . '": ' . $this->_dryRunCounter . ' actions for "' . $action . '" were not executed because of the dry mode.', 'dry');
    }
  }
  
  /**
   * Resets the counter
   */
  protected function _resetCounter()
  {
    $this->_dryRunCounter = 0;
    $this->_errorCounter = 0;
  }
}
