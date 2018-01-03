<?php

/**
 * Static JSON Object Class
 */

class StaticJSONObject {
  private $websiteOptions = [];

  /**
   * Construct the JSON Object
   */
  public function __construct($postId, $params = []) {
    $this->websiteOptions = $params['options'];
    $this->globalOptions = $params['globalOptions'];
    $this->language = $params['language'];

    $this->json_path = apply_filters(STATIC_JSON_PREFIX . 'json_path', wp_upload_dir()['basedir'] . DIRECTORY_SEPARATOR . 'static-json' . DIRECTORY_SEPARATOR);
  }

  /**
   * Output JSON
   */
  public function outputJSON() {
    $json_data = [];
    $site_settings_data = $this->getSiteSettingsData();
    $header_data = $this->generateHeader();
    $footer_data = $this->generateFooter();
    $pages_data = $this->generatePages();
    $post_types_data = $this->generatePostTypes();
    $static_translations_data = $this->getStaticTranslations();

    $json_data = [
      'site_settings' => $site_settings_data,
      'header' => $header_data,
      'footer' => $footer_data,
      'pages' => $pages_data,
      'translations' => $static_translations_data
    ];

    foreach ($post_types_data as $key => $data) {
      $json_data[$key] = $data;
    }

    $this->writeCompiledJsonToFile(json_encode($json_data));
  }

  /**
   * Load post types data and generate a JSON Object
   */
  private function generatePostTypes() {
      $post_types = apply_filters(STATIC_JSON_PREFIX . 'post_types', $this->websiteOptions['post_types']);
      $post_types_data = [];

      if ($post_types) {
        foreach ($post_types as $post_type) {
          if(($post_type === 'page') || ($post_type === 'post')) continue;

          $post_types_data[$post_type] = $this->getAllDataForPostType($post_type);
        };
      }

      return $post_types_data;
  }

  /**
   * Load pages data and generate a JSON Object
   */
  private function generatePages() {
    $pages_data = [];
    $page_id = "";
    $children = [];

    foreach ($this->websiteOptions['pages'] as $page) {
      if ($page->post_parent > 0) {
        if (sizeof($children[$page->post_parent]) === 0) {
          $children[$page->post_parent] = [];
        }

        $children[$page->post_parent][] = apply_filters(STATIC_JSON_PREFIX . 'post_type' . '_' . 'page', get_fields($page->ID), $page->ID);
      } else {
        $pages_data[$page->post_name] = apply_filters(STATIC_JSON_PREFIX . 'post_type' . '_' . 'page', get_fields($page->ID), $page->ID);
      }

    }

    foreach ($children as $parentId => $childPageObject) {
      $parentPage = get_post($parentId);
      $pages_data[$parentPage->post_name]['subpages'] = $childPageObject;
    }

    return $pages_data;
  }

  private function getAllDataForPostType($postType) {
    $data = [];

    if (post_type_exists($postType)) {
      $postTypeArgs = [
        'post_type' => $postType,
        'posts_per_page' => -1,
        'orderby' => 'menu_order',
        'order' => 'asc',
      ];

      $posts = new WP_Query($postTypeArgs);

      if ($posts->have_posts()) {
        while ($posts->have_posts()) {
          $posts->the_post();

          $data[] = apply_filters(STATIC_JSON_PREFIX . 'post_type' . '_' . $postType, get_fields($posts->post->ID), $posts->post->ID, $this->language);
        }
      }
    } else {
      $data = apply_filters(STATIC_JSON_PREFIX . 'post_type' . '_' . $postType, []);
    }


    return $data;
  }

  /**
   * Load data from the header options and generate a JSON Object
   */
  private function generateHeader() {
    $links = [];
    $headerData = $this->websiteOptions['header'];

    if ($headerData) {
      foreach ($headerData as $headerItem)
      {

        $link = [];

        $link['type'] = $headerItem['link_source'];
        $link['title'] = $headerItem['title'];

        // get the link url

        if(!empty($headerItem['internal_link']))
        {

        $link['url'] = apply_filters(STATIC_JSON_PREFIX . 'header_url', $headerItem['internal_link']->post_name, $this->language);

        }
        else if(!empty($headerItem['external_link'])){
          $link['url'] = $headerItem['external_link'];
        }else{
          $link['url'] = null;
        };
        array_push($links, $link);

      };
    }

    return $links;

  }

  /**
   * Load data from the footer options and generate a JSON Object
   */
  private function generateFooter() {
    $footerData = $this->websiteOptions['footer'];

    return $footerData;
  }

  private function getSiteSettingsData() {
    $site_settings_data = [
        'name' => get_option('blogname'),
        'description' => get_option('blogdescription'),
        'homepage_url' => ($this->language === 'en') ? get_option('home') : $this->language === 'zh-hans' ? get_option('home') . '/cn' : get_option('home') . '/' . $this->language,
        'site_url' => get_option('siteurl')
    ];

    $site_settings_data = array_merge($site_settings_data, apply_filters(STATIC_JSON_PREFIX . 'additional_site_settings', []));

    return $site_settings_data;
  }

  /**
   * Get the static translations data
   */

  private function getStaticTranslations() {
    $translations = get_field('translations', 'options');

    $formatted_data = [];

    if ($translations) {
      foreach ($translations as $translation) {
        $formatted_data[$translation['key']] = $translation['text'];
      }
    }

    return $formatted_data;
  }


  /**
   * Utility functions
   */

  private function writeCompiledJsonToFile($json_data)
  {
    $json_file_name = $this->language ? 'site-data_' . $this->language . '.json' : 'site-data.json';
    $json_upload_directory_name = '';
    $upload_base_path_url =$this->json_path;
    $upload_path_url = $this->json_path . $json_file_name;

    if($this->jsonFileUploadRules($json_file_name, $json_upload_directory_name, $upload_base_path_url))
    {
      file_put_contents($upload_path_url, $json_data);
    };
  }

  private function jsonFileUploadRules($json_data_file_name="site-data.json", $static_json_directory_name="static-json", $upload_folder_path)
  {
    $json_directory_location = $upload_folder_path . DIRECTORY_SEPARATOR. $static_json_directory_name . DIRECTORY_SEPARATOR;
    $json_file_location = $json_directory_location . $json_data_file_name;
    $json_upload_directory_exists = false;
    $json_file_exists = false;

    // checking if the upload directory exists, if not create one
    if (!file_exists($json_directory_location))
    {
      if(mkdir($json_directory_location, 0777)){
        $json_upload_directory_exists = true;
      };
    }else{
      $json_upload_directory_exists = true;
    };


    // checking if the required json file exists, if not create one
    if (!file_exists($json_file_location)) {
      $handle = fopen($json_file_location, 'w');
      if ($handle) {
        fclose($handle);
        $json_file_exists = true;
      };
    }else{
      $json_file_exists = true;
    };

    return (($json_upload_directory_exists && $json_file_exists) == true) ? true : false;
  }


}
