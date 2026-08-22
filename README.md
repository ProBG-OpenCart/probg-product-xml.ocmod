# ProBG Product XML Generator

**Current version: 2.1.3**  
**Platform: OpenCart 3.x**  
**Author: ProBG**

ProBG Product XML Generator generates configurable XML product feeds for OpenCart. The extension supports multiple independent feeds, category-based product filtering, configurable product fields, SEO-friendly feed URLs and multilingual product data.

## Main features

- Multiple independent XML feeds.
- Unique name and code for every feed.
- Enable/disable each feed separately.
- Export all active products or only products from selected categories.
- Category selection with autocomplete in the administration.
- Optional inclusion of products from subcategories.
- Optional inclusion/exclusion of out-of-stock products.
- `SELECT DISTINCT` product filtering so a product assigned to more than one selected category is exported only once.
- Direct XML URL and SEO-friendly XML URL for every feed.
- Individual enable/disable control for exported product fields.
- Main and additional product images with full URLs.
- Product categories with full category paths.
- Product options and option values.
- HTML description in CDATA with optional plain-text conversion.
- Bulgarian and English administration language files.
- Existing feed data is intentionally preserved when the extension is uninstalled.

## Exported product data

Product ID and product name form the core product identification. Depending on the settings of each feed, the XML can also contain:

- Description
- Meta Title
- Meta Description
- Meta Keywords
- Product Tags
- Product URL
- SEO URL keyword
- Price
- Special Price
- Main Image
- Additional Images
- Stock information
- Product Status
- Quantity
- Weight
- Length
- Width
- Height
- Manufacturer / Brand
- Model
- SKU
- UPC
- EAN
- JAN
- ISBN
- MPN
- Categories
- Product Options

## Category filtering

Each feed can have its own category selection.

- If **no categories are selected**, all active products are exported.
- If **one or more categories are selected**, only products assigned to those categories are exported.
- Product filtering uses `product_to_category` and `SELECT DISTINCT`, so the same product is not duplicated when it belongs to multiple selected categories.
- The feed can optionally include products from subcategories.

Category filtering controls **which products are exported**. The separate category output setting controls **whether the product categories are included as XML data**.

## Description and HTML

The `<description>` field is written inside CDATA.

When HTML output is enabled, the extension preserves useful HTML structure while allowing the description to be exported safely. When the **Plain Text Description** option is enabled, HTML tags are removed and only the text content is exported.

## Feed URLs

Each feed has a direct URL in the following format:

```text
index.php?route=extension/feed/product_xml&code=all-products
```

A feed with code `supplier-feed`, for example, is available directly at:

```text
index.php?route=extension/feed/product_xml&code=supplier-feed
```

## SEO-friendly XML URLs

Add the following rule to `.htaccess` immediately after `RewriteBase /`:

```apache
RewriteRule ^product-feed/([a-z0-9-]+)\.xml$ index.php?route=extension/feed/product_xml&code=$1 [L,QSA]
```

The feed will then be available as:

```text
https://example.com/product-feed/all-products.xml
```

or:

```text
https://example.com/product-feed/supplier-feed.xml
```

## Installation

1. Make a backup of the OpenCart files and database.
2. Upload the extension package from **Extensions → Installer**.
3. Open **Extensions → Extensions** and select **Feeds**.
4. Install **ProBG Product XML Generator**.
5. Open the extension and configure the required feeds.
6. If SEO-friendly feed URLs are required, add the supplied rewrite rule to `.htaccess`.
7. Refresh **Extensions → Modifications** when required by your OpenCart installation.

## Updating from 1.1.0

Version 2.0.0 introduced multiple independent feeds and dedicated database tables.

When upgrading from the legacy settings:

1. Make a backup of the files and database.
2. Upload the new package through **Extensions → Installer**.
3. Reinstall the feed extension from **Extensions → Extensions → Feeds**.
4. On the first installation of the 2.x structure, legacy settings are migrated into a feed with code `all-products`.
5. Existing data in the new feed tables is preserved on uninstall.
6. Refresh the modification cache.

---

# Version history

## 2.1.3

- Added product SEO URL keyword export as `seo_url`.
- SEO keyword is resolved for the current store and language from OpenCart's `seo_url` data.
- The full product URL remains available separately through the `url` field.

## 2.1.2

- Added Meta Keywords export as `meta_keyword`.
- Added product Tags export as `tags`.
- Both fields are exported in CDATA.
- Both fields can be enabled or disabled independently for each feed.

## 2.1.1

- Improved product description export with HTML support.
- Description is exported in CDATA.
- Added support for retaining useful HTML structure in descriptions.
- Added the option to convert the description to plain text by removing HTML.
- Improved description handling for external XML consumers.

## 2.1.0

Added additional product data fields, each configurable independently per feed:

- Meta Title (`meta_title`)
- Meta Description (`meta_description`)
- Product Status (`status`)
- Weight (`weight`)
- Length (`length`)
- Width (`width`)
- Height (`height`)

## 2.0.0

Major architecture update:

- Added support for multiple independent XML feeds.
- Added administration list for creating, editing and deleting feeds.
- Added a unique feed name and URL code.
- Added separate status for each feed.
- Added dedicated database tables for feed configuration and category relations.
- Added independent field settings for every feed.
- Added category filtering per feed.
- Added autocomplete category selection.
- Added direct URLs using the feed code.
- Added SEO-friendly URLs in the form `/product-feed/{code}.xml`.
- Added migration of legacy 1.x settings to the default `all-products` feed.
- Feed records are preserved intentionally during uninstall.

## 1.1.0

- Added category-based product filtering.
- Added administration selection of categories used to determine which products are exported.
- When no categories are selected, all active products are exported.
- Added duplicate protection for products assigned to multiple selected categories.
- Improved XML feed configuration and field selection.

## 1.0.0

Initial release of ProBG Product XML Generator.

- XML export of OpenCart products.
- Product ID and product name as core product data.
- Configurable export of description, product URL, price and special price.
- Main image and additional product images.
- Stock and quantity data.
- Manufacturer and model.
- SKU, UPC, EAN, JAN, ISBN and MPN identifiers.
- Product categories.
- Product options and option values.
- Enable/disable settings for optional XML fields.
- Direct XML feed route through OpenCart.

---

## Compatibility

The current repository version is developed for OpenCart 3.x. The existing project documentation specifically covers OpenCart 3.0.2.0, while the extension follows the OpenCart 3 feed-extension structure.

## License and support

Developed by **ProBG** for OpenCart stores and product-data integrations.

## Installation package

Upload `probg-product-xml-2.1.3.ocmod.zip` through **Extensions → Installer** and refresh the OCMOD modifications cache.

## Support development

If this module is useful to you, you can support its development through Revolut:

[![Buy me a coffee](https://img.shields.io/badge/Buy%20me%20a%20coffee-Revolut-0075EB?style=for-the-badge&logo=revolut&logoColor=white)](https://revolut.me/vtotev)
