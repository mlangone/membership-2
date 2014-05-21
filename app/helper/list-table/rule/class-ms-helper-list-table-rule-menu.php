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

/**
 * Membership List Table 
 *
 *
 * @since 4.0.0
 *
 */
class MS_Helper_List_Table_Rule_Menu extends MS_Helper_List_Table_Rule {

	protected $id = 'rule_menu';
		
	public function get_columns() {
		return apply_filters( "membership_helper_list_table_{$this->id}_columns", array(
				'cb'     => '<input type="checkbox" />',
				'menu' => __( 'Menu title', MS_TEXT_DOMAIN ),
				'access' => __( 'Access', MS_TEXT_DOMAIN ),
		) );
	}
	
	public function column_default( $item, $column_name ) {
		$html = '';
		switch( $column_name ) {
			case 'menu':
				if( $item->parent_id ) {
					$html = "&#8211;&nbsp; $item->title";
				}
				else {
					$html = "MENU - $item->title";
				}
				
				break;
			default:
				$html = print_r( $item, true ) ;
				break;
		}
		return $html;
	}
	
	public function column_cb( $item ) {
		
		$html = '';
		if( $item->parent_id ) {
			$html = sprintf( '<input type="checkbox" name="item[]" value="%1$s" />', $item->id );
		}
		return $html;
	}

	public function column_access( $item ) {
	
		$html = '';
		if( $item->parent_id ) {
				
			$html = parent::column_access( $item );
		}
		return $html;
	}
	
	public function get_views(){
		$views = parent::get_views();
		unset( $views['dripped'] );
		return $views;
	}
	
}