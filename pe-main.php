<?php
/* 
 * 	Plugin Name: xGen Post Status
	Plugin URI: http://xgensolutions.in/
	Description: Set the status of the post as active/inactive permanently or for a specific period of time
	Version: 1.0
	Author: xGen Solutions
	Author URI: http://xgensolutions.in/
	License: GPLv2 or later
*/
class clsPexGen {

	/**
	 * Hook into the appropriate actions when the class is constructed.
	 */
	public function __construct() {
		//Check if the user has right to edit and add the post, if not then the meta box will not be shown
		global $wpdb;
		
		require (ABSPATH . WPINC . '/pluggable.php');
		if(current_user_can('edit_posts')){
			add_action( 'add_meta_boxes', array( $this, 'fnAddMetaBox' ) );
			add_action( 'save_post', array( $this, 'fnSavePost' ));
			add_action( 'delete_post', array( $this, 'fnDeletePost' )); 
			add_action( 'admin_init', array($this, 'fnAdminInit'));
			add_action( 'pre_get_posts', array($this, 'fnLimitPost'));
			register_activation_hook( __FILE__, array($this,'fnPexGenInit') );
			$this->strTable = $wpdb->prefix . 'xgen_post_info';
		}
	}

	/**
	 * Creates the table required for the plugin.
	 */
	public function fnPexGenInit(){
		
		global $wpdb;
		
		if($wpdb->get_var("show tables like '".$this->strTable."'") != $this->strTable) 
		{
			if ( ! empty( $wpdb->charset ) )
				$charset_collate = "DEFAULT CHARACTER SET $wpdb->charset";
			if ( ! empty( $wpdb->collate ) )
				$charset_collate .= " COLLATE $wpdb->collate";
			
			$strxGenSql = "CREATE TABLE IF NOT EXISTS `" . $this->strTable . "` (
						`id` mediumint(9) NOT NULL AUTO_INCREMENT,
						`post_id` mediumint(9) NOT NULL,
						`status_from` date NOT NULL,
						`status_to` date NOT NULL,
						`status` ENUM('active','inactive') NOT NULL,
						UNIQUE KEY `id` (`id`)
					) ENGINE=InnoDB  DEFAULT CHARSET=latin1;";
					
			require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
			dbDelta($strxGenSql);
			$arrArgs = array(
						'post_type'        => 'post',
						'post_status'      => 'publish'
					);
			$arrExistingPost = get_posts( $arrArgs );
			
			foreach($arrExistingPost as $hndlPost){
				 setup_postdata( $hndlPost );
				 $wpdb->insert($this->strTable,array('post_id' => $hndlPost->ID));
			}
		}
		
		 
	}

	/**
	 * Adds the meta box container.
	 */
	public function fnAddMetaBox() {
		$screens = array( 'post', 'page' );

    	foreach ( $screens as $screen ) {
				add_meta_box(
				 	'xGen Post Status'
				,'<h3>xGen Post Status</h3>'
				,array( $this, 'fnPeRenderHTML' )
				,'post'
				,'advanced'
				,'high'
			);
		}
	}

	/**
	 * Save the post info to plugin table when the post is saved.
	 *
	 * @param int $post_id The ID of the post being saved.
	 */
	public function fnSavePost($intPostId) {

		/*
		 * We need to verify this came from the our screen and with proper authorization,
		 * because save_post can be triggered at other times.
		 */
		global $wpdb;
			
		if(!wp_is_post_revision($intPostId) && !wp_is_post_autosave($intPostId)){
	   		$strStatus = $_POST['bolPostStatus'];
			$strFromDate = $_POST['txt_from_date'];
			$strToDate = $_POST['txt_to_date'];
			$arrDbData = array(
								'status_from' => $strFromDate,
								'status_to' => $strToDate,
								'status' => $strStatus
							);
			if($_POST['original_publish']=='Update'){
				$wpdb->update( $this->strTable, $arrDbData, array('post_id' => $intPostId));
			}elseif($_POST['original_publish']=='Publish'){
				$arrDbData['post_id'] = $intPostId;
				$wpdb->insert($this->strTable,$arrDbData);
			}
		} 
	}


	/**
	 * Render Meta Box content.
	 *
	 * @param WP_Post $post The post object.
	 */
	public function fnPeRenderHTML( $post ) {

		// Add an nonce field so we can check for it later.
		wp_nonce_field( 'peInnerCustomBox', 'peInnerCustomBoxNonce' );
			
		$arrScreenType = get_current_screen();

		if($arrScreenType->action!='add' && $arrScreenType->id=='post'){
			global $post;
			$intPostID = $post->ID;
			$arrPostStatusInfo = $this->fnGetPostDetails($intPostID,'status,status_from,status_to');

			if($arrPostStatusInfo[0]->status=='active') {
				$strYesActive = 'selected=selected';
				$strNoActive = '';
			}else{
				$strYesActive = '';
				$strNoActive = 'selected=selected';
			}
			
			if($arrPostStatusInfo[0]->status_from!='0000-00-00'){
				$strFromDate = $arrPostStatusInfo[0]->status_from;
			}else{
				$strFromDate = '';
			}
			
			if($arrPostStatusInfo[0]->status_to!='0000-00-00'){
				$strToDate = $arrPostStatusInfo[0]->status_to;
			}else{
				$strToDate = '';
			}
			
		}else{
			$strYesActive = 'selected=selected';
			$strNoActive = '';
			$strFromDate = '';
			$strToDate = '';
		}
		// Display the form, using the current value.
		echo "<table class='widefat'>
					<tr>
						<td class='xgen-padding-top'>
							<label for='bolPostStatus'>Status of the post</label>
						</td>
						<td class='xgen-zero-padding'>
							<select id='bolPostStatus' name='bolPostStatus'>
								<option value='active' $strYesActive >Active</option>
								<option value='inactive' $strNoActive >Inactive</option>
							</select>
						</td>
						<td class='xgen-padding-top'>
							<label for='txt_from_date'>Start Date</label>
						</td>
						<td class='xgen-zero-padding'>
							<input type='text' id='txt_from_date' name='txt_from_date' value='$strFromDate' class='from_date'>
						</td>
						<td class='xgen-padding-top'>
							<label for='txt_to_date'>End Date</label>
						</td>
						<td class='xgen-zero-padding'>
							<input type='text' id='txt_to_date' name='txt_to_date' value='$strToDate' class='to_date'>
						</td>
					</tr>
				</table>";
	}
	
	/**
	 * Load jquery UI and datepicker file.
	 *
	 */
	public function fnAdminInit(){
		
		wp_enqueue_script('jquery');
		wp_enqueue_script('jquery-ui-core');
		wp_enqueue_script('jquery-ui-datepicker');
		wp_enqueue_style( 'jquery-style',plugin_dir_url(__FILE__).'css/jquery-ui.css');
		wp_enqueue_script('jquery-set-picker',plugin_dir_url(__FILE__).'js/set_datepicker.js');
	}
	
	
	/**
	 * Get the post related information like status, duration and post ID
	 * @param interger $intPostId The post id which is optional.
	 *
	 */
	public function fnGetPostDetails($intPostId=0, $strFieldName=''){
		
		global $wpdb;	
		
		($strFieldName!='') ? $strSelectField = $strFieldName : $strSelectField = '*';
		
		($intPostId!=0)	? $strPostWhere = " where post_id = $intPostId" : $strPostWhere = '';

		$arrResult = $wpdb->get_results("select $strSelectField from ".$this->strTable.$strPostWhere);
		
		return $arrResult;
	}
	
	/**
	 * Function to delete the post entry from the plugin table when the respective post is deleted from master table
	 */
	public function fnDeletePost($intPostId){
		global $wpdb;
		 
   		$wpdb->query("DELETE FROM ".$this->strTable." WHERE post_id=".$intPostId);
	}
	
	public function fnLimitPost($query){
		global $wpdb;
		
		$strEndDate = date('Y-m-d');
		$arrPostInfo = $wpdb->get_results('select post_id, status_to, status_from, status from '.$this->strTable);
		$strExcludePost = '0';
		foreach($arrPostInfo as $hndlPost){
			if($hndlPost->status=='active'){
				if($hndlPost->status_from!='0000-00-00' && $hndlPost->status_to!='0000-00-00'){
					if($hndlPost->status_from > $strEndDate){
						$strExcludePost .= ','.$hndlPost->post_id;
					}elseif($hndlPost->status_to < $strEndDate){
						$strExcludePost .= ','.$hndlPost->post_id;
					}
				}elseif($hndlPost->status_from!='0000-00-00' && $hndlPost->status_to=='0000-00-00'){
					if($hndlPost->status_from > $strEndDate){
						$strExcludePost .= ','.$hndlPost->post_id;
					}
				}elseif($hndlPost->status_from=='0000-00-00' && $hndlPost->status_to!='0000-00-00'){
					if($hndlPost->status_to < $strEndDate){
						$strExcludePost .= ','.$hndlPost->post_id;
					}
				}
			}elseif($hndlPost->status=='inactive'){
				if($hndlPost->status_from=='0000-00-00' && $hndlPost->status_to=='0000-00-00'){
					$strExcludePost .= ','.$hndlPost->post_id;
				}elseif($hndlPost->status_from!='0000-00-00' && $hndlPost->status_to!='0000-00-00'){
					if($hndlPost->status_from <= $strEndDate && $hndlPost->status_to >= $strEndDate){
						$strExcludePost .= ','.$hndlPost->post_id;
					}
				}elseif($hndlPost->status_from!='0000-00-00' && $hndlPost->status_to=='0000-00-00'){
					if($hndlPost->status_from <= $strEndDate){
						$strExcludePost .= ','.$hndlPost->post_id;
					}
				}elseif($hndlPost->status_from=='0000-00-00' && $hndlPost->status_to!='0000-00-00'){
					if($hndlPost->status_to >= $strEndDate){
						$strExcludePost .= ','.$hndlPost->post_id;
					}
				}
			}
		}
		if( ! is_admin() && $query->is_main_query() &&  $query->is_home()) {
			$arrExcludePost = explode(',',$strExcludePost);
			$query->set('post__not_in',$arrExcludePost);
		}
		
	}
}	
$objPexGen = new clsPexGen();
?>