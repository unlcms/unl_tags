<?php

namespace Drupal\unl_tags\Form;

use Drupal\Core\Form\FormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\Core\Url;
use Drupal\taxonomy\Entity\Term;
use Drupal\taxonomy\Entity\Vocabulary;

/**
 * Form to import set of UNL tags.
 */
class ImportTags extends FormBase {

  /**
   * {@inheritdoc}
   */
  public function getFormId() {
    return 'unl_tags_import';
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form['submit'] = array(
      '#type' => 'submit',
      '#value' => t('Import Tags'),
    );

    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state) {
    parent::validateForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    $url = 'https://raw.githubusercontent.com/unl/tags/master/data/flat.json';
    
    $tags = file_get_contents($url);
    
    if (!$tags) {
      drupal_set_message($this->t('Unable to get the tags.', [], 'error'));
      $form_state->setRedirect('unl_tags.import_tags');
      return $form;
    }
    
    $tags = json_decode($tags, true);
    
    if (!$tags || empty($tags)) {
      drupal_set_message($this->t('Tags could not be json_decoded', [], 'error'));
      $form_state->setRedirect('unl_tags.import_tags');
      return $form;
    }
    
    if (!$vocab = Vocabulary::load('unl_tags')) {
      // Create it.
      $vocab = Vocabulary::create([
        'vid' => 'unl_tags',
        'description' => 'UNL controlled vocabulary',
        'name' => 'UNL Tags',
      ]);
      $vocab->save();
    }
    
    $added = 0;
    $updated = 0;
    $removed = 0;

    $existing = \Drupal::entityTypeManager()->getStorage('taxonomy_term')->loadByProperties(['vid'=>$vocab->id()]);

    foreach ($existing as $term) {
      $machineName = $machine_name = $term->get('machine_name')->first()->getValue()['value'];
      if (!isset($tags[$machineName])) {
        $term->delete();
        $removed++;
      }
    }
    
    foreach ($tags as $machineName=>$humanName) {
      $term_existing = taxonomy_term_machine_name_load($machineName, $vocab->id());
      // TODO: load by machine name instead
      
      if (count($term_existing) == 0) {
        $new_term = Term::create(['name' => $humanName, 'vid' => $vocab->id(), 'machine_name'=>$machineName]);
        $new_term->save();
        $added++;
      } 
      else {
        if (is_array($term_existing)) {
          $term = $term_existing[0];
        } 
        else {
          $term = $term_existing;
        }
        
        if ($term->name->first()->getValue()['value'] != $humanName) {
          //Update the human name
          $term->name = $humanName;
          $term->save();
          $updated++;
        }
      }
      
      // TODO: what about COB (college) vs COB (building)
    }
    
    drupal_set_message(t('Tags have been imported. @added added, @updated updated, and @removed removed.', ['@added' => $added, '@updated' => $updated, '@removed' => $removed]));

    return $form_state->setRedirectUrl($vocab->toUrl());
  }
}
