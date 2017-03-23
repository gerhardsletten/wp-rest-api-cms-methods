# WP REST API V2 CMS Methods

A Wordpress plugin that exposes some endpoint that makes it easier to use Wordpress as a backend for another app. Its great if you want to use wordpress as a backend for your Javascript SSR Appliction.

It also has a customizer panel where you can set the url to your frontend application, so wordpress editors can go directly to the page within the application with the preview-links in wp-admin.

## Custom endpoints

* `GET /wp-json/rest-cms-plugin/v1/page/` {path: '/'} => A page object by its path
* `GET /menu/(?P<location>[a-zA-Z0-9_-]+)` => A menu object by its identifier

### Page

```rest
GET /wp-json/rest-cms-plugin/v1/page/
{
  path: '/parent-page/sub-page'
}
```

Returns an object with these properties:

```js
{
  "id":       1,
  "title":    "..",
  "excerpt":  "..",
  "content":  "..",
  "image":    "..", // Pages feature image
  "type":     "..", // page-frontpage.php ==> "frontpage", or "default" if no custom page-template is used
  "url":      "..", // Path for page
  "path":     [], // List with ancestor pages including this page
  "fields":   {}, // Optional, if acf is installed, an object of the pages custom fields
  "children"  [], // Optional, with a filter you can conditionally choose to list the pages child-pages
}
```

#### Wordpress filters

`rest_cms_image_size` the size of the image for a page, default `large`

```php
add_filter('rest_cms_image_size', function ($size, $page_id) {
  return $size;
}, 10, 2);
```

`rest_cms_page_type` customize the pages type, defaults to the template-name without `page-` and `.php`, and `default` if no custom page-template is chosen for this page.

```php
add_filter('rest_cms_page_type', function ($type, $page_id) {
  return $type;
}, 10, 2);
```

`rest_cms_page_show_children` should this page display its children, defaults to `false`

```php
add_filter('rest_cms_page_show_children', function ($show, $type, $page_id) {
  return true;
}, 10, 3);
```

`rest_cms_page_children_options` options for displaying subpages, can override image-size and if you need some ACF-fields

```php
add_filter('rest_cms_page_children_options', function ($options, $type, $id) {
  return array(
    'image_size' => 'thumbnail',
    'fields' => array()
  );
}, 10, 3);
```

`rest_cms_subpage_item` customize the complete listing of an subpage

```php
add_filter('rest_cms_subpage_item', function ($item) {
  return $item;
}, 10, 3);
```

`rest_cms_page_json` customize the complete listing of an page

```php
add_filter('rest_cms_page_json', function ($json, $request) {
  return $json;
}, 10, 3);
```

### Menu

```rest
GET /wp-json/rest-cms-plugin/v1/menu/primary
```

Returns an object with these properties:

```js
{
  "name":    "..",  // The name you have given the menu
  "items":   [..]   // Its menu-items
}
```

#### Wordpress filters

`rest_cms_menu_item` customize the complete listing of a menu-item

```php
add_filter('rest_cms_menu_item', function ($link, $item) {
  return $link;
}, 10, 3);
```

`rest_cms_menu_json` customize the complete listing of a menu

```php
add_filter('rest_cms_menu_json', function ($json, $request) {
  return $json;
}, 10, 3);
```
