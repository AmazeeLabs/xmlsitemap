<?php

/**
 * @file
 * Contains \Drupal\xmlsitemap\Tests\XmlSitemapNodeFunctionalTest.
 */

namespace Drupal\xmlsitemap\Tests;

use Drupal\Core\Language\LanguageInterface;

/**
 * Tests the generation of user links.
 */
class XmlSitemapNodeFunctionalTest extends XmlSitemapTestBase {

  /**
   * Modules to enable.
   *
   * @var array
   */
  public static $modules = array('node', 'xmlsitemap', 'taxonomy');

  /**
   * Nodes created during the test for testCron() method.
   *
   * @var array
   */
  protected $nodes = array();

  public static function getInfo() {
    return array(
      'name' => 'XML sitemap node',
      'description' => 'Functional tests for the XML sitemap module node entity.',
      'group' => 'XML sitemap',
    );
  }

  /**
   * {@inheritdoc}
   */
  public function setUp() {
    parent::setUp();

    if ($this->profile != 'standard') {
      $this->drupalCreateContentType(array(
      'type' => 'page',
      'name' => 'Basic page',
      'settings' => array(
          // Set proper default options for the page content type.
        'node' => array(
          'options' => array('promote' => FALSE),
          'submitted' => FALSE,
        ), )));
          $this->drupalCreateContentType(array('type' => 'article', 'name' => 'Article'));
    }

    $this->admin_user = $this->drupalCreateUser(array('administer nodes', 'bypass node access', 'administer content types', 'administer xmlsitemap', 'administer taxonomy'));
    $this->normal_user = $this->drupalCreateUser(array('create page content', 'edit any page content', 'access content', 'view own unpublished content'));

    // allow anonymous user to view user profiles
    $user_role = entity_load('user_role', DRUPAL_ANONYMOUS_RID);
    $user_role->grantPermission('access content');
    $user_role->save();

    xmlsitemap_link_bundle_enable('node', 'article');
    xmlsitemap_link_bundle_enable('node', 'page');
    $this->config->set('xmlsitemap_entity_taxonomy_vocabulary', 1);
    $this->config->set('xmlsitemap_entity_taxonomy_term', 1);
    $this->config->save();
    xmlsitemap_link_bundle_settings_save('node', 'page', array('status' => 1, 'priority' => 0.6, 'changefreq' => XMLSITEMAP_FREQUENCY_WEEKLY));

    // Add a vocabulary so we can test different view modes.
    $vocabulary = entity_create('taxonomy_vocabulary', array(
      'name' => 'Tags',
      'description' => $this->randomMachineName(),
      'vid' => 'tags',
      'langcode' => LanguageInterface::LANGCODE_NOT_SPECIFIED,
      'help' => '',
    ));
    $vocabulary->save();

    xmlsitemap_link_bundle_enable('taxonomy_term', 'tags');
    // Set up a field and instance.
    $field_name = 'tags';
    entity_create('field_storage_config', array(
      'name' => $field_name,
      'entity_type' => 'node',
      'type' => 'taxonomy_term_reference',
      'settings' => array(
        'allowed_values' => array(
          array(
            'vocabulary' => $vocabulary->vid,
            'parent' => '0',
          ),
        ),
      ),
      'cardinality' => '-1',
    ))->save();
    entity_create('field_instance_config', array(
      'field_name' => $field_name,
      'entity_type' => 'node',
      'bundle' => 'page',
    ))->save();

    entity_get_form_display('node', 'page', 'default')
        ->setComponent($field_name, array(
          'type' => 'taxonomy_autocomplete',
        ))
        ->save();

    // Show on default display and teaser.
    entity_get_display('node', 'page', 'default')
        ->setComponent($field_name, array(
          'type' => 'taxonomy_term_reference_link',
        ))
        ->save();
    entity_get_display('node', 'page', 'teaser')
        ->setComponent($field_name, array(
          'type' => 'taxonomy_term_reference_link',
        ))
        ->save();
  }

  public function testTagsField() {
    $this->drupalLogin($this->admin_user);
    $this->drupalGet('node/add/page');
    $title_key = 'title[0][value]';
    $body_key = 'body[0][value]';

    // Fill in node creation form and preview node.
    $edit = array();
    $edit[$title_key] = $this->randomMachineName(8);
    $edit[$body_key] = $this->randomMachineName(16);
    $edit['tags'] = 'tag1, tag2, tag3';
    $this->drupalPostForm('node/add/page', $edit, t('Save and publish'));

    $tags = entity_load_multiple('taxonomy_term');
    foreach ($tags as $tag) {
      $this->assertSitemapLinkValues('taxonomy_term', $tag->id(), array('status' => 0, 'priority' => 0.5, 'changefreq' => 0));
      $tag->delete();
    }

    xmlsitemap_link_bundle_settings_save('taxonomy_term', 'tags', array('status' => 1, 'priority' => 0.2, 'changefreq' => XMLSITEMAP_FREQUENCY_HOURLY));

    $this->drupalPostForm('node/add/page', $edit, t('Save and publish'));

    $tags = entity_load_multiple('taxonomy_term');
    foreach ($tags as $tag) {
      $this->assertSitemapLinkValues('taxonomy_term', $tag->id(), array('status' => 1, 'priority' => 0.2, 'changefreq' => XMLSITEMAP_FREQUENCY_HOURLY));
      $tag->delete();
    }
  }

  public function testNodeSettings() {
    $node = $this->drupalCreateNode(array('publish' => 0, 'uid' => $this->normal_user->id()));
    $this->assertSitemapLinkValues('node', $node->id(), array('access' => 1, 'status' => 1, 'priority' => 0.6, 'status_override' => 0, 'priority_override' => 0, 'changefreq' => XMLSITEMAP_FREQUENCY_WEEKLY));

    $this->drupalLogin($this->normal_user);
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertNoField('xmlsitemap[status]');
    $this->assertNoField('xmlsitemap[priority]');

    $edit = array(
      'title[0][value]' => 'Test node title',
      'body[0][value]' => 'Test node body',
    );
    $this->drupalPostForm('node/' . $node->id() . '/edit', $edit, t('Save'));
    $this->assertText('Basic page Test node title has been updated.');
    $this->assertSitemapLinkValues('node', $node->id(), array('access' => 1, 'status' => 1, 'priority' => 0.6, 'status_override' => 0, 'priority_override' => 0, 'changefreq' => XMLSITEMAP_FREQUENCY_WEEKLY));

    $this->drupalLogin($this->admin_user);
    $this->drupalGet('node/' . $node->id() . '/edit');
    $this->assertField('xmlsitemap[status]');
    $this->assertField('xmlsitemap[priority]');
    $this->assertField('xmlsitemap[changefreq]');
    $edit = array(
      'xmlsitemap[status]' => 1,
      'xmlsitemap[priority]' => 0.9,
      'xmlsitemap[changefreq]' => XMLSITEMAP_FREQUENCY_ALWAYS,
    );
    $this->drupalPostForm('node/' . $node->id() . '/edit', $edit, t('Save and keep published'));
    $this->assertText('Basic page Test node title has been updated.');
    $this->assertSitemapLinkValues('node', $node->id(), array('access' => 1, 'status' => 1, 'priority' => 0.9, 'status_override' => 1, 'priority_override' => 1, 'changefreq' => XMLSITEMAP_FREQUENCY_ALWAYS));

    $edit = array(
      'xmlsitemap[status]' => 'default',
      'xmlsitemap[priority]' => 'default',
    );
    $this->drupalPostForm('node/' . $node->id() . '/edit', $edit, t('Save and keep published'));
    $this->assertText('Basic page Test node title has been updated.');
    $this->assertSitemapLinkValues('node', $node->id(), array('access' => 1, 'status' => 1, 'priority' => 0.6, 'status_override' => 0, 'priority_override' => 0));
  }

  /**
   * Test the content type settings.
   */
  public function testTypeSettings() {
    $this->drupalLogin($this->admin_user);

    $node_old = $this->drupalCreateNode();
    $this->assertSitemapLinkValues('node', $node_old->id(), array('status' => 1, 'priority' => 0.6, 'changefreq' => XMLSITEMAP_FREQUENCY_WEEKLY));

    $edit = array(
      'xmlsitemap[status]' => 0,
      'xmlsitemap[priority]' => '0.0',
    );
    $this->drupalPostForm('admin/config/search/xmlsitemap/settings/node/page', $edit, t('Save configuration'));
    $this->assertText('The configuration options have been saved.');
    $node = $this->drupalCreateNode();
    $this->assertSitemapLinkValues('node', $node->id(), array('status' => 0, 'priority' => 0.0));
    $this->assertSitemapLinkValues('node', $node_old->id(), array('status' => 0, 'priority' => 0.0));

    $edit = array(
      'type' => 'page2',
    );
    $this->drupalPostForm('admin/structure/types/manage/page', $edit, t('Save content type'));
    $this->assertText('Changed the content type of 2 posts from page to page2.');
    $this->assertText('The content type Basic page has been updated.');

    $this->assertSitemapLinkValues('node', $node->id(), array('subtype' => 'page2', 'status' => 0, 'priority' => 0.0));
    $this->assertSitemapLinkValues('node', $node_old->id(), array('subtype' => 'page2', 'status' => 0, 'priority' => 0.0));
    $this->assertEqual(count($this->linkStorage->loadMultiple(array('type' => 'node', 'subtype' => 'page'))), 0);
    $this->assertEqual(count($this->linkStorage->loadMultiple(array('type' => 'node', 'subtype' => 'page2'))), 2);

    // delete all pages in order to allow content type deletion
    $node->delete();
    $node_old->delete();

    $this->drupalPostForm('admin/structure/types/manage/page2/delete', array(), t('Delete'));
    $this->assertText('The content type Basic page has been deleted.');
    $this->assertFalse($this->linkStorage->loadMultiple(array('type' => 'node', 'subtype' => 'page2')), 'Nodes with deleted node type removed from {xmlsitemap}.');
  }

  /**
   * Test the import of old nodes via cron.
   */
  public function testCron() {
    $limit = 5;
    $this->config->set('batch_limit', $limit)->save();

    $nodes = array();
    for ($i = 1; $i <= ($limit + 1); $i++) {
      $node = $this->drupalCreateNode();
      array_push($nodes, $node);
      // Need to delay by one second so the nodes don't all have the same
      // timestamp.
      sleep(1);
    }

    // Clear all the node link data so we can emulate 'old' nodes.
    db_delete('xmlsitemap')
        ->condition('type', 'node')
        ->execute();

    // Run cron to import old nodes.
    xmlsitemap_cron();

    for ($i = 1; $i <= ($limit + 1); $i++) {
      $node = array_pop($nodes);
      if ($i != 1) {
        // The first $limit nodes should be inserted.
        $this->assertSitemapLinkValues('node', $node->id(), array('access' => 1, 'status' => 1));
      }
      else {
        // Any beyond $limit should not be in the sitemap.
        $this->assertNoSitemapLink(array('type' => 'node', 'id' => $node->id()));
      }
    }
  }

}
