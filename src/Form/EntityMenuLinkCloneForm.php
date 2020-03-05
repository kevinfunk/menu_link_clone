<?php

namespace Drupal\menu_link_clone\Form;

use Drupal\Core\Form\FormStateInterface;
use Drupal\entity_clone\Form\EntityCloneForm;
use Drupal\menu_link_content\Entity\MenuLinkContent;
use Drupal\Core\Url;
use Drupal\Component\Uuid\Php;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\TranslationManager;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Drupal\Core\Messenger\Messenger;
use Drupal\Core\StringTranslation\StringTranslationTrait;

/**
 * Provides a menu link clone form.
 */
class EntityMenuLinkCloneForm extends EntityCloneForm {

  use StringTranslationTrait;

  /**
   * Generate unique id(uuid).
   *
   * @var \Drupal\administerusersbyrole\Services\AccessManagerInterface
   */
  protected $uuidinterface;

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('entity_type.manager'),
      $container->get('current_route_match'),
      $container->get('string_translation'),
      $container->get('event_dispatcher'),
      $container->get('messenger'),
      $container->get('uuid')
    );
  }

  /**
   * Constructs a clone menu lines with parent construct.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entity_type_manager
   *   The entity type manager.
   * @param \Drupal\Core\Routing\RouteMatchInterface $route_match
   *   The route match service.
   * @param \Drupal\Core\StringTranslation\TranslationManager $string_translation
   *   The string translation manager.
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The event dispatcher service.
   * @param \Drupal\Core\Messenger\Messenger $messenger
   *   The messenger service.
   * @param \Drupal\Component\Uuid\Php $uuid_interface
   *   Generate unique id(uuid).
   */
  public function __construct(EntityTypeManagerInterface $entity_type_manager, RouteMatchInterface $route_match, TranslationManager $string_translation, EventDispatcherInterface $eventDispatcher, Messenger $messenger, Php $uuid_interface) {
    parent::__construct($entity_type_manager, $route_match, $string_translation, $eventDispatcher, $messenger);
    $this->uuidinterface = $uuid_interface;
  }

  /**
   * {@inheritdoc}
   *
   * @return array
   *   A render form array for a page.
   */
  public function buildForm(array $form, FormStateInterface $form_state) {
    $form = parent::buildForm($form, $form_state);
    $form['clone_links'] = [
      '#type'          => 'checkbox',
      '#title'         => $this->t('Clone with Links'),
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
        $this->messenger->addMessage($this->t('Self(Admin) created menu links are not available in @label menu.', ['@label' => $this->entity->label()]));
      }
      else {
        $result = $this->cloneMenuLinks($sourceMenuId, $destMenuId);
        if ($result) {
          $this->messenger->addMessage($this->t('Self(Admin) created Links are cloned successfully for @label menu.', ['@label' => $form_state->getValue('label')]));
        }
        else {
          $this->messenger->addMessage($this->t('Unsuccessfull to clone links for @label, Please try again or contact to site admin.', ['@label' => $form_state->getValue('label')]));
        }
      }
    }
    $response = Url::fromRoute('entity.menu.collection');
    $form_state->setRedirectUrl($response);
  }

  /**
   * Clone menu items.
   *
   * @param object $source_menu_name
   *   Source menu name from we need to clone the menu items.
   * @param string $target_menu_name
   *   Destination menu name to clone the menu items.
   *
   * @return bool
   *   Return the True or False.
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
   * Get menu items ids.
   *
   * @param string $menu_id
   *   Menu name for which we can get there items.
   *
   * @return array
   *   Retrun array with there menu items tree.
   */
  protected function getMenuItems($menu_id) {
    $result = [];
    $storage = $this->entityTypeManager->getStorage('menu_link_content');
    $menuLinkItems = $storage->loadByProperties(['menu_name' => $menu_id]);
    if (!empty($menuLinkItems)) {
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
   *
   * @return bool
   *   Return True and False.
   */
  protected function menuLinksAvailabilityCheck($source_menu_id) {
    $result = FALSE;
    if (!empty($source_menu_id)) {
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
   *
   * @return array
   *   Retrun array with there menu items tree.
   */
  protected function resetLinkItems($menu_links_object_multiple) {
    $result = [];
    foreach ($menu_links_object_multiple as $link) {
      if (!empty($link)) {
        $linkArray = $link->toArray();
        $linkData = [];
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
   *
   * @return array
   *   Retrun array with there menu items tree.
   */
  protected function setUuidForMenuItems($menu_links_object_multiple, $target_menu_name) {
    $uuid_map = [];
    // Create an uuid mapping table.
    foreach ($menu_links_object_multiple as $id => $menu) {
      $uuid = $menu['uuid']['value'];
      // Assume uuid is not duplicated here.
      $new_uuid = $this->uuidinterface->generate();
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
   *
   * @return array
   *   Retrun array with there menu items tree.
   */
  protected function createMenuLinkClone($menu_links_object_multiple) {
    $result = FALSE;
    foreach ($menu_links_object_multiple as $id => $menu) {
      if (isset($id) && !empty($id)) {
        unset($menu['revision_id']);
        unset($menu['bundle']);
        $save_menu = MenuLinkContent::create($menu);
        $save_menu->save();
        if ($save_menu) {
          $result = TRUE;
        }
      }
    }
    return $result;
  }

}
