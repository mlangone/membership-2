<?php
/**
 * @copyright Incsub (http://incsub.com/)
 *
 * @license http://opensource.org/licenses/GPL-2.0 GNU General Public License, version 2 (GPL-2.0)
 * 
 * This program is free software; you can redistribute it and/or modify 
 * it under the terms of the GNU General Public License, version 2, as  
 * published by the Free Software Foundation.                           
 *
 * This program is distributed in the hope that it will be useful,      
 * but WITHOUT ANY WARRANTY; without even the implied warranty of       
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the        
 * GNU General Public License for more details.                         
 *
 * You should have received a copy of the GNU General Public License    
 * along with this program; if not, write to the Free Software          
 * Foundation, Inc., 51 Franklin St, Fifth Floor, Boston,               
 * MA 02110-1301 USA                                                    
 *
*/

class MS_Model_Coupon extends MS_Model_Custom_Post_Type {
	
	public static $POST_TYPE = 'ms_coupon';
	
	protected static $CLASS_NAME = __CLASS__;
	
	const TYPE_VALUE = 'value';
	
	const TYPE_PERCENT = 'percent';
	
	/**
	 *  Time in seconds to redeem the coupon after its been applied, before it goes back into the pool.
	 */
	const COUPON_REDEMPTION_TIME = 3600;
	
	protected $code;

	protected $discount;
	
	protected $discount_type;
	
	protected $start_date;
	
	protected $expire_date;
	
	protected $membership_id = 0;
	
	protected $max_uses;
	
	protected $used = 0;
	
	protected $coupon_message;
	
	protected static $ignore_fields = array( 'coupon_message', 'actions', 'filters' );
	
	public static function get_discount_types() {
		return apply_filters( 'ms_model_coupon_get_discount_types', array(
				self::TYPE_VALUE => __( '$', MS_TEXT_DOMAIN ),
				self::TYPE_PERCENT => __( '%', MS_TEXT_DOMAIN ),
			) 
		);
	}
	
	public static function get_discount_type_desc( $type ) {
		$types = self::get_discount_types();
		if( array_key_exists( $type, $types) ) {
			return apply_filters( 'ms_model_coupon_get_discount_type', $types[ $type ] );
		}
	}
	
	public static function get_coupon_count( $args = null ) {
		$defaults = array(
				'post_type' => self::$POST_TYPE,
				'post_status' => 'any',
		);
		$args = wp_parse_args( $args, $defaults );
	
		$query = new WP_Query($args);
		return $query->found_posts;
	
	}
	
	public static function get_coupons( $args = null ) {
		$defaults = array(
				'post_type' => self::$POST_TYPE,
				'posts_per_page' => 10,
				'post_status' => 'any',
				'order' => 'DESC',
		);
		$args = wp_parse_args( $args, $defaults );
	
		$query = new WP_Query($args);
		$items = $query->get_posts();
	
		$coupons = array();
		foreach ( $items as $item ) {
			$coupons[] = MS_Factory::get_factory()->load_coupon( $item->ID );
		}
		return $coupons;
	}
	
	/**
	 * Load coupon using coupon code.
	 * 
	 * @since 4.0
	 * 
	 * @access public
	 * @param string $code The coupon code used to load model
	 * @return MS_Model_Coupon The coupon model, or null if not found.
	 */
	public static function load_by_coupon_code( $code ) {
		$code = sanitize_text_field( $code );
		
		$args = array(
				'post_type' => self::$POST_TYPE,
				'posts_per_page' => 1,
				'post_status' => 'any',
				'fields' => 'ids',
				'meta_query' => array(
					array(
							'key'     => 'code',
							'value'   => $code,
					),
				)
		);

		$query = new WP_Query( $args );
		
		$item = $query->get_posts();

		$coupon_id = 0;
		if( ! empty( $item[0] ) ) {
			$coupon_id = $item[0];
		}
		
		return MS_Factory::get_factory()->load_coupon( $coupon_id );
	}
	
	/**
	 * Verify is coupon is valid.
	 * 
	 * Checks for maximun number of uses, date range and membership_id restriction.
	 * 
	 * @since 4.0
	 * 
	 * @access public
	 * @param int $membership_id The membership id for which coupon is applied
	 * @return boolean Returns the verification.
	 */
	public function is_valid_coupon( $membership_id = 0 ) {

		if ( empty( $this->code ) ) {
			$this->coupon_message = __( 'Coupon code not found.', MS_TEXT_DOMAIN );
			return false;
		}
		if( $this->max_uses && $this->used > $this->max_uses ) {
			$this->coupon_message = __( 'No Coupons remaining for this code.', MS_TEXT_DOMAIN );
			return false;
		}
		$timestamp = current_time( 'timestamp', true );
		if( ! empty( $this->start_date ) && strtotime( $this->start_date ) > $timestamp ) {
			$this->coupon_message = __( 'This Coupon is not valid yet.', MS_TEXT_DOMAIN );
			return false;
		}
		if( ! empty( $this->expire_date ) && strtotime( $this->expire_date ) < $timestamp ) {
			$this->coupon_message = __( 'This Coupon has expired.', MS_TEXT_DOMAIN );
			return false;
		}
		if( ! empty( $this->membership_id ) && $membership_id != $this->membership_id ) {
			$this->coupon_message = __( 'This Coupon is not valid for this membership.', MS_TEXT_DOMAIN );
			return false;
		}
		
		return true;
	}
	
	/**
	 * Apply coupon to get discount.
	 * 
	 * If trial period is enabled, the discount will be applied in the trial price (even if it is free).
	 * If the membership price is free, the discount will be zero.
	 * If discount is bigger than the price, the discount will be equal to the price.
	 * 
	 * @since 4.0
	 * 
	 * @param MS_Model_Membership $membership The membership to apply coupon.
	 * @return float The discount value.
	 */
	public function get_discount_value( $membership ) {
		
		$price = ( $membership->trial_period_enabled ) ? $membership->trial_price : $membership->price;
		$original_price = $price;
		$discount = 0;
		
		if( $this->is_valid_coupon( $membership->id ) ) {
			if( self::TYPE_PERCENT == $this->discount_type && $this->discount < 100 ) {
				$price = $price - $price * $this->discount / 100;
			} 
			elseif( self::TYPE_VALUE == $this->discount_type ) {
				$price = $price - $this->discount;
			} 
		
			if( $price < 0 ) {
				$price = 0;
			}
			$discount = $original_price - $price;
			$this->coupon_message = sprintf( __( 'Using Coupon code: %s. Discount applied: %s %s', MS_TEXT_DOMAIN ), $this->code, MS_Plugin::instance()->settings->currency, $discount );
		}
		
		return apply_filters( 'ms_model_coupon_apply_discount', $discount, $membership, $this );
	
	}
	
	/**
	 * Save coupon application.
	 * 
	 * Saving the application to keep track of the application in gateway return.
	 * Using COUPON_REDEMPTION_TIME to expire coupon application.
	 * 
	 * @since 4.0
	 * @param int $membership_id The membership id to apply the coupon.
	 * @param float $discount The discount value.
	 */
	public function save_coupon_application( $membership ) {
		global $blog_id;
	
		$discount = $this->get_discount_value( $membership );
		
		$global = ( defined( 'MS_MEMBERSHIP_GLOBAL_TABLES' ) && MS_MEMBERSHIP_GLOBAL_TABLES === true );
		
		$time = apply_filters( 'ms_model_coupon_save_coupon_application_redemption_time', self::COUPON_REDEMPTION_TIME );
	
		/** Grab the user account as we should be logged in by now */
		$user = MS_Model_Member::get_current_member();
	
		$transient_name = "ms_coupon_{$blog_id}_{$user->id}_{$membership->id}";
		$transient_value = array(
				'coupon_id' => $this->id,
				'user_id' => $user->id,
				'membership_id'	=> $membership->id,
				'discount' => $discount,
				'coupon_message' => $this->coupon_message,
		);

		if ( $global && function_exists( 'get_site_transient' ) ) {
			set_site_transient( $transient_name, $transient_value, $time );
		} 
		else {
			set_transient( $transient_name, $transient_value, $time );
		}
	}
	
	/**
	 * Get coupon application for user.
	 * 
	 * 
	 * @since 4.0
	 * @param int $user_id The user id.
	 * @param int $membership_id The membership id.
	 * @return MS_Model_Coupon The coupon model object.
	 */
	public static function get_coupon_application( $user_id, $membership_id ) {
		global $blog_id;
		
		$global = ( defined( 'MS_MEMBERSHIP_GLOBAL_TABLES' ) && MS_MEMBERSHIP_GLOBAL_TABLES === true );
		
		$transient_name = "ms_coupon_{$blog_id}_{$user_id}_{$membership_id}";
		
		if ( $global && function_exists( 'get_site_transient' ) ) {
			$transient_value = get_site_transient( $transient_name );
		}
		else {
			$transient_value = get_transient( $transient_name );
		}
		
		$coupon = null;
		if( ! empty ( $transient_value ) ) {
			$coupon = MS_Factory::get_factory()->load_coupon( $transient_value['coupon_id'] );
			$coupon->coupon_message = $transient_value['coupon_message'];
		}
		
		return $coupon;
	}
	
	/**
	 * Remove the application for this coupon.
	 * 
	 * @since 4.0
	 * @param int $user_id The user id.
	 * @param int $membership_id The membership id.
	 */
	public static function remove_coupon_application( $user_id, $membership_id ) {
	
		global $blog_id;
	
		$global = ( defined( 'MS_MEMBERSHIP_GLOBAL_TABLES' ) && MS_MEMBERSHIP_GLOBAL_TABLES === true );
		
		$transient_name = "ms_coupon_{$blog_id}_{$user_id}_{$membership_id}";
		
		if ( $global && function_exists( 'get_site_transient' ) ) {
			delete_site_transient( $transient_name );
		}
		else {
			delete_transient( $transient_name );
		}

	}
	
	/**
	 * Returns property.
	 *
	 * @since 4.0
	 *
	 * @access public
	 * @param string $property The name of a property.
	 * @return mixed Returns mixed value of a property or NULL if a property doesn't exist.
	 */
	public function __get( $property ) {
		switch( $property ) {
			case 'remaining_uses':
				if( $this->max_uses > 0 ) {
					$value = $this->max_uses - $this->used;
				}
				else {
					$value = __( 'Unlimited', MS_TEXT_DOMAIN );
				}
				return $value;
				break;
			default:
				return $this->$property;
				break;
		}
		
	}
	
	/**
	 * Set specific property.
	 *
	 * @since 4.0
	 *
	 * @access public
	 * @param string $property The name of a property to associate.
	 * @param mixed $value The value of a property.
	 */
	public function __set( $property, $value ) {
		if ( property_exists( $this, $property ) ) {
			switch( $property ) {
				case 'code':
					$value = sanitize_text_field( preg_replace("/[^a-zA-Z0-9\s]/", "", $value ) );
					$this->$property = strtoupper( $value );
					$this->name = $this->$property;
					break;
				case 'discount':
					$this->$property = floatval( $value );
					break;
				case 'discount_type':
					if( array_key_exists( $value, self::get_discount_types() ) ) {
						$this->$property = $value;
					}
					break;
				case 'start_date':
					$this->$property = $this->validate_date( $value );
					break;
				case 'expire_date':
					$this->$property = $this->validate_date( $value );
					if( strtotime( $this->$property ) < strtotime( $this->start_date ) ) {
						$this->$property = null;
					}
					break;
				case 'membership_id':
					if( 0 == $value || MS_Model_Membership::is_valid_membership( $value ) ) {
						$this->$property = $value;
					}
					break;
				case 'max_uses':
				case 'used':
					$this->$property = absint( $value );
					break;
				default:
					$this->$property = $value;
					break;
			}
		}
	}
}