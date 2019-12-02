<?php

namespace Drupal\menu_link_clone\Tests;

use Drupal\simpletest\WebTestBase;
use Drupal\menu_link_content\Entity\MenuLinkContent;

/**
 * Create a menu and test a link clone.
 *
 * @group menu_link_clone
 */
class MenuLinkCloneTest extends WebTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = ['entity_clone', 'menu_ui', 'menu_link_clone'];

  /**
   * Permissions to grant admin user.
   *
   * @var array
   */
  protected $permissions = [
    'clone menu entity',
    'administer menu',
  ];

  /**
   * An administrative user with permission to configure menus settings.
   *
   * @var \Drupal\user\UserInterface
   */
  protected $adminUser;

  /**
   * Sets the test up.
   */
  protected function setUp() {
    parent::setUp();

    $this->adminUser = $this->drupalCreateUser($this->permissions);
    $this->drupalLogin($this->adminUser);
  }

  /**
   * Test menu link clone.
   */
  public function testMenuLinkClone() {

    $menus = \Drupal::entityTypeManager()
      ->getStorage('menu')
      ->loadByProperties([
        'id' => 'account',
      ]);
    $menu = reset($menus);

    //Custom menu link
    $menu_link = MenuLinkContent::create([
      'title' => 'Test',
      'link' => ['uri' => 'internal:/'],
      'menu_name' => 'account',
      'expanded' => TRUE,
      'weight' => 0,
    ]);
    $menu_link->save();

    $edit = [
      'label' => 'Test menu cloned',
      'id' => 'test_menu_cloned',
      'clone_links' => TRUE,
    ];
    $this->drupalPostForm('entity_clone/menu/' . $menu->id(), $edit, t('Clone'));

    $menus = \Drupal::entityTypeManager()
      ->getStorage('menu')
      ->loadByProperties([
        'id' => $edit['id'],
      ]);
    $menu = reset($menus);
    $this->assertTrue($menu, 'Test menu cloned found in database.');

    $menuLink = \Drupal::entityTypeManager()
      ->getStorage('menu_link_content')
      ->loadByProperties([
        'menu_name' => $edit['id'],
      ]);
    $menuLink = reset($menuLink);
    $this->assertTrue($menuLink, "Menu link found in database for Test menu cloned");
  }

}
