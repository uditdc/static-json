<?php

define('STATIC_JSON_PREFIX', 'static_json_parse_');

/**
 * Generic Filters
 *
 * 1. URL Filter
 * 2. Image Filter
 * 3. Video Filter
 * 4. Taxonomy Filter
 *
 */

// URL Filter
add_filter(STATIC_JSON_PREFIX . 'url', function($permalink, $postId) {
  global $sitepress;

  $post = get_post($postId);
  $postType = get_post_type_object($post->post_type);

  $postTypeSlug = is_page() ? '' : $postType->rewrite['slug'];
  $postName = is_page() ? $postId === get_option('page_on_front') ? '' : $post->post_name : $post->post_name;


  $siteUrl = str_replace('cms', '', site_url());
  $language = '';

  if (function_exists('icl_object_id')) {
    $language = $sitepress->get_current_language();
  }

  $languagePath = $language === 'zh-hans' ? 'cn' . DIRECTORY_SEPARATOR : '';

  return $siteUrl . $languagePath . $postTypeSlug . DIRECTORY_SEPARATOR . $postName;
}, 10, 2);

// Image Filter
add_filter(STATIC_JSON_PREFIX . 'image', function($rawData, $size = 'full') {
  if(!$rawData['id']) return;

  $image = wp_get_attachment_image_src($rawData['id'], $size);
  $mediumImage = wp_get_attachment_image_src($rawData['id'], 'medium');

  if (!$rawData['alt']) {
    $rawData['alt'] = get_post_meta($rawData['id'], '_wp_attachment_image_alt', true);
  }

  $data = [
    'url' => $image[0],
    'width' => $image[1],
    'height' => $image[2],
    'alt' => $rawData['alt'],
    'caption' => $rawData['caption'],
    'thumbnail' => [
      'url' => $mediumImage[0],
      'width' => $mediumImage[1],
      'height' => $mediumImage[2],
    ]
  ];

  return $data;
}, 10, 2);

// File Filter
add_filter(STATIC_JSON_PREFIX . 'file', function($rawData) {
  if(!$rawData['id']) return $rawData;

  $data = [
    'id' => $rawData['id'],
    'name' => $rawData['filename'],
    'url' => $rawData['url'],
    'type' => $rawData['mime_type']
  ];

  return $data;
}, 10);

// Taxonomy Filter
add_filter(STATIC_JSON_PREFIX . 'taxonomy', function($terms, $taxonomy) {
  $data = [];

  if ($terms && !is_wp_error($terms)) {
    foreach ($terms as $term) {
      $termData = [
        'id' => $term->term_id,
        'name' => $term->name,
        'slug' => $term->slug
      ];

      $customFields = get_fields($taxonomy . '_' . $term->term_id);

      if ($customFields) {
        foreach ($customFields as $key => $value) {
          $termData[$key] = $value;
        }
      }

      $data[] = $termData;
    }
  }

  return $data;
}, 10, 2);

// Yoast SEO Filter
add_filter(STATIC_JSON_PREFIX . 'seo', function($postId){
   $default_post_data = get_post($postId);
   $seo_title = get_post_meta($postId, '_yoast_wpseo_title', true);
   $seo_slug = get_post_meta($postId, '_yoast_wpseo_slug', true);
   $open_graph_title = get_post_meta($postId, '_yoast_wpseo_opengraph-title', true);

   $seo_data = array(
      'title' => (!empty($seo_title)) ? $seo_title : $default_post_data->post_title,
      'slug' => (!empty($seo_slug)) ? $seo_slug : $default_post_data->post_name,
      'meta_description' => get_post_meta($postId, '_yoast_wpseo_metadesc', true),
      'og_title' => (!empty($open_graph_title)) ? $open_graph_title : $default_post_data->post_title,
      'og_description' => get_post_meta($postId, '_yoast_wpseo_opengraph-description', true),
      'og_image' => get_post_meta($postId, '_yoast_wpseo_opengraph-image', true)
    );

   return $seo_data;
});

// Repeater recurrsive parser
add_filter(STATIC_JSON_PREFIX . 'flexible_content', function($rawData, $postId, $parentKey) {
  $pageData = [];

  if ($rawData) {
    foreach ($rawData as $fieldData) {
      $pageData[] = apply_filters(STATIC_JSON_PREFIX . 'regular', $fieldData, $postId, $parentKey);
    }
  }

  return $pageData;
}, 10, 3);

// Repeater recurrsive parser
add_filter(STATIC_JSON_PREFIX . 'regular', function($rawData, $postId, $parentKey) {
  $pageData = [];

  if ($rawData) {
    foreach ($rawData as $fieldName => $fieldData) {
      $pageData[$fieldName] = [];

      if ($parentKey && isset($rawData['acf_fc_layout'])) {
        if ($fieldName == 'acf_fc_layout') {
          $pageData['acf_fc_layout'] = $fieldData;
          continue;
        }

        $field = get_field_object($parentKey, $postId);

        $layoutKey = array_search($rawData['acf_fc_layout'], array_column($field['layouts'], 'name'));
        $layouts = $field['layouts'][$layoutKey];

        $subFieldLayoutKey = array_search($fieldName, array_column($layouts['sub_fields'], 'name'));
        $field = $layouts['sub_fields'][$subFieldLayoutKey];
      } elseif ($parentKey && strpos($parentKey, '_field_')) {
        $parentKeySplit = explode('_field_', $parentKey);
        $parentKey = $parentKeySplit[1];

        $field = get_field_object('field_' . $parentKey, $postId);
        $subFieldLayoutKey = array_search($fieldName, array_column($field['sub_fields'], 'name'));

        $field = $field['sub_fields'][$subFieldLayoutKey];
      } else {
        $field = get_field_object($fieldName, $postId);
      }

      $fieldType = $field['type'];

      if ($fieldType == 'repeater') {
        $pageData[$fieldName] = apply_filters(STATIC_JSON_PREFIX . 'flexible_content', $fieldData, $postId, $field['key']);
      } else if ($fieldType == 'flexible_content') {
        $pageData[$fieldName] = apply_filters(STATIC_JSON_PREFIX . 'flexible_content', $fieldData, $postId, $field['key']);
      } else if ($fieldType == 'image') {
        $pageData[$fieldName] = apply_filters(STATIC_JSON_PREFIX . 'image', $fieldData, 'full');
      } else if ($fieldType == 'url') {
        // $pageData[$fieldName] = apply_filters(STATIC_JSON_PREFIX . 'url', $fieldData, $postId);
        $pageData[$fieldName] = $fieldData;
      } else if ($fieldType == 'taxonomy') {
        $taxonomyDataToSend = $fieldData;

        if (!is_array($fieldData)) {
          $taxonomyDataToSend = [$fieldData];
        }

        $pageData[$fieldName] = apply_filters(STATIC_JSON_PREFIX . 'taxonomy', $taxonomyDataToSend, $field['taxonomy']);
      } else if ($fieldType == 'gallery') {
        $pageData[$fieldName] = [];

        foreach ($fieldData as $galleryImage) {
          $pageData[$fieldName][] = apply_filters(STATIC_JSON_PREFIX . 'image', $galleryImage, 'full');
        }
      } else if ($fieldType == 'post_object') {
        if (is_array($fieldData)) {
          $pageData[$fieldName] = [];

          foreach ($fieldData as $fieldDataObject) {
            $pageData[$fieldName][] = apply_filters(STATIC_JSON_PREFIX . 'post_type_' . $fieldDataObject->post_type, get_fields($fieldDataObject->ID), $fieldDataObject->ID);
          }
        } else {
          $pageData[$fieldName] = apply_filters(STATIC_JSON_PREFIX . 'post_type_' . $fieldData->post_type, get_fields($fieldDataObject->ID), $fieldDataObject->ID);
        }
      } else {
        $pageData[$fieldName] = apply_filters(STATIC_JSON_PREFIX . $fieldType, $fieldData);
      }
    }
  }

  return $pageData;
}, 10, 3);

/**
 * Post Types Defination
 *
 * 1. Post
 * 2. Page
 * 3. Product
 */

add_filter(STATIC_JSON_PREFIX . 'post_type' . '_' . 'page', function($rawData, $postId) {
  $pageData['id'] = $postId;
  $pageData = array_merge($pageData, apply_filters(STATIC_JSON_PREFIX . 'regular', $rawData, $postId, null));
  $pageData['seo'] = apply_filters(STATIC_JSON_PREFIX . 'seo', $postId, null);

  $pageObject = get_post($postId);

  if ($pageObject) {
    $pageName = $pageObject->post_name;
    $pageUpdatedData = apply_filters(STATIC_JSON_PREFIX . 'page_' . $pageName, $pageData, $postId);

    if ($pageUpdatedData) {
      $pageData = $pageUpdatedData;
    }
  }

  return $pageData;
}, 10, 2);
