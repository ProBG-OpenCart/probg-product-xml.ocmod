<?php
class ControllerExtensionFeedProductXml extends Controller {

  public function index() {
    // Някои външни системи изпращат копиран URL с буквално `&amp;code=`.
    // В този случай PHP създава GET параметър `amp;code`, вместо `code`.
    if (isset($this->request->get['code'])) {
      $code = $this->request->get['code'];
    } elseif (isset($this->request->get['amp;code'])) {
      $code = $this->request->get['amp;code'];
    } else {
      $code = '';
    }

    $code = strtolower(trim(html_entity_decode($code, ENT_QUOTES, 'UTF-8')));
    $feed_query = $this->db->query("SELECT * FROM `" . DB_PREFIX . "product_xml_feed` WHERE code = '" . $this->db->escape($code) . "' LIMIT 1");

    if (!$feed_query->num_rows) {
      $this->response->addHeader('HTTP/1.1 404 Not Found');
      $this->response->setOutput('Feed not found');
      return;
    }

    if (!$feed_query->row['status']) {
      $this->response->addHeader('HTTP/1.1 410 Gone');
      $this->response->setOutput('Feed disabled');
      return;
    }

    $feed = $feed_query->row;
    $feed['settings'] = json_decode($feed['settings'], true);
    if (!is_array($feed['settings'])) $feed['settings'] = array();

    // Запазва съвместимостта с вече изградената XML логика.
    foreach ($feed['settings'] as $key => $value) {
      $config_key = ($key === 'show_categories') ? 'feed_product_xml_categories' : 'feed_product_xml_' . $key;
      $this->config->set($config_key, (int)$value);
    }

    $this->load->model('catalog/product');
    $this->load->model('catalog/category');

    $xml = new DOMDocument('1.0', 'UTF-8');
    $xml->formatOutput = true;

    $products_node = $xml->createElement('products');
    $products_node->setAttribute('generated', date('Y-m-d H:i:s'));

    $xml->appendChild($products_node);

    $category_rows = $this->db->query("SELECT category_id FROM `" . DB_PREFIX . "product_xml_feed_category` WHERE feed_id = '" . (int)$feed['feed_id'] . "'")->rows;
    $category_ids = array_map('intval', array_column($category_rows, 'category_id'));

    if ($category_ids && !empty($feed['settings']['include_subcategories'])) {
      $descendants = $this->db->query("SELECT DISTINCT category_id FROM `" . DB_PREFIX . "category_path` WHERE path_id IN (" . implode(',', $category_ids) . ")")->rows;
      $category_ids = array_values(array_unique(array_merge($category_ids, array_map('intval', array_column($descendants, 'category_id')))));
    }

    $sql = "SELECT DISTINCT p.product_id FROM `" . DB_PREFIX . "product` p";
    if ($category_ids) {
      $sql .= " INNER JOIN `" . DB_PREFIX . "product_to_category` p2c ON (p.product_id = p2c.product_id)";
    }
    $sql .= " INNER JOIN `" . DB_PREFIX . "product_to_store` p2s ON (p.product_id = p2s.product_id AND p2s.store_id = '" . (int)$this->config->get('config_store_id') . "')";
    $sql .= " WHERE p.status = '1' AND p.date_available <= NOW()";
    if ($category_ids) $sql .= " AND p2c.category_id IN (" . implode(',', $category_ids) . ")";
    if (empty($feed['settings']['include_out_of_stock'])) $sql .= " AND p.quantity > '0'";
    $sql .= " ORDER BY p.product_id ASC";
    $results = $this->db->query($sql);

    foreach ($results->rows as $row) {

      $product = $this->model_catalog_product->getProduct($row['product_id']);

      if (!$product) {
        continue;
      }

      $product_node = $xml->createElement('product');

      /*
      |------------------------------------------------------
      | Mandatory fields
      |------------------------------------------------------
      */

      $id = $xml->createElement('id');
      $id->appendChild(
        $xml->createTextNode($product['product_id'])
      );

      $product_node->appendChild($id);

      $name = $xml->createElement('name');
      $name->appendChild(
        $xml->createCDATASection($product['name'])
      );

      $product_node->appendChild($name);

      /*
      |------------------------------------------------------
      | URL
      |------------------------------------------------------
      */

      if ($this->config->get('feed_product_xml_product_url')) {

        $url = $xml->createElement('url');

        $url->appendChild(
          $xml->createTextNode(
            $this->url->link(
              'product/product',
              'product_id=' . $product['product_id']
            )
          )
        );

        $product_node->appendChild($url);
      }

      /*
      |------------------------------------------------------
      | Description
      |------------------------------------------------------
      */

      if ($this->config->get('feed_product_xml_description')) {

        $description_text = html_entity_decode(
          $product['description'],
          ENT_QUOTES,
          'UTF-8'
        );

        if ($this->config->get('feed_product_xml_plain_text_description')) {

          $description_text = trim(strip_tags($description_text));

        } else {
          // Премахване на font тагове, но запазване на съдържанието
          $description_text = preg_replace(
            '#</?font\b[^>]*>#is',
            '',
            $description_text
          );

          // Премахване на script тагове
          $description_text = preg_replace(
            '#<script\b[^>]*>.*?</script>#is',
            '',
            $description_text
          );

          // Премахване на style тагове
          $description_text = preg_replace(
            '#<style\b[^>]*>.*?</style>#is',
            '',
            $description_text
          );

          // Премахване на class атрибути
          $description_text = preg_replace(
            '/\sclass=("|\')[^"\']*\1/i',
            '',
            $description_text
          );

          // Премахване на style атрибути
          $description_text = preg_replace(
            '/\sstyle=("|\')[^"\']*\1/i',
            '',
            $description_text
          );

          // Премахване на JS събития
          $description_text = preg_replace(
            '/\son[a-z]+=("|\')[^"\']*\1/i',
            '',
            $description_text
          );

          $description_text = trim($description_text);
        }

        $description = $xml->createElement('description');

        $description->appendChild(
          $xml->createCDATASection($description_text)
        );

        $product_node->appendChild($description);
      }

      /*
      |------------------------------------------------------
      | Meta Title / Meta Description
      |------------------------------------------------------
      */
      if ($this->config->get('feed_product_xml_meta_title')) {
        $meta_title = $xml->createElement('meta_title');
        $meta_title->appendChild(
          $xml->createCDATASection(isset($product['meta_title']) ? $product['meta_title'] : '')
        );
        $product_node->appendChild($meta_title);
      }

      if ($this->config->get('feed_product_xml_meta_description')) {
        $meta_description = $xml->createElement('meta_description');
        $meta_description->appendChild(
          $xml->createCDATASection(isset($product['meta_description']) ? $product['meta_description'] : '')
        );
        $product_node->appendChild($meta_description);
      }

      if ($this->config->get('feed_product_xml_meta_keyword')) {
        $meta_keyword = $xml->createElement('meta_keyword');
        $meta_keyword->appendChild(
          $xml->createCDATASection(isset($product['meta_keyword']) ? $product['meta_keyword'] : '')
        );
        $product_node->appendChild($meta_keyword);
      }

      if ($this->config->get('feed_product_xml_tags')) {
        $tags = $xml->createElement('tags');
        $tags->appendChild(
          $xml->createCDATASection(isset($product['tag']) ? $product['tag'] : '')
        );
        $product_node->appendChild($tags);
      }

      /*
      |------------------------------------------------------
      | Price
      |------------------------------------------------------
      */

      if ($this->config->get('feed_product_xml_price')) {

        $price = $xml->createElement(
          'price',
          number_format((float)$product['price'], 2, '.', '')
        );

        $product_node->appendChild($price);
      }

      /*
      |------------------------------------------------------
      | Special Price
      |------------------------------------------------------
      */

      if ($this->config->get('feed_product_xml_special')) {

        $special_price = '';

        $special = $this->db->query("
                    SELECT price
                    FROM " . DB_PREFIX . "product_special
                    WHERE product_id = '" . (int)$product['product_id'] . "'
                    AND customer_group_id = '" . (int)$this->config->get('config_customer_group_id') . "'
                    AND (date_start = '0000-00-00' OR date_start < NOW())
                    AND (date_end = '0000-00-00' OR date_end > NOW())
                    ORDER BY priority ASC, price ASC
                    LIMIT 1
                ");

        if ($special->num_rows) {
          $special_price = $special->row['price'];
        }

        $special_node = $xml->createElement(
          'special_price',
          $special_price
        );

        $product_node->appendChild($special_node);
      }

      /*
      |------------------------------------------------------
      | Quantity
      |------------------------------------------------------
      */

      if ($this->config->get('feed_product_xml_quantity')) {

        $quantity = $xml->createElement(
          'quantity',
          (int)$product['quantity']
        );

        $product_node->appendChild($quantity);
      }

      /*
      |------------------------------------------------------
      | Stock
      |------------------------------------------------------
      */

      if ($this->config->get('feed_product_xml_stock')) {

        $stock = $xml->createElement(
          'stock',
          ($product['quantity'] > 0 ? 'In Stock' : 'Out Of Stock')
        );

        $product_node->appendChild($stock);
      }

      /*
      |------------------------------------------------------
      | Product Status
      |------------------------------------------------------
      */
      if ($this->config->get('feed_product_xml_product_status')) {
        $status = $xml->createElement(
          'status',
          isset($product['status']) ? (int)$product['status'] : 1
        );
        $product_node->appendChild($status);
      }

      /*
      |------------------------------------------------------
      | Weight and Dimensions
      |------------------------------------------------------
      */
      foreach (array('weight', 'length', 'width', 'height') as $dimension_field) {
        if ($this->config->get('feed_product_xml_' . $dimension_field)) {
          $dimension_node = $xml->createElement(
            $dimension_field,
            $this->formatNumber(isset($product[$dimension_field]) ? $product[$dimension_field] : 0)
          );
          $product_node->appendChild($dimension_node);
        }
      }

      /*
      |------------------------------------------------------
      | Brand
      |------------------------------------------------------
      */

      if ($this->config->get('feed_product_xml_brand')) {

        $brand = $xml->createElement('brand');

        $brand->appendChild(
          $xml->createCDATASection($product['manufacturer'])
        );

        $product_node->appendChild($brand);
      }

      /*
      |------------------------------------------------------
      | Model
      |------------------------------------------------------
      */

      if ($this->config->get('feed_product_xml_model')) {

        $model = $xml->createElement('model');

        $model->appendChild(
          $xml->createCDATASection($product['model'])
        );

        $product_node->appendChild($model);
      }

      /*
      |------------------------------------------------------
      | SKU / UPC / EAN / JAN / ISBN / MPN
      |------------------------------------------------------
      */

      $fields = array(
        'sku',
        'upc',
        'ean',
        'jan',
        'isbn',
        'mpn'
      );

      foreach ($fields as $field) {

        if ($this->config->get('feed_product_xml_' . $field)) {

          $node = $xml->createElement($field);

          $node->appendChild(
            $xml->createCDATASection($product[$field])
          );

          $product_node->appendChild($node);
        }
      }

      /*
      |------------------------------------------------------
      | Main Image
      |------------------------------------------------------
      */

      if (
        $this->config->get('feed_product_xml_image')
        && !empty($product['image'])
      ) {

        $image = $xml->createElement(
          'image',
          HTTPS_SERVER . 'image/' . $product['image']
        );

        $product_node->appendChild($image);
      }


      /*
      |------------------------------------------------------
      | Additional  Images
      |------------------------------------------------------
      */
      if ($this->config->get('feed_product_xml_additional_images')) {

        $images = $this->model_catalog_product->getProductImages($product['product_id']);

        if ($images) {

          $additional_images = $xml->createElement('additional_images');

          foreach ($images as $img) {

            if (!empty($img['image'])) {

              $image_node = $xml->createElement(
                'image',
                $this->config->get('config_ssl') . 'image/' . ltrim($img['image'], '/')
              );

              $additional_images->appendChild($image_node);
            }
          }

          $product_node->appendChild($additional_images);
        }
      }



      /*
      |------------------------------------------------------
      | Options
      |------------------------------------------------------
      */
      if ($this->config->get('feed_product_xml_options')) {
        $product_options = $this->model_catalog_product->getProductOptions($product['product_id']);
        if ($product_options) {
          $options_node = $xml->createElement('options');
          foreach ($product_options as $product_option) {
            $option_node = $xml->createElement('option');
            $option_node->setAttribute('name', $product_option['name']);
            if (!empty($product_option['product_option_value'])) {
              foreach ($product_option['product_option_value'] as $option_value) {
                $value_node = $xml->createElement('value');
                $value_node->appendChild($xml->createCDATASection($option_value['name']));
                $value_node->setAttribute('quantity', (int)$option_value['quantity']);
                $value_node->setAttribute('price', $option_value['price_prefix'] . number_format((float)$option_value['price'], 2, '.', ''));
                $option_node->appendChild($value_node);
              }
            } elseif (isset($product_option['value'])) {
              $option_node->appendChild($xml->createCDATASection($product_option['value']));
            }
            $options_node->appendChild($option_node);
          }
          $product_node->appendChild($options_node);
        }
      }

      /*
      |------------------------------------------------------
      | Categories
      |------------------------------------------------------
      */
      if ($this->config->get('feed_product_xml_categories')) {

        $categories = $this->db->query("
        SELECT c.category_id
        FROM " . DB_PREFIX . "product_to_category p2c
        LEFT JOIN " . DB_PREFIX . "category c 
            ON (p2c.category_id = c.category_id)
        WHERE p2c.product_id = '" . (int)$product['product_id'] . "'
    ");

        if ($categories->num_rows) {

          $categories_node = $xml->createElement('categories');

          foreach ($categories->rows as $category) {

            $category_path = $this->getCategoryPath($category['category_id']);

            if ($category_path) {

              $category_node = $xml->createElement('category');

              $category_node->appendChild(
                $xml->createCDATASection($category_path)
              );

              $categories_node->appendChild($category_node);
            }
          }

          $product_node->appendChild($categories_node);
        }
      }

      $products_node->appendChild($product_node);

    }

    $this->response->addHeader('Content-Type: application/xml; charset=utf-8');
    $this->response->setOutput($xml->saveXML());
  }
  private function getCategoryPath($category_id) {

    $path = array();

    $query = $this->db->query("
        SELECT c.category_id,
               cd.name,
               c.parent_id
        FROM " . DB_PREFIX . "category c
        LEFT JOIN " . DB_PREFIX . "category_description cd
            ON (c.category_id = cd.category_id)
        WHERE c.category_id = '" . (int)$category_id . "'
        AND cd.language_id = '" . (int)$this->config->get('config_language_id') . "'
    ");

    if ($query->num_rows) {

      $path[] = $query->row['name'];

      if ($query->row['parent_id']) {

        $parent = $this->getCategoryPath(
          $query->row['parent_id']
        );

        if ($parent) {
          $path[] = $parent;
        }
      }

      return implode(' > ', array_reverse($path));
    }

    return '';
  }

  private function formatNumber($value) {
    $formatted = rtrim(rtrim(number_format((float)$value, 8, '.', ''), '0'), '.');
    return $formatted === '' ? '0' : $formatted;
  }
}
