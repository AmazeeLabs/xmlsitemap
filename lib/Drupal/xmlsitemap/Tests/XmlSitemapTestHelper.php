<?php

/**
 * @file
 * Definition of Drupal\xmlsitemap\Tests\XmlSitemapTestHelper.
 */
namespace Drupal\xmlsitemap\Tests;

use Drupal\simpletest\WebTestBase;
/**
 * Helper test class with some added functions for testing.
 */
class XmlSitemapTestHelper extends WebTestBase {

  protected $admin_user;

  function setUp($modules = array()) {
    array_unshift($modules, 'xmlsitemap');
    parent::setUp();
  }

  function tearDown() {
    // Capture any (remaining) watchdog errors.
    $this->assertNoWatchdogErrors();

    parent::tearDown();
  }

  /**
   * Assert the page does not respond with the specified response code.
   *
   * @param $code
   *   Response code. For example 200 is a successful page request. For a list
   *   of all codes see http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html.
   * @param $message
   *   Message to display.
   * @return
   *   Assertion result.
   */
  protected function assertNoResponse($code, $message = '') {
    $curl_code = curl_getinfo($this->curlHandle, CURLINFO_HTTP_CODE);
    $match = is_array($code) ? in_array($curl_code, $code) : $curl_code == $code;
    return $this->assertFalse($match, $message ? $message : t('HTTP response not expected !code, actual !curl_code', array('!code' => $code, '!curl_code' => $curl_code)), t('Browser'));
  }

  /**
   * Check the files directory is created (massive fails if not done).
   *
   * @todo This can be removed when http://drupal.org/node/654752 is fixed.
   */
  protected function checkFilesDirectory() {
    if (!xmlsitemap_check_directory()) {
      $this->fail(t('Sitemap directory was found and writable for testing.'));
    }
  }

  /**
   * Retrieves an XML sitemap.
   *
   * @param $context
   *   An optional array of the XML sitemap's context.
   * @param $options
   *   Options to be forwarded to url(). These values will be merged with, but
   *   always override $sitemap->uri['options'].
   * @param $headers
   *   An array containing additional HTTP request headers, each formatted as
   *   "name: value".
   * @return
   *   The retrieved HTML string, also available as $this->drupalGetContent()
   */
  protected function drupalGetSitemap(array $context = array(), array $options = array(), array $headers = array()) {
    $sitemap = xmlsitemap_sitemap_load_by_context($context);
    if (!$sitemap) {
      return $this->fail('Could not load sitemap by context.');
    }
    return $this->drupalGet($sitemap->uri['path'], $options + $sitemap->uri['options'], $headers);
  }

  /**
   * Regenerate the sitemap by setting the regenerate flag and running cron.
   */
  protected function regenerateSitemap() {
    variable_set('xmlsitemap_regenerate_needed', TRUE);
    variable_set('xmlsitemap_generated_last', 0);
    $this->cronRun();
    $this->assertTrue(variable_get('xmlsitemap_generated_last', 0) && !variable_get('xmlsitemap_regenerate_needed', FALSE), t('XML sitemaps regenerated and flag cleared.'));
  }

  protected function assertSitemapLink($entity_type, $entity_id = NULL) {
    if (is_array($entity_type)) {
      $links = xmlsitemap_link_load_multiple($entity_type);
      $link = $links ? reset($links) : FALSE;
    }
    else {
      $link = xmlsitemap_link_load($entity_type, $entity_id);
    }
    $this->assertTrue(is_array($link), 'Link loaded.');
    return $link;
  }

  protected function assertNoSitemapLink($entity_type, $entity_id = NULL) {
    if (is_array($entity_type)) {
      $links = xmlsitemap_link_load_multiple($entity_type);
      $link = $links ? reset($links) : FALSE;
    }
    else {
      $link = xmlsitemap_link_load($entity_type, $entity_id);
    }
    $this->assertFalse($link, 'Link not loaded.');
    return $link;
  }

  protected function assertSitemapLinkVisible($entity_type, $entity_id) {
    $link = xmlsitemap_link_load($entity_type, $entity_id);
    $this->assertTrue($link && $link['access'] && $link['status'], t('Sitemap link @type @id is visible.', array('@type' => $entity_type, '@id' => $entity_id)));
  }

  protected function assertSitemapLinkNotVisible($entity_type, $entity_id) {
    $link = xmlsitemap_link_load($entity_type, $entity_id);
    $this->assertTrue($link && !($link['access'] && $link['status']), t('Sitemap link @type @id is not visible.', array('@type' => $entity_type, '@id' => $entity_id)));
  }

  protected function assertSitemapLinkValues($entity_type, $entity_id, array $conditions) {
    $link = xmlsitemap_link_load($entity_type, $entity_id);

    if (!$link) {
      return $this->fail(t('Could not load sitemap link for @type @id.', array('@type' => $entity_type, '@id' => $entity_id)));
    }

    foreach ($conditions as $key => $value) {
      if ($value === NULL || $link[$key] === NULL) {
        // For nullable fields, always check for identical values (===).
        $this->assertIdentical($link[$key], $value, t('Identical values for @type @id link field @key.', array('@type' => $entity_type, '@id' => $entity_id, '@key' => $key)));
      }
      else {
        // Otherwise check simple equality (==).
        $this->assertEqual($link[$key], $value, t('Equal values for @type @id link field @key.', array('@type' => $entity_type, '@id' => $entity_id, '@key' => $key)));
      }
    }
  }

  protected function assertNotSitemapLinkValues($entity_type, $entity_id, array $conditions) {
    $link = xmlsitemap_link_load($entity_type, $entity_id);

    if (!$link) {
      return $this->fail(t('Could not load sitemap link for @type @id.', array('@type' => $entity_type, '@id' => $entity_id)));
    }

    foreach ($conditions as $key => $value) {
      if ($value === NULL || $link[$key] === NULL) {
        // For nullable fields, always check for identical values (===).
        $this->assertNotIdentical($link[$key], $value, t('Not identical values for @type @id link field @key.', array('@type' => $entity_type, '@id' => $entity_id, '@key' => $key)));
      }
      else {
        // Otherwise check simple equality (==).
        $this->assertNotEqual($link[$key], $value, t('Not equal values for link @type @id field @key.', array('@type' => $entity_type, '@id' => $entity_id, '@key' => $key)));
      }
    }
  }

  protected function assertRawSitemapLinks() {
    $links = func_get_args();
    foreach ($links as $link) {
      $path = url($link['loc'], array('language' => xmlsitemap_language_load($link['language']), 'absolute' => TRUE));
      $this->assertRaw($link['loc'], t('Link %path found in the sitemap.', array('%path' => $path)));
    }
  }

  protected function assertNoRawSitemapLinks() {
    $links = func_get_args();
    foreach ($links as $link) {
      $path = url($link['loc'], array('language' => xmlsitemap_language_load($link['language']), 'absolute' => TRUE));
      $this->assertNoRaw($link['loc'], t('Link %path not found in the sitemap.', array('%path' => $path)));
    }
  }

  protected function addSitemapLink(array $link = array()) {
    $last_id = &drupal_static(__FUNCTION__, 1);

    $link += array(
      'type' => 'testing',
      'id' => $last_id,
      'access' => 1,
      'status' => 1,
    );

    // Make the default path easier to read than a random string.
    $link += array('loc' => $link['type'] . '-' . $link['id']);

    $last_id = max($last_id, $link['id']) + 1;
    xmlsitemap_link_save($link);
    return $link;
  }

  protected function assertFlag($variable, $assert_value = TRUE, $reset_if_true = TRUE) {
    $value = xmlsitemap_var($variable);

    if ($reset_if_true && $value) {
      variable_set('xmlsitemap_' . $variable, FALSE);
    }

    return $this->assertEqual($value, $assert_value, "xmlsitemap_$variable is " . ($assert_value ? 'TRUE' : 'FALSE'));
  }

  protected function assertXMLSitemapProblems($problem_text = FALSE) {
    $this->drupalGet('admin/config/search/xmlsitemap');
    $this->assertText(t('One or more problems were detected with your XML sitemap configuration'));
    if ($problem_text) {
      $this->assertText($problem_text);
    }
  }

  protected function assertNoXMLSitemapProblems() {
    $this->drupalGet('admin/config/search/xmlsitemap');
    $this->assertNoText(t('One or more problems were detected with your XML sitemap configuration'));
  }

  /**
   * Fetch all seen watchdog messages.
   *
   * @todo Add unit tests for this function.
   */
  protected function getWatchdogMessages(array $conditions = array(), $reset = FALSE) {
    static $seen_ids = array();

    if (!module_exists('dblog') || $reset) {
      $seen_ids = array();
      return array();
    }

    $query = db_select('watchdog');
    $query->fields('watchdog', array('wid', 'type', 'severity', 'message', 'variables', 'timestamp'));
    foreach ($conditions as $field => $value) {
      if ($field == 'variables' && !is_string($value)) {
        $value = serialize($value);
      }
      $query->condition($field, $value);
    }
    if ($seen_ids) {
      $query->condition('wid', $seen_ids, 'NOT IN');
    }
    $query->orderBy('timestamp');
    $messages = $query->execute()->fetchAllAssoc('wid');

    $seen_ids = array_merge($seen_ids, array_keys($messages));
    return $messages;
  }

  protected function assertWatchdogMessage(array $conditions, $message = 'Watchdog message found.') {
    $this->assertTrue($this->getWatchdogMessages($conditions), $message);
  }

  protected function assertNoWatchdogMessage(array $conditions, $message = 'Watchdog message not found.') {
    $this->assertFalse($this->getWatchdogMessages($conditions), $message);
  }

  /**
   * Check that there were no watchdog errors or worse.
   */
  protected function assertNoWatchdogErrors() {
    $messages = $this->getWatchdogMessages();
    $verbose = array();

    foreach ($messages as $message) {
      $message->text = $this->formatWatchdogMessage($message);
      if (in_array($message->severity, array(WATCHDOG_EMERGENCY, WATCHDOG_ALERT, WATCHDOG_CRITICAL, WATCHDOG_ERROR, WATCHDOG_WARNING))) {
        $this->fail($message->text);
      }
      $verbose[] = $message->text;
    }

    if ($verbose) {
      array_unshift($verbose, '<h2>Watchdog messages</h2>');
      $this->verbose(implode("<br />", $verbose), 'Watchdog messages from test run');
    }

    // Clear the seen watchdog messages since we've failed on any errors.
    $this->getWatchdogMessages(array(), TRUE);
  }

  /**
   * Format a watchdog message in a one-line summary.
   *
   * @param $message
   *   A watchdog messsage object.
   * @return
   *   A string containing the watchdog message's timestamp, severity, type,
   *   and actual message.
   */
  private function formatWatchdogMessage(stdClass $message) {
    static $levels;

    if (!isset($levels)) {
      module_load_include('admin.inc', 'dblog');
      $levels = watchdog_severity_levels();
    }

    return t('@timestamp - @severity - @type - !message', array(
      '@timestamp' => $message->timestamp,
      '@severity' => $levels[$message->severity],
      '@type' => $message->type,
      '!message' => theme_dblog_message(array('event' => $message, 'link' => FALSE)),
    ));
  }

  /**
   * Log verbose message in a text file.
   *
   * This is a copy of DrupalWebTestCase->verbose() but allows a customizable
   * summary message rather than hard-coding 'Verbose message'.
   *
   * @param $verbose_message
   *   The verbose message to be stored.
   * @param $message
   *   Message to display.
   * @see simpletest_verbose()
   *
   * @todo Remove when http://drupal.org/node/800426 is fixed.
   */
  protected function verbose($verbose_message, $message = 'Verbose message') {
    if ($id = simpletest_verbose($verbose_message)) {
      $url = file_create_url($this->originalFileDirectory . '/simpletest/verbose/' . get_class($this) . '-' . $id . '.html');
      $this->error(l($message, $url, array('attributes' => array('target' => '_blank'))), 'User notice');
    }
  }

}

class XMLSitemapUnitTest extends XMLSitemapTestHelper {

  public static function getInfo() {
    return array(
      'name' => 'XML sitemap unit tests',
      'description' => 'Unit tests for the XML sitemap module.',
      'group' => 'XML sitemap',
    );
  }

  function testAssertFlag() {
    variable_set('xmlsitemap_rebuild_needed', TRUE);
    $this->assertTrue(xmlsitemap_var('rebuild_needed'));
    $this->assertTrue($this->assertFlag('rebuild_needed', TRUE, FALSE));
    $this->assertTrue(xmlsitemap_var('rebuild_needed'));
    $this->assertTrue($this->assertFlag('rebuild_needed', TRUE, TRUE));
    $this->assertFalse(xmlsitemap_var('rebuild_needed'));
    $this->assertTrue($this->assertFlag('rebuild_needed', FALSE, FALSE));
    $this->assertFalse(xmlsitemap_var('rebuild_needed'));
  }

  /**
   * Tests for xmlsitemap_get_changefreq().
   */
  function testGetChangefreq() {
    // The test values.
    $values = array(
      0,
      mt_rand(1, XMLSITEMAP_FREQUENCY_ALWAYS),
      mt_rand(XMLSITEMAP_FREQUENCY_ALWAYS + 1, XMLSITEMAP_FREQUENCY_HOURLY),
      mt_rand(XMLSITEMAP_FREQUENCY_HOURLY + 1, XMLSITEMAP_FREQUENCY_DAILY),
      mt_rand(XMLSITEMAP_FREQUENCY_DAILY + 1, XMLSITEMAP_FREQUENCY_WEEKLY),
      mt_rand(XMLSITEMAP_FREQUENCY_WEEKLY + 1, XMLSITEMAP_FREQUENCY_MONTHLY),
      mt_rand(XMLSITEMAP_FREQUENCY_MONTHLY + 1, XMLSITEMAP_FREQUENCY_YEARLY),
      mt_rand(XMLSITEMAP_FREQUENCY_YEARLY + 1, mt_getrandmax()),
    );

    // The expected values.
    $expected = array(
      FALSE,
      'always',
      'hourly',
      'daily',
      'weekly',
      'monthly',
      'yearly',
      'never',
    );

    foreach ($values as $i => $value) {
      $actual = xmlsitemap_get_changefreq($value);
      $this->assertIdentical($actual, $expected[$i]);
    }
  }

  /**
   * Tests for xmlsitemap_get_chunk_count().
   */
  function testGetChunkCount() {
    // Set a low chunk size for testing.
    variable_set('xmlsitemap_chunk_size', 4);

    // Make the total number of links just equal to the chunk size.
    $count = db_query("SELECT COUNT(id) FROM {xmlsitemap}")->fetchField();
    for ($i = $count; $i < 4; $i++) {
      $this->addSitemapLink();
      $this->assertEqual(xmlsitemap_get_chunk_count(TRUE), 1);
    }
    $this->assertEqual(db_query("SELECT COUNT(id) FROM {xmlsitemap}")->fetchField(), 4);

    // Add a disabled link, should not change the chunk count.
    $this->addSitemapLink(array('status' => FALSE));
    $this->assertEqual(xmlsitemap_get_chunk_count(TRUE), 1);

    // Add a visible link, should finally bump up the chunk count.
    $this->addSitemapLink();
    $this->assertEqual(xmlsitemap_get_chunk_count(TRUE), 2);

    // Change all links to disabled. The chunk count should be 1 not 0.
    db_query("UPDATE {xmlsitemap} SET status = 0");
    $this->assertEqual(xmlsitemap_get_chunk_count(TRUE), 1);
    $this->assertEqual(xmlsitemap_get_link_count(), 0);

    // Delete all links. The chunk count should be 1 not 0.
    db_query("DELETE FROM {xmlsitemap}");
    $this->assertEqual(db_query("SELECT COUNT(id) FROM {xmlsitemap}")->fetchField(), 0);
    $this->assertEqual(xmlsitemap_get_chunk_count(TRUE), 1);
  }

  //function testGetChunkFile() {
  //}
  //
  //function testGetChunkSize() {
  //}
  //
  //function testGetLinkCount() {
  //}

  /**
   * Tests for xmlsitemap_calculate_changereq().
   */
  function testCalculateChangefreq() {
    // The test values.
    $values = array(
      array(),
      array(REQUEST_TIME),
      array(REQUEST_TIME, REQUEST_TIME - 200),
      array(REQUEST_TIME - 200, REQUEST_TIME, REQUEST_TIME - 600),
    );

    // Expected values.
    $expected = array(0, 0, 200, 300);

    foreach ($values as $i => $value) {
      $actual = xmlsitemap_calculate_changefreq($value);
      $this->assertEqual($actual, $expected[$i]);
    }
  }

  /**
   * Test for xmlsitemap_recalculate_changefreq().
   */
  function testRecalculateChangefreq() {
    // The starting test value.
    $value = array('lastmod' => REQUEST_TIME - 1000, 'changefreq' => 0, 'changecount' => 0);

    // Expected values.
    $expecteds = array(
      array('lastmod' => REQUEST_TIME, 'changefreq' => 1000, 'changecount' => 1),
      array('lastmod' => REQUEST_TIME, 'changefreq' => 500, 'changecount' => 2),
      array('lastmod' => REQUEST_TIME, 'changefreq' => 333, 'changecount' => 3),
    );

    foreach ($expecteds as $expected) {
      xmlsitemap_recalculate_changefreq($value);
      $this->assertEqual($value, $expected);
    }
  }

  /**
   * Tests for xmlsitemap_switch_user and xmlsitemap_restore_user().
   */
  function testSwitchUser() {
    global $user;

    $original_user = $user;
    $new_user = $this->drupalCreateUser();

    // Switch to a new valid user.
    $this->assertEqual(xmlsitemap_switch_user($new_user), TRUE);
    $this->assertEqual($user->uid, $new_user->uid);

    // Switch again to the anonymous user.
    $this->assertEqual(xmlsitemap_switch_user(0), TRUE);
    $this->assertEqual($user->uid, 0);

    // Switch again to the new user.
    $this->assertEqual(xmlsitemap_switch_user($new_user->uid), TRUE);
    $this->assertEqual($user->uid, $new_user->uid);

    // Test that after two switches the original user was restored.
    $this->assertEqual(xmlsitemap_restore_user(), TRUE);
    $this->assertEqual($user->uid, $original_user->uid);

    // Attempt to switch to the same user.
    $this->assertEqual(xmlsitemap_switch_user($original_user->uid), FALSE);
    $this->assertEqual($user->uid, $original_user->uid);
    $this->assertEqual(xmlsitemap_restore_user(), FALSE);
    $this->assertEqual($user->uid, $original_user->uid);

    // Attempt to switch to an invalid user ID.
    $invalid_uid = db_query("SELECT MAX(uid) FROM {users}")->fetchField() + 100;
    $this->assertEqual(xmlsitemap_switch_user($invalid_uid), FALSE);
    $this->assertEqual($user->uid, $original_user->uid);
    $this->assertEqual(xmlsitemap_restore_user(), FALSE);
    $this->assertEqual($user->uid, $original_user->uid);

    // Attempt user switching when the original user is anonymous.
    $user = drupal_anonymous_user();
    $this->assertEqual(xmlsitemap_switch_user(0), FALSE);
    $this->assertEqual($user->uid, 0);
    $this->assertEqual(xmlsitemap_restore_user(), FALSE);
    $this->assertEqual($user->uid, 0);
  }

  //function testLoadLink() {
  //}

  /**
   * Tests for xmlsitemap_link_save().
   */
  function testSaveLink() {
    $link = array('type' => 'testing', 'id' => 1, 'loc' => 'testing', 'status' => 1);
    xmlsitemap_link_save($link);
    $this->assertFlag('regenerate_needed', TRUE);

    $link['status'] = 0;
    xmlsitemap_link_save($link);
    $this->assertFlag('regenerate_needed', TRUE);

    $link['priority'] = 0.5;
    $link['loc'] = 'new_location';
    $link['status'] = 1;
    xmlsitemap_link_save($link);
    $this->assertFlag('regenerate_needed', TRUE);

    $link['priority'] = 0.0;
    xmlsitemap_link_save($link);
    $this->assertFlag('regenerate_needed', TRUE);

    $link['priority'] = 0.1;
    xmlsitemap_link_save($link);
    $this->assertFlag('regenerate_needed', TRUE);

    $link['priority'] = 1.0;
    xmlsitemap_link_save($link);
    $this->assertFlag('regenerate_needed', TRUE);

    $link['priority'] = 1;
    xmlsitemap_link_save($link);
    $this->assertFlag('regenerate_needed', FALSE);

    $link['priority'] = 0;
    xmlsitemap_link_save($link);
    $this->assertFlag('regenerate_needed', TRUE);

    $link['priority'] = 0.5;
    xmlsitemap_link_save($link);
    $this->assertFlag('regenerate_needed', TRUE);

    $link['priority'] = 0.5;
    $link['priority_override'] = 0;
    $link['status'] = 1;
    xmlsitemap_link_save($link);
    $this->assertFlag('regenerate_needed', FALSE);
  }

  /**
   * Tests for xmlsitemap_link_delete().
   */
  function testLinkDelete() {
    // Add our testing data.
    $link1 = $this->addSitemapLink(array('loc' => 'testing1', 'status' => 0));
    $link2 = $this->addSitemapLink(array('loc' => 'testing1', 'status' => 1));
    $link3 = $this->addSitemapLink(array('status' => 0));
    variable_set('xmlsitemap_regenerate_needed', FALSE);

    // Test delete multiple links.
    // Test that the regenerate flag is set when visible links are deleted.
    $deleted = xmlsitemap_link_delete_multiple(array('loc' => 'testing1'));
    $this->assertEqual($deleted, 2);
    $this->assertFalse(xmlsitemap_link_load($link1['type'], $link1['id']));
    $this->assertFalse(xmlsitemap_link_load($link2['type'], $link2['id']));
    $this->assertTrue(xmlsitemap_link_load($link3['type'], $link3['id']));
    $this->assertFlag('regenerate_needed', TRUE);

    $deleted = xmlsitemap_link_delete($link3['type'], $link3['id']);
    $this->assertEqual($deleted, 1);
    $this->assertFalse(xmlsitemap_link_load($link3['type'], $link3['id']));
    $this->assertFlag('regenerate_needed', FALSE);
  }

  /**
   * Tests for xmlsitemap_link_update_multiple().
   */
  function testUpdateLinks() {
    // Add our testing data.
    $links = array();
    $links[1] = $this->addSitemapLink(array('subtype' => 'group1'));
    $links[2] = $this->addSitemapLink(array('subtype' => 'group1'));
    $links[3] = $this->addSitemapLink(array('subtype' => 'group2'));
    variable_set('xmlsitemap_regenerate_needed', FALSE);
    // id | type    | subtype | language | access | status | priority
    // 1  | testing | group1  | ''       | 1      | 1      | 0.5
    // 2  | testing | group1  | ''       | 1      | 1      | 0.5
    // 3  | testing | group2  | ''       | 1      | 1      | 0.5

    $updated = xmlsitemap_link_update_multiple(array('status' => 0), array('type' => 'testing', 'subtype' => 'group1', 'status_override' => 0));
    $this->assertEqual($updated, 2);
    $this->assertFlag('regenerate_needed', TRUE);
    // id | type    | subtype | language | status | priority
    // 1  | testing | group1  | ''       | 0      | 0.5
    // 2  | testing | group1  | ''       | 0      | 0.5
    // 3  | testing | group2  | ''       | 1      | 0.5

    $updated = xmlsitemap_link_update_multiple(array('priority' => 0.0), array('type' => 'testing', 'subtype' => 'group1', 'priority_override' => 0));
    $this->assertEqual($updated, 2);
    $this->assertFlag('regenerate_needed', FALSE);
    // id | type    | subtype | language | status | priority
    // 1  | testing | group1  | ''       | 0      | 0.0
    // 2  | testing | group1  | ''       | 0      | 0.0
    // 3  | testing | group2  | ''       | 1      | 0.5

    $updated = xmlsitemap_link_update_multiple(array('subtype' => 'group2'), array('type' => 'testing', 'subtype' => 'group1'));
    $this->assertEqual($updated, 2);
    $this->assertFlag('regenerate_needed', FALSE);
    // id | type    | subtype | language | status | priority
    // 1  | testing | group2  | ''       | 0      | 0.0
    // 2  | testing | group2  | ''       | 0      | 0.0
    // 3  | testing | group2  | ''       | 1      | 0.5

    $updated = xmlsitemap_link_update_multiple(array('status' => 1), array('type' => 'testing', 'subtype' => 'group2', 'status_override' => 0, 'status' => 0));
    $this->assertEqual($updated, 2);
    $this->assertFlag('regenerate_needed', TRUE);
    // id | type    | subtype | language | status | priority
    // 1  | testing | group2  | ''       | 1      | 0.0
    // 2  | testing | group2  | ''       | 1      | 0.0
    // 3  | testing | group2  | ''       | 1      | 0.5
  }

  /**
   * Test that duplicate paths are skipped during generation.
   */
  function testDuplicatePaths() {
    $link1 = $this->addSitemapLink(array('loc' => 'duplicate'));
    $link2 = $this->addSitemapLink(array('loc' => 'duplicate'));
    $this->regenerateSitemap();
    $this->drupalGetSitemap();
    $this->assertUniqueText('duplicate');
  }

  /**
   * Test that the sitemap will not be genereated before the lifetime expires.
   */
  function testMinimumLifetime() {
    variable_set('xmlsitemap_minimum_lifetime', 300);
    $this->regenerateSitemap();

    $link = $this->addSitemapLink(array('loc' => 'lifetime-test'));
    $this->cronRun();
    $this->drupalGetSitemap();
    $this->assertResponse(200);
    $this->assertNoRaw('lifetime-test');

    variable_set('xmlsitemap_generated_last', REQUEST_TIME - 400);
    $this->cronRun();
    $this->drupalGetSitemap();
    $this->assertRaw('lifetime-test');

    xmlsitemap_link_delete($link['type'], $link['id']);
    $this->cronRun();
    $this->drupalGetSitemap();
    $this->assertRaw('lifetime-test');

    $this->regenerateSitemap();
    $this->drupalGetSitemap();
    $this->assertResponse(200);
    $this->assertNoRaw('lifetime-test');
  }

}

class XMLSitemapFunctionalTest extends XMLSitemapTestHelper {

  public static function getInfo() {
    return array(
      'name' => 'XML sitemap interface tests',
      'description' => 'Functional tests for the XML sitemap module.',
      'group' => 'XML sitemap',
    );
  }

  function setUp($modules = array()) {
    $modules[] = 'path';
    parent::setUp($modules);
    $this->admin_user = $this->drupalCreateUser(array('access content', 'administer site configuration', 'administer xmlsitemap'));
    $this->drupalLogin($this->admin_user);
  }

  /**
   * Test the sitemap file caching.
   */
  function testSitemapCaching() {
    $this->regenerateSitemap();
    $this->drupalGetSitemap();
    $this->assertResponse(200);
    $etag = $this->drupalGetHeader('etag');
    $last_modified = $this->drupalGetHeader('last-modified');
    $this->assertTrue($etag, t('Etag header found.'));
    $this->assertTrue($last_modified, t('Last-modified header found.'));

    $this->drupalGetSitemap(array(), array(), array('If-Modified-Since: ' . $last_modified, 'If-None-Match: ' . $etag));
    $this->assertResponse(304);
  }

  /**
   * Test base URL functionality.
   */
  function testBaseURL() {
    $edit = array('xmlsitemap_base_url' => '');
    $this->drupalPost('admin/config/search/xmlsitemap/settings', $edit, t('Save configuration'));
    $this->assertText(t('Default base URL field is required.'));

    $edit = array('xmlsitemap_base_url' => 'invalid');
    $this->drupalPost('admin/config/search/xmlsitemap/settings', $edit, t('Save configuration'));
    $this->assertText(t('Invalid base URL.'));

    $edit = array('xmlsitemap_base_url' => 'http://example.com/ ');
    $this->drupalPost('admin/config/search/xmlsitemap/settings', $edit, t('Save configuration'));
    $this->assertText(t('Invalid base URL.'));

    $edit = array('xmlsitemap_base_url' => 'http://example.com/');
    $this->drupalPost('admin/config/search/xmlsitemap/settings', $edit, t('Save configuration'));
    $this->assertText(t('The configuration options have been saved.'));

    $this->regenerateSitemap();
    $this->drupalGetSitemap(array(), array('base_url' => NULL));
    $this->assertRaw('<loc>http://example.com/</loc>');
  }

  /**
   * Test that configuration problems are reported properly in the status report.
   */
  function testStatusReport() {
    // Test the rebuild flag.
    // @todo Re-enable these tests once we get a xmlsitemap_test.module.
    //variable_set('xmlsitemap_generated_last', REQUEST_TIME);
    //variable_set('xmlsitemap_rebuild_needed', TRUE);
    //$this->assertXMLSitemapProblems(t('The XML sitemap data is out of sync and needs to be completely rebuilt.'));
    //$this->clickLink(t('completely rebuilt'));
    //$this->assertResponse(200);
    //variable_set('xmlsitemap_rebuild_needed', FALSE);
    //$this->assertNoXMLSitemapProblems();
    // Test the regenerate flag (and cron hasn't run in a while).
    variable_set('xmlsitemap_regenerate_needed', TRUE);
    variable_set('xmlsitemap_generated_last', REQUEST_TIME - variable_get('cron_threshold_warning', 172800) - 100);
    $this->assertXMLSitemapProblems(t('The XML cached files are out of date and need to be regenerated. You can run cron manually to regenerate the sitemap files.'));
    $this->clickLink(t('run cron manually'));
    $this->assertResponse(200);
    $this->assertNoXMLSitemapProblems();

    // Test chunk count > 1000.
    // Test directory not writable.
  }

}

class XMLSitemapRobotsTxtIntegrationTest extends XMLSitemapTestHelper {

  public static function getInfo() {
    return array(
      'name' => 'XML sitemap robots.txt',
      'description' => 'Integration tests for the XML sitemap and robots.txt module.',
      'group' => 'XML sitemap',
      'dependencies' => array('robotstxt'),
    );
  }

  function setUp($modules = array()) {
    $modules[] = 'robotstxt';
    parent::setUp($modules);
  }

  function testRobotsTxt() {
    // Request the un-clean robots.txt path so this will work in case there is
    // still the robots.txt file in the root directory.
    $this->drupalGet('', array('query' => array('q' => 'robots.txt')));
    $this->assertRaw('Sitemap: ' . url('sitemap.xml', array('absolute' => TRUE)));
  }

}
