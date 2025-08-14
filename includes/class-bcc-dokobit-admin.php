<?php
if (!defined('ABSPATH')) {
	exit;
}

class BCC_Dokobit_Admin {

	public static function add_admin_submenus() {
		$parent = 'business-central-connector';

		add_submenu_page(
			$parent,
			__('Dokobit Companies', 'business-central-connector'),
			__('Dokobit Companies', 'business-central-connector'),
			'manage_options',
			'bcc-dokobit-companies',
			array(__CLASS__, 'render_companies_page')
		);

		add_submenu_page(
			$parent,
			__('Dokobit User Phones', 'business-central-connector'),
			__('Dokobit User Phones', 'business-central-connector'),
			'manage_options',
			'bcc-dokobit-user-phones',
			array(__CLASS__, 'render_user_phones_page')
		);
	}

	public static function render_companies_page() {
		if (isset($_POST['action'])) {
			if (!wp_verify_nonce($_POST['_wpnonce'], 'bcc_dokobit_company_action')) {
				wp_die(__('Security check failed', 'business-central-connector'));
			}

			if ($_POST['action'] === 'add_company' && !empty($_POST['company_name'])) {
				BCC_Dokobit_Database::add_company(sanitize_text_field($_POST['company_name']));
				echo '<div class="notice notice-success"><p>' . __('Company added successfully.', 'business-central-connector') . '</p></div>';
			} elseif ($_POST['action'] === 'delete_company' && !empty($_POST['company_id'])) {
				if (BCC_Dokobit_Database::delete_company(intval($_POST['company_id']))) {
					echo '<div class="notice notice-success"><p>' . __('Company deleted successfully.', 'business-central-connector') . '</p></div>';
				} else {
					echo '<div class="notice notice-error"><p>' . __('Cannot delete company with associated users.', 'business-central-connector') . '</p></div>';
				}
			}
		}

		$companies = BCC_Dokobit_Database::get_companies();
		?>
		<div class="wrap">
			<h1><?php echo esc_html(get_admin_page_title()); ?></h1>

			<h2><?php _e('Add New Company', 'business-central-connector'); ?></h2>
			<form method="post" action="">
				<?php wp_nonce_field('bcc_dokobit_company_action'); ?>
				<input type="hidden" name="action" value="add_company">
				<table class="form-table">
					<tr>
						<th scope="row"><label for="company_name"><?php _e('Company Name', 'business-central-connector'); ?></label></th>
						<td>
							<input type="text" name="company_name" id="company_name" class="regular-text" required>
						</td>
					</tr>
				</table>
				<?php submit_button(__('Add Company', 'business-central-connector')); ?>
			</form>

			<h2><?php _e('Existing Companies', 'business-central-connector'); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php _e('ID', 'business-central-connector'); ?></th>
						<th><?php _e('Company Name', 'business-central-connector'); ?></th>
						<th><?php _e('Created At', 'business-central-connector'); ?></th>
						<th><?php _e('Actions', 'business-central-connector'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ($companies): ?>
						<?php foreach ($companies as $company): ?>
							<tr>
								<td><?php echo esc_html($company->id); ?></td>
								<td><?php echo esc_html($company->company_name); ?></td>
								<td><?php echo esc_html($company->created_at); ?></td>
								<td>
									<form method="post" action="" style="display:inline;">
										<?php wp_nonce_field('bcc_dokobit_company_action'); ?>
										<input type="hidden" name="action" value="delete_company">
										<input type="hidden" name="company_id" value="<?php echo esc_attr($company->id); ?>">
										<button type="submit" class="button button-small" onclick="return confirm('<?php esc_attr_e('Are you sure?', 'business-central-connector'); ?>');">
											<?php _e('Delete', 'business-central-connector'); ?>
										</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else: ?>
						<tr>
							<td colspan="4"><?php _e('No companies found.', 'business-central-connector'); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}

	public static function render_user_phones_page() {
        if (isset($_POST['action'])) {
			if (!wp_verify_nonce($_POST['_wpnonce'], 'bcc_dokobit_phone_action')) {
				wp_die(__('Security check failed', 'business-central-connector'));
			}

            if ($_POST['action'] === 'add_phone') {
                $company_id = !empty($_POST['company_id']) ? intval($_POST['company_id']) : 0;
                $phone_raw = isset($_POST['phone_number']) ? sanitize_text_field($_POST['phone_number']) : '';
                $personal_code_raw = isset($_POST['personal_code']) ? sanitize_text_field($_POST['personal_code']) : '';

                if ($company_id === 0 || $phone_raw === '' || $personal_code_raw === '') {
                    echo '<div class="notice notice-error"><p>' . __('All fields are required.', 'business-central-connector') . '</p></div>';
                } else {
                    // Normalize phone: allow without +354
                    $digits = preg_replace('/[^0-9]/', '', $phone_raw);
                    if (strpos($phone_raw, '+') === 0) {
                        $phone = '+' . $digits;
                    } elseif (strlen($digits) === 7 || strlen($digits) === 8) {
                        // Iceland local length; prepend +354
                        $phone = '+354' . $digits;
                    } elseif (strlen($digits) === 10 && substr($digits, 0, 3) === '354') {
                        $phone = '+'. $digits;
                    } else {
                        // default: add + if missing
                        $phone = (strpos($phone_raw, '+') === 0) ? $phone_raw : '+' . $digits;
                    }

                    // Normalize kennitala: 10 digits
                    $kt = preg_replace('/[^0-9]/', '', $personal_code_raw);
                    if (strlen($kt) !== 10) {
                        echo '<div class="notice notice-error"><p>' . __('Kennitala must be 10 digits.', 'business-central-connector') . '</p></div>';
                    } else {
                        // Create new user with role under company
                        $email = isset($_POST['new_user_email']) ? sanitize_email($_POST['new_user_email']) : '';
                        $display_name = isset($_POST['new_user_name']) ? sanitize_text_field($_POST['new_user_name']) : '';
                        if (empty($email)) {
                            echo '<div class="notice notice-error"><p>' . __('Email is required to create user.', 'business-central-connector') . '</p></div>';
                        } else {
                            $password = wp_generate_password(20, true);
                            $username_base = sanitize_user(current(explode('@', $email)));
                            $username = $username_base;
                            $suffix = 1;
                            while (username_exists($username)) {
                                $username = $username_base . $suffix;
                                $suffix++;
                            }
                            $user_id = wp_insert_user(array(
                                'user_login' => $username,
                                'user_email' => $email,
                                'user_pass' => $password,
                                'display_name' => $display_name,
                                'role' => 'dokobit_company_user'
                            ));
                            if (is_wp_error($user_id)) {
                                echo '<div class="notice notice-error"><p>' . esc_html($user_id->get_error_message()) . '</p></div>';
                            } else {
                                // Link user to company via user meta
                                update_user_meta($user_id, 'dokobit_company_id', $company_id);

                                if (BCC_Dokobit_Database::add_user_phone(
                                    intval($user_id),
                                    $phone,
                                    intval($company_id),
                                    $kt
                                )) {
                                    wp_new_user_notification($user_id, null, 'user');
                                    echo '<div class="notice notice-success"><p>' . __('User created and phone added successfully.', 'business-central-connector') . '</p></div>';
                                } else {
                                    echo '<div class="notice notice-error"><p>' . __('Failed to add phone number. It may already exist.', 'business-central-connector') . '</p></div>';
                                }
                            }
                        }
                    }
                }
			} elseif ($_POST['action'] === 'delete_phone' && !empty($_POST['phone_id'])) {
				if (BCC_Dokobit_Database::delete_user_phone(intval($_POST['phone_id']))) {
					echo '<div class="notice notice-success"><p>' . __('Phone number deleted successfully.', 'business-central-connector') . '</p></div>';
				}
			}
		}

        $user_phones = BCC_Dokobit_Database::get_user_phones();
        $companies = BCC_Dokobit_Database::get_companies();
		?>
		<div class="wrap">
			<h1><?php echo esc_html(get_admin_page_title()); ?></h1>

			<h2><?php _e('Add User Phone', 'business-central-connector'); ?></h2>
			<form method="post" action="">
				<?php wp_nonce_field('bcc_dokobit_phone_action'); ?>
				<input type="hidden" name="action" value="add_phone">
				<table class="form-table">
					<tr>
                        <th scope="row"><label for="new_user_email"><?php _e('User', 'business-central-connector'); ?></label></th>
						<td>
                            <input type="email" name="new_user_email" id="new_user_email" class="regular-text" placeholder="user@example.com" required>
                            <p class="description"><?php _e('This will create a new user with the Dokobit Company User role under the selected company.', 'business-central-connector'); ?></p>
                            <label for="new_user_name" style="display:block;margin-top:8px;"><?php _e('Display Name (optional)', 'business-central-connector'); ?></label>
                            <input type="text" name="new_user_name" id="new_user_name" class="regular-text" placeholder="Full Name">
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="phone_number"><?php _e('Phone Number', 'business-central-connector'); ?></label></th>
						<td>
                            <input type="text" name="phone_number" id="phone_number" class="regular-text" placeholder="6867428" required>
                            <p class="description"><?php _e('No need to include +354; it will be added automatically for Iceland local numbers.', 'business-central-connector'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="personal_code"><?php _e('Personal Code (Kennitala)', 'business-central-connector'); ?></label></th>
						<td>
							<input type="text" name="personal_code" id="personal_code" class="regular-text" placeholder="0111912079" required>
							<p class="description"><?php _e('10-digit Icelandic personal identification number (kennitala)', 'business-central-connector'); ?></p>
						</td>
					</tr>
					<tr>
						<th scope="row"><label for="company_id"><?php _e('Company', 'business-central-connector'); ?></label></th>
						<td>
							<select name="company_id" id="company_id" required>
                                <option value=""><?php _e('Select Company', 'business-central-connector'); ?></option>
								<?php foreach ($companies as $company): ?>
									<option value="<?php echo esc_attr($company->id); ?>">
										<?php echo esc_html($company->company_name); ?>
									</option>
								<?php endforeach; ?>
							</select>
						</td>
					</tr>
				</table>
                <?php submit_button(__('Create User & Add Phone', 'business-central-connector')); ?>
			</form>

			<h2><?php _e('User Phone Associations', 'business-central-connector'); ?></h2>
			<table class="wp-list-table widefat fixed striped">
				<thead>
					<tr>
						<th><?php _e('User', 'business-central-connector'); ?></th>
						<th><?php _e('Phone Number', 'business-central-connector'); ?></th>
						<th><?php _e('Personal Code', 'business-central-connector'); ?></th>
						<th><?php _e('Company', 'business-central-connector'); ?></th>
						<th><?php _e('Created At', 'business-central-connector'); ?></th>
						<th><?php _e('Actions', 'business-central-connector'); ?></th>
					</tr>
				</thead>
				<tbody>
					<?php if ($user_phones): ?>
						<?php foreach ($user_phones as $user_phone): ?>
							<tr>
								<td><?php echo esc_html($user_phone->user_login . ' (' . $user_phone->user_email . ')'); ?></td>
								<td><?php echo esc_html($user_phone->phone_number); ?></td>
								<td><?php echo esc_html($user_phone->personal_code ?? '-'); ?></td>
								<td><?php echo esc_html($user_phone->company_name); ?></td>
								<td><?php echo esc_html($user_phone->created_at); ?></td>
								<td>
									<form method="post" action="" style="display:inline;">
										<?php wp_nonce_field('bcc_dokobit_phone_action'); ?>
										<input type="hidden" name="action" value="delete_phone">
										<input type="hidden" name="phone_id" value="<?php echo esc_attr($user_phone->id); ?>">
										<button type="submit" class="button button-small" onclick="return confirm('<?php esc_attr_e('Are you sure?', 'business-central-connector'); ?>');">
											<?php _e('Delete', 'business-central-connector'); ?>
										</button>
									</form>
								</td>
							</tr>
						<?php endforeach; ?>
					<?php else: ?>
						<tr>
							<td colspan="6"><?php _e('No phone associations found.', 'business-central-connector'); ?></td>
						</tr>
					<?php endif; ?>
				</tbody>
			</table>
		</div>
		<?php
	}
}


