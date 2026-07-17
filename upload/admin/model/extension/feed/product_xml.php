<?php
class ModelExtensionFeedProductXml extends Model {
  public function install() {
    $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "product_xml_feed` (
      `feed_id` int(11) NOT NULL AUTO_INCREMENT,
      `name` varchar(255) NOT NULL,
      `code` varchar(64) NOT NULL,
      `status` tinyint(1) NOT NULL DEFAULT '1',
      `settings` mediumtext NOT NULL,
      `date_added` datetime NOT NULL,
      `date_modified` datetime NOT NULL,
      PRIMARY KEY (`feed_id`),
      UNIQUE KEY `code` (`code`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");

    $this->db->query("CREATE TABLE IF NOT EXISTS `" . DB_PREFIX . "product_xml_feed_category` (
      `feed_id` int(11) NOT NULL,
      `category_id` int(11) NOT NULL,
      PRIMARY KEY (`feed_id`,`category_id`),
      KEY `category_id` (`category_id`)
    ) ENGINE=MyISAM DEFAULT CHARSET=utf8 COLLATE=utf8_general_ci");
  }

  public function addFeed($data) {
    $settings = isset($data['settings']) ? $data['settings'] : array();
    $this->db->query("INSERT INTO `" . DB_PREFIX . "product_xml_feed` SET name = '" . $this->db->escape($data['name']) . "', code = '" . $this->db->escape($data['code']) . "', status = '" . (int)$data['status'] . "', settings = '" . $this->db->escape(json_encode($settings)) . "', date_added = NOW(), date_modified = NOW()");
    $feed_id = $this->db->getLastId();
    $this->setCategories($feed_id, isset($data['category_ids']) ? $data['category_ids'] : array());
    return $feed_id;
  }

  public function editFeed($feed_id, $data) {
    $settings = isset($data['settings']) ? $data['settings'] : array();
    $this->db->query("UPDATE `" . DB_PREFIX . "product_xml_feed` SET name = '" . $this->db->escape($data['name']) . "', code = '" . $this->db->escape($data['code']) . "', status = '" . (int)$data['status'] . "', settings = '" . $this->db->escape(json_encode($settings)) . "', date_modified = NOW() WHERE feed_id = '" . (int)$feed_id . "'");
    $this->setCategories($feed_id, isset($data['category_ids']) ? $data['category_ids'] : array());
  }

  private function setCategories($feed_id, $category_ids) {
    $this->db->query("DELETE FROM `" . DB_PREFIX . "product_xml_feed_category` WHERE feed_id = '" . (int)$feed_id . "'");
    foreach (array_unique(array_map('intval', (array)$category_ids)) as $category_id) {
      if ($category_id > 0) {
        $this->db->query("INSERT IGNORE INTO `" . DB_PREFIX . "product_xml_feed_category` SET feed_id = '" . (int)$feed_id . "', category_id = '" . (int)$category_id . "'");
      }
    }
  }

  public function deleteFeed($feed_id) {
    $this->db->query("DELETE FROM `" . DB_PREFIX . "product_xml_feed` WHERE feed_id = '" . (int)$feed_id . "'");
    $this->db->query("DELETE FROM `" . DB_PREFIX . "product_xml_feed_category` WHERE feed_id = '" . (int)$feed_id . "'");
  }

  public function getFeed($feed_id) {
    $query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_xml_feed` WHERE feed_id = '" . (int)$feed_id . "'");
    if (!$query->num_rows) return array();
    $feed = $query->row;
    $feed['settings'] = json_decode($feed['settings'], true);
    if (!is_array($feed['settings'])) $feed['settings'] = array();
    $feed['category_ids'] = $this->getFeedCategories($feed_id);
    return $feed;
  }

  public function getFeeds() {
    return $this->db->query("SELECT f.*, (SELECT COUNT(*) FROM `" . DB_PREFIX . "product_xml_feed_category` fc WHERE fc.feed_id = f.feed_id) AS category_count FROM `" . DB_PREFIX . "product_xml_feed` f ORDER BY f.name ASC")->rows;
  }

  public function getFeedCategories($feed_id) {
    $rows = $this->db->query("SELECT category_id FROM `" . DB_PREFIX . "product_xml_feed_category` WHERE feed_id = '" . (int)$feed_id . "'")->rows;
    return array_column($rows, 'category_id');
  }

  public function codeExists($code, $feed_id = 0) {
    $query = $this->db->query("SELECT feed_id FROM `" . DB_PREFIX . "product_xml_feed` WHERE code = '" . $this->db->escape($code) . "' AND feed_id != '" . (int)$feed_id . "' LIMIT 1");
    return (bool)$query->num_rows;
  }
}
