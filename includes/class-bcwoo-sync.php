<?php
/**
 * BCWoo_Sync - Clean Business Central to WooCommerce Sync
 * 
 * This class provides clean, focused sync operations from Business Central to WooCommerce.
 * It handles the complete sync process including data mapping, image import, and error handling.
 */

if (!defined('ABSPATH')) {
    exit;
}

class BCWoo_Sync {
    public static function run_full() {
        self::sync_items(true);
    }
    
    public static function run_incremental() {
        self::sync_items(false);
    }



    private static function import_images($image_responses, $product_id) {
        // $image_responses: array of wp_remote_get responses with body stream
        $ids = [];
        require_once ABSPATH . 'wp-admin/includes/image.php';
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';

        foreach ($image_responses as $i => $resp) {
            if (!$resp) continue;
            $body = wp_remote_retrieve_body($resp);
            $type = wp_remote_retrieve_header($resp, 'content-type');
            $ext  = ($type && strpos($type, '/') !== false) ? explode('/', $type)[1] : 'jpg';
            $filename = "bc-{$product_id}-" . ($i+1) . ".{$ext}";

            // Sideload to media library
            $tmp = wp_tempnam($filename);
            if (!$tmp) continue;
            file_put_contents($tmp, $body);

            $file = [
                'name' => $filename,
                'type' => $type ?: 'image/jpeg',
                'tmp_name' => $tmp,
                'error' => 0,
                'size' => filesize($tmp),
            ];
            // Avoid duplicates by hashing optional
            $attach_id = media_handle_sideload($file, 0, "Imported from Business Central");
            if (!is_wp_error($attach_id)) {
                $ids[] = $attach_id;
            } else {
                @unlink($tmp);
            }
        }
        return $ids;
    }

    private static function fetch_all_items($client, $companyId, $filter = null) {
        $items = [];
        $page = $client->list_items($companyId, $filter, 100);
        $items = array_merge($items, $page['value'] ?? []);
        while (!empty($page['@odata.nextLink'])) {
            $page = $client->list_items_next($page['@odata.nextLink']);
            $items = array_merge($items, $page['value'] ?? []);
        }
        return $items;
    }

    private static function pull_pictures($client, $companyId, $itemId) {
        $pics = $client->get_item_pictures($companyId, $itemId);
        $images = [];
        foreach (($pics['value'] ?? []) as $p) {
            $resp = $client->download_picture_stream($p);
            if ($resp) $images[] = $resp;
        }
        return $images;
    }

    public static function sync_items($full = false) {
        $opts = get_option('bcc_settings', []); // Use bcc_settings instead of bcwoo_options
        $companyId = $opts['company_id'] ?? '';
        if (!$companyId) throw new Exception('Company ID missing');

        $client = new BCWoo_Client();

        // Build filter
        $filter = null;
        if (!$full && !empty($opts['last_sync'])) {
            // BC uses ISO 8601; ensure Z
            $since = esc_sql($opts['last_sync']);
            $filter = "lastModifiedDateTime gt {$since}";
        }

        $items = self::fetch_all_items($client, $companyId, $filter);

        foreach ($items as $item) {
            try {
                $mapped = self::map_item_to_wc($item);
                $images = self::pull_pictures($client, $companyId, $item['id']);
                self::upsert_product($mapped, $images, $opts);
            } catch (\Throwable $e) {
                error_log('[BCWoo] Item sync failed: ' . $e->getMessage());
                continue;
            }
        }

        // Save last sync timestamp
        update_option('bcc_settings', array_merge($opts, ['last_sync' => gmdate('c')]));
    }
    
    /**
     * Enhanced sync with detailed result tracking
     */
    public static function sync_items_with_results($full = false) {
        $opts = get_option('bcc_settings', []);
        $companyId = $opts['company_id'] ?? '';
        if (!$companyId) throw new Exception('Company ID missing');

        $client = new BCWoo_Client();
        
        $results = [
            'sync_type' => $full ? 'full' : 'incremental',
            'total_processed' => 0,
            'created' => 0,
            'updated' => 0,
            'skipped' => 0,
            'errors' => [],
            'sync_timestamp' => current_time('mysql')
        ];

        // Build filter
        $filter = null;
        if (!$full && !empty($opts['last_sync'])) {
            $since = esc_sql($opts['last_sync']);
            $filter = "lastModifiedDateTime gt {$since}";
        }

        $items = self::fetch_all_items($client, $companyId, $filter);
        $results['total_processed'] = count($items);

        foreach ($items as $item) {
            try {
                $mapped = self::map_item_to_wc($item);
                $images = self::pull_pictures($client, $companyId, $item['id']);
                
                $product_id = self::upsert_product($mapped, $images, $opts);
                
                if ($product_id) {
                    // Check if this was a new product or update
                    $existing_product = wc_get_product_id_by_sku($mapped['sku']);
                    if ($existing_product && $existing_product === $product_id) {
                        $results['updated']++;
                    } else {
                        $results['created']++;
                    }
                } else {
                    $results['skipped']++;
                }
                
            } catch (\Throwable $e) {
                $error_msg = 'Item ' . ($item['number'] ?? 'unknown') . ': ' . $e->getMessage();
                $results['errors'][] = $error_msg;
                error_log('[BCWoo] Item sync failed: ' . $error_msg);
                continue;
            }
        }

        // Save last sync timestamp
        update_option('bcc_last_product_sync_timestamp', $results['sync_timestamp']);
        
        return $results;
    }

    /**
     * Dry run - preview what would be synced without actually syncing
     */
    public static function dry_run($full = false) {
        $opts = get_option('bcc_settings', []);
        $companyId = $opts['company_id'] ?? '';
        if (!$companyId) throw new Exception('Company ID missing');

        $client = new BCWoo_Client();
        
        // Build filter
        $filter = null;
        if (!$full && !empty($opts['last_sync'])) {
            $since = esc_sql($opts['last_sync']);
            $filter = "lastModifiedDateTime gt {$since}";
        }

        // Limit to first 100 items for preview
        $page = $client->list_items($companyId, $filter, 100);
        $items = $page['value'] ?? [];

        $results = [
            'sync_type' => $full ? 'full' : 'incremental',
            'total_found' => count($items),
            'total_available' => $page['@odata.count'] ?? 'unknown',
            'filter_applied' => $filter,
            'items' => $items
        ];

        return $results;
    }

    /**
     * Enhanced dry run with diff computation (no DB writes, no media import)
     */
    public static function dry_run_with_diffs() {
        $opts = get_option('bcc_settings', []);
        $companyId = $opts['company_id'] ?? '';
        if (!$companyId) throw new Exception('Company ID missing');

        $client = new BCWoo_Client();

        // Incremental by last_sync
        $filter = null;
        if (!empty($opts['last_sync'])) {
            $filter = "lastModifiedDateTime gt {$opts['last_sync']}";
        }

        $items = self::fetch_all_items($client, $companyId, $filter);

        $rows = [];
        foreach ($items as $item) {
            $mapped = self::map_item_to_wc($item);
            $sku = $mapped['sku'];
            if (!$sku) continue;

            $existing_id = wc_get_product_id_by_sku($sku);
            if (!$existing_id) {
                $rows[] = [
                    'sku' => $sku,
                    'status' => 'CREATE',
                    'name' => $mapped['name'],
                    'price' => $mapped['price'],
                    'stock' => $mapped['stock'],
                    'category' => ($item['itemCategoryCode'] ?? $item['itemCategoryId'] ?? ''),
                    'images' => 'will fetch',
                    'changes' => 'N/A (new)'
                ];
                continue;
            }
            
            $prod = wc_get_product($existing_id);
            $diffs = [];

            if ($prod->get_name() !== $mapped['name']) $diffs[] = 'name';
            $currPrice = (float)$prod->get_regular_price();
            if ((string)$currPrice !== (string)($mapped['price'] ?? '')) $diffs[] = 'price';
            if ($prod->get_manage_stock()) {
                if ((int)$prod->get_stock_quantity() !== (int)$mapped['stock']) $diffs[] = 'stock';
            } else {
                if ($mapped['stock'] !== null) $diffs[] = 'stock(+manage)';
            }
            $bcCat = ($item['itemCategoryCode'] ?? $item['itemCategoryId'] ?? '');
            if ($bcCat) $diffs[] = 'category(map check)';

            $rows[] = [
                'sku' => $sku,
                'status' => empty($diffs) ? 'SKIP' : 'UPDATE',
                'name' => $mapped['name'],
                'price' => $mapped['price'],
                'stock' => $mapped['stock'],
                'category' => $bcCat,
                'images' => 'unchanged (preview)',
                'changes' => empty($diffs) ? '—' : implode(', ', $diffs),
            ];
        }

        // Build compact HTML table for admin
        ob_start();
        echo '<table class="widefat striped"><thead><tr>';
        foreach (['SKU','Action','Name','Price','Stock','BC Category','Images','Changes'] as $col) {
            printf('<th>%s</th>', esc_html($col));
        }
        echo '</tr></thead><tbody>';
        foreach ($rows as $r) {
            printf(
                '<tr><td>%s</td><td><strong>%s</strong></td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td><td>%s</td></tr>',
                esc_html($r['sku']),
                esc_html($r['status']),
                esc_html($r['name']),
                esc_html((string)$r['price']),
                esc_html((string)$r['stock']),
                esc_html((string)$r['category']),
                esc_html($r['images']),
                esc_html($r['changes'])
            );
        }
        echo '</tbody></table>';
        
        if (!empty($rows)) {
            echo '<p><strong>Total items to process:</strong> ' . count($rows) . '</p>';
            if ($filter) {
                echo '<p><strong>Filter applied:</strong> ' . esc_html($filter) . '</p>';
            }
        }
        
        return ob_get_clean();
    }

    /**
     * Rebuild images for products
     */
    public static function rebuild_images($sku = null) {
        $opts = get_option('bcc_settings', []);
        $companyId = $opts['company_id'] ?? '';
        if (!$companyId) throw new Exception('Company ID missing');
        $client = new BCWoo_Client();

        $count = 0;
        if ($sku) {
            $pid = wc_get_product_id_by_sku($sku);
            if ($pid) {
                $count += self::rebuild_images_for_product($client, $companyId, $pid, $sku);
            }
            return $count;
        }

        // All products with SKUs – CAUTION heavy
        $args = [
            'status' => ['publish','draft','pending','private'],
            'limit'  => -1,
            'return' => 'ids',
        ];
        $ids = wc_get_products($args);
        foreach ($ids as $pid) {
            $prod = wc_get_product($pid);
            $psku = $prod->get_sku();
            if (!$psku) continue;
            $count += self::rebuild_images_for_product($client, $companyId, $pid, $psku);
        }
        return $count;
    }

    /**
     * Rebuild images for a specific product
     */
    private static function rebuild_images_for_product($client, $companyId, $product_id, $sku) {
        // Find BC Item by number == SKU
        $filter = "number eq '" . str_replace("'", "''", $sku) . "'";
        $page = $client->list_items($companyId, $filter, 1);
        $item = ($page['value'][0] ?? null);
        if (!$item) return 0;

        // Pull fresh pictures
        $images = self::pull_pictures($client, $companyId, $item['id']);
        if (empty($images)) return 0;

        // Remove existing featured & gallery images
        $prod = wc_get_product($product_id);
        $old = [];
        $fid = $prod->get_image_id();
        if ($fid) $old[] = $fid;
        foreach ($prod->get_gallery_image_ids() as $gid) $old[] = $gid;

        // Import new
        $ids = self::import_images($images, $product_id);
        if (!empty($ids)) {
            $prod->set_image_id(array_shift($ids));
            $prod->set_gallery_image_ids($ids);
            $prod->save();
        }

        // Optionally delete old attachments (keep if you want history)
        foreach ($old as $aid) {
            if (!in_array($aid, $ids, true)) {
                wp_delete_attachment($aid, true);
            }
        }
        return 1;
    }

    /**
     * Enhanced map_item_to_wc with category mapping support
     */
    private static function map_item_to_wc($item) {
        $mapped = [
            'sku'         => $item['number'] ?? '',
            'name'        => $item['displayName'] ?? ($item['description'] ?? ''),
            'description' => wp_kses_post($item['description'] ?? ''),
            'price'       => isset($item['unitPrice']) ? (float)$item['unitPrice'] : null,
            'stock'       => isset($item['inventory']) ? (int)$item['inventory'] : null,
            'active'      => !empty($item['blocked']) ? false : true,
            'bc_category' => $item['itemCategoryCode'] ?? ($item['itemCategoryId'] ?? ''),
            'bc_id'       => $item['id'] ?? null,
        ];

        return $mapped;
    }

    /**
     * Parse category mapping string into array
     */
    private static function parse_category_map($mapStr) {
        $map = [];
        foreach (preg_split('/\r\n|\r|\n/', (string)$mapStr) as $line) {
            $line = trim($line);
            if ($line === '' || str_starts_with($line, '#')) continue;
            // Format: BCCode => Woo Category Name
            if (strpos($line, '=>') !== false) {
                [$bc, $woo] = array_map('trim', explode('=>', $line, 2));
                if ($bc !== '' && $woo !== '') $map[$bc] = $woo;
            }
        }
        return $map;
    }

    /**
     * Ensure WooCommerce category exists and return term ID
     */
    private static function ensure_woo_category($name) {
        $term = term_exists($name, 'product_cat');
        if (!$term) {
            $term = wp_insert_term($name, 'product_cat');
        }
        if (is_wp_error($term)) return 0;
        return (int)($term['term_id'] ?? $term['term_taxonomy_id'] ?? 0);
    }

    /**
     * Apply category mapping to a product
     */
    private static function apply_category_mapping($product_id, $bcCategoryCode, $opts) {
        if (!$bcCategoryCode) return;
        $map = self::parse_category_map($opts['category_map'] ?? '');
        if (empty($map[$bcCategoryCode])) return;
        $wooCatName = $map[$bcCategoryCode];
        $term_id = self::ensure_woo_category($wooCatName);
        if ($term_id) {
            wp_set_object_terms($product_id, [$term_id], 'product_cat', false);
        }
    }

    /**
     * Enhanced upsert_product with category mapping support
     */
    private static function upsert_product($mapped, $images, $opts = null) {
        if (empty($mapped['sku'])) return;
        $opts = $opts ?: get_option('bcc_settings', []);

        $product_id = wc_get_product_id_by_sku($mapped['sku']);
        if ($product_id) {
            $product = wc_get_product($product_id);
        } else {
            $product = new WC_Product_Simple();
            $product->set_sku($mapped['sku']);
        }

        $product->set_name($mapped['name']);
        if ($mapped['description']) $product->set_description($mapped['description']);
        if (!is_null($mapped['price'])) $product->set_regular_price((string)$mapped['price']);
        if (!is_null($mapped['stock'])) {
            $product->set_manage_stock(true);
            $product->set_stock_quantity(max(0, $mapped['stock']));
            $product->set_stock_status($mapped['stock'] > 0 ? 'instock' : 'outofstock');
        }
        $product->set_status($mapped['active'] ? 'publish' : 'draft');

        $product_id = $product->save();

        // Apply category mapping (if any)
        self::apply_category_mapping($product_id, $mapped['bc_category'], $opts);

        // Images
        if (!empty($images)) {
            $attachment_ids = self::import_images($images, $product_id);
            if (!empty($attachment_ids)) {
                $product->set_image_id(array_shift($attachment_ids));
                $product->set_gallery_image_ids($attachment_ids);
                $product->save();
            }
        }
        return $product_id;
    }

    /**
     * Map Business Central category to WooCommerce category (legacy method - kept for compatibility)
     */
    private static function map_bc_category_to_woo($bc_category_id) {
        $opts = get_option('bcc_settings', []);
        $category_map = $opts['category_map'] ?? '';
        
        if (empty($category_map)) {
            return [];
        }

        // Parse category mappings
        $mappings = [];
        $lines = explode("\n", $category_map);
        foreach ($lines as $line) {
            $line = trim($line);
            if (empty($line) || strpos($line, '=>') === false) continue;
            
            $parts = explode('=>', $line, 2);
            if (count($parts) === 2) {
                $bc_code = trim($parts[0]);
                $woo_name = trim($parts[1]);
                if (!empty($bc_code) && !empty($woo_name)) {
                    $mappings[$bc_code] = $woo_name;
                }
            }
        }

        // Find matching category
        foreach ($mappings as $bc_code => $woo_name) {
            if (stripos($bc_category_id, $bc_code) !== false) {
                // Create or get WooCommerce category
                $term = term_exists($woo_name, 'product_cat');
                if (!$term) {
                    $term = wp_insert_term($woo_name, 'product_cat');
                }
                
                if (!is_wp_error($term)) {
                    return [$term['term_id']];
                }
            }
        }

        return [];
    }


}
