<?php
class ControllerExtensionFeedProductXml extends Controller {
  private $error = array();
  private $fields = array('description','plain_text_description','meta_title','meta_description','meta_keyword','tags','product_url','seo_url','price','special','image','additional_images','stock','product_status','quantity','weight','length','width','height','brand','model','sku','upc','ean','jan','isbn','mpn','show_categories','options','include_subcategories','include_out_of_stock');

  public function index() {
    $this->load->language('extension/feed/product_xml');
    $this->document->setTitle($this->language->get('heading_title'));
    $this->load->model('extension/feed/product_xml');
    $data = $this->commonData();
    $data['add'] = $this->url->link('extension/feed/product_xml/form', 'user_token=' . $data['user_token'], true);
    $data['delete'] = $this->url->link('extension/feed/product_xml/delete', 'user_token=' . $data['user_token'], true);
    $data['feeds'] = array();
    foreach ($this->model_extension_feed_product_xml->getFeeds() as $feed) {
      $data['feeds'][] = array(
        'feed_id' => $feed['feed_id'], 'name' => $feed['name'], 'code' => $feed['code'],
        'status' => $feed['status'], 'category_count' => $feed['category_count'],
        'url' => HTTP_CATALOG . 'index.php?route=extension/feed/product_xml&code=' . rawurlencode($feed['code']),
        'seo_url' => HTTP_CATALOG . 'product-feed/' . rawurlencode($feed['code']) . '.xml',
        'edit' => $this->url->link('extension/feed/product_xml/form', 'user_token=' . $data['user_token'] . '&feed_id=' . $feed['feed_id'], true)
      );
    }
    $data['success'] = isset($this->session->data['success']) ? $this->session->data['success'] : '';
    unset($this->session->data['success']);
    $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
    $this->response->setOutput($this->load->view('extension/feed/product_xml_list', $data));
  }

  public function form() {
    $this->load->language('extension/feed/product_xml');
    $this->document->setTitle($this->language->get('heading_title'));
    $this->load->model('extension/feed/product_xml');
    $this->load->model('catalog/category');
    $feed_id = isset($this->request->get['feed_id']) ? (int)$this->request->get['feed_id'] : 0;

    if (($this->request->server['REQUEST_METHOD'] == 'POST') && $this->validateForm($feed_id)) {
      $payload = $this->normalisePost();
      if ($feed_id) $this->model_extension_feed_product_xml->editFeed($feed_id, $payload);
      else $this->model_extension_feed_product_xml->addFeed($payload);
      $this->session->data['success'] = $this->language->get('text_success');
      $this->response->redirect($this->url->link('extension/feed/product_xml', 'user_token=' . $this->session->data['user_token'], true));
    }

    $feed = $feed_id ? $this->model_extension_feed_product_xml->getFeed($feed_id) : array();
    $default_settings = array_fill_keys($this->fields, 1);
    $default_settings['plain_text_description'] = 0;
    $defaults = array('name'=>'','code'=>'','status'=>1,'category_ids'=>array(),'settings'=>$default_settings);
    $feed = array_replace_recursive($defaults, $feed);
    if ($this->request->server['REQUEST_METHOD'] == 'POST') $feed = array_replace_recursive($feed, $this->normalisePost());

    $data = $this->commonData();
    $data['feed_id'] = $feed_id;
    $data['name'] = $feed['name']; $data['code'] = $feed['code']; $data['status'] = $feed['status'];
    $data['settings'] = $feed['settings']; $data['fields'] = $this->fields;
    $data['action'] = $this->url->link('extension/feed/product_xml/form', 'user_token=' . $data['user_token'] . ($feed_id ? '&feed_id=' . $feed_id : ''), true);
    $data['cancel'] = $this->url->link('extension/feed/product_xml', 'user_token=' . $data['user_token'], true);
    $data['feed_url'] = $feed['code'] ? HTTP_CATALOG . 'index.php?route=extension/feed/product_xml&code=' . rawurlencode($feed['code']) : '';
    $data['feed_url_seo'] = $feed['code'] ? HTTP_CATALOG . 'product-feed/' . rawurlencode($feed['code']) . '.xml' : '';
    $data['htaccess_rule'] = 'RewriteRule ^product-feed/([a-z0-9-]+)\\.xml$ index.php?route=extension/feed/product_xml&code=$1 [L,QSA]';
    $data['categories_selected'] = array();
    foreach ((array)$feed['category_ids'] as $category_id) {
      $info = $this->model_catalog_category->getCategory($category_id);
      if ($info) $data['categories_selected'][] = array('category_id'=>$info['category_id'], 'name'=>$info['name']);
    }
    $data['error_warning'] = isset($this->error['warning']) ? $this->error['warning'] : '';
    $data['error_name'] = isset($this->error['name']) ? $this->error['name'] : '';
    $data['error_code'] = isset($this->error['code']) ? $this->error['code'] : '';
    $this->response->setOutput($this->load->view('extension/feed/product_xml_form', $data));
  }

  private function normalisePost() {
    $settings = array();
    foreach ($this->fields as $field) $settings[$field] = !empty($this->request->post['settings'][$field]) ? 1 : 0;
    return array(
      'name' => isset($this->request->post['name']) ? trim($this->request->post['name']) : '',
      'code' => isset($this->request->post['code']) ? strtolower(trim($this->request->post['code'])) : '',
      'status' => !empty($this->request->post['status']) ? 1 : 0,
      'category_ids' => isset($this->request->post['category_ids']) ? (array)$this->request->post['category_ids'] : array(),
      'settings' => $settings
    );
  }

  protected function validateForm($feed_id) {
    if (!$this->user->hasPermission('modify', 'extension/feed/product_xml')) $this->error['warning'] = $this->language->get('error_permission');
    $name = isset($this->request->post['name']) ? trim($this->request->post['name']) : '';
    $code = isset($this->request->post['code']) ? strtolower(trim($this->request->post['code'])) : '';
    if (utf8_strlen($name) < 3 || utf8_strlen($name) > 255) $this->error['name'] = $this->language->get('error_name');
    if (!preg_match('/^[a-z0-9]+(?:-[a-z0-9]+)*$/', $code)) $this->error['code'] = $this->language->get('error_code');
    elseif ($this->model_extension_feed_product_xml->codeExists($code, $feed_id)) $this->error['code'] = $this->language->get('error_code_exists');
    return !$this->error;
  }

  public function delete() {
    $this->load->language('extension/feed/product_xml');
    $this->load->model('extension/feed/product_xml');
    if (!$this->user->hasPermission('modify', 'extension/feed/product_xml')) $this->error['warning'] = $this->language->get('error_permission');
    if (!$this->error && isset($this->request->post['selected'])) {
      foreach ($this->request->post['selected'] as $feed_id) $this->model_extension_feed_product_xml->deleteFeed($feed_id);
      $this->session->data['success'] = $this->language->get('text_success');
    }
    $this->response->redirect($this->url->link('extension/feed/product_xml', 'user_token=' . $this->session->data['user_token'], true));
  }

  public function autocomplete() {
    $json = array();
    if (isset($this->request->get['filter_name'])) {
      $this->load->model('catalog/category');
      $results = $this->model_catalog_category->getCategories(array('filter_name'=>$this->request->get['filter_name'],'sort'=>'name','order'=>'ASC','start'=>0,'limit'=>20));
      foreach ($results as $result) $json[] = array('category_id'=>$result['category_id'], 'name'=>strip_tags(html_entity_decode($result['name'], ENT_QUOTES, 'UTF-8')));
    }
    $this->response->addHeader('Content-Type: application/json');
    $this->response->setOutput(json_encode($json));
  }

  public function install() {
    $this->load->model('extension/feed/product_xml');
    $this->model_extension_feed_product_xml->install();
    if (!$this->model_extension_feed_product_xml->getFeeds()) {
      $legacy = array();
      $has_legacy = $this->config->get('feed_product_xml_status') !== null;
      foreach ($this->fields as $field) {
        if ($has_legacy) {
          $legacy_key = ($field === 'show_categories') ? 'feed_product_xml_categories' : 'feed_product_xml_' . $field;
          $value = $this->config->get($legacy_key);
          $legacy[$field] = is_array($value) ? 1 : (int)$value;
        } else {
          $legacy[$field] = !in_array($field, array('plain_text_description', 'upc', 'jan', 'isbn', 'include_subcategories'), true) ? 1 : 0;
        }
      }
      $legacy['include_subcategories'] = 0;
      $legacy['include_out_of_stock'] = 1;
      $old_categories = $this->config->get('feed_product_xml_categories');
      $this->model_extension_feed_product_xml->addFeed(array(
        'name' => 'Всички продукти',
        'code' => 'all-products',
        'status' => $has_legacy ? (int)$this->config->get('feed_product_xml_status') : 1,
        'settings' => $legacy,
        'category_ids' => is_array($old_categories) ? $old_categories : array()
      ));
    }
  }

  public function uninstall() { /* Данните се запазват умишлено при деинсталиране. */ }

  private function commonData() {
    $data = $this->load->language('extension/feed/product_xml');
    $data['user_token'] = $this->session->data['user_token'];
    $data['breadcrumbs'] = array(
      array('text'=>$this->language->get('text_home'),'href'=>$this->url->link('common/dashboard','user_token='.$data['user_token'],true)),
      array('text'=>$this->language->get('text_extension'),'href'=>$this->url->link('marketplace/extension','user_token='.$data['user_token'].'&type=feed',true)),
      array('text'=>$this->language->get('heading_title'),'href'=>$this->url->link('extension/feed/product_xml','user_token='.$data['user_token'],true))
    );
    $data['header']=$this->load->controller('common/header'); $data['column_left']=$this->load->controller('common/column_left'); $data['footer']=$this->load->controller('common/footer');
    return $data;
  }
}
