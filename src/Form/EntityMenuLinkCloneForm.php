<?php

namespace Drupal\menu_link_clone\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_clone\Form\EntityCloneForm;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\Core\Url;

/**
 * Provides a menu link clone form.
 */
class EntityMenuLinkCloneForm extends EntityCloneForm {

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['clone_links'] = [
      '#type'          => 'checkbox',
      '#title'         => t('Clone with Links'),
      '#required'      => FALSE,
      '#default_value' => FALSE,
      '#weight'  => 0,
    ];
    return $form;
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state) {
    parent::submitForm($form, $form_state);
    $cloneLink = $form_state->getValue('clone_links');
    if ($cloneLink) {
      $sourceMenuId = $this->entity->id();
      $destMenuId = $form_state->getValue('id');
      $sourceMenuExistence = $this->menuLinksAvailabilityCheck($sourceMenuId);
      if (!$sourceMenuExistence) {
        $this->messenger->addMessage($this->stringTranslationManager->translate('Self(Admin) created menu links are not available in ' . $this->entity->label() . ' menu.'));
      }
      else {
        $result = $this->cloneMenuLinks($sourceMenuId, $destMenuId);
        if ($result) {
          $this->messenger->addMessage($this->stringTranslationManager->translate('Self(Admin) created Links are cloned successfully for ' . $form_state->getValue('label') . ' menu.'));
        }
        else {
          $this->messenger->addMessage($this->stringTranslationManager->translate('Unsuccessfull to clone links for ' . $form_state->getValue('label') . ', Please try again or contact to site admin.'));
        }
      }
    }
    $response = Url::fromUserInput('/admin/structure/menu');
    $form_state->setRedirectUrl($response);
  }

  /**
   * Clone menu items.
   *
   * @param object $source_menu_name
   *   Source menu name from we need to clone the menu items.
   * @param string $target_menu_name
   *   Destination menu name to clone the menu items.
   */
  protected function cloneMenuLinks($source_menu_name, $target_menu_name) {
    $result = FALSE;
    $menuLinkItems = $this->getMenuItems($source_menu_name);
    if ($menuLinkItems['status']) {
      $data = $this->resetLinkItems($menuLinkItems['items']);
      $data = $this->setUuidForMenuItems($data, $target_menu_name);
      $data = $this->createMenuLinkClone($data);
      if ($data) {
        $result = TRUE;
      }
    }
    return $result;
  }

  /**
   * Genereate UUID (Everytime gives you new unique ids.).
   */
  protected function genUuid() {
    $uuid_service = \Drupal::service('uuid');
    $uuid = $uuid_service->generate();
    return $uuid;
  }

  /**
   * Get menu items ids.
   *
   * @param string $menu_id
   *   Menu name for which we can get there items.
   */
  protected function getMenuItems($menu_id) {
    $result = [];
    $storage = \Drupal::entityManager()->getStorage('menu_link_content');
    $menuLinkItems = $storage->loadByProperties(['menu_name' => $menu_id]);
    if (isset($menuLinkItems) && !empty($menuLinkItems)) {
      $result['status'] = TRUE;
      $result['items'] = $menuLinkItems;
    }
    else {
      $result['status'] = FALSE;
      $result['items'] = [];
    }
    return $result;
  }

  /**
   * Check Menu Link items are availabe inside the menu.
   *
   * @param string $source_menu_id
   *   Menu name for which we need to check their items.
   */
  protected function menuLinksAvailabilityCheck($source_menu_id) {
    $result = FALSE;
    if (isset($source_menu_id) && !empty($source_menu_id)) {
      $menuLinkItems = $this->getMenuItems($source_menu_id);
      if ($menuLinkItems['status']) {
        $result = TRUE;
      }
    }
    return $result;
  }

  /**
   * Reset elements in menu item object.
   *
   * @param object $menu_links_object_multiple
   *   Menu Items Object.
   */
  protected function resetLinkItems($menu_links_object_multiple) {
    $result = [];
    foreach ($menu_links_object_multiple as $link) {
      if (!empty($link)) {
        $linkArray = $link->toArray();
        foreach ($linkArray as $key => $linkArrayItem) {
          $linkData[$key] = reset($linkArrayItem);
        }
        $result[$link->id()] = $linkData;
      }
    }
    return $result;
  }

  /**
   * Set UUID for menu items.
   *
   * @param object $menu_links_object_multiple
   *   Menu Items Object.
   * @param string $target_menu_name
   *   Menu Name for which we need to set UUID.
   */
  protected function setUuidForMenuItems($menu_links_object_multiple, $target_menu_name) {
    $uuid_map = [];
    // Create an uuid mapping table.
    foreach ($menu_links_object_multiple as $id => $menu) {
      $uuid = $menu['uuid']['value'];
      // Assume uuid is not duplicated here.
      $new_uuid = $this->genUuid();
      $uuid_map['menu_link_content:' . $uuid] = 'menu_link_content:' . $new_uuid;
      $menu_links_object_multiple[$id]['uuid'] = $new_uuid;
      unset($menu_links_object_multiple[$id]['id']);
      $menu_links_object_multiple[$id]['menu_name'] = $target_menu_name;
      if (isset($menu_links_object_multiple[$id]['parent']['value']) && !empty($menu_links_object_multiple[$id]['parent']['value'])) {
        $menu_links_object_multiple[$id]['parent']['value'] = $uuid_map[$menu_links_object_multiple[$id]['parent']['value']];
      }
    }
    return $menu_links_object_multiple;
  }

  /**
   * Create menu links.
   *
   * @param object $menu_links_object_multiple
   *   Menu Items Object.
   */
  protected function createMenuLinkClone($menu_links_object_multiple) {
    $result = FALSE;
    foreach ($menu_links_object_multiple as $id => $menu) {
      unset($menu['revision_id']);
      unset($menu['bundle']);
      $save_menu = MenuLinkContent::create($menu);
      $save_menu->save();
      if ($save_menu) {
        $result = TRUE;
      }
    }
    return $result;
  }

}
