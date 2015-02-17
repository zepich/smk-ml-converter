<?php
/**
 * Post converter to convert each post
 * 
 * @package Blogwerk\Converter
 * @author Matthias Zobrist <matthias.zobrist@zepi.net>
 * @copyright Copyright (c) 2015 zepi (http://www.zepi.net)
 */

namespace Blogwerk\Converter;

use \Blogwerk\CliCore;
use \Blogwerk\Configuration;
use \Blogwerk\Entity\TranslationData;
use \Blogwerk\Output;

/**
 * Post converter to convert each post
 * 
 * @author Matthias Zobrist <matthias.zobrist@zepi.net>
 * @copyright Copyright (c) 2015 zepi (http://www.zepi.net)
 */
class Post
{
  const DEFAULT_LANGUAGE = '__DEFAULT__';
  
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
   * Loops trough all posts and reassign all posts to the correct language with Polylang.
   */
  public function convertPosts()
  {
    $allTranslationData = $this->_getTranslationData();
    
    // Start the progress monitor
    Output::output('Looping trough all translation data and convert every post.', 'info');
    $bar = Output::startProgressBar(count($allTranslationData));
    
    // Get all available languages
    $availableLanguages = pll_languages_list();
    
    $notTranslated = array();
    $dryRunCounter = 0;
    foreach ($allTranslationData as $trid => $translationData) {
      $bar->advance();
      
      $postIds = array();
      
      // Loop trough all language data and convert each post
      foreach ($translationData->getLanguageData() as $languageData) {
        $language = $languageData['language'];
        $postId = $languageData['postId'];
        
        // Set the default language if the post hasn't any language data
        if ($language === self::DEFAULT_LANGUAGE) {
          $language = pll_default_language();
        }
        
        // If the language is not available
        if (!in_array($language, $availableLanguages)) {
          $notTranslated[] = $postId;
        }
        
        // Set the post language
        if (!$this->_cliCore->isDryRun()) {
          pll_set_post_language($postId, $language);
        } else {
          $dryRunCounter++;
        }
        
        // Add the post id to our postIds array
        $postIds[$language] = $postId;
      }
      
      // Save the translation data for this trid
      if (!$this->_cliCore->isDryRun()) {
        pll_save_post_translations($postIds);
      } else {
        $dryRunCounter++;
      }
    }

    $bar->finish();
    Output::outputLine();
    
    // If there are posts which weren't translated then we have to print the ids here
    $countNotTranslated = count($notTranslated);
    if ($countNotTranslated > 0) {
      Output::output($countNotTranslated . ' posts could not converted to Polylang because the language was not available.', 'error');
      Output::output('Ids of not translated posts: ' . implode(', ', $notTranslated), 'error');
    }
    
    // If the dry mode is active we print here the number of actions we haven't executed.
    if ($dryRunCounter > 0) {
      Output::output($dryRunCounter . ' actions were not executed because of the dry mode.', 'dry');
    }
    
  }
  
  /**
   * Returns an array with all TranslationData which are available for the instance.
   * 
   * @return array
   */
  protected function _getTranslationData()
  {
    // Load the raw translation data from the database
    $translationTable = $this->_configuration->get('dbconverter', 'oldPrefix') . 'translations';
    $postsTable = $this->_configuration->get('dbconverter', 'newPrefix') . 'posts';
    $query = 'SELECT t.*, p.post_lang 
              FROM ' . $translationTable . ' as t
              LEFT JOIN ' . $postsTable . ' p ON t.post_id = p.ID';
    $data = $this->_pdo->query($query)->fetchAll();
    
    // Start the progressbar
    Output::output('Generate translation data structure...', 'info');
    $bar = Output::startProgressBar(count($data));
    
    // Create the translation data array
    $translationData = array();
    foreach ($data as $row) {
      $trid = $row['trid'];
      
      // If the trid is not set yet create a new translation data object
      if (!isset($translationData[$trid])) {
        $translationData[$trid] = new TranslationData($trid);
      }
      
      $language = $row['post_lang'];
      if (trim($language) == '') {
        $language = self::DEFAULT_LANGUAGE;
      }
      
      // Add the language
      $translationData[$trid]->addLanguage(
        $language, 
        $row['post_id']
      );
      
      $bar->advance();
    }
    
    $bar->finish();
    Output::outputLine();
    
    return $translationData;
  }
}
