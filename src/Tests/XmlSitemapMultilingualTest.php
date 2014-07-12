<?php

/**
 * @file
 * Contains \Drupal\xmlsitemap\Tests\XmlSitemapMultilingualTest.
 */

namespace Drupal\xmlsitemap\Tests;

use Drupal\Core\Language\LanguageInterface;

/**
 * Tests the generation of multilingual sitemaps.
 */
class XmlSitemapMultilingualTest extends XmlSitemapMultilingualTestBase {

  public static $modules = array('language', 'xmlsitemap', 'node', 'locale', 'content_translation', 'system');

  public static function getInfo() {
    return array(
      'name' => 'XML sitemap i18n tests',
      'description' => 'Functional and integration tests for the XML sitemap and internationalization modules.',
      'group' => 'XML sitemap',
    );
  }

  public function setUp() {
    parent::setUp();

    $this->drupalLogin($this->admin_user);
    $edit = array(
      'site_default_language' => 'en',
    );
    $this->drupalPostForm('admin/config/regional/settings', $edit, t('Save configuration'));
    // Enable URL language detection and selection.
    $edit = array('language_interface[enabled][language-url]' => '1');
    $this->drupalPostForm('admin/config/regional/language/detection', $edit, t('Save settings'));
  }

  public function testLanguageSelection() {
    $this->drupalLogin($this->admin_user);
    // Create our three different language nodes.
    $node = $this->addSitemapLink(array('type' => 'node', 'language' => LanguageInterface::LANGCODE_NOT_SPECIFIED));
    $node_en = $this->addSitemapLink(array('type' => 'node', 'language' => 'en'));
    $node_fr = $this->addSitemapLink(array('type' => 'node', 'language' => 'fr'));

    // Create three non-node language nodes.
    $link = $this->addSitemapLink(array('language' => LanguageInterface::LANGCODE_NOT_SPECIFIED));
    $link_en = $this->addSitemapLink(array('language' => 'en'));
    $link_fr = $this->addSitemapLink(array('language' => 'fr'));

    \Drupal::config('xmlsitemap.settings')->set('i18n_selection_mode', 'off')->save();
    $this->regenerateSitemap();
    $this->drupalGetSitemap(array('language' => 'en'));
    $this->assertRawSitemapLinks($node, $node_en, $node_fr, $link, $link_en, $link_fr);
    $this->drupalGet('fr/sitemap.xml');
    $this->assertRawSitemapLinks($node, $node_en, $node_fr, $link, $link_en, $link_fr);

    \Drupal::config('xmlsitemap.settings')->set('i18n_selection_mode', 'simple')->save();
    $this->regenerateSitemap();
    $this->drupalGetSitemap(array('language' => 'en'));
    $this->assertRawSitemapLinks($node, $node_en, $link, $link_en);
    $this->assertNoRawSitemapLinks($node_fr, $link_fr);
    $this->drupalGet('fr/sitemap.xml');
    $this->assertRawSitemapLinks($node, $node_fr, $link, $link_fr);
    $this->assertNoRawSitemapLinks($node_en, $link_en);

    \Drupal::config('xmlsitemap.settings')->set('i18n_selection_mode', 'mixed')->save();
    $this->regenerateSitemap();
    $this->drupalGetSitemap(array('language' => 'en'));
    $this->assertRawSitemapLinks($node, $node_en, $link, $link_en);
    $this->assertNoRawSitemapLinks($node_fr, $link_fr);
    $this->drupalGet('fr/sitemap.xml');
    $this->assertRawSitemapLinks($node, $node_en, $node_fr, $link, $link_en, $link_fr);

    \Drupal::config('xmlsitemap.settings')->set('i18n_selection_mode', 'default')->save();
    $this->regenerateSitemap();
    $this->drupalGetSitemap(array('language' => 'en'));
    $this->assertRawSitemapLinks($node, $node_en, $link, $link_en);
    $this->assertNoRawSitemapLinks($node_fr, $link_fr);
    $this->drupalGet('fr/sitemap.xml');
    $this->assertRawSitemapLinks($node, $node_en, $link, $link_en);
    $this->assertNoRawSitemapLinks($node_fr, $link_fr);

    // With strict mode, the language neutral node should not be found, but the
    // language neutral non-node should be.
    \Drupal::config('xmlsitemap.settings')->set('i18n_selection_mode', 'strict')->save();
    $this->regenerateSitemap();
    $this->drupalGetSitemap(array('language' => 'en'));
    $this->assertRawSitemapLinks($node_en, $link, $link_en);
    $this->assertNoRawSitemapLinks($node, $node_fr, $link_fr);
    $this->drupalGet('fr/sitemap.xml');
    $this->assertRawSitemapLinks($node_fr, $link, $link_fr);
    $this->assertNoRawSitemapLinks($node, $node_en, $link_en);
  }

}
