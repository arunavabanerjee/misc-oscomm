<?php
/*
  $Id$

  osCommerce, Open Source E-Commerce Solutions
  http://www.oscommerce.com

  Copyright (c) 2010 osCommerce

  Released under the GNU General Public License
*/

////
// Parse search string into indivual objects
function tep_parse_search_string($search_str = '', &$objects) {
	$search_str = trim(strtolower($search_str));

	// Break up $search_str on whitespace; quoted string will be reconstructed later
	$pieces = preg_split('/[[:space:]]+/', $search_str);
	$objects = array();
	$tmpstring = '';
	$flag = '';

	$pieces_tmp = array();
	foreach ($pieces as $pic) {
		if (strlen($pic) > 2) {
			$pieces_tmp[] = $pic;
		}
	}
	$pieces = $pieces_tmp;

	for ($k=0; $k<count($pieces); $k++) {
		while (substr($pieces[$k], 0, 1) == '(') {
			$objects[] = '(';
			if (strlen($pieces[$k]) > 1) {
				$pieces[$k] = substr($pieces[$k], 1);
			} else {
				$pieces[$k] = '';
			}
		}

		$post_objects = array();

		while (substr($pieces[$k], -1) == ')')  {
			$post_objects[] = ')';
			if (strlen($pieces[$k]) > 1) {
				$pieces[$k] = substr($pieces[$k], 0, -1);
			} else {
				$pieces[$k] = '';
			}
		}

		// Check individual words

		if ( (substr($pieces[$k], -1) != '"') && (substr($pieces[$k], 0, 1) != '"') ) {
			$objects[] = trim($pieces[$k]);

			for ($j=0; $j<count($post_objects); $j++) {
				$objects[] = $post_objects[$j];
			}
		} else {
			/* This means that the $piece is either the beginning or the end of a string.
			 So, we'll slurp up the $pieces and stick them together until we get to the
			end of the string or run out of pieces.
			*/

			// Add this word to the $tmpstring, starting the $tmpstring
			$tmpstring = trim(preg_replace('/"/', ' ', $pieces[$k]));

			// Check for one possible exception to the rule. That there is a single quoted word.
			if (substr($pieces[$k], -1 ) == '"') {
				// Turn the flag off for future iterations
				$flag = 'off';

				$objects[] = trim(preg_replace('/"/', ' ', $pieces[$k]));

				for ($j=0; $j<count($post_objects); $j++) {
					$objects[] = $post_objects[$j];
				}

				unset($tmpstring);

				// Stop looking for the end of the string and move onto the next word.
				continue;
			}

			// Otherwise, turn on the flag to indicate no quotes have been found attached to this word in the string.
			$flag = 'on';

			// Move on to the next word
			$k++;

			// Keep reading until the end of the string as long as the $flag is on

			while ( ($flag == 'on') && ($k < count($pieces)) ) {
				while (substr($pieces[$k], -1) == ')') {
					$post_objects[] = ')';
					if (strlen($pieces[$k]) > 1) {
						$pieces[$k] = substr($pieces[$k], 0, -1);
					} else {
						$pieces[$k] = '';
					}
				}

				// If the word doesn't end in double quotes, append it to the $tmpstring.
				if (substr($pieces[$k], -1) != '"') {
					// Tack this word onto the current string entity
					$tmpstring .= ' ' . $pieces[$k];

					// Move on to the next word
					$k++;
					continue;
				} else {
					/* If the $piece ends in double quotes, strip the double quotes, tack the
					 $piece onto the tail of the string, push the $tmpstring onto the $haves,
					kill the $tmpstring, turn the $flag "off", and return.
					*/
					$tmpstring .= ' ' . trim(preg_replace('/"/', ' ', $pieces[$k]));

					// Push the $tmpstring onto the array of stuff to search for
					$objects[] = trim($tmpstring);

					for ($j=0; $j<count($post_objects); $j++) {
						$objects[] = $post_objects[$j];
					}

					unset($tmpstring);

					// Turn off the flag to exit the loop
					$flag = 'off';
				}
			}
		}
	}

	// add default logical operators if needed
	$temp = array();
	for($i=0; $i<(count($objects)-1); $i++) {
		$temp[] = $objects[$i];
		if ( ($objects[$i] != 'and') &&
		($objects[$i] != 'or') &&
		($objects[$i] != '(') &&
		($objects[$i+1] != 'and') &&
		($objects[$i+1] != 'or') &&
		($objects[$i+1] != ')') ) {
			$temp[] = ADVANCED_SEARCH_DEFAULT_OPERATOR;
		}
	}
	$temp[] = $objects[$i];
	$objects = $temp;

	$keyword_count = 0;
	$operator_count = 0;
	$balance = 0;
	for($i=0; $i<count($objects); $i++) {
		if ($objects[$i] == '(') $balance --;
		if ($objects[$i] == ')') $balance ++;
		if ( ($objects[$i] == 'and') || ($objects[$i] == 'or') ) {
			$operator_count ++;
		} elseif ( ($objects[$i]) && ($objects[$i] != '(') && ($objects[$i] != ')') ) {
			$keyword_count ++;
		}
	}

	if ( ($operator_count < $keyword_count) && ($balance == 0) ) {
		return true;
	} else {
		return false;
	}
}

  require('includes/application_top.php');
  
  if (isset($HTTP_GET_VARS['search'])) {
  	$HTTP_GET_VARS['search'] = stripslashes(urldecode($HTTP_GET_VARS['search']));
  	$HTTP_GET_VARS['search'] = trim($HTTP_GET_VARS['search']);
  	$url_search = urlencode($HTTP_GET_VARS['search']);
  }
  
  define('MAX_PROD_ADMIN_SIDE', 25);

  require(DIR_WS_CLASSES . 'currencies.php');
  $currencies = new currencies();

  $action = (isset($HTTP_GET_VARS['action']) ? $HTTP_GET_VARS['action'] : '');
//BOF Admin product paging
  $page = (isset($HTTP_GET_VARS['page']) ? $HTTP_GET_VARS['page'] : '1');
//EOF Admin product paging
  
  // Modular SEO Header Tags
  // copied because of bug
  include( DIR_WS_MODULES . 'header_tags/categories_products_process.php' );
  
  // begin Extra Product Fields
  function get_exclude_list($value_id) {
    $exclude_list = array();
    $query = tep_db_query('select value_id1 from ' . TABLE_EPF_EXCLUDE . ' where value_id2 = ' . (int)$value_id);
    while ($check = tep_db_fetch_array($query)) {
      $exclude_list[] = $check['value_id1'];
    }
    $query = tep_db_query('select value_id2 from ' . TABLE_EPF_EXCLUDE . ' where value_id1 = ' . (int)$value_id);
    while ($check = tep_db_fetch_array($query)) {
      $exclude_list[] = $check['value_id2'];
    }
    return $exclude_list;
  }
  function get_children($value_id) {
    return explode(',', $value_id . tep_list_epf_children($value_id));
  }
  function get_parent_list($value_id) {
    $sql = tep_db_query("select parent_id from " . TABLE_EPF_VALUES . " where value_id = " . (int)$value_id);
    $value = tep_db_fetch_array($sql);
    if ($value['parent_id'] > 0) {
      return get_parent_list($value['parent_id']) . ',' . $value_id;
    } else {
      return $value_id;
    }
  }
  function get_category_children($parent_id) {
    $cat_list = array($parent_id);
    $query = tep_db_query('select categories_id from ' . TABLE_CATEGORIES . ' where parent_id = ' . (int)$parent_id);
    while ($cat = tep_db_fetch_array($query)) {
      $children = get_category_children($cat['categories_id']);
      $cat_list = array_merge($cat_list, $children);
    }
    return $cat_list;
  }
  // get categories for current product
  $product_categories = array($HTTP_GET_VARS['cPath']);
  if (($action == 'new_product') && isset($HTTP_GET_VARS['pID'])) {
    $query = tep_db_query('select categories_id from ' . TABLE_PRODUCTS_TO_CATEGORIES . ' where products_id = ' . (int)$HTTP_GET_VARS['pID']);
    while ($cat = tep_db_fetch_array($query)) {
      $product_categories[] = $cat['categories_id'];
    }    
  }
  $epf_query = tep_db_query("select * from " . TABLE_EPF . " e join " . TABLE_EPF_LABELS . " l where (e.epf_status or e.epf_show_in_admin) and (e.epf_id = l.epf_id) order by e.epf_order");
  $epf = array();
  $xfields = array();
  $link_groups = array();
  $linked_fields = array();
  while ($e = tep_db_fetch_array($epf_query)) {  // retrieve all active extra fields for all languages
    $field = 'extra_value';
    if ($e['epf_uses_value_list']) {
      if ($e['epf_multi_select']) {
        $field .= '_ms';
      } else {
        $field .= '_id';
      }
    }
    $field .= $e['epf_id'];
    $values = '';
    if ($e['epf_uses_value_list'] && $e['epf_active_for_language'] && ($e['epf_has_linked_field'] || $e['epf_multi_select'])) { // if field requires javascript during entry
      $values = array();
      $value_query = tep_db_query('select value_id, value_depends_on from ' . TABLE_EPF_VALUES . ' where epf_id = ' . (int)$e['epf_id'] . ' and languages_id = ' . (int)$e['languages_id']);
      while ($v = tep_db_fetch_array($value_query)) {
        $values[] = $v['value_id'];
        if ($e['epf_has_linked_field'] && $e['epf_multi_select'] && ($v['value_depends_on'] != 0)) {
          $linked_fields[$e['epf_links_to']][$e['languages_id']][$v['value_depends_on']][] = $v['value_id'];
          if (!in_array($v['value_depends_on'], $link_groups[$e['epf_links_to']][$e['languages_id']])) $link_groups[$e['epf_links_to']][$e['languages_id']][] = $v['value_depends_on'];
        }
      }
    }
    if ($e['epf_all_categories']) {
      $hidden_field = false;
    } else {
      $hidden_field = true;
      $base_categories = explode('|', $e['epf_category_ids']);
      $all_epf_categories = array();
      foreach ($base_categories as $cat) {
        $children = get_category_children($cat);
        $all_epf_categories = array_merge($all_epf_categories, $children);
      }
      foreach ($all_epf_categories as $cat) {
        if (in_array($cat, $product_categories)) $hidden_field = false;
      }
    }
    $epf[] = array('id' => $e['epf_id'],
                   'label' => $e['epf_label'],
                   'uses_list' => $e['epf_uses_value_list'],
                   'multi_select' => $e['epf_multi_select'],
                   'show_chain' => $e['epf_show_parent_chain'],
                   'checkbox' => $e['epf_checked_entry'],
                   'display_type' => $e['epf_value_display_type'],
                   'columns' => $e['epf_num_columns'],
                   'linked' => $e['epf_has_linked_field'],
                   'links_to' => $e['epf_links_to'],
                   'size' => $e['epf_size'],
                   'language' => $e['languages_id'],
                   'language_active' => $e['epf_active_for_language'],
                   'values' => $values,
                   'textarea' => $e['epf_textarea'],
                   'field' => $field,
                   'hidden' => $hidden_field);
    if (!in_array( $field, $xfields))
      $xfields[] = $field; // build list of distinct fields    
  }
// end Extra Product Fields
  
  
  // Ultimate SEO URLs v2.2d
  // If the action will affect the cache entries
  if ( preg_match("/(insert|update|setflag)/i", $action) ) include_once('includes/reset_seo_cache.php');
  

  if (tep_not_null($action)) {
    switch ($action) {
      case 'remove_main_image':
      	 $products_id = (int)$_GET['pID'];
      	 $product_image_query = tep_db_query("select products_image from " . TABLE_PRODUCTS . " where products_id = '" . $products_id . "'");
      	 $product_image = tep_db_fetch_array($product_image_query);
      	 $duplicate_image_query = tep_db_query("select count(*) as total from " . TABLE_PRODUCTS . " where products_image = '" . tep_db_input($product_image['products_image']) . "'");
      	 $duplicate_image = tep_db_fetch_array($duplicate_image_query);
      	 
      	 if ($duplicate_image['total'] < 2) {
      	 	if (file_exists(DIR_FS_CATALOG_IMAGES . $product_image['products_image'])) {
      	 		@unlink(DIR_FS_CATALOG_IMAGES . $product_image['products_image']);
      	 	}
      	 }
      	 
      	 tep_db_query('update ' . TABLE_PRODUCTS . ' set products_image = "image_coming_soon.jpg" where products_id = "' . $products_id  . '"');
      	 
      	 break;
      case 'setflag':
        if ( ($HTTP_GET_VARS['flag'] == '0') || ($HTTP_GET_VARS['flag'] == '1') ) {
          if (isset($HTTP_GET_VARS['pID'])) {
            tep_set_product_status($HTTP_GET_VARS['pID'], $HTTP_GET_VARS['flag']);
          }
        }
//BOF Admin product paging
        if (isset($_GET['search'])) {
            tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'page=' . $page . '&cPath=' . $HTTP_GET_VARS['cPath'] . '&pID=' . $HTTP_GET_VARS['pID']) . '&search=' . $url_search);
        } else {
            tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'page=' . $page . '&cPath=' . $HTTP_GET_VARS['cPath'] . '&pID=' . $HTTP_GET_VARS['pID']));
        }
//EOF Admin product paging

        //tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $HTTP_GET_VARS['cPath'] . '&pID=' . $HTTP_GET_VARS['pID']));
        break;
      case 'setretailflag':
        if ( ($HTTP_GET_VARS['flag'] == '0') || ($HTTP_GET_VARS['flag'] == '1') ) {
          if (isset($HTTP_GET_VARS['pID'])) {
            tep_set_product_retail_status($HTTP_GET_VARS['pID'], $HTTP_GET_VARS['flag']);
          }
        }
//BOF Admin product paging
        if (isset($_GET['search'])) {
            tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'page=' . $page . '&cPath=' . $HTTP_GET_VARS['cPath'] . '&pID=' . $HTTP_GET_VARS['pID']) . '&search=' . $url_search);
        } else {
            tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'page=' . $page . '&cPath=' . $HTTP_GET_VARS['cPath'] . '&pID=' . $HTTP_GET_VARS['pID']));
        }
//EOF Admin product paging

        //tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $HTTP_GET_VARS['cPath'] . '&pID=' . $HTTP_GET_VARS['pID']));
        break;
      case 'setdropshipflag':
        	if ( ($HTTP_GET_VARS['flag'] == '0') || ($HTTP_GET_VARS['flag'] == '1') ) {
        		if (isset($HTTP_GET_VARS['pID'])) {
        			tep_set_product_dropship_status($HTTP_GET_VARS['pID'], $HTTP_GET_VARS['flag']);
        		}
        	}
        	//BOF Admin product paging
        	if (isset($_GET['search'])) {
        		tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'page=' . $page . '&cPath=' . $HTTP_GET_VARS['cPath'] . '&pID=' . $HTTP_GET_VARS['pID']) . '&search=' . $url_search);
        	} else {
        		tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'page=' . $page . '&cPath=' . $HTTP_GET_VARS['cPath'] . '&pID=' . $HTTP_GET_VARS['pID']));
        	}
        	//EOF Admin product paging
        
        	//tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $HTTP_GET_VARS['cPath'] . '&pID=' . $HTTP_GET_VARS['pID']));
        	break;
      case 'setcategoryflag':
        if ( ($HTTP_GET_VARS['flag'] == '0') || ($HTTP_GET_VARS['flag'] == '1') ) {
          if (isset($HTTP_GET_VARS['cID'])) {
            tep_set_category_status($HTTP_GET_VARS['cID'], $HTTP_GET_VARS['flag']);
          }

          if (USE_CACHE == 'true') {
            tep_reset_cache_block('categories');
            tep_reset_cache_block('also_purchased');
          }
        }

        tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $HTTP_GET_VARS['cPath'] . '&cID=' . $HTTP_GET_VARS['cID']));
        break;
      case 'setretailcategoryflag':
        if ( ($HTTP_GET_VARS['flag'] == '0') || ($HTTP_GET_VARS['flag'] == '1') ) {
          if (isset($HTTP_GET_VARS['cID'])) {
            tep_set_retail_category_status($HTTP_GET_VARS['cID'], $HTTP_GET_VARS['flag']);
          }

          if (USE_CACHE == 'true') {
            tep_reset_cache_block('categories');
            tep_reset_cache_block('also_purchased');
          }
        }

        tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $HTTP_GET_VARS['cPath'] . '&cID=' . $HTTP_GET_VARS['cID']));
        break;
      case 'insert_category':
      case 'update_category':
        if (isset($HTTP_POST_VARS['categories_id'])) $categories_id = tep_db_prepare_input($HTTP_POST_VARS['categories_id']);
        $sort_order = tep_db_prepare_input($HTTP_POST_VARS['sort_order']);

        $sql_data_array = array('sort_order' => (int)$sort_order);

        if ($action == 'insert_category') {
          $insert_sql_data = array('parent_id' => $current_category_id,
                                   'date_added' => 'now()');

          $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

          tep_db_perform(TABLE_CATEGORIES, $sql_data_array);

          $categories_id = tep_db_insert_id();
        } elseif ($action == 'update_category') {
          $update_sql_data = array('last_modified' => 'now()');

          $sql_data_array = array_merge($sql_data_array, $update_sql_data);

          tep_db_perform(TABLE_CATEGORIES, $sql_data_array, 'update', "categories_id = '" . (int)$categories_id . "'");
        }

        $languages = tep_get_languages();
        for ($i=0, $n=sizeof($languages); $i<$n; $i++) {
          $categories_name_array = $HTTP_POST_VARS['categories_name'];

          $language_id = $languages[$i]['id'];

          $sql_data_array = array('categories_name' => tep_db_prepare_input($categories_name_array[$language_id]));

          if ($action == 'insert_category') {
            $insert_sql_data = array('categories_id' => $categories_id,
                                     'language_id' => $languages[$i]['id']);

            $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

            tep_db_perform(TABLE_CATEGORIES_DESCRIPTION, $sql_data_array);
          } elseif ($action == 'update_category') {
            tep_db_perform(TABLE_CATEGORIES_DESCRIPTION, $sql_data_array, 'update', "categories_id = '" . (int)$categories_id . "' and language_id = '" . (int)$languages[$i]['id'] . "'");
          }
        }

        $categories_image = new upload('categories_image');
        $categories_image->set_destination(DIR_FS_CATALOG_IMAGES);

        if ($categories_image->parse() && $categories_image->save()) {
          tep_db_query("update " . TABLE_CATEGORIES . " set categories_image = '" . tep_db_input($categories_image->filename) . "' where categories_id = '" . (int)$categories_id . "'");
        }

        if (USE_CACHE == 'true') {
          tep_reset_cache_block('categories');
          tep_reset_cache_block('also_purchased');
        }

        //tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&cID=' . $categories_id));
		tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'page=' . $page . '&cPath=' . $cPath . '&cID=' . $categories_id));
        break;
      case 'delete_category_confirm':
        if (isset($HTTP_POST_VARS['categories_id'])) {
          $categories_id = tep_db_prepare_input($HTTP_POST_VARS['categories_id']);

          $categories = tep_get_category_tree($categories_id, '', '0', '', true);
          $products = array();
          $products_delete = array();

          for ($i=0, $n=sizeof($categories); $i<$n; $i++) {
            $product_ids_query = tep_db_query("select products_id from " . TABLE_PRODUCTS_TO_CATEGORIES . " where categories_id = '" . (int)$categories[$i]['id'] . "'");

            while ($product_ids = tep_db_fetch_array($product_ids_query)) {
              $products[$product_ids['products_id']]['categories'][] = $categories[$i]['id'];
            }
          }

          reset($products);
          while (list($key, $value) = each($products)) {
            $category_ids = '';

            for ($i=0, $n=sizeof($value['categories']); $i<$n; $i++) {
              $category_ids .= "'" . (int)$value['categories'][$i] . "', ";
            }
            $category_ids = substr($category_ids, 0, -2);

            $check_query = tep_db_query("select count(*) as total from " . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . (int)$key . "' and categories_id not in (" . $category_ids . ")");
            $check = tep_db_fetch_array($check_query);
            if ($check['total'] < '1') {
              $products_delete[$key] = $key;
            }
          }

// removing categories can be a lengthy process
          tep_set_time_limit(0);

          reset($products_delete);
          while (list($key) = each($products_delete)) {
            //tep_remove_product($key);
            tep_move_product_to_parent_category($key, $categories_id, $_POST['cPath']);
          }
          
          for ($i=0, $n=sizeof($categories); $i<$n; $i++) {
          	tep_remove_category($categories[$i]['id']);
          }
        }

        if (USE_CACHE == 'true') {
          tep_reset_cache_block('categories');
          tep_reset_cache_block('also_purchased');
        }

        //tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath));
		tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'page=' . $page . '&cPath=' . $cPath));
        break;
      case 'delete_product_confirm': //var_dump($HTTP_POST_VARS); 
        if (isset($HTTP_POST_VARS['products_id']) && isset($HTTP_POST_VARS['product_categories']) && is_array($HTTP_POST_VARS['product_categories'])) {
          $product_id = tep_db_prepare_input($HTTP_POST_VARS['products_id']);
          $product_categories = $HTTP_POST_VARS['product_categories'];

          for ($i=0, $n=sizeof($product_categories); $i<$n; $i++) {
            tep_db_query("delete from " . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . (int)$product_id . "' and categories_id = '" . (int)$product_categories[$i] . "'");
          }

          $product_categories_query = tep_db_query("select count(*) as total from " . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . (int)$product_id . "'");
          $product_categories = tep_db_fetch_array($product_categories_query);
          
          tep_db_query("delete from " . TABLE_PROMOTIONS . " where products_id = '" . tep_db_input($product_id) . "' ");

          if ($product_categories['total'] == '0') {
	    /*delete slug, so it does not cause error in deletion*/
	    tep_db_query("delete from products_slug where id = '" . tep_db_input($product_id) . "' ");
            tep_remove_product($product_id);
            // BOF Separate Pricing Per Customer
            tep_db_query("delete from " . TABLE_PRODUCTS_GROUPS . " where products_id = '" . tep_db_input($product_id) . "' ");
            // EOF Separate Pricing Per Customer
          }
        }
        if (isset($HTTP_POST_VARS['products_id']) && isset($HTTP_GET_VARS['search'])) {
        	$product_id = tep_db_prepare_input($HTTP_POST_VARS['products_id']);
        	$product_categories = $HTTP_POST_VARS['product_categories'];
        
        	for ($i=0, $n=sizeof($product_categories); $i<$n; $i++) {
        		tep_db_query("delete from " . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . (int)$product_id . "' and categories_id = '" . (int)$product_categories[$i] . "'");
        	}
        
        	$product_categories_query = tep_db_query("select count(*) as total from " . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . (int)$product_id . "'");
        	$product_categories = tep_db_fetch_array($product_categories_query);
        
        	tep_db_query("delete from " . TABLE_PROMOTIONS . " where products_id = '" . tep_db_input($product_id) . "' ");
        
        	if ($product_categories['total'] == '0') {
        		tep_remove_product($product_id);
        		// BOF Separate Pricing Per Customer
        		tep_db_query("delete from " . TABLE_PRODUCTS_GROUPS . " where products_id = '" . tep_db_input($product_id) . "' ");
        		// EOF Separate Pricing Per Customer
        	}
        }

	$messageStack->add_session('Success: Product With ID: '.$HTTP_POST_VARS['products_id'].' Has Been Deleted.', 'success'); 

        if (USE_CACHE == 'true') {
          tep_reset_cache_block('categories');
          tep_reset_cache_block('also_purchased');
        }
        
        $search = '';
        if (isset($_GET['search'])) {
        	$search .= '&search=' . $url_search . '&page=' . $page;
        }

        //tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath));
	tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'page=' . $page . '&cPath=' . $cPath . $search));

        break;
      case 'move_category_confirm':
        if (isset($HTTP_POST_VARS['categories_id']) && ($HTTP_POST_VARS['categories_id'] != $HTTP_POST_VARS['move_to_category_id'])) {
          $categories_id = tep_db_prepare_input($HTTP_POST_VARS['categories_id']);
          $new_parent_id = tep_db_prepare_input($HTTP_POST_VARS['move_to_category_id']);

          $path = explode('_', tep_get_generated_category_path_ids($new_parent_id));

          if (in_array($categories_id, $path)) {
            $messageStack->add_session(ERROR_CANNOT_MOVE_CATEGORY_TO_PARENT, 'error');

            tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&cID=' . $categories_id));
          } else {
            tep_db_query("update " . TABLE_CATEGORIES . " set parent_id = '" . (int)$new_parent_id . "', last_modified = now() where categories_id = '" . (int)$categories_id . "'");

            if (USE_CACHE == 'true') {
              tep_reset_cache_block('categories');
              tep_reset_cache_block('also_purchased');
            }

            tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $new_parent_id . '&cID=' . $categories_id));
          }
        }

        break;
      case 'move_product_confirm':
        $products_id = tep_db_prepare_input($HTTP_POST_VARS['products_id']);
        $new_parent_id = tep_db_prepare_input($HTTP_POST_VARS['move_to_category_id']);

        $duplicate_check_query = tep_db_query("select count(1) as total from " . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . (int)$products_id . "' and categories_id = '" . (int)$new_parent_id . "'");
        $duplicate_check = tep_db_fetch_array($duplicate_check_query);
        if ($duplicate_check['total'] < 1) tep_db_query("update " . TABLE_PRODUCTS_TO_CATEGORIES . " set categories_id = '" . (int)$new_parent_id . "' where products_id = '" . (int)$products_id . "' and categories_id = '" . (int)$current_category_id . "'");

        if (USE_CACHE == 'true') {
          tep_reset_cache_block('categories');
          tep_reset_cache_block('also_purchased');
        }

        tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $new_parent_id . '&pID=' . $products_id));
        break;
      case 'move_product_outofstock':
        $products_id = tep_db_prepare_input($HTTP_GET_VARS['pID']);
        $new_parent_id = tep_db_prepare_input($HTTP_GET_VARS['move_to_category_id']);

        $duplicate_check_query = tep_db_query("select count(1) as total from " . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . (int)$products_id . "' and categories_id = '" . (int)$new_parent_id . "'");
        $duplicate_check = tep_db_fetch_array($duplicate_check_query);
        if ($duplicate_check['total'] < 1){ 
	  tep_db_query("update " . TABLE_PRODUCTS_TO_CATEGORIES . " set categories_id = '" . (int)$new_parent_id . "' where products_id = '" . (int)$products_id . "' and categories_id = '" . (int)$current_category_id . "'"); 

         $option_check_query = tep_db_query("select count(1) as total from " . TABLE_PRODUCTS_ATTRIBUTES . " where products_id = '" . (int)$products_id . "'"); $option_check = tep_db_fetch_array($option_check_query); 
         if ($option_check['total'] >= 1){ 
	  tep_db_query("update " . TABLE_PRODUCTS . " set products_quantity = '" . (int)0 . "' where products_id = '" . (int)$products_id . "'"); 
	  tep_db_query("update " . TABLE_PRODUCTS_ATTRIBUTES . " set quantity = '" . (int)0 ."' where products_id = '" . (int)$products_id. "'");
           $messageStack->add_session('Success: Product With Options Moved to OutOfStock', 'success'); 
         } else {
           $messageStack->add_session('Success: Product Moved to OutOfStock', 'success');
         }

         if (USE_CACHE == 'true') {
          tep_reset_cache_block('categories');
          tep_reset_cache_block('also_purchased');
         }

         tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $new_parent_id . '&pID=' . $products_id));
	}

        break;
      case 'send_to_amazon':
        $products_id = tep_db_prepare_input($HTTP_GET_VARS['pID']);
        $search = '';
        if (isset($_GET['search'])) {
              $search .= '&search=' . $url_search . '&page=' . $page;
        }

        tep_db_query('insert into queue_sync_amazon (products_id, status, created_at) values ('.$products_id.', 1, '.time().')');

        tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'page=' . $page . '&pID='.$products_id.'&cPath=' . $cPath . $search));
        break;
      case 'insert_product':
      case 'update_product':
        if (isset($HTTP_GET_VARS['pID'])) $products_id = tep_db_prepare_input($HTTP_GET_VARS['pID']);
        $products_date_available = tep_db_prepare_input($HTTP_POST_VARS['products_date_available']);
        
        if ($action == 'update_product') {
        	if (empty($HTTP_POST_VARS['products_model'])) {
        		if (isset($HTTP_GET_VARS['search'])) {
        			tep_redirect(tep_href_link(FILENAME_CATEGORIES,  'page=' . $page . '&search=' . $url_search . '&pID=' . $products_id . '&error_msg=Invalid data. Product not updated'));
        		}
        		elseif (isset($_GET['mID'])) {
        			tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'mID=' . $_GET['mID'] . '&pID=' . $products_id . '&action=view_manufacturer_products' . '&error_msg=Invalid data. Product not updated'));
        		}
        		else {
        			//find the real place
        			tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&pID=' . $products_id . '&error_msg=Invalid data. Product not updated'));
        		}
        		break;
        	}
        }

        $products_date_available = (date('Y-m-d') < $products_date_available) ? $products_date_available : 'null';
        
        $ignore_status = 0;
        if (isset($_POST['ignore_status'])) {
        	$ignore_status = 1;
        	$HTTP_POST_VARS['products_status'] = 1;
        }
        
        $product_quantity = (int)tep_db_prepare_input($HTTP_POST_VARS['products_quantity']);
        if (isset($products_id)) {
	        $product_attributes_stock_sum_q = tep_db_query('select sum(quantity) as total from ' . TABLE_PRODUCTS_ATTRIBUTES . ' where products_id = ' . $products_id);
	        $product_attributes_stock_sum = tep_db_fetch_array($product_attributes_stock_sum_q);
	        $product_attributes_stock_sum = $product_attributes_stock_sum['total'];        
	        if ($product_attributes_stock_sum) {
	        	$product_quantity = $product_attributes_stock_sum;
	        }
        }

        $sql_data_array = array('products_quantity' => $product_quantity,
                                'products_model' => tep_db_prepare_input($HTTP_POST_VARS['products_model']),
                                'products_price' => tep_db_prepare_input($HTTP_POST_VARS['products_price']),
                                'products_date_available' => $products_date_available,
                                'products_weight' => (float)tep_db_prepare_input($HTTP_POST_VARS['products_weight']),
                                'products_status' => tep_db_prepare_input($HTTP_POST_VARS['products_status']),
        			'products_dropship_status' => tep_db_prepare_input($HTTP_POST_VARS['products_status']),
        			'products_retail_status' => tep_db_prepare_input($HTTP_POST_VARS['products_status']),
        			'products_new' => tep_db_prepare_input($HTTP_POST_VARS['products_new']),
                                'products_tax_class_id' => tep_db_prepare_input($HTTP_POST_VARS['products_tax_class_id']),
                                'manufacturers_id' => (int)tep_db_prepare_input($HTTP_POST_VARS['manufacturers_id']),
        			'manufacturers_id2' => (int)tep_db_prepare_input($HTTP_POST_VARS['manufacturers_id2']),
        			'last_modified_by' => $admin['username'],
        			'ignore_status' => $ignore_status);
       	if ($product_quantity == 0 && !$ignore_status) {
       		$sql_data_array['products_status'] = 0;
       		$sql_data_array['products_retail_status'] = 0;
       		$sql_data_array['products_dropship_status'] = 0;       		
       	}

        $products_image = new upload('products_image');
        $products_image->set_destination(DIR_FS_CATALOG_IMAGES);
        if ($products_image->parse()) {
        	$imageFilenameTmp = explode('.', $products_image->filename);
        	$imageExt = $imageFilenameTmp[count($imageFilenameTmp) - 1];        	
	        $products_image->set_filename(substr(strtolower(preg_replace('/[^a-zA-Z0-9]/s', '_', $HTTP_POST_VARS['products_name'][1])), 0, 10) . time() . '.' . $imageExt);
	        if ($products_image->save()) {
	          $sql_data_array['products_image'] = tep_db_prepare_input($products_image->filename);
	        }
        }

        if ($action == 'insert_product') {
          $insert_sql_data = array('products_date_added' => 'now()');

          $sql_data_array = array_merge($sql_data_array, $insert_sql_data);

          tep_db_perform(TABLE_PRODUCTS, $sql_data_array);
          $products_id = tep_db_insert_id();

          tep_db_query("insert into " . TABLE_PRODUCTS_TO_CATEGORIES . " (products_id, categories_id) values ('" . (int)$products_id . "', '" . (int)$current_category_id . "')");
          // BOF Separate Pricing Per Customer
		  $customers_group_query = tep_db_query("select customers_group_id, customers_group_name from " . TABLE_CUSTOMERS_GROUPS . " where customers_group_id != '0' order by customers_group_id");
		  while ($customers_group = tep_db_fetch_array($customers_group_query)) // Gets all of the customers groups
		  {
		  	if (($_POST['sppcoption'][$customers_group['customers_group_id']]) && ($_POST['sppcprice'][$customers_group['customers_group_id']] != '')) {
			  tep_db_query("insert into " . TABLE_PRODUCTS_GROUPS . " (products_id, customers_group_id, customers_group_price) values ('" . $products_id . "', '" . $customers_group['customers_group_id'] . "', '" . $_POST['sppcprice'][$customers_group['customers_group_id']] . "')");
		    }
		  }
          // EOF Separate Pricing Per Customer
          
        } elseif ($action == 'update_product') {          
          $check_status_query = tep_db_query('select products_retail_status,products_dropship_status from ' . TABLE_PRODUCTS . ' where products_id = ' . $products_id);
          $check_status = tep_db_fetch_array($check_status_query);
          
          $update_sql_data = array(
          	'products_last_modified' => 'now()',
          	'products_retail_status' => $check_status['products_retail_status'],
          	'products_dropship_status' => $check_status['products_dropship_status']
          );

          $sql_data_array = array_merge($sql_data_array, $update_sql_data);

          tep_db_perform(TABLE_PRODUCTS, $sql_data_array, 'update', "products_id = '" . (int)$products_id . "'");
          
          // BOF Separate Pricing Per Customer
		  $customers_group_query = tep_db_query("select customers_group_id, customers_group_name from " . TABLE_CUSTOMERS_GROUPS . " where customers_group_id != '0' order by customers_group_id");
		  while ($customers_group = tep_db_fetch_array($customers_group_query)) // Gets all of the customers groups
		    {
		    $attributes_query = tep_db_query("select customers_group_id, customers_group_price from " . TABLE_PRODUCTS_GROUPS . " where ((products_id = '" . $products_id . "') && (customers_group_id = " . $customers_group['customers_group_id'] . ")) order by customers_group_id");
		    $attributes = tep_db_fetch_array($attributes_query);
		    if (tep_db_num_rows($attributes_query) > 0) {
			  if ($_POST['sppcoption'][$customers_group['customers_group_id']]) { // this is checking if the check box is checked
			    if ( ($_POST['sppcprice'][$customers_group['customers_group_id']] <> $attributes['customers_group_price']) && ($attributes['customers_group_id'] == $customers_group['customers_group_id']) ) {
				  tep_db_query("update " . TABLE_PRODUCTS_GROUPS . " set customers_group_price = '" . $_POST['sppcprice'][$customers_group['customers_group_id']] . "' where customers_group_id = '" . $attributes['customers_group_id'] . "' and products_id = '" . $products_id . "'");
				  $attributes = tep_db_fetch_array($attributes_query);
			    }
			    elseif (($_POST['sppcprice'][$customers_group['customers_group_id']] == $attributes['customers_group_price'])) {
				  $attributes = tep_db_fetch_array($attributes_query);
			    }
			  }
			  else {
			    tep_db_query("delete from " . TABLE_PRODUCTS_GROUPS . " where customers_group_id = '" . $customers_group['customers_group_id'] . "' and products_id = '" . $products_id . "'");
			    $attributes = tep_db_fetch_array($attributes_query);
			  }
		    }
		    elseif (($_POST['sppcoption'][$customers_group['customers_group_id']]) && ($_POST['sppcprice'][$customers_group['customers_group_id']] != '')) {
			  tep_db_query("insert into " . TABLE_PRODUCTS_GROUPS . " (products_id, customers_group_id, customers_group_price) values ('" . $products_id . "', '" . $customers_group['customers_group_id'] . "', '" . $_POST['sppcprice'][$customers_group['customers_group_id']] . "')");
			  $attributes = tep_db_fetch_array($attributes_query);
		    }
		  }
		  // EOF Separate Pricing Per Customer
        }
        
        // Add promotional product
        if ( $HTTP_POST_VARS[ 'products_promotional' ] == 1 ) {
        	tep_db_query('delete from ' . TABLE_PROMOTIONS . ' where products_id = ' . (int)$products_id);
        	tep_db_query('insert into ' . TABLE_PROMOTIONS . ' (products_id) VALUES (' . (int)$products_id . ')');
        }
        else {
        	tep_db_query('delete from ' . TABLE_PROMOTIONS . ' where products_id = ' . (int)$products_id);
        }
        
        // Modular SEO Header Tags
  		include( DIR_WS_MODULES . 'header_tags/categories_products_process.php' );
        
        
        /** AJAX Attribute Manager  **/ 
  		require_once('attributeManager/includes/attributeManagerUpdateAtomic.inc.php'); 
		/** AJAX Attribute Manager  end **/

        $languages = tep_get_languages();
        for ($i=0, $n=sizeof($languages); $i<$n; $i++) {
          $language_id = $languages[$i]['id'];

          $sql_data_array = array('products_name' => tep_db_prepare_input($HTTP_POST_VARS['products_name'][$language_id]),
          			  'products_subname' => tep_db_prepare_input($HTTP_POST_VARS['products_subname'][$language_id]),
                                  'products_description' => tep_db_prepare_input($HTTP_POST_VARS['products_description'][$language_id]),
          			  'products_description_retail' => tep_db_prepare_input($HTTP_POST_VARS['products_description_retail'][$language_id]),
                                  'products_url' => tep_db_prepare_input($HTTP_POST_VARS['products_url'][$language_id]));
          
          // begin Extra Product Fields
            foreach ($epf as $e) {
              if ($e['language'] == $language_id) {
                if ($e['language_active']) {
                  if ($e['multi_select']) {
                    if (empty($HTTP_POST_VARS[$e['field']][$language_id])) {
                      $value = 'null';
                    } else {
                      //validate multi-select values in case JavaScript was turned off and couldn't prevent errors
                      $value_list = $HTTP_POST_VARS[$e['field']][$language_id];
                      if ($e['linked']) { // validate linked values if field is linked
                        $link_validated_list = array();
                        $lv = 0;
                        foreach ($epf as $lf) {
                          if ($lf['id'] == $e['links_to']) {
                            $lv = (int)$HTTP_POST_VARS[$lf['field']][$language_id];
                          }
                        }
                        $validation_query_raw = 'select value_id from ' . TABLE_EPF_VALUES . ' where epf_id = ' . (int)$e['id'] . ' and languages_id = ' . (int)$e['language'] . ' and ';
                        if ($lv == 0) {
                          $validation_query_raw .= 'value_depends_on = 0';
                        } else {
                          $validation_query_raw .= '(value_depends_on in (0,' . get_parent_list($lv) . '))';
                        }
                        $validation_query = tep_db_query($validation_query_raw);
                        $valid_values = array();
                        while ($valid = tep_db_fetch_array($validation_query)) {
                          $valid_values[] = $valid['value_id'];
                        }
                        foreach ($value_list as $v) {
                          if (in_array($v, $valid_values)) $link_validated_list[] = $v;
                        }
                      } else {
                        $link_validated_list = $value_list;
                      }
                      $validated_value_list = array(); // validate excluded values
                      $excluded_values = array();
                      foreach ($link_validated_list as $v) {
                        if (!in_array($v, $excluded_values)) {
                          $validated_value_list[] = $v;
                          $tmp = get_exclude_list($v);
                          $excluded_values = array_merge($excluded_values, $tmp);
                        }
                      }
                      $value = '|';
                      $sort_query = tep_db_query('select value_id from ' . TABLE_EPF_VALUES . ' where epf_id = ' . (int)$e['id'] . ' and languages_id = ' . (int)$e['language'] . ' order by sort_order, epf_value');
                      while ($val = tep_db_fetch_array($sort_query)) { // store input values in sorted order
                        if (in_array($val['value_id'], $validated_value_list))
                          $value .= tep_db_prepare_input($val['value_id']) . '|';
                      }
                    }
                  } else {
                    $value = tep_db_prepare_input($HTTP_POST_VARS[$e['field']][$language_id]);
                    if ($value == '')
                      $value = (($e['uses_list'] && !$e['multi_select']) ? 0 : 'null');
                  }
                  $extra = array($e['field'] => $value);
                } else {
                  $extra = array($e['field'] => (($e['uses_list'] && !$e['multi_select']) ? 0 : 'null'));
                }
                $sql_data_array = array_merge($sql_data_array, $extra);
              }
            }
            // end Extra Product Fields
          
          
	      // begin Extra Product Fields
	      $extra = array();
	      foreach ($xfields as $f) {
	        $extra[$f] = $HTTP_POST_VARS[$f];
	      }
	      // end Extra Product Fields             

          if ($action == 'insert_product') {
            $insert_sql_data = array('products_id' => $products_id,'language_id' => $language_id);
            $sql_data_array = array_merge($sql_data_array, $insert_sql_data);
            tep_db_perform(TABLE_PRODUCTS_DESCRIPTION, $sql_data_array);
          } elseif ($action == 'update_product') {
            tep_db_perform(TABLE_PRODUCTS_DESCRIPTION, $sql_data_array, 'update', "products_id = '" . (int)$products_id . "' and language_id = '" . (int)$language_id . "'");
          }
        }

        $pi_sort_order = 0;
        $piArray = array(0);

        foreach ($HTTP_POST_FILES as $key => $value) {
// Update existing large product images
          if (preg_match('/^products_image_large_([0-9]+)$/', $key, $matches)) {
            $pi_sort_order++;
            $sql_data_array = array('htmlcontent' => tep_db_prepare_input($HTTP_POST_VARS['products_image_htmlcontent_' . $matches[1]]),
                                    'sort_order' => $pi_sort_order);
            $t = new upload($key);
            $t->set_destination(DIR_FS_CATALOG_IMAGES);
            if ($t->parse()) {
            	$imageFilenameTmp = explode('.', $t->filename);
            	$imageExt = $imageFilenameTmp[count($imageFilenameTmp) - 1];
            	$t->set_filename(substr(strtolower(preg_replace('/[^a-zA-Z0-9]/s', '_', $t->filename)), 0, 40) . time() . '.' . $imageExt);
            	if ($t->save()) {
              		$sql_data_array['image'] = tep_db_prepare_input($t->filename);
            	}
            }

            tep_db_perform(TABLE_PRODUCTS_IMAGES, $sql_data_array, 'update', "products_id = '" . (int)$products_id . "' and id = '" . (int)$matches[1] . "'");

            $piArray[] = (int)$matches[1];
          } elseif (preg_match('/^products_image_large_new_([0-9]+)$/', $key, $matches)) {
// Insert new large product images
            $sql_data_array = array('products_id' => (int)$products_id,
                                    'htmlcontent' => tep_db_prepare_input($HTTP_POST_VARS['products_image_htmlcontent_new_' . $matches[1]]));

            $t = new upload($key);
            $t->set_destination(DIR_FS_CATALOG_IMAGES);
            if ($t->parse()){
            	$imageFilenameTmp = explode('.', $t->filename);
            	$imageExt = $imageFilenameTmp[count($imageFilenameTmp) - 1];
            	$t->set_filename(substr(strtolower(preg_replace('/[^a-zA-Z0-9]/s', '_', $t->filename)), 0, 40) . time() . '.' . $imageExt);
            	if ($t->save()) {
              		$pi_sort_order++;
		        $sql_data_array['image'] = tep_db_prepare_input($t->filename);
        		$sql_data_array['sort_order'] = $pi_sort_order;
             		tep_db_perform(TABLE_PRODUCTS_IMAGES, $sql_data_array);
			$piArray[] = tep_db_insert_id();
            	}
            }
          }
        }

        $product_images_query = tep_db_query("select image from " . TABLE_PRODUCTS_IMAGES . " where products_id = '" . (int)$products_id . "' and id not in (" . implode(',', $piArray) . ")");
        if (tep_db_num_rows($product_images_query)) {
          while ($product_images = tep_db_fetch_array($product_images_query)) {
            $duplicate_image_query = tep_db_query("select count(*) as total from " . TABLE_PRODUCTS_IMAGES . " where image = '" . tep_db_input($product_images['image']) . "'");
            $duplicate_image = tep_db_fetch_array($duplicate_image_query);

            if ($duplicate_image['total'] < 2) {
              if (file_exists(DIR_FS_CATALOG_IMAGES . $product_images['image'])) {
                //@unlink(DIR_FS_CATALOG_IMAGES . $product_images['image']);
              }
            }
          }

          tep_db_query("delete from " . TABLE_PRODUCTS_IMAGES . " where products_id = '" . (int)$products_id . "' and id not in (" . implode(',', $piArray) . ")");
        }

        if (USE_CACHE == 'true') {
          tep_reset_cache_block('categories');
          tep_reset_cache_block('also_purchased');
        }

        //tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&pID=' . $products_id));
//BOF Admin product paging return page stabilizations patch
          if (isset($HTTP_GET_VARS['search'])) {
            tep_redirect(tep_href_link(FILENAME_CATEGORIES,  'page=' . $page . '&search=' . $url_search . '&pID=' . $products_id) . ((isset($_GET['listing'])) ? '&listing=' . $_GET['listing'] : ''));
          } 
          elseif (isset($_GET['mID'])) {
          	tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'mID=' . $_GET['mID'] . '&pID=' . $products_id . '&action=view_manufacturer_products'));
          }
          else {
            //find the real place
            tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&pID=' . $products_id));
          }
//EOF Admin product paging return page stabilizations patch
        break;
      case 'copy_to_confirm':
        if (isset($HTTP_POST_VARS['products_id']) && isset($HTTP_POST_VARS['categories_id'])) {
          $products_id = tep_db_prepare_input($HTTP_POST_VARS['products_id']);
          $categories_id = tep_db_prepare_input($HTTP_POST_VARS['categories_id']);

          if ($HTTP_POST_VARS['copy_as'] == 'link') {
            if ($categories_id != $current_category_id) {
              $check_query = tep_db_query("select count(*) as total from " . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id = '" . (int)$products_id . "' and categories_id = '" . (int)$categories_id . "'");
              $check = tep_db_fetch_array($check_query);
              if ($check['total'] < '1') {
                tep_db_query("insert into " . TABLE_PRODUCTS_TO_CATEGORIES . " (products_id, categories_id) values ('" . (int)$products_id . "', '" . (int)$categories_id . "')");
              }
            } else {
              $messageStack->add_session(ERROR_CANNOT_LINK_TO_SAME_CATEGORY, 'error');
            }
          } elseif ($HTTP_POST_VARS['copy_as'] == 'duplicate') {
            $product_query = tep_db_query("select products_quantity, products_model, products_image, products_price, products_date_available, products_weight, products_tax_class_id, manufacturers_id, manufacturers_id2 from " . TABLE_PRODUCTS . " where products_id = '" . (int)$products_id . "'");
            $product = tep_db_fetch_array($product_query);

            tep_db_query("insert into " . TABLE_PRODUCTS . " (products_quantity, products_model,products_image, products_price, products_date_added, products_date_available, products_weight, products_status, products_tax_class_id, manufacturers_id, manufacturers_id2) values ('" . tep_db_input($product['products_quantity']) . "', '" . tep_db_input($product['products_model']) . "', '" . tep_db_input($product['products_image']) . "', '" . tep_db_input($product['products_price']) . "',  now(), " . (empty($product['products_date_available']) ? "null" : "'" . tep_db_input($product['products_date_available']) . "'") . ", '" . tep_db_input($product['products_weight']) . "', '0', '" . (int)$product['products_tax_class_id'] . "', '" . (int)$product['manufacturers_id'] . "', '" . (int)$product['manufacturers_id2'] . "')");
            $dup_products_id = tep_db_insert_id();

            // description copy modified to work with Extra Product Fields
            $description_query = tep_db_query("select * from " . TABLE_PRODUCTS_DESCRIPTION . " where products_id = '" . (int)$products_id . "'");
            while ($description = tep_db_fetch_array($description_query)) {
              $description['products_id'] = $dup_products_id;
              $description['products_viewed'] = 0;
              tep_db_perform(TABLE_PRODUCTS_DESCRIPTION, $description);
            }
			// end Extra Product Fields            

            $product_images_query = tep_db_query("select image, htmlcontent, sort_order from " . TABLE_PRODUCTS_IMAGES . " where products_id = '" . (int)$products_id . "'");
            while ($product_images = tep_db_fetch_array($product_images_query)) {
              tep_db_query("insert into " . TABLE_PRODUCTS_IMAGES . " (products_id, image, htmlcontent, sort_order) values ('" . (int)$dup_products_id . "', '" . tep_db_input($product_images['image']) . "', '" . tep_db_input($product_images['htmlcontent']) . "', '" . tep_db_input($product_images['sort_order']) . "')");
            }

            tep_db_query("insert into " . TABLE_PRODUCTS_TO_CATEGORIES . " (products_id, categories_id) values ('" . (int)$dup_products_id . "', '" . (int)$categories_id . "')");
            
            // BOF Separate Pricing Per Customer originally 2006-04-26 by Infobroker
			$cg_price_query = tep_db_query("select customers_group_id, customers_group_price from " . TABLE_PRODUCTS_GROUPS . " where products_id = '" . $products_id . "' order by customers_group_id");
			
			// insert customer group prices in table products_groups when there are any for the copied product
			if (tep_db_num_rows($cg_price_query) > 0) {
			  while ( $cg_prices = tep_db_fetch_array($cg_price_query)) {
				tep_db_query("insert into " . TABLE_PRODUCTS_GROUPS . " (customers_group_id, customers_group_price, products_id) values ('" . (int)$cg_prices['customers_group_id'] . "', '" . tep_db_input($cg_prices['customers_group_price']) . "', '" . (int)$dup_products_id . "')");
			  } // end while ( $cg_prices = tep_db_fetch_array($cg_price_query))
			} // end if (tep_db_num_rows($cg_price_query) > 0)
				
			// EOF Separate Pricing Per Customer originally 2006-04-26 by Infobroker
            
            $products_id = $dup_products_id;
          }

          if (USE_CACHE == 'true') {
            tep_reset_cache_block('categories');
            tep_reset_cache_block('also_purchased');
          }
        }

        tep_redirect(tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $categories_id . '&pID=' . $products_id));
        break;
    }
  }

// check if the catalog image directory exists
  if (is_dir(DIR_FS_CATALOG_IMAGES)) {
    if (!tep_is_writable(DIR_FS_CATALOG_IMAGES)) $messageStack->add(ERROR_CATALOG_IMAGE_DIRECTORY_NOT_WRITEABLE, 'error');
  } else {
    $messageStack->add(ERROR_CATALOG_IMAGE_DIRECTORY_DOES_NOT_EXIST, 'error');
  }
  
// begin Extra Product Fields
if ($action == 'new_product') {
  foreach ($epf as $e) {
    if ($e['language_active']) {
      if ($e['multi_select']) {
        echo '<script type="text/javascript">' . "\n";
        echo "function process_" . $e['field'] . '_' . $e['language'] . "(id) {\n";
        echo "  var thisbox = document.getElementById('ms' + id);\n";
        echo "  if (thisbox.checked) {\n";
        echo "    switch (id) {\n";
        foreach ($e['values'] as $val) {
          $el = get_exclude_list($val);
          if (!empty($el)) {
            echo "      case " . $val . ":\n";
            foreach($el as $i) {
              echo "        var cb = document.getElementById('ms" . $i . "');\n";
              echo "        cb.checked = false;\n";
            }
            echo "        break;\n";
          }
        }
        echo "      default: ;\n";
        echo "    }\n";
        echo "  }\n";
        echo "}\n";
        echo "</script>\n";
      } elseif ($e['uses_list'] && $e['linked']) {
        echo '<script type="text/javascript">' . "\n";
        if ($e['checkbox']) {
          echo "function process_" . $e['field'] . '_' . $e['language'] . "(id) {\n";
        } else {
          echo "function process_" . $e['field'] . '_' . $e['language'] . "() {\n";
          echo "  var id = document.getElementById('lv" . $e['id'] . '_' . $e['language'] . "').value;\n";
        }
        if (!empty($link_groups[$e['id']][$e['language']])) {
          foreach ($link_groups[$e['id']][$e['language']] as $val) {
            echo "  var lf = document.getElementById('lf" . $e['id'] . '_' . $e['language'] . '_' . $val . "');\n";
            echo "  lf.style.display = 'none'; lf.disabled = true;\n";
            foreach ($linked_fields[$e['id']][$e['language']][$val] as $id) {
              echo "  document.getElementById('ms" . $id . "').disabled = true;\n";
            }
          }
          foreach ($link_groups[$e['id']][$e['language']] as $val) {
            echo "  if (";
            $first = true;
            $enables = '';
            foreach(get_children($val) as $x) {
              if ($first) {
                $first = false;
              } else {
                echo ' || ';
              }
              echo '(id == ' . $x . ')';
            }
            echo ") {\n";
            echo "    var lf = document.getElementById('lf" . $e['id'] . '_' . $e['language'] . '_' . $val . "');\n";
            echo "    lf.style.display = ''; lf.disabled = false;\n";
            foreach ($linked_fields[$e['id']][$e['language']][$val] as $id) {
              $enables .= "    document.getElementById('ms" . $id . "').disabled = false;\n";
            }
            echo $enables;
            echo "  }\n";
          }
          foreach ($linked_fields[$e['id']][$e['language']] as $group) {
            foreach ($group as $id) {
              echo "  var lv = document.getElementById('ms" . $id . "');\n";
              echo "  if (lv.disabled == true) { lv.checked = false; }\n";
            }
          }
        }
        echo "}\n";
        echo "</script>\n";
      }
    }
  }
} // end Extra Product Fields

  require(DIR_WS_INCLUDES . 'template_top.php');

  if ($action == 'new_product') {
    $parameters = array('products_name' => '',
    				   'products_subname' => '',
                       'products_description' => '',
    				   'products_description_retail' => '',
                       'products_url' => '',
                       'products_id' => '',
                       'products_quantity' => '',
                       'products_model' => '',
                       'products_image' => '',
                       'products_larger_images' => array(),
                       'products_price' => '',
                       'products_weight' => '1',
                       'products_date_added' => '',
                       'products_last_modified' => '',
                       'products_date_available' => '',
                       'products_status' => '',
                       'products_tax_class_id' => '1',
                       'manufacturers_id' => '',
    				   'manufacturers_id2' => '',
    				   'products_new' => '',
    				   'products_promotional' => 0
    );

    // begin Extra Product Fields
    foreach ($xfields as $f) {
      $parameters = array_merge($parameters, array($f => ''));
    }
	// end Extra Product Fields 
    
    $pInfo = new objectInfo($parameters);

    if (isset($HTTP_GET_VARS['pID']) && empty($HTTP_POST_VARS)) {
      // begin Extra Product Fields
      $query = "select pd.products_name, pd.products_subname, pd.products_description, pd.products_description_retail, pd.products_url, p.products_id, p.products_quantity, p.products_model, p.products_image, p.products_price, p.products_weight, p.products_date_added, p.products_last_modified, date_format(p.products_date_available, '%Y-%m-%d') as products_date_available, p.products_status, p.products_new, p.products_tax_class_id, p.manufacturers_id, p.manufacturers_id2, p.ignore_status, (select 1 from ". TABLE_PROMOTIONS ." where products_id = " . (int)$HTTP_GET_VARS['pID'] . ") as products_promotional";
      foreach ($xfields as $f) {
        $query .= ', pd.' . $f;
      }
      $query .= " from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd where p.products_id = '" . (int)$HTTP_GET_VARS['pID'] . "' and p.products_id = pd.products_id and pd.language_id = '" . (int)$languages_id . "'";
      $product_query = tep_db_query($query);
      // end Extra Product Fields
      $product = tep_db_fetch_array($product_query);

      $pInfo->objectInfo($product);

      $product_images_query = tep_db_query("select id, image, htmlcontent, sort_order from " . TABLE_PRODUCTS_IMAGES . " where products_id = '" . (int)$product['products_id'] . "' order by sort_order");
      while ($product_images = tep_db_fetch_array($product_images_query)) {
        $pInfo->products_larger_images[] = array('id' => $product_images['id'],
                                                 'image' => $product_images['image'],
                                                 'htmlcontent' => $product_images['htmlcontent'],
                                                 'sort_order' => $product_images['sort_order']);
      }
    }

    $manufacturers_array = array(array('id' => '', 'text' => TEXT_NONE));
    $manufacturers_query = tep_db_query("select manufacturers_id, manufacturers_name from " . TABLE_MANUFACTURERS . " order by manufacturers_name");
    while ($manufacturers = tep_db_fetch_array($manufacturers_query)) {
      $manufacturers_array[] = array('id' => $manufacturers['manufacturers_id'],
                                     'text' => $manufacturers['manufacturers_name']);
    }

    $tax_class_array = array(array('id' => '0', 'text' => TEXT_NONE));
    $tax_class_query = tep_db_query("select tax_class_id, tax_class_title from " . TABLE_TAX_CLASS . " order by tax_class_title");
    while ($tax_class = tep_db_fetch_array($tax_class_query)) {
      $tax_class_array[] = array('id' => $tax_class['tax_class_id'],
                                 'text' => $tax_class['tax_class_title']);
    }

    $languages = tep_get_languages();

    if (!isset($pInfo->products_status)) $pInfo->products_status = '1';
    switch ($pInfo->products_status) {
      case '0': $in_status = false; $out_status = true; break;
      case '1':
      default: $in_status = true; $out_status = false;
    }
    
  	switch ($pInfo->products_new) {
      case '0': $in_new_release = false; $out_new_release = true; break;
      case '1':
      default: $in_new_release = true; $out_new_release = false;
    }
    
  	switch ($pInfo->products_promotional) {
  		case null: 
      	case '0': $in_promotional = false; $out_promotional = true; break;
      	case '1':
      	default: $in_promotional = true; $out_promotional = false;
    }

    $form_action = (isset($HTTP_GET_VARS['pID'])) ? 'update_product' : 'insert_product';
?>
<script type="text/javascript"><!--
var tax_rates = new Array();
<?php
    for ($i=0, $n=sizeof($tax_class_array); $i<$n; $i++) {
      if ($tax_class_array[$i]['id'] > 0) {
        echo 'tax_rates["' . $tax_class_array[$i]['id'] . '"] = ' . tep_get_tax_rate_value($tax_class_array[$i]['id']) . ';' . "\n";
      }
    }
?>

function doRound(x, places) {
  return Math.round(x * Math.pow(10, places)) / Math.pow(10, places);
}

function getTaxRate() {
  var selected_value = document.forms["new_product"].products_tax_class_id.selectedIndex;
  var parameterVal = document.forms["new_product"].products_tax_class_id[selected_value].value;

  if ( (parameterVal > 0) && (tax_rates[parameterVal] > 0) ) {
    return tax_rates[parameterVal];
  } else {
    return 0;
  }
}

function updateGross() {
  var taxRate = getTaxRate();
  var grossValue = document.forms["new_product"].products_price.value;
  
  if (taxRate > 0) {
    grossValue = grossValue * ((taxRate / 100) + 1);
  }

  document.forms["new_product"].products_price_gross.value = doRound(grossValue, 4);
}

function updateNet() {
  var taxRate = getTaxRate();
  var netValue = document.forms["new_product"].products_price_gross.value;

  if (taxRate > 0) {
    netValue = netValue / ((taxRate / 100) + 1);
  }

  document.forms["new_product"].products_price.value = doRound(netValue, 4);
}

//--></script>
    <?php //echo tep_draw_form('new_product', FILENAME_CATEGORIES, 'cPath=' . $cPath . (isset($HTTP_GET_VARS['pID']) ? '&pID=' . $HTTP_GET_VARS['pID'] : '') . '&action=' . $form_action, 'post', 'enctype="multipart/form-data"'); ?>
    <?php
//BOF Admin product paging
if (isset($HTTP_GET_VARS['search']) && $HTTP_GET_VARS['search'] !== '') {
  $trueEditPath1 = 'page=' . $page . '&search=' . $url_search . (isset($HTTP_GET_VARS['pID']) ? '&pID=' . $HTTP_GET_VARS['pID'] : '') . ((isset($_GET['listing'])) ? '&listing=' . $_GET['listing'] : '');
} 
elseif (isset($HTTP_GET_VARS['mID']) && $HTTP_GET_VARS['mID'] !== '') {
	$trueEditPath1 =  'mID=' . $HTTP_GET_VARS['mID'] . (isset($HTTP_GET_VARS['page']) ? '&page=' . $HTTP_GET_VARS['page'] : '');
}
else {
  $trueEditPath1 =  'cPath=' . $cPath . (isset($HTTP_GET_VARS['page']) ? '&page=' . $HTTP_GET_VARS['page'] : '');
}
//EOF Admin product paging
echo tep_draw_form('new_product', FILENAME_CATEGORIES, $trueEditPath1 . (isset($HTTP_GET_VARS['pID']) ? '&pID=' . $HTTP_GET_VARS['pID'] : '') . '&action=' . $form_action, 'post', 'enctype="multipart/form-data"'); ?>
    <table border="0" width="100%" cellspacing="0" cellpadding="2">
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo sprintf(TEXT_NEW_PRODUCT, tep_output_generated_category_path($current_category_id)); ?></td>
            <td class="pageHeading" align="right"><?php echo tep_draw_separator('pixel_trans.gif', HEADING_IMAGE_WIDTH, HEADING_IMAGE_HEIGHT); ?></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
      </tr>
      <tr>
        <td><table border="0" cellspacing="0" cellpadding="2">
          <tr>
            <td class="main"><?php echo TEXT_PRODUCTS_STATUS; ?></td>
            <td class="main">
            	<?php 
            		echo tep_draw_separator('pixel_trans.gif', '24', '15') . '&nbsp;' . tep_draw_radio_field('products_status', '1', $in_status) . '&nbsp;' . TEXT_PRODUCT_AVAILABLE . '&nbsp;' . tep_draw_radio_field('products_status', '0', $out_status) . '&nbsp;' . TEXT_PRODUCT_NOT_AVAILABLE; 
            		echo '&nbsp;' . tep_draw_checkbox_field('ignore_status', '1', (($pInfo->ignore_status) ? true : false)) . 'Always online';
            	?>
            </td>
          </tr>
          <tr>
            <td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo 'Last modified by:'; ?></td>
            <td class="main">
            <?php 
            	echo tep_draw_separator('pixel_trans.gif', '24', '15') . '&nbsp;';
            	$lmb_query = tep_db_query('select last_modified_by from ' . TABLE_PRODUCTS . ' where products_id = ' . (int)$HTTP_GET_VARS['pID']);
            	$lmb = tep_db_fetch_array($lmb_query);
            	echo tep_draw_input_field('last_modified_by', $lmb['last_modified_by'], 'readonly="readonly"'); 
            ?>
            </td>
          </tr>
          <tr>
            <td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo TEXT_PRODUCTS_NEW_RELEASE; ?></td>
            <td class="main"><?php echo tep_draw_separator('pixel_trans.gif', '24', '15') . '&nbsp;' . tep_draw_radio_field('products_new', '1', $in_new_release) . '&nbsp;' . TEXT_PRODUCTS_NEW_RELEASE_YES . '&nbsp;' . tep_draw_radio_field('products_new', '0', $out_new_release) . '&nbsp;' . TEXT_PRODUCTS_NEW_RELEASE_NO; ?></td>
          </tr>
          <tr>
            <td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo TEXT_PRODUCTS_PROMOTIONAL; ?></td>
            <td class="main"><?php echo tep_draw_separator('pixel_trans.gif', '24', '15') . '&nbsp;' . tep_draw_radio_field('products_promotional', '1', $in_promotional) . '&nbsp;' . TEXT_PRODUCTS_PROMOTIONAL_YES . '&nbsp;' . tep_draw_radio_field('products_promotional', '0', $out_promotional) . '&nbsp;' . TEXT_PRODUCTS_PROMOTIONAL_NO; ?></td>
          </tr>
          <tr>
            <td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo TEXT_PRODUCTS_DATE_AVAILABLE; ?></td>
            <td class="main"><?php echo tep_draw_separator('pixel_trans.gif', '24', '15') . '&nbsp;' . tep_draw_input_field('products_date_available', $pInfo->products_date_available, 'id="products_date_available"') . ' <small>(YYYY-MM-DD)</small>'; ?></td>
          </tr>
          <tr>
            <td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo TEXT_PRODUCTS_MANUFACTURER; ?></td>
            <td class="main"><?php echo tep_draw_separator('pixel_trans.gif', '24', '15') . '&nbsp;' . tep_draw_pull_down_menu('manufacturers_id', $manufacturers_array, $pInfo->manufacturers_id); ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo 'Products Sub-manufacturer:'; ?></td>
            <td class="main"><?php echo tep_draw_separator('pixel_trans.gif', '24', '15') . '&nbsp;' . tep_draw_pull_down_menu('manufacturers_id2', $manufacturers_array, $pInfo->manufacturers_id2); ?></td>
          </tr>
          <tr>
            <td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
          </tr>
<?php
    for ($i=0, $n=sizeof($languages); $i<$n; $i++) {
?>
          <tr>
            <td class="main"><?php if ($i == 0) echo TEXT_PRODUCTS_NAME; ?></td>
            <td class="main"><?php echo tep_image(DIR_WS_CATALOG_LANGUAGES . $languages[$i]['directory'] . '/images/' . $languages[$i]['image'], $languages[$i]['name']) . '&nbsp;' . tep_draw_input_field('products_name[' . $languages[$i]['id'] . ']', (isset($products_name[$languages[$i]['id']]) ? stripslashes($products_name[$languages[$i]['id']]) : tep_get_products_name($pInfo->products_id, $languages[$i]['id']))); ?></td>
          </tr>
<?php
    }
?>
          <tr>
            <td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
          </tr>
          
<?php
    for ($i=0, $n=sizeof($languages); $i<$n; $i++) {
?>
          <tr>
            <td class="main"><?php if ($i == 0) echo TEXT_PRODUCTS_SUBNAME; ?></td>
            <td class="main"><?php echo tep_image(DIR_WS_CATALOG_LANGUAGES . $languages[$i]['directory'] . '/images/' . $languages[$i]['image'], $languages[$i]['name']) . '&nbsp;' . tep_draw_input_field('products_subname[' . $languages[$i]['id'] . ']', (isset($products_subname[$languages[$i]['id']]) ? stripslashes($products_subname[$languages[$i]['id']]) : tep_get_products_subname($pInfo->products_id, $languages[$i]['id'])), 'size="105" maxlength="175"'); ?></td>
          </tr>
<?php
    }
?>
          <tr>
            <td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
          </tr>          
          
          <tr bgcolor="#ebebff">
            <td class="main"><?php echo TEXT_PRODUCTS_TAX_CLASS; ?></td>
            <td class="main"><?php echo tep_draw_separator('pixel_trans.gif', '24', '15') . '&nbsp;' . tep_draw_pull_down_menu('products_tax_class_id', $tax_class_array, $pInfo->products_tax_class_id, 'onchange="updateGross()"'); ?></td>
          </tr>
          <tr bgcolor="#ebebff">
            <td class="main"><?php echo TEXT_PRODUCTS_PRICE_NET; ?></td>
            <td class="main"><?php echo tep_draw_separator('pixel_trans.gif', '24', '15') . '&nbsp;' . tep_draw_input_field('products_price', $pInfo->products_price, 'onKeyUp="updateGross()"'); ?></td>
          </tr>
          <tr bgcolor="#ebebff">
            <td class="main"><?php echo TEXT_PRODUCTS_PRICE_GROSS; ?></td>
            <td class="main"><?php echo tep_draw_separator('pixel_trans.gif', '24', '15') . '&nbsp;' . tep_draw_input_field('products_price_gross', $pInfo->products_price, 'OnKeyUp="updateNet()"'); ?></td>
          </tr>
          <!-- AJAX Attribute Manager  -->
          <tr>
          	<td colspan="2"><?php require_once( 'attributeManager/includes/attributeManagerPlaceHolder.inc.php' )?></td>
          </tr>
		  <!-- AJAX Attribute Manager end -->
          <tr>
            <td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
          </tr>
<script type="text/javascript"><!--
updateGross();
//--></script>
<!-- BOF Separate Pricing Per Customer -->
<?php
    $customers_group_query = tep_db_query("select customers_group_id, customers_group_name from " . TABLE_CUSTOMERS_GROUPS . " where customers_group_id != '0' order by customers_group_id");
    $header = false;
    while ($customers_group = tep_db_fetch_array($customers_group_query)) {

     if (tep_db_num_rows($customers_group_query) > 0) {
       $attributes_query = tep_db_query("select customers_group_id, customers_group_price from " . TABLE_PRODUCTS_GROUPS . " where products_id = '" . $pInfo->products_id . "' and customers_group_id = '" . $customers_group['customers_group_id'] . "' order by customers_group_id");
     } else {
         $attributes = array('customers_group_id' => 'new');
     }
 if (!$header) { ?>

    <tr bgcolor="#ebebff">
    <td class="main" colspan="2" style="font-style: italic"><?php echo TEXT_CUSTOMERS_GROUPS_NOTE; ?>
</td>
    </tr>
 <?php
 $header = true;
 } // end if (!header), makes sure this is only shown once
 ?>
        <tr bgcolor="#ebebff">
       <td class="main"><?php // only change in version 4.1.1
             if (isset($pInfo->sppcoption)) {
	   echo tep_draw_checkbox_field('sppcoption[' . $customers_group['customers_group_id'] . ']', 'sppcoption[' . $customers_group['customers_group_id'] . ']', (isset($pInfo->sppcoption[ $customers_group['customers_group_id']])) ? 1: 0);
      } else {
      echo tep_draw_checkbox_field('sppcoption[' . $customers_group['customers_group_id'] . ']', 'sppcoption[' . $customers_group['customers_group_id'] . ']', true) . '&nbsp;' . $customers_group['customers_group_name'];
      }
?>
 &nbsp;</td>
       <td class="main"><?php
       if ($attributes = tep_db_fetch_array($attributes_query)) {
       echo tep_draw_separator('pixel_trans.gif', '24', '15') . '&nbsp;' . tep_draw_input_field('sppcprice[' . $customers_group['customers_group_id'] . ']', $attributes['customers_group_price']);
       }  else {
	       if (isset($pInfo->sppcprice[$customers_group['customers_group_id']])) { // when a preview was done and the back button used
		       $sppc_cg_price = $pInfo->sppcprice[$customers_group['customers_group_id']];
	       } else { // nothing in the db, nothing in the post variables
		       $sppc_cg_price = '';
	       }
	   echo tep_draw_separator('pixel_trans.gif', '24', '15') . '&nbsp;' . tep_draw_input_field('sppcprice[' . $customers_group['customers_group_id'] . ']', $sppc_cg_price );
	 }  ?></td>
    </tr>
<?php
        } // end while ($customers_group = tep_db_fetch_array($customers_group_query))
?>
          <tr>
            <td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
          </tr>
<!-- EOF Separate Pricing Per Customer -->
<?php
    for ($i=0, $n=sizeof($languages); $i<$n; $i++) {
?>
          <tr>
            <td class="main" valign="top"><?php if ($i == 0) echo TEXT_PRODUCTS_DESCRIPTION; ?></td>
            <td><table border="0" cellspacing="0" cellpadding="0">
              <tr>
                <td class="main" valign="top"><?php echo tep_image(DIR_WS_CATALOG_LANGUAGES . $languages[$i]['directory'] . '/images/' . $languages[$i]['image'], $languages[$i]['name']); ?>&nbsp;</td>
                <td class="main"><?php echo tep_draw_textarea_field('products_description[' . $languages[$i]['id'] . ']', 'soft', '70', '15', (isset($products_description[$languages[$i]['id']]) ? stripslashes($products_description[$languages[$i]['id']]) : tep_get_products_description($pInfo->products_id, $languages[$i]['id']))); ?></td>
              </tr>
            </table></td>
          </tr>
<?php
    }
    
    for ($i=0, $n=sizeof($languages); $i<$n; $i++) {
    	?>
              <tr>
                <td class="main" valign="top"><?php if ($i == 0) echo TEXT_PRODUCTS_DESCRIPTION_RETAIL; ?></td>
                <td><table border="0" cellspacing="0" cellpadding="0">
                  <tr>
                    <td class="main" valign="top"><?php echo tep_image(DIR_WS_CATALOG_LANGUAGES . $languages[$i]['directory'] . '/images/' . $languages[$i]['image'], $languages[$i]['name']); ?>&nbsp;</td>
                    <td class="main"><?php echo tep_draw_textarea_field('products_description_retail[' . $languages[$i]['id'] . ']', 'soft', '70', '15', (isset($products_description_retail[$languages[$i]['id']]) ? stripslashes($products_description_retail[$languages[$i]['id']]) : tep_get_products_description_retail($pInfo->products_id, $languages[$i]['id']))); ?></td>
                  </tr>
                </table></td>
              </tr>
    <?php
        }
    
    // Modular SEO Header Tags
  	//include( DIR_WS_MODULES . 'header_tags/products_insert.php' );
?>
          <tr>
            <td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo TEXT_PRODUCTS_QUANTITY; ?></td>
            <td class="main"><?php echo tep_draw_separator('pixel_trans.gif', '24', '15') . '&nbsp;' . tep_draw_input_field('products_quantity', $pInfo->products_quantity); ?></td>
          </tr>
          <tr>
            <td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo TEXT_PRODUCTS_MODEL; ?></td>
            <td class="main"><?php echo tep_draw_separator('pixel_trans.gif', '24', '15') . '&nbsp;' . tep_draw_input_field('products_model', $pInfo->products_model); ?></td>
          </tr>
          <tr>
            <td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
          </tr>
          <tr>
            <td class="main" valign="top"><?php echo TEXT_PRODUCTS_IMAGE; ?></td>
            <td class="main" style="padding-left: 30px;">
              <div><?php echo '<strong>' . TEXT_PRODUCTS_MAIN_IMAGE . ' <small>(' . SMALL_IMAGE_WIDTH . ' x ' . SMALL_IMAGE_HEIGHT . 'px)</small></strong><br />' . (tep_not_null($pInfo->products_image) ? '<a href="' . DIR_WS_CATALOG_IMAGES . $pInfo->products_image . '" target="_blank">' . $pInfo->products_image . '</a> &#124; ' : '') . tep_draw_file_field('products_image'); ?> | <a href="categories.php?action=remove_main_image&pID=<?=$pInfo->products_id?>&cPath=<?=$_GET['cPath']?>&page=<?=$_GET['page']?>">Delete image</a></div>

              <ul id="piList">
<?php
    $pi_counter = 0;

    foreach ($pInfo->products_larger_images as $pi) {
      $pi_counter++;

      echo '                <li id="piId' . $pi_counter . '" class="ui-state-default"><span class="ui-icon ui-icon-arrowthick-2-n-s" style="float: right;"></span><a href="#" onclick="showPiDelConfirm(' . $pi_counter . ');return false;" class="ui-icon ui-icon-trash" style="float: right;"></a><strong>' . TEXT_PRODUCTS_LARGE_IMAGE . ' ' . $pi_counter . '</strong><br />' . tep_draw_file_field('products_image_large_' . $pi['id']) . '<br /><a href="' . DIR_WS_CATALOG_IMAGES . $pi['image'] . '" target="_blank">' . $pi['image'] . '</a><br /><br />' . TEXT_PRODUCTS_LARGE_IMAGE_HTML_CONTENT . '<br />' . tep_draw_textarea_field('products_image_htmlcontent_' . $pi['id'], 'soft', '70', '3', $pi['htmlcontent']) . '</li>';
    }
?>
              </ul>

              <a href="#" onclick="addNewPiForm();return false;"><span class="ui-icon ui-icon-plus" style="float: left;"></span><?php echo TEXT_PRODUCTS_ADD_LARGE_IMAGE; ?></a>

<div id="piDelConfirm" title="<?php echo TEXT_PRODUCTS_LARGE_IMAGE_DELETE_TITLE; ?>">
  <p><span class="ui-icon ui-icon-alert" style="float:left; margin:0 7px 20px 0;"></span><?php echo TEXT_PRODUCTS_LARGE_IMAGE_CONFIRM_DELETE; ?></p>
</div>

<style type="text/css">
#piList { list-style-type: none; margin: 0; padding: 0; }
#piList li { margin: 5px 0; padding: 2px; }
</style>

<script type="text/javascript">
$('#piList').sortable({
  containment: 'parent'
});

var piSize = <?php echo $pi_counter; ?>;

function addNewPiForm() {
  piSize++;

  $('#piList').append('<li id="piId' + piSize + '" class="ui-state-default"><span class="ui-icon ui-icon-arrowthick-2-n-s" style="float: right;"></span><a href="#" onclick="showPiDelConfirm(' + piSize + ');return false;" class="ui-icon ui-icon-trash" style="float: right;"></a><strong><?php echo TEXT_PRODUCTS_LARGE_IMAGE; ?></strong><br /><input type="file" name="products_image_large_new_' + piSize + '" /><br /><br /><?php echo TEXT_PRODUCTS_LARGE_IMAGE_HTML_CONTENT; ?><br /><textarea name="products_image_htmlcontent_new_' + piSize + '" wrap="soft" cols="70" rows="3"></textarea></li>');
}

var piDelConfirmId = 0;

$('#piDelConfirm').dialog({
  autoOpen: false,
  resizable: false,
  draggable: false,
  modal: true,
  buttons: {
    'Delete': function() {
      $('#piId' + piDelConfirmId).effect('blind').remove();
      $(this).dialog('close');
    },
    Cancel: function() {
      $(this).dialog('close');
    }
  }
});

function showPiDelConfirm(piId) {
  piDelConfirmId = piId;

  $('#piDelConfirm').dialog('open');
}
</script>

            </td>
          </tr>
          <tr>
            <td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
          </tr>
<?php
    for ($i=0, $n=sizeof($languages); $i<$n; $i++) {
?>
          <tr>
            <td class="main"><?php if ($i == 0) echo TEXT_PRODUCTS_URL . '<br /><small>' . TEXT_PRODUCTS_URL_WITHOUT_HTTP . '</small>'; ?></td>
            <td class="main"><?php echo tep_image(DIR_WS_CATALOG_LANGUAGES . $languages[$i]['directory'] . '/images/' . $languages[$i]['image'], $languages[$i]['name']) . '&nbsp;' . tep_draw_input_field('products_url[' . $languages[$i]['id'] . ']', (isset($products_url[$languages[$i]['id']]) ? stripslashes($products_url[$languages[$i]['id']]) : tep_get_products_url($pInfo->products_id, $languages[$i]['id']))); ?></td>
          </tr>
<?php
    }
?>
          <tr>
            <td colspan="2"><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
          </tr>
          <tr>
            <td class="main"><?php echo TEXT_PRODUCTS_WEIGHT; ?></td>
            <td class="main"><?php echo tep_draw_separator('pixel_trans.gif', '24', '15') . '&nbsp;' . tep_draw_input_field('products_weight', $pInfo->products_weight); ?></td>
          </tr>
          
          <tr bgcolor="#ebebff">
            <td></td>
            <th class="main"><?php echo TEXT_EXTRA_FIELDS; ?></th>
          </tr>
<?php  // begin Extra Product Fields
          foreach ($epf as $e) {
        	  for ($i=0, $n=sizeof($languages); $i<$n; $i++) {
        	    if ($e['language'] == $languages[$i]['id']) {
        	      if ($e['language_active']) {
      	          $currentval = (isset($extra[$e['field']][$languages[$i]['id']]) ? stripslashes($extra[$e['field']][$languages[$i]['id']]) : tep_get_product_extra_value($e['id'], $pInfo->products_id, $languages[$i]['id']));
        	        if ($e['uses_list']) {
        	          if ($e['multi_select']) {
           	          $currentval = (isset($extra[$e['field']][$languages[$i]['id']]) ? $extra[$e['field']][$languages[$i]['id']] : explode('|', trim(tep_get_product_extra_value($e['id'], $pInfo->products_id, $languages[$i]['id']), '|')));
        	            $value_query = tep_db_query('select value_id, value_depends_on from ' . TABLE_EPF_VALUES . ' where epf_id = ' . (int) $e['id'] . ' and languages_id = ' . (int)$e['language'] . ' order by value_depends_on, sort_order, epf_value');
        	            $epfvals = array(array());
        	            while ($val = tep_db_fetch_array($value_query)) {
        	              $epfvals[$val['value_depends_on']][] = $val['value_id'];
        	            }
        	            $inp = '';
        	            if ($e['linked']) {
        	              $tmp =  (isset($extra['extra_value_id' . $e['links_to']][$languages[$i]['id']]) ? stripslashes($extra['extra_value_id' . $e['links_to']][$languages[$i]['id']]) : tep_get_product_extra_value($e['links_to'], $pInfo->products_id, $languages[$i]['id']));
        	              $tmp = get_parent_list($tmp);
        	              $current_linked_val = explode(',', $tmp);
        	            } else {
        	              $current_linked_val = array(0);
        	            }
        	            foreach ($epfvals as $key => $vallist) {
                        $col = 0;
                        if ($e['linked']) {
                          $tparms = ' id="lf' . $e['links_to'] . '_' . $languages[$i]['id'] . '_' . $key . '"';
                          if (($key != 0) && !in_array($key, $current_linked_val))
                            $tparms .= ' style="display: none" disabled';
                        } else {
                          $tparms = '';
                        }
                        $inp .= '<table' . $tparms . '><tr>';
                        foreach ($vallist as $value) {
                          $col++;
                          if ($col > $e['columns']) {
                            $inp .= '</tr><tr>';
                            $col = 1;
                          }
                          $inp .= '<td>' . tep_draw_checkbox_field($e['field'] . "[" . $languages[$i]['id'] . "][]", $value, in_array($value, $currentval), '', 'onClick="process_' . $e['field'] . '_' . $e['language'] . '(' . $value . ')" id="ms' . $value . '"') . '</td><td>' . ($value == '0' ? TEXT_NOT_APPLY : tep_get_extra_field_list_value($value, false, $e['display_type'])) . '<td><td>&nbsp;</td>';
                        }
                        $inp .= '</tr></table>';
        	            }
        	          } else {
          	          $epfvals = tep_build_epf_pulldown($e['id'], $languages[$i]['id'], array(array('id' => 0, 'text' => TEXT_NOT_APPLY)));
          	          if ($e['checkbox']) {
                        $col = 0;
                        $inp = '<table><tr>';
                        foreach ($epfvals as $value) {
                          $col++;
                          if ($col > $e['columns']) {
                            $inp .= '</tr><tr>';
                            $col = 1;
                          }
                          $inp .= '<td>' . tep_draw_radio_field($e['field'] . "[" . $languages[$i]['id'] . "]", $value['id'], false, $currentval, ($e['linked'] ? 'onClick="process_' . $e['field'] . '_' . $e['language'] . '(' . $value['id'] . ')"' : '')) . '</td><td>' . ($value['id'] == '0' ? TEXT_NOT_APPLY : tep_get_extra_field_list_value($value['id'], false, $e['display_type'])) . '<td><td>&nbsp;</td>';
                        }
                        $inp .= '</tr></table>';
          	          } else {
          	            $inp = tep_draw_pull_down_menu($e['field'] . "[" . $languages[$i]['id'] . "]",  $epfvals, $currentval, ($e['linked'] ? 'onChange="process_' . $e['field'] . '_' . $e['language'] . '()" id="lv' . $e['id'] . '_' . $languages[$i]['id'] . '"' : ''));
          	          }
        	          }
        	        } else {
        	          if ($e['textarea']) {
          	            $inp = tep_draw_textarea_field($e['field'] . "[" . $languages[$i]['id'] . "]", 'soft', '70', '5', $currentval, 'id="' . $e['field'] . "_" . $languages[$i]['id'] . '"');
          	          // if using the TinyMCE HTML editor then uncomment the following line
          	          //$inp .= '<br /><a href="javascript:toggleHTMLEditor(\'' . $e['field'] . "_" . $languages[$i]['id'] . '\');">' . TEXT_TOGGLE_HTML . '</a>';
        	          } else {
          	            $inp = tep_draw_input_field($e['field'] . "[" . $languages[$i]['id'] . "]", $currentval, "maxlength=" . $e['size'] . " size=" . (($e['size'] >= 130) ? '130' : $e['size']));
        	          }
        	        }
?>
          <tr bgcolor="#ebebff" <?php if ($e['hidden']) echo 'style="display: none"'; ?>>
            <td class="main"><?php echo $e['label']; ?>:</td>
            <td class="main"><?php echo tep_image(HTTP_CATALOG_SERVER . DIR_WS_CATALOG_LANGUAGES . $languages[$i]['directory'] . '/images/' . $languages[$i]['image'], $languages[$i]['name']) . '&nbsp;' . $inp; ?></td>
          </tr>
<?php
                }
              }
            }
          } 
// end Extra Product Fields
?>
          
          
        </table></td>
      </tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
      </tr>
<?php
//BOF Admin product paging
if (isset($HTTP_GET_VARS['search'])) {
  $trueCancelPath =  'page=' . $page . '&search=' . $url_search . (isset($HTTP_GET_VARS['pID']) ? '&pID=' . $HTTP_GET_VARS['pID'] : '') . ((isset($_GET['listing'])) ? '&listing=' . $_GET['listing'] : '');
} 
elseif (isset($_GET['mID'])) {
	$trueCancelPath =  (isset($HTTP_GET_VARS['page']) ? 'page=' . $HTTP_GET_VARS['page'] : '') . '&mID=' . $_GET['mID'] . (isset($HTTP_GET_VARS['pID']) ? '&pID=' . $HTTP_GET_VARS['pID'] : '') . '&action=view_manufacturer_products';
}
else {
  $trueCancelPath =  (isset($HTTP_GET_VARS['page']) ? 'page=' . $HTTP_GET_VARS['page'] : '') . '&cPath=' . $cPath . (isset($HTTP_GET_VARS['pID']) ? '&pID=' . $HTTP_GET_VARS['pID'] : '');
}
//EOF Admin product paging
?>
      <tr>
        <!-- <td class="smallText" align="right"><?php echo tep_draw_hidden_field('products_date_added', (tep_not_null($pInfo->products_date_added) ? $pInfo->products_date_added : date('Y-m-d'))) . tep_draw_button(IMAGE_SAVE, 'disk', null, 'primary') . tep_draw_button(IMAGE_CANCEL, 'close', tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . (isset($HTTP_GET_VARS['pID']) ? '&pID=' . $HTTP_GET_VARS['pID'] : ''))); ?></td> -->
		<td class="smallText" align="right"><?php echo tep_draw_hidden_field('products_date_added', (tep_not_null($pInfo->products_date_added) ? $pInfo->products_date_added : date('Y-m-d'))) . tep_draw_button(IMAGE_SAVE, 'disk', null, 'primary') . tep_draw_button(IMAGE_CANCEL, 'close', tep_href_link(FILENAME_CATEGORIES, $trueCancelPath)); ?></td>
      </tr>
    </table>

<script type="text/javascript">
$('#products_date_available').datepicker({
  dateFormat: 'yy-mm-dd'
});
</script>

    </form>
<?php
  } elseif ($action == 'new_product_preview') {
    // begin Extra Product Fields
    $query = "select p.products_id, pd.language_id, pd.products_name, pd.products_description, pd.products_url, p.products_quantity, p.products_model, p.products_image, p.products_price, p.products_weight, p.products_date_added, p.products_last_modified, p.products_date_available, p.products_status, p.manufacturers_id, p.manufacturers_id2";
    foreach ($xfields as $f) {
      $query .= ', pd.' . $f;
    }
    $query .= " from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd where p.products_id = pd.products_id and p.products_id = '" . (int)$HTTP_GET_VARS['pID'] . "'";
    $product_query = tep_db_query($query);
    // end Extra Product Fields
    $product = tep_db_fetch_array($product_query);


    $pInfo = new objectInfo($product);
    $products_image_name = $pInfo->products_image;

    $languages = tep_get_languages();
    for ($i=0, $n=sizeof($languages); $i<$n; $i++) {
      $pInfo->products_name = stripslashes(tep_get_products_name($pInfo->products_id, $languages[$i]['id']));
      $pInfo->products_description = tep_get_products_description($pInfo->products_id, $languages[$i]['id']);
      $pInfo->products_url = tep_get_products_url($pInfo->products_id, $languages[$i]['id']);
?>
    <table border="0" width="100%" cellspacing="0" cellpadding="2">
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo tep_image(DIR_WS_CATALOG_LANGUAGES . $languages[$i]['directory'] . '/images/' . $languages[$i]['image'], $languages[$i]['name']) . '&nbsp;' . $pInfo->products_name; ?></td>
            <td class="pageHeading" align="right"><?php echo $currencies->format($pInfo->products_price); ?></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
      </tr>
      <tr>
        <td class="main"><?php echo tep_image(DIR_WS_CATALOG_IMAGES . $products_image_name, $pInfo->products_name, SMALL_IMAGE_WIDTH, SMALL_IMAGE_HEIGHT, 'align="right" hspace="5" vspace="5"') . $pInfo->products_description; ?></td>
      </tr>
<?php

// begin Extra Product Fields
         foreach ($epf as $e) {
           if ($e['language'] == $languages[$i]['id']) {
             if ($e['language_active']) {
               if (isset($HTTP_GET_VARS['read']) && ($HTTP_GET_VARS['read'] == 'only')) {
                 $value = tep_get_product_extra_value($e['id'], $pInfo->products_id, $languages[$i]['id']);
                 if ($e['multi_select'] && ($value != '')) {
                   $value = explode('|', trim($value, '|'));
                 }
               } else {
                 if ($e['multi_select']) {
                   $value = $extra[$e['field']][$languages[$i]['id']];
                 } else {
                   $value = tep_db_prepare_input($extra[$e['field']][$languages[$i]['id']]);
                   if ($e['uses_list'] && ($value == 0)) $value = '';
                 }
               }
               if (tep_not_null($value)) {
                 echo '<tr><td class="main"><b>' . $e['label'] . ': </b>';
                 if ($e['uses_list']) {
                   if ($e['multi_select']) {
                     $output = array();
                     foreach ($value as $val) {
                       $output[] = tep_get_extra_field_list_value($val, $e['show_chain'], $e['display_type']);
                     }
                     echo implode(', ', $output);
                   } else {
                     echo tep_get_extra_field_list_value($value, $e['show_chain'], $e['display_type']);
                   }
                 } else {
                   echo $value;
                 }
                 echo "</td></tr>\n";
               }
             }
           }
         }
// end Extra Product Fields

      if ($pInfo->products_url) {
?>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
      </tr>
      <tr>
        <td class="main"><?php echo sprintf(TEXT_PRODUCT_MORE_INFORMATION, $pInfo->products_url); ?></td>
      </tr>
<?php
      }
?>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
      </tr>
<?php
      if ($pInfo->products_date_available > date('Y-m-d')) {
?>
      <tr>
        <td align="center" class="smallText"><?php echo sprintf(TEXT_PRODUCT_DATE_AVAILABLE, tep_date_long($pInfo->products_date_available)); ?></td>
      </tr>
<?php
      } else {
?>
      <tr>
        <td align="center" class="smallText"><?php echo sprintf(TEXT_PRODUCT_DATE_ADDED, tep_date_long($pInfo->products_date_added)); ?></td>
      </tr>
<?php
      }
?>
      <tr>
        <td><?php echo tep_draw_separator('pixel_trans.gif', '1', '10'); ?></td>
      </tr>
<?php
    }

    if (isset($HTTP_GET_VARS['origin'])) {
      $pos_params = strpos($HTTP_GET_VARS['origin'], '?', 0);
      if ($pos_params != false) {
        $back_url = substr($HTTP_GET_VARS['origin'], 0, $pos_params);
        $back_url_params = substr($HTTP_GET_VARS['origin'], $pos_params + 1);
      } else {
        $back_url = $HTTP_GET_VARS['origin'];
        $back_url_params = '';
      }
    } else {
      $back_url = FILENAME_CATEGORIES;
      //$back_url_params = 'cPath=' . $cPath . '&pID=' . $pInfo->products_id;
	  $back_url_params = 'page=' . $page . '&cPath=' . $cPath . '&pID=' . $pInfo->products_id . ((isset($HTTP_GET_VARS['search'])) ? ('&search=' . $url_search) : '');
    }
?>
      <tr>
        <td align="right" class="smallText"><?php echo tep_draw_button(IMAGE_BACK, 'triangle-1-w', tep_href_link($back_url, $back_url_params, 'NONSSL')); ?></td>
      </tr>
    </table>
<?php
  } else {
?>
    <table border="0" width="100%" cellspacing="0" cellpadding="2">
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td class="pageHeading"><?php echo HEADING_TITLE; ?><?php if (isset($_GET['error_msg'])) { echo '<br><span style="font-size: 10px; color: red;">' . $_GET['error_msg'] . '</span>'; }?></td>
            <td class="pageHeading" align="right"><?php echo tep_draw_separator('pixel_trans.gif', 1, HEADING_IMAGE_HEIGHT); ?></td>
            <td align="right"><table border="0" width="100%" cellspacing="0" cellpadding="0">
              <tr>
                <td class="smallText" align="right">
<?php
    echo tep_draw_form('search', FILENAME_CATEGORIES, '', 'get');
    echo HEADING_TITLE_SEARCH . ' ' . tep_draw_input_field('search');
    echo tep_hide_session_id() . '</form>';
?>
                </td>
              </tr>
              <tr>
                <td class="smallText" align="right">
<?php
    echo tep_draw_form('goto', FILENAME_CATEGORIES, '', 'get');
    
    // adding caching for the category tree
    $category_tree_cache_file = DIR_FS_ADMIN . 'tmp/category_tree.cache';
    $category_tree = '';
    if (file_exists($category_tree_cache_file) && (filemtime($category_tree_cache_file) > (time() - 60 * 5 ))) {
    	// Cache file is less than five minutes old.
    	// Don't bother refreshing, just use the file as-is.
    	$category_tree = file_get_contents($category_tree_cache_file);
    	$category_tree = unserialize($category_tree);
    } else {
    	// Our cache is out-of-date, so load the data from our remote server,
    	// and also save it over our cache for next time.
    	$category_tree = tep_get_category_tree();
    	file_put_contents($category_tree_cache_file, serialize($category_tree), LOCK_EX);
    }
    
    echo HEADING_TITLE_GOTO . ' ' . tep_draw_pull_down_menu('cPath', $category_tree, $current_category_id, 'onchange="this.form.submit();"');
    echo tep_hide_session_id() . '</form>';
?>
                </td>
              </tr>
            </table></td>
          </tr>
        </table></td>
      </tr>
      <tr>
        <td><table border="0" width="100%" cellspacing="0" cellpadding="0">
          <tr>
            <td valign="top"><table border="0" width="100%" cellspacing="0" cellpadding="2">
              <tr class="dataTableHeadingRow">
                <td class="dataTableHeadingContent">
                	<?php echo TABLE_HEADING_CATEGORIES_PRODUCTS; ?>
                	<?php if (isset($_GET['search']) || (strstr($_GET['cPath'],'626'))) { ?>
                			<a href="<?php echo tep_href_link(FILENAME_CATEGORIES, tep_get_all_get_params(array('listing', 'cID', 'pID')) . 'listing=products_name'); ?>"><?php echo tep_image(DIR_WS_IMAGES . 'icon_up.gif', 'Sort by product name ascending'); ?></a>&nbsp;<a href="<?php echo tep_href_link(FILENAME_CATEGORIES, tep_get_all_get_params(array('listing', 'cID', 'pID')) . 'listing=products_name-desc'); ?>"><?php echo tep_image(DIR_WS_IMAGES . 'icon_down.gif', 'Sort by product name descending'); ?></a>
                	<?php } ?>                
                </td>
                <td class="dataTableHeadingContent" align="left">
                	<?php echo TABLE_HEADING_PRODUCTS_MODEL; ?>
                	<?php if (isset($_GET['search']) || (strstr($_GET['cPath'],'626'))) { ?>
                			<a href="<?php echo tep_href_link(FILENAME_CATEGORIES, tep_get_all_get_params(array('listing', 'cID', 'pID')) . 'listing=products_model'); ?>"><?php echo tep_image(DIR_WS_IMAGES . 'icon_up.gif', 'Sort by product code ascending'); ?></a>&nbsp;<a href="<?php echo tep_href_link(FILENAME_CATEGORIES, tep_get_all_get_params(array('listing', 'cID', 'pID')) . 'listing=products_model-desc'); ?>"><?php echo tep_image(DIR_WS_IMAGES . 'icon_down.gif', 'Sort by product code descending'); ?></a>
                	<?php } ?>
                </td>
                <td class="dataTableHeadingContent" align="left"><?php echo 'Warehouse Code'; ?>
                	<?php if (isset($_GET['search'])  || (strstr($_GET['cPath'],'626'))) { ?>
                			<a href="<?php echo tep_href_link(FILENAME_CATEGORIES, tep_get_all_get_params(array('listing', 'cID', 'pID')) . 'listing=warehouse_code'); ?>"><?php echo tep_image(DIR_WS_IMAGES . 'icon_up.gif', 'Sort by warehouse code ascending'); ?></a>&nbsp;<a href="<?php echo tep_href_link(FILENAME_CATEGORIES, tep_get_all_get_params(array('listing', 'cID', 'pID')) . 'listing=warehouse_code-desc'); ?>"><?php echo tep_image(DIR_WS_IMAGES . 'icon_down.gif', 'Sort by warehouse code descending'); ?></a>
                	<?php } ?>
		</td>
                <td class="dataTableHeadingContent" align="center"><?php echo 'Wholesale'; ?></td>
                <td class="dataTableHeadingContent" align="center"><?php echo 'Dropship'; ?></td>
                <td class="dataTableHeadingContent" align="center"><?php echo 'Retail'; ?></td>
                <td class="dataTableHeadingContent" align="left"><?php echo TABLE_HEADING_IN_STOCK; ?>
		       <?php if (isset($_GET['search']) || (strstr($_GET['cPath'],'626'))) { ?>
                			<a href="<?php echo tep_href_link(FILENAME_CATEGORIES, tep_get_all_get_params(array('listing', 'cID', 'pID')) . 'listing=in_stock'); ?>"><?php echo tep_image(DIR_WS_IMAGES . 'icon_up.gif', 'Sort by Quantity ascending'); ?></a>&nbsp;<a href="<?php echo tep_href_link(FILENAME_CATEGORIES, tep_get_all_get_params(array('listing', 'cID', 'pID')) . 'listing=in_stock-desc'); ?>"><?php echo tep_image(DIR_WS_IMAGES . 'icon_down.gif', 'Sort by Quantity descending'); ?></a>
                	<?php } ?>
		</td>
                <td class="dataTableHeadingContent" align="right"><?php echo TABLE_HEADING_ACTION; ?>&nbsp;</td>
              </tr>
<?php
    $categories_count = 0;
    $rows = 0;
    if (isset($HTTP_GET_VARS['search'])) {
      $search = tep_db_prepare_input($HTTP_GET_VARS['search']);
      $categories_query = tep_db_query("select c.categories_id, cd.categories_name, c.categories_image, c.parent_id, c.sort_order, c.date_added, c.last_modified, c.categories_status, c.categories_retail_status from " . TABLE_CATEGORIES . " c, " . TABLE_CATEGORIES_DESCRIPTION . " cd where c.categories_id = cd.categories_id and cd.language_id = '" . (int)$languages_id . "' and cd.categories_name like '%" . tep_db_input($search) . "%' order by c.sort_order, cd.categories_name");
    } 
    elseif (isset($HTTP_GET_VARS['action']) && $HTTP_GET_VARS['action'] == 'view_manufacturer_products') {
    	$categories_query = tep_db_query("select c.categories_id, cd.categories_name, c.categories_image, c.parent_id, c.sort_order, c.date_added, c.last_modified, c.categories_status, c.categories_retail_status from " . TABLE_CATEGORIES . " c, " . TABLE_CATEGORIES_DESCRIPTION . " cd where c.parent_id = '" . (int)$current_category_id . "' and c.categories_id = cd.categories_id and cd.language_id = '" . (int)$languages_id . "' and 1 = 2 order by c.sort_order, cd.categories_name");
    }
    else {
      $categories_query = tep_db_query("select c.categories_id, cd.categories_name, c.categories_image, c.parent_id, c.sort_order, c.date_added, c.last_modified, c.categories_status, c.categories_retail_status from " . TABLE_CATEGORIES . " c, " . TABLE_CATEGORIES_DESCRIPTION . " cd where c.parent_id = '" . (int)$current_category_id . "' and c.categories_id = cd.categories_id and cd.language_id = '" . (int)$languages_id . "' order by c.sort_order, cd.categories_name");
    }
    while ($categories = tep_db_fetch_array($categories_query)) {
      $categories_count++;
      $rows++;

// Get parent_id for subcategories if search
      if (isset($HTTP_GET_VARS['search'])) $cPath= $categories['parent_id'];

      if ((!isset($HTTP_GET_VARS['cID']) && !isset($HTTP_GET_VARS['pID']) || (isset($HTTP_GET_VARS['cID']) && ($HTTP_GET_VARS['cID'] == $categories['categories_id']))) && !isset($cInfo) && (substr($action, 0, 3) != 'new')) {
        $category_childs = array('childs_count' => tep_childs_in_category_count($categories['categories_id']));
        $category_products = array('products_count' => tep_products_in_category_count($categories['categories_id']));

        $cInfo_array = array_merge($categories, $category_childs, $category_products);
        $cInfo = new objectInfo($cInfo_array);
      }

      if (isset($cInfo) && is_object($cInfo) && ($categories['categories_id'] == $cInfo->categories_id) ) {
        echo '              <tr id="defaultSelected" class="dataTableRowSelected" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' . tep_href_link(FILENAME_CATEGORIES, tep_get_path($categories['categories_id'])) . '\'">' . "\n";
      } else {
        echo '              <tr class="dataTableRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&cID=' . $categories['categories_id']) . '\'">' . "\n";
      }
?>
                <td class="dataTableContent"><?php echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, tep_get_path($categories['categories_id'])) . '">' . tep_image(DIR_WS_ICONS . 'folder.gif', ICON_FOLDER) . '</a>&nbsp;<strong>' . stripslashes($categories['categories_name']) . '</strong>'; ?></td>
                <td class="dataTableContent"></td>
        <td class="dataTableContent"></td>
                <td class="dataTableContent" align="center">
                <?php
					if ($categories['categories_status'] == '1') {
				        echo tep_image(DIR_WS_IMAGES . 'icon_status_green.gif', IMAGE_ICON_STATUS_GREEN, 10, 10) . '&nbsp;&nbsp;<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=setcategoryflag&flag=0&cID=' . $categories['categories_id'] . '&cPath=' . $cPath) . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_red_light.gif', IMAGE_ICON_STATUS_RED_LIGHT, 10, 10) . '</a>';
				    } else {
				        echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=setcategoryflag&flag=1&cID=' . $categories['categories_id'] . '&cPath=' . $cPath) . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_green_light.gif', IMAGE_ICON_STATUS_GREEN_LIGHT, 10, 10) . '</a>&nbsp;&nbsp;' . tep_image(DIR_WS_IMAGES . 'icon_status_red.gif', IMAGE_ICON_STATUS_RED, 10, 10);
				    }
				?>
				</td>
				<td class="dataTableContent" align="center">
				
				</td>
                <td class="dataTableContent" align="center">
                <?php
					if ($categories['categories_retail_status'] == '1') {
				        echo tep_image(DIR_WS_IMAGES . 'icon_status_green.gif', IMAGE_ICON_STATUS_GREEN, 10, 10) . '&nbsp;&nbsp;<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=setretailcategoryflag&flag=0&cID=' . $categories['categories_id'] . '&cPath=' . $cPath) . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_red_light.gif', IMAGE_ICON_STATUS_RED_LIGHT, 10, 10) . '</a>';
				    } else {
				        echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=setretailcategoryflag&flag=1&cID=' . $categories['categories_id'] . '&cPath=' . $cPath) . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_green_light.gif', IMAGE_ICON_STATUS_GREEN_LIGHT, 10, 10) . '</a>&nbsp;&nbsp;' . tep_image(DIR_WS_IMAGES . 'icon_status_red.gif', IMAGE_ICON_STATUS_RED, 10, 10);
				    }
				?>
                </td>
                <td></td>
                <td class="dataTableContent" align="right"><?php if (isset($cInfo) && is_object($cInfo) && ($categories['categories_id'] == $cInfo->categories_id) ) { echo tep_image(DIR_WS_IMAGES . 'icon_arrow_right.gif', ''); } else { echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&cID=' . $categories['categories_id']) . '">' . tep_image(DIR_WS_IMAGES . 'icon_info.gif', IMAGE_ICON_INFO) . '</a>'; } ?>&nbsp;</td>
              </tr>
<?php
    }

    $products_count = 0;
    if (isset($HTTP_GET_VARS['search'])) {
		$search_key = $HTTP_GET_VARS['search'];
		tep_parse_search_string($search_key, $search_keywords);
		$fts_escape_search_key = preg_replace("/[^A-Za-z0-9]/", ' ', $search_key);
		$extra_fields_search = 
			"(p.products_model LIKE '%" . tep_db_input($search_key) . "%' or
			pd.products_name LIKE '%" . tep_db_input($search_key) . "%' or 
			pd.extra_value3 LIKE '%" . tep_db_input($search_key) . "%' or
			pd.extra_value2 ='" . tep_db_input($search_key) . "' or
			pd.extra_value24 ='" . tep_db_input($search_key) . "' or
            (select group_concat(concat(barcode, mpn, ' ') separator ' ') from products_attributes pa where pa.products_id = p.products_id) like '%" . tep_db_input($search_key) . "%'
			) and 
			(MATCH(pd.products_search_text) AGAINST ('" . tep_db_input($fts_escape_search_key) . "*' IN BOOLEAN MODE))";
		$extra_fields_search_tmp = array();
		if (count($search_keywords) > 1) {
			foreach ($search_keywords as $keyword) {
				if (!in_array($keyword, array('and', 'or'))) {
					$extra_fields_search_tmp []= "MATCH(pd.products_search_text) AGAINST ('" . tep_db_input($keyword) . "*' IN BOOLEAN MODE)";
				}
			}
		}
		if (count($extra_fields_search_tmp) > 1) {
			$extra_fields_search .= ' or (' . implode(' and ', $extra_fields_search_tmp) . ')';
		}
		$extra_fields_search .= ' or (p.products_id = "' . tep_db_input($search_key) . '")';
	    //$products_query = tep_db_query("select p.products_id, p.products_model, pd.products_name, p.products_quantity, p.products_image, p.products_price, p.products_date_added, p.products_last_modified, p.products_date_available, p.products_status, p.products_retail_status, p2c.categories_id from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd, " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c, " . TABLE_MANUFACTURERS . " m where p.products_id = pd.products_id and pd.language_id = '" . (int)$languages_id . "' and p.products_id = p2c.products_id and (m.manufacturers_id = p.manufacturers_id or p.manufacturers_id = 0) and (pd.products_name like '%" . tep_db_input($search) . "%' or p.products_model like '%" . tep_db_input($search) . "%' or m.manufacturers_name like '%" . tep_db_input($search) . "%' or " . $extra_fields_search . ") group by products_id order by pd.products_name");
      //$products_query = "select p.products_id, p.products_model, pd.products_name, p.products_quantity, p.products_image, p.products_price, p.products_date_added, p.products_last_modified, p.products_date_available, p.products_status, p.products_dropship_status, p.products_retail_status, pd.extra_value32 from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd where p.products_id = pd.products_id and pd.language_id = '" . (int)$languages_id . "' and (" . $extra_fields_search . ") group by products_id";
      $products_query = "select p.products_id, p.products_model, pd.products_name, p.products_quantity, p.products_image, p.products_price, p.products_date_added, p.products_last_modified, p.products_date_available, p.products_status, p.products_dropship_status, p.products_retail_status, pd.extra_value32, pd.extra_value18, pd.extra_value6, pd.extra_value7,  p.products_weight, p.manufacturers_id, p.manufacturers_id2, p.products_tax_class_id from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd where p.products_id = pd.products_id and pd.language_id = '" . (int)$languages_id . "' and (" . $extra_fields_search . ") group by products_id";

      	$order_by = 'order by products_name';
      	if (isset($_GET['listing'])) {
	      	switch ($_GET['listing']) {
	      		case 'products_name':
	      			$order_by = 'order by pd.products_name';
	      			break;
      			case 'products_name-desc':
      				$order_by = 'order by pd.products_name desc';
      				break;
      			case 'products_model-desc':
      				$order_by = 'order by p.products_model desc';
      				break;
      			case 'products_model':
      				$order_by = 'order by p.products_model';
      				break;
			case 'warehouse_code':
				$order_by = 'order by pd.extra_value32';
				break;
			case 'warehouse_code-desc':
				$order_by = 'order by pd.extra_value32 desc';
				break;
			case 'in_stock':
				$order_by = 'order by p.products_quantity';
				break;
			case 'in_stock-desc':
				$order_by = 'order by p.products_quantity desc';
				break;
	      	}
    	}
    	$products_query .= ' ' . $order_by;
      	
      	//echo $products_query;
    } 
    elseif (isset($HTTP_GET_VARS['action']) && $HTTP_GET_VARS['action'] == 'view_manufacturer_products' && isset($HTTP_GET_VARS['mID'])) {
    	$mID = (int)$HTTP_GET_VARS['mID'];
    	//$products_query = tep_db_query("select p.products_id, p.products_model, pd.products_name, p.products_quantity, p.products_image, p.products_price, p.products_date_added, p.products_last_modified, p.products_date_available, p.products_status, p.products_retail_status from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd where p.products_id = pd.products_id and pd.language_id = '" . (int)$languages_id . "' and p.manufacturers_id = " . $HTTP_GET_VARS['mID'] . " order by pd.products_name");
    	$products_query = "select p.products_id, p.products_model, pd.products_name, p.products_quantity, p.products_image, p.products_price, p.products_date_added, p.products_last_modified, p.products_date_available, p.products_status, p.products_dropship_status, p.products_retail_status, pd.extra_value32, pd.extra_value18, pd.extra_value6, pd.extra_value7,  p.products_weight, p.manufacturers_id, p.manufacturers_id2, p.products_tax_class_id from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd where p.products_id = pd.products_id and pd.language_id = '" . (int)$languages_id . "' and (p.manufacturers_id = " . $HTTP_GET_VARS['mID'] . " or p.manufacturers_id2 = " . $HTTP_GET_VARS['mID'] . ") order by pd.products_name";
    }
    else {
      //$products_query = tep_db_query("select p.products_id, p.products_model, pd.products_name, p.products_quantity, p.products_image, p.products_price, p.products_date_added, p.products_last_modified, p.products_date_available, p.products_status, p.products_retail_status from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd, " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c where p.products_id = pd.products_id and pd.language_id = '" . (int)$languages_id . "' and p.products_id = p2c.products_id and p2c.categories_id = '" . (int)$current_category_id . "' order by pd.products_name");
      //$products_query = "select p.products_id, p.products_model, pd.products_name, p.products_quantity, p.products_image, p.products_price, p.products_date_added, p.products_last_modified, p.products_date_available, p.products_status, p.products_dropship_status, p.products_retail_status, pd.extra_value32 from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd, " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c where p.products_id = pd.products_id and pd.language_id = '" . (int)$languages_id . "' and p.products_id = p2c.products_id and p2c.categories_id = '" . (int)$current_category_id . "' order by pd.products_name";
      $products_query = "select p.products_id, p.products_model, pd.products_name, p.products_quantity, p.products_image, p.products_price, p.products_date_added, p.products_last_modified, p.products_date_available, p.products_status, p.products_dropship_status, p.products_retail_status, pd.extra_value32, pd.extra_value18, pd.extra_value6, pd.extra_value7,  p.products_weight, p.manufacturers_id, p.manufacturers_id2, p.products_tax_class_id from " . TABLE_PRODUCTS . " p, " . TABLE_PRODUCTS_DESCRIPTION . " pd, " . TABLE_PRODUCTS_TO_CATEGORIES . " p2c where p.products_id = pd.products_id and pd.language_id = '" . (int)$languages_id . "' and p.products_id = p2c.products_id and p2c.categories_id = '" . (int)$current_category_id . "' ";

        $order_by = 'order by products_name';
      	if (isset($_GET['listing'])  && (strstr($_GET['cPath'],'626'))) {
	      	switch ($_GET['listing']) {
	      		case 'products_name':
	      			$order_by = 'order by pd.products_name';
	      			break;
      			case 'products_name-desc':
      				$order_by = 'order by pd.products_name desc';
      				break;
      			case 'products_model-desc':
      				$order_by = 'order by p.products_model desc';
      				break;
      			case 'products_model':
      				$order_by = 'order by p.products_model';
      				break;
			case 'warehouse_code':
				$order_by = 'order by pd.extra_value32';
				break;
			case 'warehouse_code-desc':
				$order_by = 'order by pd.extra_value32 desc';
				break;
			case 'in_stock':
				$order_by = 'order by p.products_quantity';
				break;
			case 'in_stock-desc':
				$order_by = 'order by p.products_quantity desc';
				break;
	      	}
    	}
    	$products_query .= ' ' . $order_by;
      	
      	//echo $products_query;
    }
//BOF Admin product paging
    $product_pID = (isset($HTTP_GET_VARS['pID']) ? $HTTP_GET_VARS['pID'] : "");
    
    if ((!isset($HTTP_GET_VARS['page']) || ($HTTP_GET_VARS['page'] == "")) && ($product_pID !== "")) {
       $products_total_query = tep_db_query($products_query);
      $count = 1;
      $pnumber = 0;
      while ($products_total = tep_db_fetch_array($products_total_query)) {
        if ((int)$products_total['products_id'] == (int)$product_pID) {
          $pnumber = $count;
          break;
        }
        $count++;
      }

      $page = ceil($pnumber/MAX_PROD_ADMIN_SIDE);
      $HTTP_GET_VARS['page'] = $page;
    }
	 
    $prod_split = new splitPageResults($HTTP_GET_VARS['page'], MAX_PROD_ADMIN_SIDE, $products_query, $prod_query_numrows);
    $products_query = tep_db_query($products_query);
//EOF Admin product paging
    while ($products = tep_db_fetch_array($products_query)) {
      $products_count++;
      $rows++;

// Get categories_id for product if search
      if (isset($HTTP_GET_VARS['search'])) $cPath = $products['categories_id'];

      if ( (!isset($HTTP_GET_VARS['pID']) && !isset($HTTP_GET_VARS['cID']) || (isset($HTTP_GET_VARS['pID']) && ($HTTP_GET_VARS['pID'] == $products['products_id']))) && !isset($pInfo) && !isset($cInfo) && (substr($action, 0, 3) != 'new')) {
// find out the rating average from customer reviews
        $reviews_query = tep_db_query("select (avg(reviews_rating) / 5 * 100) as average_rating from " . TABLE_REVIEWS . " where products_id = '" . (int)$products['products_id'] . "'");
        $reviews = tep_db_fetch_array($reviews_query);
        $pInfo_array = array_merge($products, $reviews);
        $pInfo = new objectInfo($pInfo_array);
      }

      if (isset($HTTP_GET_VARS['action']) && $HTTP_GET_VARS['action'] == 'view_manufacturer_products' && isset($HTTP_GET_VARS['mID'])) {
      	  if (isset($pInfo) && is_object($pInfo) && ($products['products_id'] == $pInfo->products_id) ) {
	        echo '              <tr id="defaultSelected" class="dataTableRowSelected" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&pID=' . $products['products_id'] . '&action=new_product_preview') . '\'">' . "\n";
	      } else {
	        echo '              <tr class="dataTableRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' . tep_href_link(FILENAME_CATEGORIES, 'mID=' . $HTTP_GET_VARS['mID'] . '&pID=' . $products['products_id'] . '&action=view_manufacturer_products') . '\'">' . "\n";
	      }
      }
      else {      
	      if (isset($pInfo) && is_object($pInfo) && ($products['products_id'] == $pInfo->products_id) ) {
			if (isset($_GET['search'])) {
				echo '              <tr id="defaultSelected" class="dataTableRowSelected" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' . tep_href_link(FILENAME_CATEGORIES, 'page=' . $page . '&search=' . $url_search . '&pID=' . $products['products_id'] . '&action=new_product_preview') . '\'">' . "\n";
			}
			else {
	        	echo '              <tr id="defaultSelected" class="dataTableRowSelected" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&pID=' . $products['products_id'] . '&action=new_product_preview') . '\'">' . "\n";
	        }
	      } else {
			if (isset($_GET['search'])) {
				echo '              <tr class="dataTableRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' . tep_href_link(FILENAME_CATEGORIES, 'page=' . $page . '&search=' . $url_search . '&pID=' . $products['products_id']) . ((isset($_GET['listing'])) ? '&listing=' . $_GET['listing'] : '') . '\'">' . "\n";
			}
			else {
	        	echo '              <tr class="dataTableRow" onmouseover="rowOverEffect(this)" onmouseout="rowOutEffect(this)" onclick="document.location.href=\'' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&pID=' . $products['products_id']) . '\'">' . "\n";
	        }
	      }
      }
?>
                <td class="dataTableContent"><?php echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&pID=' . $products['products_id'] . '&action=new_product_preview') . '">' . tep_image(DIR_WS_ICONS . 'preview.gif', ICON_PREVIEW) . '</a>&nbsp;' . stripslashes($products['products_name']); ?></td>
                <td class="dataTableContent" align="left"><?php echo $products['products_model']; ?></td>
                <td class="dataTableContent" align="left"><?php echo $products['extra_value32']; ?></td>
                <td class="dataTableContent" align="center">
<?php
//BOF Admin product paging
if (isset($_GET['search'])) {
  $truePath= 'page=' . $page . '&search=' . $url_search . '&pID=' . $products['products_id'];
} else {
  $truePath = 'page=' . $page . '&cPath=' . $cPath . '&pID=' . $products['products_id'];
}
//EOF Admin product paging
      if ($products['products_status'] == '1') {
		if (isset($_GET['search'])) {
			echo tep_image(DIR_WS_IMAGES . 'icon_status_green.gif', IMAGE_ICON_STATUS_GREEN, 10, 10) . '&nbsp;&nbsp;<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=setflag&flag=0&pID=' . $products['products_id'] . '&search=' . $url_search . '&page=' . $page) . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_red_light.gif', IMAGE_ICON_STATUS_RED_LIGHT, 10, 10) . '</a>';	
		}
		else {
        	echo tep_image(DIR_WS_IMAGES . 'icon_status_green.gif', IMAGE_ICON_STATUS_GREEN, 10, 10) . '&nbsp;&nbsp;<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=setflag&flag=0&pID=' . $products['products_id'] . '&cPath=' . $cPath . '&page=' . $page) . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_red_light.gif', IMAGE_ICON_STATUS_RED_LIGHT, 10, 10) . '</a>';
        }
      } else {
		if (isset($_GET['search'])) {
			echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=setflag&flag=1&pID=' . $products['products_id'] . '&search=' . $url_search . '&page=' . $page) . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_green_light.gif', IMAGE_ICON_STATUS_GREEN_LIGHT, 10, 10) . '</a>&nbsp;&nbsp;' . tep_image(DIR_WS_IMAGES . 'icon_status_red.gif', IMAGE_ICON_STATUS_RED, 10, 10);
		}
		else {
        	echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=setflag&flag=1&pID=' . $products['products_id'] . '&cPath=' . $cPath . '&page=' . $page) . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_green_light.gif', IMAGE_ICON_STATUS_GREEN_LIGHT, 10, 10) . '</a>&nbsp;&nbsp;' . tep_image(DIR_WS_IMAGES . 'icon_status_red.gif', IMAGE_ICON_STATUS_RED, 10, 10);
        }
      }
?></td>
		
		<td class="dataTableContent" align="center">
<?php
      if ($products['products_dropship_status'] == '1') {
		if (isset($_GET['search'])) {
			echo tep_image(DIR_WS_IMAGES . 'icon_status_green.gif', IMAGE_ICON_STATUS_GREEN, 10, 10) . '&nbsp;&nbsp;<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=setdropshipflag&flag=0&pID=' . $products['products_id'] . '&search=' . $url_search . '&page=' . $page) . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_red_light.gif', IMAGE_ICON_STATUS_RED_LIGHT, 10, 10) . '</a>';
		}
		else {
			echo tep_image(DIR_WS_IMAGES . 'icon_status_green.gif', IMAGE_ICON_STATUS_GREEN, 10, 10) . '&nbsp;&nbsp;<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=setdropshipflag&flag=0&pID=' . $products['products_id'] . '&cPath=' . $cPath . '&page=' . $page) . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_red_light.gif', IMAGE_ICON_STATUS_RED_LIGHT, 10, 10) . '</a>';
		}
      } else {
		if (isset($_GET['search'])) {
			echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=setdropshipflag&flag=1&pID=' . $products['products_id'] . '&search=' . $url_search . '&page=' . $page) . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_green_light.gif', IMAGE_ICON_STATUS_GREEN_LIGHT, 10, 10) . '</a>&nbsp;&nbsp;' . tep_image(DIR_WS_IMAGES . 'icon_status_red.gif', IMAGE_ICON_STATUS_RED, 10, 10);
		}
		else {
        	echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=setdropshipflag&flag=1&pID=' . $products['products_id'] . '&cPath=' . $cPath . '&page=' . $page) . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_green_light.gif', IMAGE_ICON_STATUS_GREEN_LIGHT, 10, 10) . '</a>&nbsp;&nbsp;' . tep_image(DIR_WS_IMAGES . 'icon_status_red.gif', IMAGE_ICON_STATUS_RED, 10, 10);
        }
      }
?></td>

                <td class="dataTableContent" align="center">
<?php
      if ($products['products_retail_status'] == '1') {
		if (isset($_GET['search'])) {
			echo tep_image(DIR_WS_IMAGES . 'icon_status_green.gif', IMAGE_ICON_STATUS_GREEN, 10, 10) . '&nbsp;&nbsp;<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=setretailflag&flag=0&pID=' . $products['products_id'] . '&search=' . $url_search . '&page=' . $page) . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_red_light.gif', IMAGE_ICON_STATUS_RED_LIGHT, 10, 10) . '</a>';
		}
		else {
			echo tep_image(DIR_WS_IMAGES . 'icon_status_green.gif', IMAGE_ICON_STATUS_GREEN, 10, 10) . '&nbsp;&nbsp;<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=setretailflag&flag=0&pID=' . $products['products_id'] . '&cPath=' . $cPath . '&page=' . $page) . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_red_light.gif', IMAGE_ICON_STATUS_RED_LIGHT, 10, 10) . '</a>';
		}
      } else {
		if (isset($_GET['search'])) {
			echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=setretailflag&flag=1&pID=' . $products['products_id'] . '&search=' . $url_search . '&page=' . $page) . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_green_light.gif', IMAGE_ICON_STATUS_GREEN_LIGHT, 10, 10) . '</a>&nbsp;&nbsp;' . tep_image(DIR_WS_IMAGES . 'icon_status_red.gif', IMAGE_ICON_STATUS_RED, 10, 10);
		}
		else {
        	echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'action=setretailflag&flag=1&pID=' . $products['products_id'] . '&cPath=' . $cPath . '&page=' . $page) . '">' . tep_image(DIR_WS_IMAGES . 'icon_status_green_light.gif', IMAGE_ICON_STATUS_GREEN_LIGHT, 10, 10) . '</a>&nbsp;&nbsp;' . tep_image(DIR_WS_IMAGES . 'icon_status_red.gif', IMAGE_ICON_STATUS_RED, 10, 10);
        }
      }
?></td>
	<td class="dataTableContent" align="center"><?php echo $products['products_quantity']; ?></td>
                <td class="dataTableContent" align="right"><?php if (isset($pInfo) && is_object($pInfo) && ($products['products_id'] == $pInfo->products_id)) { echo tep_image(DIR_WS_IMAGES . 'icon_arrow_right.gif', ''); } else { echo '<a href="' . tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&pID=' . $products['products_id']) . '">' . tep_image(DIR_WS_IMAGES . 'icon_info.gif', IMAGE_ICON_INFO) . '</a>'; } ?>&nbsp;</td>
              </tr>
              
<?php 
	// product attributes stock
	$product_attributes_q = tep_db_query('select options_sku, quantity from ' . TABLE_PRODUCTS_ATTRIBUTES . ' where products_id = ' . $products['products_id'] . ' order by options_sku');
	while ($product_attribute = tep_db_fetch_array($product_attributes_q)) {
		?>
		<tr>
			<td class="dataTableContent"> --------------------------------> </td>
			<td class="dataTableContent" align="left"><?php echo $product_attribute['options_sku']; ?></td>
			<td class="dataTableContent"></td>
			<td class="dataTableContent"></td>
			<td class="dataTableContent"></td>
			<td class="dataTableContent" align="center"><?php echo $product_attribute['quantity']; ?></td>
			<td class="dataTableContent"></td>
		</tr>
		<?php 
	}
?>              
              
              
<?php
    }

    $cPath_back = '';
    if (sizeof($cPath_array) > 0) {
      for ($i=0, $n=sizeof($cPath_array)-1; $i<$n; $i++) {
        if (empty($cPath_back)) {
          $cPath_back .= $cPath_array[$i];
        } else {
          $cPath_back .= '_' . $cPath_array[$i];
        }
      }
    }

    $cPath_back = (tep_not_null($cPath_back)) ? 'cPath=' . $cPath_back . '&' : '';
?>
<? //BOF Admin product paging ?>
              <tr>
                <td colspan="3">
                  <table border="0" width="100%" cellspacing="0" cellpadding="2">
                    <tr>
                      <td class="smallText" valign="top"><?php echo $prod_split->display_count($prod_query_numrows, MAX_PROD_ADMIN_SIDE, $HTTP_GET_VARS['page'], TEXT_DISPLAY_NUMBER_OF_PRODUCTS); ?></td>
                      <td class="smallText" align="right"><?php echo $prod_split->display_links($prod_query_numrows, MAX_PROD_ADMIN_SIDE, MAX_DISPLAY_PAGE_LINKS, $HTTP_GET_VARS['page'], tep_get_all_get_params(array('page','pID'))); ?></td>
                    </tr>
                  </table>
                </td>
              </tr>
<? //EOF Admin product paging ?>
              <tr>
                <td colspan="3"><table border="0" width="100%" cellspacing="0" cellpadding="2">
                  <tr>
                    <td class="smallText"><?php echo TEXT_CATEGORIES . '&nbsp;' . $categories_count . '<br />'/* . TEXT_PRODUCTS . '&nbsp;' . $products_count*/; ?></td>
                    <td align="right" class="smallText"><?php if (sizeof($cPath_array) > 0) echo tep_draw_button(IMAGE_BACK, 'triangle-1-w', tep_href_link(FILENAME_CATEGORIES, $cPath_back . 'cID=' . $current_category_id)); if (!isset($HTTP_GET_VARS['search'])) echo tep_draw_button(IMAGE_NEW_CATEGORY, 'plus', tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&action=new_category')) . tep_draw_button(IMAGE_NEW_PRODUCT, 'plus', tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&action=new_product')); ?>&nbsp;</td>
                  </tr>
                </table></td>
              </tr>
            </table></td>
<?php
    $heading = array();
    $contents = array();
    switch ($action) {
      case 'new_category':
        $heading[] = array('text' => '<strong>' . TEXT_INFO_HEADING_NEW_CATEGORY . '</strong>');

        $contents = array('form' => tep_draw_form('newcategory', FILENAME_CATEGORIES, 'action=insert_category&cPath=' . $cPath, 'post', 'enctype="multipart/form-data"'));
        $contents[] = array('text' => TEXT_NEW_CATEGORY_INTRO);

        $category_inputs_string = '';
        $languages = tep_get_languages();
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
          $category_inputs_string .= '<br />' . tep_image(DIR_WS_CATALOG_LANGUAGES . $languages[$i]['directory'] . '/images/' . $languages[$i]['image'], $languages[$i]['name']) . '&nbsp;' . tep_draw_input_field('categories_name[' . $languages[$i]['id'] . ']');
        }

        $contents[] = array('text' => '<br />' . TEXT_CATEGORIES_NAME . $category_inputs_string);
        $contents[] = array('text' => '<br />' . TEXT_CATEGORIES_IMAGE . '<br />' . tep_draw_file_field('categories_image'));
        $contents[] = array('text' => '<br />' . TEXT_SORT_ORDER . '<br />' . tep_draw_input_field('sort_order', '', 'size="2"'));
        $contents[] = array('align' => 'center', 'text' => '<br />' . tep_draw_button(IMAGE_SAVE, 'disk', null, 'primary') . tep_draw_button(IMAGE_CANCEL, 'close', tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath)));
        break;
      case 'edit_category':
        $heading[] = array('text' => '<strong>' . TEXT_INFO_HEADING_EDIT_CATEGORY . '</strong>');

        $contents = array('form' => tep_draw_form('categories', FILENAME_CATEGORIES, 'action=update_category&cPath=' . $cPath, 'post', 'enctype="multipart/form-data"') . tep_draw_hidden_field('categories_id', $cInfo->categories_id));
        $contents[] = array('text' => TEXT_EDIT_INTRO);

        $category_inputs_string = '';
        $languages = tep_get_languages();
        for ($i = 0, $n = sizeof($languages); $i < $n; $i++) {
          $category_inputs_string .= '<br />' . tep_image(DIR_WS_CATALOG_LANGUAGES . $languages[$i]['directory'] . '/images/' . $languages[$i]['image'], $languages[$i]['name']) . '&nbsp;' . tep_draw_input_field('categories_name[' . $languages[$i]['id'] . ']', tep_get_category_name($cInfo->categories_id, $languages[$i]['id']));
        }

        $contents[] = array('text' => '<br />' . TEXT_EDIT_CATEGORIES_NAME . $category_inputs_string);
        $contents[] = array('text' => '<br />' . tep_image(DIR_WS_CATALOG_IMAGES . $cInfo->categories_image, $cInfo->categories_name) . '<br />' . DIR_WS_CATALOG_IMAGES . '<br /><strong>' . $cInfo->categories_image . '</strong>');
        $contents[] = array('text' => '<br />' . TEXT_EDIT_CATEGORIES_IMAGE . '<br />' . tep_draw_file_field('categories_image'));
        $contents[] = array('text' => '<br />' . TEXT_EDIT_SORT_ORDER . '<br />' . tep_draw_input_field('sort_order', $cInfo->sort_order, 'size="2"'));
        $contents[] = array('align' => 'center', 'text' => '<br />' . tep_draw_button(IMAGE_SAVE, 'disk', null, 'primary') . tep_draw_button(IMAGE_CANCEL, 'close', tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&cID=' . $cInfo->categories_id)));
        break;
      case 'delete_category':
        $heading[] = array('text' => '<strong>' . TEXT_INFO_HEADING_DELETE_CATEGORY . '</strong>');

        $contents = array('form' => tep_draw_form('categories', FILENAME_CATEGORIES, 'action=delete_category_confirm&cPath=' . $cPath) . tep_draw_hidden_field('categories_id', $cInfo->categories_id) . tep_draw_hidden_field('cPath', $_GET['cPath']));
        $contents[] = array('text' => TEXT_DELETE_CATEGORY_INTRO);
        $contents[] = array('text' => '<br /><strong>' . $cInfo->categories_name . '</strong>');
        if ($cInfo->childs_count > 0) $contents[] = array('text' => '<br />' . sprintf(TEXT_DELETE_WARNING_CHILDS, $cInfo->childs_count));
        if ($cInfo->products_count > 0) $contents[] = array('text' => '<br />' . sprintf(TEXT_DELETE_WARNING_PRODUCTS, $cInfo->products_count));
        $contents[] = array('align' => 'center', 'text' => '<br />' . tep_draw_button(IMAGE_DELETE, 'trash', null, 'primary') . tep_draw_button(IMAGE_CANCEL, 'close', tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&cID=' . $cInfo->categories_id)));
        break;
      case 'move_category':
        $heading[] = array('text' => '<strong>' . TEXT_INFO_HEADING_MOVE_CATEGORY . '</strong>');

        $contents = array('form' => tep_draw_form('categories', FILENAME_CATEGORIES, 'action=move_category_confirm&cPath=' . $cPath) . tep_draw_hidden_field('categories_id', $cInfo->categories_id));
        $contents[] = array('text' => sprintf(TEXT_MOVE_CATEGORIES_INTRO, $cInfo->categories_name));
        $contents[] = array('text' => '<br />' . sprintf(TEXT_MOVE, $cInfo->categories_name) . '<br />' . tep_draw_pull_down_menu('move_to_category_id', tep_get_category_tree(), $current_category_id));
        $contents[] = array('align' => 'center', 'text' => '<br />' . tep_draw_button(IMAGE_MOVE, 'arrow-4', null, 'primary') . tep_draw_button(IMAGE_CANCEL, 'close', tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&cID=' . $cInfo->categories_id)));
        break;
      case 'delete_product':
        $heading[] = array('text' => '<strong>' . TEXT_INFO_HEADING_DELETE_PRODUCT . '</strong>');
        
        $search = '';
        if (isset($_GET['search'])) {
			$search .= '&search=' . $url_search . '&page=' . $page;
		}

        $contents = array('form' => tep_draw_form('products', FILENAME_CATEGORIES, 'action=delete_product_confirm&cPath=' . $cPath . $search) . tep_draw_hidden_field('products_id', $pInfo->products_id));
        $contents[] = array('text' => TEXT_DELETE_PRODUCT_INTRO);
        $contents[] = array('text' => '<br /><strong>' . $pInfo->products_name . '</strong>');

        $product_categories_string = '';
        $product_categories = tep_generate_category_path($pInfo->products_id, 'product');
        for ($i = 0, $n = sizeof($product_categories); $i < $n; $i++) {
          $category_path = '';
          for ($j = 0, $k = sizeof($product_categories[$i]); $j < $k; $j++) {
            $category_path .= $product_categories[$i][$j]['text'] . '&nbsp;&gt;&nbsp;';
          }
          $category_path = substr($category_path, 0, -16);
          $product_categories_string .= tep_draw_checkbox_field('product_categories[]', $product_categories[$i][sizeof($product_categories[$i])-1]['id'], true) . '&nbsp;' . $category_path . '<br />';
        }
        $product_categories_string = substr($product_categories_string, 0, -4);

        $contents[] = array('text' => '<br />' . $product_categories_string);
        $contents[] = array('align' => 'center', 'text' => '<br />' . tep_draw_button(IMAGE_DELETE, 'trash', null, 'primary') . tep_draw_button(IMAGE_CANCEL, 'close', tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&pID=' . $pInfo->products_id)));
        break;
      case 'move_product':
        $heading[] = array('text' => '<strong>' . TEXT_INFO_HEADING_MOVE_PRODUCT . '</strong>');
        
        $search = '';
        if (isset($_GET['search'])) { $search .= '&search=' . $url_search . '&page=' . $page; }

        $contents = array('form' => tep_draw_form('products', FILENAME_CATEGORIES, 'action=move_product_confirm&cPath=' . $cPath . $search) . tep_draw_hidden_field('products_id', $pInfo->products_id));
        $contents[] = array('text' => sprintf(TEXT_MOVE_PRODUCTS_INTRO, $pInfo->products_name));
        $contents[] = array('text' => '<br />' . TEXT_INFO_CURRENT_CATEGORIES . '<br /><strong>' . tep_output_generated_category_path($pInfo->products_id, 'product') . '</strong>');
        $contents[] = array('text' => '<br />' . sprintf(TEXT_MOVE, $pInfo->products_name) . '<br />' . tep_draw_pull_down_menu('move_to_category_id', tep_get_category_tree(), $current_category_id));
        $contents[] = array('align' => 'center', 'text' => '<br />' . tep_draw_button(IMAGE_MOVE, 'arrow-4', null, 'primary') . tep_draw_button(IMAGE_CANCEL, 'close', tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&pID=' . $pInfo->products_id)));
        break;
      case 'copy_to':
        $heading[] = array('text' => '<strong>' . TEXT_INFO_HEADING_COPY_TO . '</strong>');

        $contents = array('form' => tep_draw_form('copy_to', FILENAME_CATEGORIES, 'action=copy_to_confirm&cPath=' . $cPath) . tep_draw_hidden_field('products_id', $pInfo->products_id));
        $contents[] = array('text' => TEXT_INFO_COPY_TO_INTRO);
        $contents[] = array('text' => '<br />' . TEXT_INFO_CURRENT_CATEGORIES . '<br /><strong>' . tep_output_generated_category_path($pInfo->products_id, 'product') . '</strong>');
        $contents[] = array('text' => '<br />' . TEXT_CATEGORIES . '<br />' . tep_draw_pull_down_menu('categories_id', tep_get_category_tree(), $current_category_id));
        $contents[] = array('text' => '<br />' . TEXT_HOW_TO_COPY . '<br />' . tep_draw_radio_field('copy_as', 'link', true) . ' ' . TEXT_COPY_AS_LINK . '<br />' . tep_draw_radio_field('copy_as', 'duplicate') . ' ' . TEXT_COPY_AS_DUPLICATE);
        $contents[] = array('align' => 'center', 'text' => '<br />' . tep_draw_button(IMAGE_COPY, 'copy', null, 'primary') . tep_draw_button(IMAGE_CANCEL, 'close', tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&pID=' . $pInfo->products_id)));
        break;
      default:
        if ($rows > 0) {
          if (isset($cInfo) && is_object($cInfo)) { // category info box contents
            $category_path_string = '';
            $category_path = tep_generate_category_path($cInfo->categories_id);
            for ($i=(sizeof($category_path[0])-1); $i>0; $i--) {
              $category_path_string .= $category_path[0][$i]['id'] . '_';
            }
            $category_path_string = substr($category_path_string, 0, -1);

            $heading[] = array('text' => '<strong>' . $cInfo->categories_name . '</strong>');

            $contents[] = array('align' => 'center', 'text' => tep_draw_button(IMAGE_EDIT, 'document', tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $category_path_string . '&cID=' . $cInfo->categories_id . '&action=edit_category')) . tep_draw_button(IMAGE_DELETE, 'trash', tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $category_path_string . '&cID=' . $cInfo->categories_id . '&action=delete_category')) . tep_draw_button(IMAGE_MOVE, 'arrow-4', tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $category_path_string . '&cID=' . $cInfo->categories_id . '&action=move_category')));
            $contents[] = array('text' => '<br />' . TEXT_DATE_ADDED . ' ' . tep_date_short($cInfo->date_added));
            if (tep_not_null($cInfo->last_modified)) $contents[] = array('text' => TEXT_LAST_MODIFIED . ' ' . tep_date_short($cInfo->last_modified));
            $contents[] = array('text' => '<br />' . tep_info_image($cInfo->categories_image, $cInfo->categories_name, HEADING_IMAGE_WIDTH, HEADING_IMAGE_HEIGHT) . '<br />' . $cInfo->categories_image);
            $contents[] = array('text' => '<br />' . TEXT_SUBCATEGORIES . ' ' . $cInfo->childs_count . '<br />' . TEXT_PRODUCTS . ' ' . $cInfo->products_count);
          } elseif (isset($pInfo) && is_object($pInfo)) { // product info box contents
	    //var_dump($pInfo);
            $heading[] = array('text' => '<strong>' . tep_get_products_name($pInfo->products_id, $languages_id) . '</strong>');
//BOF Admin product paging
            if (isset($_GET['search'])) {

              $cPath_query = "select distinct products_id, categories_id from " . TABLE_PRODUCTS_TO_CATEGORIES . " where products_id= '" . $pInfo->products_id . "' ";
              $cPath_fetch = mysql_fetch_array(mysql_query($cPath_query));
              $cPath2 = $cPath_fetch['categories_id'];

              $truecPath =  'page=' . $page . '&cPath=' . $cPath2 . '&pID=' . $pInfo->products_id . '&search=' . $url_search;
              if (isset($_GET['listing'])) {
              	$truecPath .= '&listing=' . $_GET['listing'];
              }
            } 
            elseif (isset($HTTP_GET_VARS['action']) && $HTTP_GET_VARS['action'] == 'view_manufacturer_products') {
				$truecPath =  'page=' . $page . '&mID=' . $_GET['mID'] . '&pID=' . $pInfo->products_id;
			}
            else {
              $truecPath =  'page=' . $page . '&cPath=' . $cPath . '&pID=' . $pInfo->products_id;
            }
//EOF Admin product paging

            //$contents[] = array('align' => 'center', 'text' => tep_draw_button(IMAGE_EDIT, 'document', tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&pID=' . $pInfo->products_id . '&action=new_product')) . tep_draw_button(IMAGE_DELETE, 'trash', tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&pID=' . $pInfo->products_id . '&action=delete_product')) . tep_draw_button(IMAGE_MOVE, 'arrow-4', tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&pID=' . $pInfo->products_id . '&action=move_product')) . tep_draw_button(IMAGE_COPY_TO, 'copy', tep_href_link(FILENAME_CATEGORIES, 'cPath=' . $cPath . '&pID=' . $pInfo->products_id . '&action=copy_to')) . tep_draw_button(IMAGE_NEW_REVIEW, 'document', tep_href_link(FILENAME_WRITE_REVIEWS, 'pID=' . $pInfo->products_id)));
            $contents[] = array('align' => 'center', 'text' => tep_draw_button(IMAGE_EDIT, 'document', tep_href_link(FILENAME_CATEGORIES, $truecPath . '&action=new_product')) . 
				tep_draw_button(IMAGE_DELETE, 'trash', tep_href_link(FILENAME_CATEGORIES, $truecPath . '&action=delete_product')) . 
				((!isset($_GET['action']) || $_GET['action'] != 'view_manufacturer_products') ? tep_draw_button(IMAGE_MOVE, 'arrow-4', tep_href_link(FILENAME_CATEGORIES, $truecPath . '&action=move_product')) : '') . 
				((!isset($_GET['action']) || $_GET['action'] != 'view_manufacturer_products') ? tep_draw_button(IMAGE_COPY_TO, 'copy', tep_href_link(FILENAME_CATEGORIES, $truecPath . '&action=copy_to')) : '') .
				tep_draw_button(IMAGE_NEW_REVIEW, 'document', tep_href_link(FILENAME_WRITE_REVIEWS, 'pID=' . $pInfo->products_id))
            );
            
            $specials_check_q = tep_db_query('select products_id from ' . TABLE_SPECIALS . ' where products_id = ' . $pInfo->products_id . ' and customers_group_id = 0 limit 1');
            $specials_check = tep_db_fetch_array($specials_check_q);
            if (!isset($specials_check['products_id'])) {
            	$contents[] = array('align' => 'center', 'text' => tep_draw_button('Set Special Retail', 'document', tep_href_link(FILENAME_SPECIALS, 'action=new&c_g=0&pID=' . $pInfo->products_id)));
            }
            
            $specials_check_q = tep_db_query('select products_id from ' . TABLE_SPECIALS . ' where products_id = ' . $pInfo->products_id . ' and customers_group_id = 1 limit 1');
            $specials_check = tep_db_fetch_array($specials_check_q);
            if (!isset($specials_check['products_id'])) {
            	$contents[] = array('align' => 'center', 'text' => tep_draw_button('Set Special Wholesale', 'document', tep_href_link(FILENAME_SPECIALS, 'action=new&c_g=1&pID=' . $pInfo->products_id)));
            }
            
            $contents[] = array('align' => 'center', 'text' => tep_draw_button('Product Stats', 'document', tep_href_link('product_stats.php', 'pID=' . $pInfo->products_id)));

            $contents[] = array('align' => 'center', 'text' => tep_draw_button('Send to Amazon', 'document', tep_href_link(FILENAME_CATEGORIES, $truecPath . '&action=send_to_amazon')));
            
	    if (!strstr($_GET['cPath'],'626')){ 
  	    $contents[] = array('align' => 'center', 'text' => tep_draw_button('Move to OutOfStock', 'document', tep_href_link(FILENAME_CATEGORIES, $truecPath . '&action=move_product_outofstock&move_to_category_id=626' )));
	    }

            $contents[] = array('text' => '<br />' . TEXT_DATE_ADDED . ' ' . tep_date_short($pInfo->products_date_added));
            if (tep_not_null($pInfo->products_last_modified)) $contents[] = array('text' => TEXT_LAST_MODIFIED . ' ' . tep_date_short($pInfo->products_last_modified));
            if (date('Y-m-d') < $pInfo->products_date_available) $contents[] = array('text' => TEXT_DATE_AVAILABLE . ' ' . tep_date_short($pInfo->products_date_available));
            $contents[] = array('text' => '<br />' . tep_info_image($pInfo->products_image, $pInfo->products_name, SMALL_IMAGE_WIDTH, SMALL_IMAGE_HEIGHT) . '<br />' . $pInfo->products_image);

	    if($pInfo->products_tax_class_id == 0){
            $contents[] = array('text' => '<br />' . TEXT_PRODUCTS_PRICE_INFO . ' ' . $currencies->format($pInfo->products_price) . '<br />' . TEXT_PRODUCTS_QUANTITY_INFO . ' ' . $pInfo->products_quantity);
            } else {
              $taxquery = 'select tax_rate from '. TABLE_TAX_RATES .' where tax_class_id = '.$pInfo->products_tax_class_id. ' limit 1'; 
	      $tax_query = tep_db_query($taxquery); $tresult = tep_db_fetch_array($tax_query); 
              $gross_price = round(($pInfo->products_price + ($pInfo->products_price * ($tresult['tax_rate']/100))), 4); 
            $contents[] = array('text' => '<br />' . TEXT_PRODUCTS_PRICE_GROSS . ' ' . $currencies->format($gross_price) . '<br />' . TEXT_PRODUCTS_QUANTITY_INFO . ' ' . $pInfo->products_quantity);
	    }
            $wholesaleQuery = 'select customers_group_price from '. TABLE_PRODUCTS_GROUPS .' where products_id = '.$pInfo->products_id. ' limit 1'; $wholesale_query = tep_db_query($wholesaleQuery); $wresult = tep_db_fetch_array($wholesale_query); 
            $contents[] = array('text' => '<br /> Wholesale Price:' . ' ' . $currencies->format($wresult[customers_group_price]));

            $contents[] = array('text' => '<br /> Location Upright/Unit:' . ' ' . $pInfo->extra_value18 );
            $contents[] = array('text' => '<br /> Location Bay:' . ' ' . $pInfo->extra_value6 );
            $contents[] = array('text' => '<br /> Locarion Shelf:' . ' ' . $pInfo->extra_value7 );
            $contents[] = array('text' => '<br />' . TEXT_PRODUCTS_WEIGHT . ' ' . number_format($pInfo->products_weight, 2));

            $manQuery = 'select manufacturers_name from '. TABLE_MANUFACTURERS .' where manufacturers_id = '.$pInfo->manufacturers_id. ' limit 1'; $manufact_query = tep_db_query($manQuery); $qresult = tep_db_fetch_array($manufact_query); 
            $contents[] = array('text' => '<br /> Manufacturers Name:' . ' ' . $qresult[manufacturers_name]);

            $categories_query = tep_db_query('select categories_id from ' . TABLE_PRODUCTS_TO_CATEGORIES . ' where products_id = ' . $pInfo->products_id . ' order by p2c_id');
            $paths = array();
            while ($categories_id = tep_db_fetch_array($categories_query)) {
            	$categories_id = $categories_id['categories_id'];
            	$path_for_product = tep_get_category_name($categories_id, 1);
            	$tmp_categories_id = tep_db_query('select parent_id from ' . TABLE_CATEGORIES . ' where categories_id = ' . $categories_id);
            	$tmp_categories_id2 = tep_db_fetch_array($tmp_categories_id);
            	if (isset($tmp_categories_id2['parent_id'])) {
            		if ($tmp_categories_id2['parent_id'] == 0) {
            			$path_for_product = 'Home' . ' \\ ' . $path_for_product;
            		}
            		else {
            			$path_for_product = tep_get_category_name($tmp_categories_id2['parent_id'], 1) . ' \\ ' . $path_for_product;
            		}
            	}
            	else {
            		$paths[] = $path_for_product;
            		continue;
            	}
            	
            	for ($i = 0; $i <= 10; $i++) {
					if (isset($tmp_categories_id2['parent_id'])) {
						$tmp_categories_id = tep_db_query('select parent_id from ' . TABLE_CATEGORIES . ' where categories_id = ' . $tmp_categories_id2['parent_id']);
						$tmp_categories_id2 = tep_db_fetch_array($tmp_categories_id);
						if (isset($tmp_categories_id2['parent_id'])) {
							if ($tmp_categories_id2['parent_id'] == 0) {
								$path_for_product = 'Home' . ' \\ ' . $path_for_product;
							}
							else {
								$path_for_product = tep_get_category_name($tmp_categories_id2['parent_id'], 1) . ' \\ ' . $path_for_product;
							}
						}
						else {
							$paths[] = $path_for_product;
							continue;
						}
					}
				}
            }
            
            $contents[] = array('text' => '<br />' . TEXT_PRODUCTS_IN_CATEGORIES . '<br />' . implode('<br />', $paths));
          }
        } else { // create category/product info
          $heading[] = array('text' => '<strong>' . EMPTY_CATEGORY . '</strong>');

          $contents[] = array('text' => TEXT_NO_CHILD_CATEGORIES_OR_PRODUCTS);
        }
        break;
    }
    
    // Modular SEO Header Tags
  	include( DIR_WS_MODULES . 'header_tags/categories_insert.php' );

    if ( (tep_not_null($heading)) && (tep_not_null($contents)) ) {
      echo '            <td width="25%" valign="top">' . "\n";

      $box = new box;
      echo $box->infoBox($heading, $contents);

      echo '            </td>' . "\n";
    }
?>
          </tr>
        </table></td>
      </tr>
    </table>
<?php
  }

  require(DIR_WS_INCLUDES . 'template_bottom.php');
  require(DIR_WS_INCLUDES . 'application_bottom.php');
?>
