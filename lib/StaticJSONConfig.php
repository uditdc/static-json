<?php

/**
 * Static JSON Object Class
 */

class StaticJSONConfig {
  private $websiteOptions = [];

  /**
   * Construct the JSON Object
   */
  public function __construct($params = []) {
    $this->websiteOptions = $params['options'];

    // Set the JSON path
    $this->json_path = apply_filters(STATIC_JSON_PREFIX . 'json_path', wp_upload_dir()['basedir'] . DIRECTORY_SEPARATOR . 'static-json' . DIRECTORY_SEPARATOR);
  }

  /**
   * Output JSON
   */
  public function outputJSON() {
    $json_data = [];

    // Output Languages
    $languages = $this->getLanguages();
    $indicies = $this->getIndicies();

    $json_data = [
      'baseDir' => $this->websiteOptions['base_dir'],
      'htmlDir' => $this->websiteOptions['html_dir'],
      'languages' => $languages,
      'indicies' => $indicies
    ];

    file_put_contents($this->json_path . 'config.json', json_encode($json_data));
  }

  private function getLanguages() {
    $languages = [];

    if (function_exists('icl_object_id')) {
      $langs = icl_get_languages('skip_missing=N&orderby=KEY&order=DIR&link_empty_to=str');

      foreach ($langs as $lang) {
        $languagePath = $lang['active'] == '1' ? '' : $lang['code'] === 'zh-hans' ? '/cn' : '/' . $lang['code'];

        $languages[] = [
          'name' => $lang['native_name'],
          'slug' => $lang['code'],
          'path' => $languagePath,
          'jsonDataFile' => $this->json_path . 'site-data_' . $lang['code'] . '.json'
        ];
      }
    } else {
      $languages[] = [
        'name' => 'English',
        'slug' => 'en',
        'path' => '',
        'jsonDataFile' => $this->json_path . 'site-data.json'
      ];
    }

    return $languages;
  }

  private function getIndicies() {
    $indicies = [];
    $templateDir = $this->websiteOptions['base_dir'] . 'templates' . DIRECTORY_SEPARATOR;

    // Get Post Types
    $post_types = $this->websiteOptions['post_types'];
    foreach ($post_types as $type) {
      $type = $type === 'post' ? 'blog' : $type;

      $indicies[] = [
        'type' => 'POSTS',
        'map' => [],
        'slug' => $type,
        'template' => $templateDir . $type . DIRECTORY_SEPARATOR . 'single.pug',
      ];
    }

    // Get Pages
    $pages = $this->websiteOptions['pages'];
    $pageSlugs = [];

    foreach ($pages as $page) {
      if($page->ID == get_option('page_on_front')) continue;

      $indicies[] = [
        'type' => 'PAGES',
        'map' => [],
        'slug' => $page->post_name,
        'template' => $templateDir . $page->post_name . '.pug',
      ];
    }


    $indicies[] = [
      'type' => 'HOMEPAGE',
      'map' => [],
      'template' => $templateDir . 'index.pug',
    ];

    return $indicies;
  }
}
