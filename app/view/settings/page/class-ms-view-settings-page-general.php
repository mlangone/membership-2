<?php

class MS_View_Settings_Page_General extends MS_View_Settings_Edit {

	public function to_html() {
		$settings = $this->data['settings'];

		$fields = array(
			'plugin_enabled' => array(
				'id' 		=> 'plugin_enabled',
				'type' 		=> MS_Helper_Html::INPUT_TYPE_RADIO_SLIDER,
				'title' 	=> __( 'Content Protection', 'membership2' ),
				'desc' 		=> __( 'This setting toggles the content protection on this site.', 'membership2' ),
				'value' 	=> MS_Plugin::is_enabled(),
				'data_ms' 	=> array(
					'action' 	=> MS_Controller_Settings::AJAX_ACTION_TOGGLE_SETTINGS,
					'setting' 	=> 'plugin_enabled',
				),
			),

			'hide_admin_bar' => array(
				'id' 		=> 'hide_admin_bar',
				'type' 		=> MS_Helper_Html::INPUT_TYPE_RADIO_SLIDER,
				'title' 	=> __( 'Hide admin toolbar', 'membership2' ),
				'desc' 		=> __( 'Hide the admin toolbar for non administrator users.', 'membership2' ),
				'value' 	=> $settings->hide_admin_bar,
				'data_ms' 	=> array(
					'action' 	=> MS_Controller_Settings::AJAX_ACTION_TOGGLE_SETTINGS,
					'setting' 	=> 'hide_admin_bar',
				),
			),

			'enable_cron_use' => array(
				'id' 		=> 'enable_cron_use',
				'type' 		=> MS_Helper_Html::INPUT_TYPE_RADIO_SLIDER,
				'title' 	=> __( 'Use WordPress Cron for sending emails', 'membership2' ),
				'desc' 		=> __( 'Process communication emails in the background every hour. Good for sites with alot of traffic', 'membership2' ),
				'value' 	=> $settings->enable_cron_use,
				'data_ms' 	=> array(
					'action' 	=> MS_Controller_Settings::AJAX_ACTION_TOGGLE_SETTINGS,
					'setting' 	=> 'enable_cron_use',
				),
			),
		);

		$fields = apply_filters( 'ms_view_settings_prepare_general_fields', $fields );
		$setup = MS_Factory::create( 'MS_View_Settings_Page_Setup' );
		$action_url = esc_url_raw( remove_query_arg( array( 'msg' ) ) );

		ob_start();

		MS_Helper_Html::settings_tab_header();
		?>
		<form action="<?php echo esc_url( $action_url ); ?>" method="post" class="cf">
			<?php
			$settings = MS_Factory::load( 'MS_Model_Settings' );
			if ( $settings->ignore_migration && MS_Model_Migrate::needs_migration() ) {
				?>
				<div class="cf">
					<?php 
					
					MS_Helper_Html::html_element( array(
						'type' => MS_Helper_Html::INPUT_TYPE_HIDDEN,
						'name' => 'rerun_migration_nonce',
						'value' => wp_create_nonce( 'rerun_migration_nonce' ),
					));
					
					MS_Helper_Html::html_element( array(
						'type' 	=> MS_Helper_Html::INPUT_TYPE_BUTTON,
						'class' => 'ms-settings-run-migration',
						'value' => __( 'Run Data Migration', 'membership2' ),
					) ); ?>
				</div>
				<?php
			}
			?>
			<div class="cf">
				<div class="ms-third">
					<?php MS_Helper_Html::html_element( $fields['plugin_enabled'] ); ?>
				</div>
				<div class="ms-third">
					<?php MS_Helper_Html::html_element( $fields['hide_admin_bar'] ); ?>
				</div>
				<div class="ms-third">
					<?php MS_Helper_Html::html_element( $fields['enable_cron_use'] ); ?>
				</div>
			</div>
			<?php
			MS_Helper_Html::html_separator();
			MS_Helper_Html::html_element( $setup->html_full_form() );
			?>
		</form>
		<?php
		return ob_get_clean();
	}

}