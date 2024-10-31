<?php
/*
Plugin Name: pqInternet's NextGEN Database Analysis and Clean-up Tool
Plugin URI: https://www.pqinternet.com/wordpress/nextgen-database-analysis-and-clean-up-tool
Description: Using NextGen Gallery can leave orphaned records in the posts and posts meta database tables, especially if you add and delete galleries often.  This also checks for orphaned records in the NextGen tables as well.  This plugin will clean up these orphaned records.
Author: fwblack
Version: 0.02
Author URI: http://www.pqInternet.com
*/

if ( is_admin() ){ // admin actions
	add_action( 'admin_menu', 'pqNGDBCLean_menu' );
	add_action( 'wp_ajax_pqNGDBClean_plugin_do_ajax_request', 'pqNGDBClean_plugin_do_ajax_request');
	add_action( 'wp_ajax_pqNGDBClean_ajax_analyze_db', 'pqNGDBClean_ajax_analyze_db');
	add_action( 'wp_ajax_pqNGDBClean_ajax_clean_db', 'pqNGDBClean_ajax_clean_db');
}

function pqNGDBClean_setup_jq() {
	// Get the Path to this plugin's folder
	$path = plugin_dir_url( __FILE__ ); 
	// Enqueue script
	wp_enqueue_script ('pqNGDBClean_sc', $path . 'js/pqNGDBClean.js' , array( 'jquery' ), '1.0.0', true );
	// Get the protocol of the current page
	$protocol = isset( $_SERVER['HTTPS'] ) ? 'https://' : 'http://';
	// Set the ajaxurl Parameter which will be output right before our js file so we can use ajaxurl
	$params = array(
		// Get the url to the admin-ajax.php file using admin_url()
		'ajaxurl' => admin_url( 'admin-ajax.php', $protocol ),);
	// Print the script to our page
	wp_localize_script( 'pqNGDBClean_sc', 'pqNGDBClean_params', $params );		
}

function pqNGDBCLean_menu() {
	add_management_page( 'NextGen Gallery Database Analysis and Clean-up', 'NextGen Gallery DB Clean', 'manage_options', 'pqNGDBCLean', 'pqNGDBCLean_options_page' );
}

function pqNGDBClean_ajax_clean_db() {
	global $wpdb;
	//set custom error handler
	set_error_handler('pqNGDBCLean_customError');
	// Instantiate WP_Ajax_Response
	$response = new WP_Ajax_Response;
	$time = date( "F jS Y, H:i:s", time() );
	pqNGDBCLean_logoutput('<hr/><p><strong>Starting pqNGDBClean_ajax_clean_db:</strong> ' . $time . '</p>', true);
	
	if (wp_verify_nonce( $_REQUEST['nonce'], 'pqNGDBClean-Clean')) 
	{
		$strOutput = '';
		//
		// Check table health and repair any tables not passing a table check
		$strTemp = '<h2>Repair Tables before cleaning:</h2><p>This must be done or errors can occur when cleaning. <em>Note: this only repairs the 5 tables we are concerned with: wp_ngg_album, wp_ngg_gallery, wp_ngg_pictures, wp_posts and wp_postmeta.</em><br/>';
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;
		$bAllGood = true;
		//
		if (!pqRepairTable($wpdb, '`' . $wpdb->prefix . 'ngg_album`', 'Album', $strOutput)) {
			$bAllGood = false;
		}
		//
		if (!pqRepairTable($wpdb, '`' . $wpdb->prefix . 'ngg_gallery`', 'Gallery', $strOutput)) {
			$bAllGood = false;
		}			
		//
		if (!pqRepairTable($wpdb, '`' . $wpdb->prefix . 'ngg_pictures`', 'Pictures', $strOutput)) {
			$bAllGood = false;
		}			
		//
		if (!pqRepairTable($wpdb, '`' . $wpdb->prefix . 'postmeta`', 'Postmeta', $strOutput)) {
			$bAllGood = false;
		}				
		//
		if (!pqRepairTable($wpdb, '`' . $wpdb->prefix . 'posts`', 'Posts', $strOutput)) {
			$bAllGood = false;
		}					
		//
		pqNGDBCLean_logoutput('</p>');
		$strOutput .= '</p>';
		if ($bAllGood) {
			$strTemp = "<h2>Cleaning NextGen Pictures Table:</h2>";
			pqNGDBCLean_logoutput($strTemp);
			$strOutput .= $strTemp;	
			//Orphaned Pictures
			$strQuery = 'DELETE FROM `' . $wpdb->prefix . 'ngg_pictures` WHERE ' . $wpdb->prefix . 'ngg_pictures.galleryid NOT IN(select ' . $wpdb->prefix . 'ngg_gallery.gid from ' . $wpdb->prefix . 'ngg_gallery)';
			pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
			$results = $wpdb->query($strQuery);
			$strTemp = "<p> - Cleaning of <strong><em>Orphaned</em></strong> Pictures in <strong><em>NextGen Pictures</em></strong> Table: " . number_format($results) . " rows deleted.</p>";
			pqNGDBCLean_logoutput($strTemp);
			$strOutput .= $strTemp;
			//
			$strTemp = "<h2>Cleaning WordPress Posts and Postmeta Tables:</h2>";
			pqNGDBCLean_logoutput($strTemp);
			$strOutput .= $strTemp;	
			//
			$strTemp = "<p><strong>WordPress <em>postmeta</em> table:</strong><br/>";
			pqNGDBCLean_logoutput($strTemp);
			$strOutput .= $strTemp;
			//wp_postmeta records referring to orphaned wp_post records referring to NextGen Album items
			$strQuery = 'DELETE FROM `' . $wpdb->prefix . 'postmeta` WHERE ' . $wpdb->prefix . 'postmeta.post_id IN (SELECT ID FROM `' . $wpdb->prefix . 'posts` WHERE ' . $wpdb->prefix . 'posts.post_type = "ngg_album" AND ' . $wpdb->prefix . 'posts.ID NOT IN(SELECT extras_post_id FROM `' . $wpdb->prefix . 'ngg_album`))';
			pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
			$results = $wpdb->query($strQuery);
			$strTemp = " - Cleaning of postmeta records referring to <strong><em>Orphaned</em></strong> posts referring to NextGen Album records: " . number_format($results) . " rows deleted.<br/>";
			pqNGDBCLean_logoutput($strTemp);
			$strOutput .= $strTemp;
			//
			//wp_postmeta records referring to orphaned wp_post records referring to NextGen Gallery items
			$strQuery = 'DELETE FROM `' . $wpdb->prefix . 'postmeta` WHERE ' . $wpdb->prefix . 'postmeta.post_id IN (SELECT ID FROM `' . $wpdb->prefix . 'posts` WHERE ' . $wpdb->prefix . 'posts.post_type = "ngg_gallery" AND ' . $wpdb->prefix . 'posts.ID NOT IN(SELECT extras_post_id FROM `' . $wpdb->prefix . 'ngg_gallery`))';
			pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
			$results = $wpdb->query($strQuery);
			$strTemp = " - Cleaning of postmeta records referring to <strong><em>Orphaned</em></strong> posts referring to NextGen Gallery records: " . number_format($results) . " rows deleted.<br/>";	
			pqNGDBCLean_logoutput($strTemp);
			$strOutput .= $strTemp;
			//
			//wp_postmeta records referring to orphaned wp_post records referring to NextGen Picture items
			$strQuery = 'DELETE FROM `' . $wpdb->prefix . 'postmeta` WHERE ' . $wpdb->prefix . 'postmeta.post_id IN (SELECT ID FROM `' . $wpdb->prefix . 'posts` WHERE ' . $wpdb->prefix . 'posts.post_type = "ngg_pictures" AND ' . $wpdb->prefix . 'posts.ID NOT IN(SELECT extras_post_id FROM `' . $wpdb->prefix . 'ngg_pictures` WHERE ' . $wpdb->prefix . 'ngg_pictures.galleryid NOT IN(select ' . $wpdb->prefix . 'ngg_gallery.gid from ' . $wpdb->prefix . 'ngg_gallery)))';
			pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
			$results = $wpdb->query($strQuery);
			$strTemp = " - Cleaning of postmeta records referring to <strong><em>Orphaned</em></strong> posts referring to NextGen Picture records: " . number_format($results) . " rows deleted.<br/>";
			pqNGDBCLean_logoutput($strTemp);
			$strOutput .= $strTemp;		
			//
			//wp_postmeta records referring to Orphaned NextGen Pictures
			$strQuery = 'DELETE FROM `' . $wpdb->prefix . 'postmeta` WHERE ' . $wpdb->prefix . 'postmeta.post_id IN (SELECT extras_post_id FROM `' . $wpdb->prefix . 'ngg_pictures` WHERE ' . $wpdb->prefix . 'ngg_pictures.galleryid NOT IN(select ' . $wpdb->prefix . 'ngg_gallery.gid from ' . $wpdb->prefix . 'ngg_gallery))';
			pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
			$results = $wpdb->query($strQuery);
			$strTemp = " - Cleaning of postmeta records referring to <strong><em>Orphaned</em></strong> NextGen Picture records: " . number_format($results) . " rows deleted.</p>";
			pqNGDBCLean_logoutput($strTemp);
			$strOutput .= $strTemp;
			//
			$strTemp = "<p><strong>WordPress <em>posts</em> table:</strong><br/>";
			pqNGDBCLean_logoutput($strTemp);
			$strOutput .= $strTemp;
			//Orphaned wp_posts records referring to NextGen Gallery Albums		
			$strQuery = 'DELETE FROM `' . $wpdb->prefix . 'posts` WHERE ' . $wpdb->prefix . 'posts.post_type = "ngg_album" AND ' . $wpdb->prefix . 'posts.ID NOT IN(SELECT extras_post_id FROM `' . $wpdb->prefix . 'ngg_album`)';
			pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
			$results = $wpdb->query($strQuery);
			$strTemp = " - Cleaning of <strong><em>Orphaned</em></strong> posts referring to NextGen Album records: " . number_format($results) . " rows deleted.<br/>";
			pqNGDBCLean_logoutput($strTemp);
			$strOutput .= $strTemp;			
			//
			//Orphaned wp_posts records referring to NextGen Gallery Records
			$strQuery = 'DELETE FROM `' . $wpdb->prefix . 'posts` WHERE ' . $wpdb->prefix . 'posts.post_type = "ngg_gallery" AND ' . $wpdb->prefix . 'posts.ID NOT IN(SELECT extras_post_id FROM `' . $wpdb->prefix . 'ngg_gallery`)';
			pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
			$results = $wpdb->query($strQuery);
			$strTemp = " - Cleaning of <strong><em>Orphaned</em></strong> posts referring to NextGen Gallery records: " . number_format($results) . " rows deleted.<br/>";
			pqNGDBCLean_logoutput($strTemp);
			$strOutput .= $strTemp;		
			//
			//Orphaned wp_posts records referring to NextGen Picture Records
			$strQuery = 'DELETE FROM `' . $wpdb->prefix . 'posts` WHERE ' . $wpdb->prefix . 'posts.post_type = "ngg_pictures" AND ' . $wpdb->prefix . 'posts.ID NOT IN(SELECT extras_post_id FROM `' . $wpdb->prefix . 'ngg_pictures` WHERE ' . $wpdb->prefix . 'ngg_pictures.galleryid NOT IN(select ' . $wpdb->prefix . 'ngg_gallery.gid from ' . $wpdb->prefix . 'ngg_gallery))';
			pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
			$results = $wpdb->query($strQuery);
			$strTemp = " - Cleaning of <strong><em>Orphaned</em></strong> posts referring to NextGen Picture records: " . number_format($results) . " rows deleted.</p>";
			pqNGDBCLean_logoutput($strTemp);
			$strOutput .= $strTemp;
			//
			// table optimization
			$strTemp = "<h2>Table Optimization:</h2><p>";
			pqNGDBCLean_logoutput($strTemp);
			$strOutput .= $strTemp;
			$bAllOptimized = true;
			if (!pqOptimizeTable($wpdb, '`' . $wpdb->prefix . 'ngg_album`', 'Album', $strOutput)) {
				$bAllOptimized = false;
			}
			//
			if (!pqOptimizeTable($wpdb, '`' . $wpdb->prefix . 'ngg_gallery`', 'Gallery', $strOutput)) {
				$bAllOptimized = false;
			}			
			//
			if (!pqOptimizeTable($wpdb, '`' . $wpdb->prefix . 'ngg_pictures`', 'Pictures', $strOutput)) {
				$bAllOptimized = false;
			}
			//
			if (!pqOptimizeTable($wpdb, '`' . $wpdb->prefix . 'postmeta`', 'Postmeta', $strOutput)) {
				$bAllOptimized = false;
			}
			//
			if (!pqOptimizeTable($wpdb, '`' . $wpdb->prefix . 'posts`', 'Posts', $strOutput)) {
				$bAllOptimized = false;
			}			
			//
			if (!$bAllOptimized) {
				$strTemp = '</p><p style="color:red;"><strong>NOTE: Some Table Optimizations Failed, Please Review The Above Information!!!</strong></p>';
				pqNGDBCLean_logoutput($strTemp);
				$strOutput .= $strTemp;
			}
			else {
				$strTemp = '</p><p>Click the "Analyze" button again the analyze the database again</p>';
				pqNGDBCLean_logoutput($strTemp);
				$strOutput .= $strTemp;
			}
			//
			$response->add( array(
				'data' => 'success',
				'supplemental' => array(
					'message' => $strOutput,
					'error' => '',
					),
				) );			
		}
		else {
			$strTemp = 'SOME TABLES COULD NOT BE SUCCESSFULLY REPAIRED.  THIS NEEDS TO BE CORRECTED BEFORE CONTINUING.';
			pqNGDBCLean_logoutput('<p><strong>' . $strTemp . '</strong></p>');
			$response->add( array(
				'data' => 'error',
				'supplemental' => array(
					'message' => $strOutput ,
					'error' => $strTemp,
					),
				) );				
		}
	}
	else
	{
		$strTemp = 'Security Error:';
		pqNGDBCLean_logoutput('<p><strong>' . $strTemp . '</strong></p>');		
		$response->add( array(
			'data' => 'error',
			'supplemental' => array(
				'message' => $strTemp ,
				'error' => '',
				),
			) );		
	}
	$time = date( "F jS Y, H:i:s", time() );
	pqNGDBCLean_logoutput('<p><strong>Finished pqNGDBClean_ajax_clean_db</strong>: ' . $time . '</p><hr/>');	
	$response->send();
	exit();	
}

function pqNGDBClean_ajax_analyze_db() {
	global $wpdb;
	//set custom error handler
	set_error_handler('pqNGDBCLean_customError');
	// Instantiate WP_Ajax_Response
	$response = new WP_Ajax_Response;
	$nOrphanedPosts = 0;
	$nOrphanedPostMeta = 0;
	$nOrphanedPictures = 0;
	$nOrphans = 0;
	$time = date( "F jS Y, H:i:s", time() );
	pqNGDBCLean_logoutput('<hr/><p><strong>Starting pqNGDBClean_ajax_analyze_db:</strong> ' . $time . '</p>', true);
	
	if (wp_verify_nonce( $_REQUEST['nonce'], 'pqNGDBClean-Analyze')) 
	{
		$strOutput = '';
		$strTemp = "<h2>Analysis of NextGen Tables:</h2>";
		pqNGDBCLean_logoutput($strTemp);
		$strOutput = $strTemp;
		//total albums
		$strQuery = 'SELECT count(ID) FROM `' . $wpdb->prefix . 'ngg_album` ';
		pqNGDBCLean_logoutput('<p><em>' . $strQuery . '</em><br/>');
		$result = $wpdb->get_var($strQuery);
		$strTemp =  " - Number of Albums in <strong><em>NextGen Albums</em></strong> Table: " . number_format($result) . "<br/>";
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;
		//total galleries
		$strQuery = 'SELECT count(GID) FROM `' . $wpdb->prefix . 'ngg_gallery` ';
		pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
		$result = $wpdb->get_var($strQuery);
		$strTemp =  " - Number of Galleries in <strong><em>NextGen Gallery</em></strong> Table: " . number_format($result) . "<br/>";
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;
		//total pictures
		$strQuery = 'SELECT count(PID) FROM `' . $wpdb->prefix . 'ngg_pictures` ';
		pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
		$result = $wpdb->get_var($strQuery);
		$strTemp=  " - Number of Pictures in <strong><em>NextGen Pictures</em></strong> Table: " . number_format($result) . "<br/>";
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;
		//
		//Orphaned Pictures
		$strQuery = 'SELECT count(PID) FROM `' . $wpdb->prefix . 'ngg_pictures` WHERE ' . $wpdb->prefix . 'ngg_pictures.galleryid NOT IN(select ' . $wpdb->prefix . 'ngg_gallery.gid from ' . $wpdb->prefix . 'ngg_gallery)';
		pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
		$result = $wpdb->get_var($strQuery);
		$nOrphanedPictures += $result;
		$strTemp =   " - Number of <strong><em>Orphaned</em></strong> Pictures in <strong><em>NextGen Pictures</em></strong> Table: <strong>" . number_format($result). "</strong></p>";
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;
		//
		$strTemp = "<h2>Analysis of WordPress Posts and Postmeta Tables:</h2>";
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;		
		//
		$strTemp = "<p><strong>WordPress <em>posts</em> table:</strong><br/>";
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;		
		//
		//number of wp_posts records
		$strQuery = 'SELECT count(ID) FROM `' . $wpdb->prefix . 'posts';
		pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
		$result = $wpdb->get_var($strQuery);
		$strTemp = " - Number of records in WordPress posts table: ". number_format($result) . " <em>(don't worry if this number seem high, the posts table contains posts, pages, media info, + a lot of other items)</em><br/>";
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;
		//
		//wp_posts records referring to NextGen records
		$strQuery = 'SELECT count(ID) FROM `' . $wpdb->prefix . 'posts` WHERE ' . $wpdb->prefix . 'posts.post_name = "mixin_nextgen_table_extras"';
		pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
		$result = $wpdb->get_var($strQuery);
		$strTemp = " - Number of posts referring to NextGen records: " . number_format($result) . "<br/>";
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;
		//
		//Orphaned wp_posts records referring to NextGen Picture Records
		$strQuery = 'SELECT count(ID) FROM `' . $wpdb->prefix . 'posts` WHERE ' . $wpdb->prefix . 'posts.post_type = "ngg_pictures" AND ' . $wpdb->prefix . 'posts.ID NOT IN(SELECT extras_post_id FROM `' . $wpdb->prefix . 'ngg_pictures` WHERE ' . $wpdb->prefix . 'ngg_pictures.galleryid NOT IN(select ' . $wpdb->prefix . 'ngg_gallery.gid from ' . $wpdb->prefix . 'ngg_gallery))';
		pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
		$result = $wpdb->get_var($strQuery);
		$nOrphanedPosts += $result;
		$strTemp = " - Number of <strong><em>Orphaned</em></strong> posts referring to NextGen Picture records: <strong>" . number_format($result) . "</strong><br/>";
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;
		//
		//Orphaned wp_posts records referring to NextGen Gallery Records
		$strQuery = 'SELECT count(ID) FROM `' . $wpdb->prefix . 'posts` WHERE ' . $wpdb->prefix . 'posts.post_type = "ngg_gallery" AND ' . $wpdb->prefix . 'posts.ID NOT IN(SELECT extras_post_id FROM `' . $wpdb->prefix . 'ngg_gallery`)';
		pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
		$result = $wpdb->get_var($strQuery);
		$nOrphanedPosts += $result;
		$strTemp = " - Number of <strong><em>Orphaned</em></strong> posts referring to NextGen Gallery records: <strong>" . number_format($result) . "</strong><br/>";
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;
		//
		//Orphaned wp_posts records referring to NextGen Gallery Albums
		$strQuery = 'SELECT count(ID) FROM `' . $wpdb->prefix . 'posts` WHERE ' . $wpdb->prefix . 'posts.post_type = "ngg_album" AND ' . $wpdb->prefix . 'posts.ID NOT IN(SELECT extras_post_id FROM `' . $wpdb->prefix . 'ngg_album`)';
		pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
		$result = $wpdb->get_var($strQuery);
		$nOrphanedPosts += $result;
		$strTemp =   " - Number of <strong><em>Orphaned</em></strong> posts referring to NextGen Album records: <strong>" . number_format($result) . "</strong></p>";
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;
		//
		$strTemp = "<p><strong>WordPress <em>postmeta</em> table:</strong><br/>";
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;
		//
		//number of records in wp_postmeta table
		$strQuery = 'SELECT count(meta_id) FROM `' . $wpdb->prefix . 'postmeta';
		pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
		$result = $wpdb->get_var($strQuery);
		$strTemp = " - Number of records in WordPress postmeta table: " . number_format($result) . " <em>(Don't worry if this number seem high, the postmeta table contains a lot of items, this number is usually much larger than the posts table)</em><br/>";		
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;
		//
		//wp_postmeta records referring to NextGen Pictures
		$strQuery = 'SELECT count(meta_id) FROM `' . $wpdb->prefix . 'postmeta` WHERE ' . $wpdb->prefix . 'postmeta.post_id IN (SELECT extras_post_id FROM `' . $wpdb->prefix . 'ngg_pictures`)';
		pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
		$result = $wpdb->get_var($strQuery);
		$strTemp = " - Number of postmeta records referring to NextGen Picture records: " . number_format($result) . "<br/>";
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;
		//		
		//wp_postmeta records referring to NextGen Galleries
		$strQuery = 'SELECT count(meta_id) FROM `' . $wpdb->prefix . 'postmeta` WHERE ' . $wpdb->prefix . 'postmeta.post_id IN (SELECT extras_post_id FROM `' . $wpdb->prefix . 'ngg_gallery`)';
		pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
		$result = $wpdb->get_var($strQuery);
		$strTemp = " - Number of postmeta records referring to NextGen Gallery records: " . number_format($result) . "<br/>";
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;
		//			
		//wp_postmeta records referring to NextGen Albums
		$strQuery = 'SELECT count(meta_id) FROM `' . $wpdb->prefix . 'postmeta` WHERE ' . $wpdb->prefix . 'postmeta.post_id IN (SELECT extras_post_id FROM `' . $wpdb->prefix . 'ngg_album`)';
		pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
		$result = $wpdb->get_var($strQuery);
		$strTemp =   " - Number of postmeta records referring to NextGen Album records: " . number_format($result) . "<br/>";
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;
		//	
		//wp_postmeta records referring to Orphaned NextGen Pictures
		$strQuery = 'SELECT count(meta_id) FROM `' . $wpdb->prefix . 'postmeta` WHERE ' . $wpdb->prefix . 'postmeta.post_id IN (SELECT extras_post_id FROM `' . $wpdb->prefix . 'ngg_pictures` WHERE ' . $wpdb->prefix . 'ngg_pictures.galleryid NOT IN(select ' . $wpdb->prefix . 'ngg_gallery.gid from ' . $wpdb->prefix . 'ngg_gallery))';
		pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
		$result = $wpdb->get_var($strQuery);
		$nOrphanedPostMeta += $result;
		$strTemp = " - Number of postmeta records referring to <strong><em>Orphaned</em></strong> NextGen Picture records: <strong>" . number_format($result) . "</strong><br/>";
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;		
		//
		//wp_postmeta records referring to orphaned wp_post records referring to NextGen Picture items
		$strQuery = 'SELECT count(meta_id) FROM `' . $wpdb->prefix . 'postmeta` WHERE ' . $wpdb->prefix . 'postmeta.post_id IN (SELECT ID FROM `' . $wpdb->prefix . 'posts` WHERE ' . $wpdb->prefix . 'posts.post_type = "ngg_pictures" AND ' . $wpdb->prefix . 'posts.ID NOT IN(SELECT extras_post_id FROM `' . $wpdb->prefix . 'ngg_pictures` WHERE ' . $wpdb->prefix . 'ngg_pictures.galleryid NOT IN(select ' . $wpdb->prefix . 'ngg_gallery.gid from ' . $wpdb->prefix . 'ngg_gallery)))';
		pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
		$result = $wpdb->get_var($strQuery);
		$nOrphanedPostMeta += $result;
		$strTemp = " - Number of postmeta records referring to <strong><em>Orphaned</em></strong> posts referring to NextGen Picture records: <strong>" . number_format($result) . "</strong><br/>";	
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;			
		//
		//wp_postmeta records referring to orphaned wp_post records referring to NextGen Gallery items
		$strQuery = 'SELECT count(meta_id) FROM `' . $wpdb->prefix . 'postmeta` WHERE ' . $wpdb->prefix . 'postmeta.post_id IN (SELECT ID FROM `' . $wpdb->prefix . 'posts` WHERE ' . $wpdb->prefix . 'posts.post_type = "ngg_gallery" AND ' . $wpdb->prefix . 'posts.ID NOT IN(SELECT extras_post_id FROM `' . $wpdb->prefix . 'ngg_gallery`))';
		pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
		$result = $wpdb->get_var($strQuery);
		$nOrphanedPostMeta += $result;
		$strTemp = " - Number of postmeta records referring to <strong><em>Orphaned</em></strong> posts referring to NextGen Gallery records: <strong>" . number_format($result) . "</strong><br/>";	
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;			
		//
		//wp_postmeta records referring to orphaned wp_post records referring to NextGen Album items
		$strQuery = 'SELECT count(meta_id) FROM `' . $wpdb->prefix . 'postmeta` WHERE ' . $wpdb->prefix . 'postmeta.post_id IN (SELECT ID FROM `' . $wpdb->prefix . 'posts` WHERE ' . $wpdb->prefix . 'posts.post_type = "ngg_album" AND ' . $wpdb->prefix . 'posts.ID NOT IN(SELECT extras_post_id FROM `' . $wpdb->prefix . 'ngg_album`))';
		pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
		$result = $wpdb->get_var($strQuery);
		$nOrphanedPostMeta += $result;
		$strTemp = " - Number of postmeta records referring to <strong><em>Orphaned</em></strong> posts referring to NextGen Album records: <strong>" . number_format($result) . "</strong><br/>";		
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;			
		//
		$strTemp = "<p><strong>WordPress <em>taxonomy</em> records referring to NextGEN</strong> (Note: as of this release we do not clean these, this is information only):<br/>";
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;	
		//
		//wp_term_taxonomy table records referencing nextgen
		$strQuery = 'SELECT COUNT(term_taxonomy_id) FROM `' . $wpdb->prefix . 'term_taxonomy` WHERE taxonomy = "ngg_tag"';
		pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
		$result = $wpdb->get_var($strQuery);
		$strTemp = ' - Number of ' . $wpdb->prefix . 'term_taxonomy records referring to NextGen: ' . number_format($result) . '<br/>';		
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;	
		//
		//wp_terms table records
		$strQuery = 'SELECT COUNT(term_id) FROM `' . $wpdb->prefix . 'terms` WHERE term_id IN (SELECT term_id FROM `' . $wpdb->prefix . 'term_taxonomy` WHERE taxonomy = "ngg_tag")';
		pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
		$result = $wpdb->get_var($strQuery);
		$strTemp = ' - Number of ' . $wpdb->prefix . 'terms records related to ' . $wpdb->prefix . 'term_taxonomy records: ' . number_format($result) . '<br/>';		
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;	
		//
		//wp_term_relationships table records
		$strQuery = 'SELECT COUNT(term_taxonomy_id) FROM `' . $wpdb->prefix . 'term_relationships` WHERE term_taxonomy_id IN (SELECT term_taxonomy_id FROM `' . $wpdb->prefix . 'term_taxonomy` WHERE taxonomy = "ngg_tag")';
		pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
		$result = $wpdb->get_var($strQuery);
		$strTemp = ' - Number of ' . $wpdb->prefix . 'term_relationships records related to ' . $wpdb->prefix . 'term_taxonomy records: ' . number_format($result) . '<br/>';		
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;		
		//
		//wp_termmeta table records
		$strQuery = 'SELECT COUNT(term_id) FROM `' . $wpdb->prefix . 'termmeta` WHERE term_id IN (SELECT term_id FROM `' . $wpdb->prefix . 'term_taxonomy` WHERE taxonomy = "ngg_tag")';
		pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
		$result = $wpdb->get_var($strQuery);
		$strTemp = ' - Number of ' . $wpdb->prefix . 'termmeta records related to ' . $wpdb->prefix . 'term_taxonomy records: ' . number_format($result) . '<br/>';		
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;	
		//
		//ngg pictures with tags
		$strQuery = 'SELECT COUNT(pid) FROM `' . $wpdb->prefix . 'ngg_pictures` WHERE pid IN (SELECT object_id FROM `' . $wpdb->prefix . 'term_relationships` WHERE term_taxonomy_id IN (SELECT term_taxonomy_id FROM `' . $wpdb->prefix . 'term_taxonomy` WHERE taxonomy = "ngg_tag"))';
		pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
		$result = $wpdb->get_var($strQuery);
		$strTemp = ' - Number of ' . $wpdb->prefix . 'ngg_picture records related to tags: ' . number_format($result) . '<br/>';		
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;		
		//ngg orphaned tags
		$strQuery = 'SELECT object_id FROM ' . $wpdb->prefix . 'term_relationships WHERE object_id NOT IN (SELECT pid FROM ' . $wpdb->prefix . 'ngg_pictures WHERE pid IN (SELECT object_id FROM ' . $wpdb->prefix . 'term_relationships WHERE term_taxonomy_id IN (SELECT term_taxonomy_id FROM ' . $wpdb->prefix . 'term_taxonomy WHERE taxonomy = "ngg_tag"))) AND term_taxonomy_id IN (SELECT term_taxonomy_id FROM ' . $wpdb->prefix . 'term_relationships WHERE term_taxonomy_id IN (SELECT term_taxonomy_id FROM ' . $wpdb->prefix . 'term_taxonomy WHERE taxonomy = "ngg_tag"))';
		pqNGDBCLean_logoutput('<em>' . $strQuery . '</em><br/>');
		$result = $wpdb->get_var($strQuery);
		$strTemp = ' - Number <em>Oprhaned</em> tags: <strong>' . number_format($result) . '</strong></p>';		
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;			
		//		
		// Check table health
		$strTemp = '<h2>Table Status:</h2><p>';
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;
		$bRepair = false;
		if (pqCheckTable($wpdb, '`' . $wpdb->prefix . 'ngg_album`', 'Album', $strOutput)) {
			$bRepair = true;
		}
		//
		if (pqCheckTable($wpdb, '`' . $wpdb->prefix . 'ngg_gallery`', 'Gallery', $strOutput)) {
			$bRepair = true;
		}				
		//
		if (pqCheckTable($wpdb, '`' . $wpdb->prefix . 'ngg_pictures`', 'Pictures', $strOutput)) {
			$bRepair = true;
		}				
		//
		if (pqCheckTable($wpdb, '`' . $wpdb->prefix . 'postmeta`', 'Postmeta', $strOutput)) {
			$bRepair = true;
		}				
		//
		if (pqCheckTable($wpdb, '`' . $wpdb->prefix . 'posts`', 'Posts', $strOutput)) {
			$bRepair = true;
		}				
		//
		// Summary
		$strTemp = '<h2>Summary:</h2>';
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;
		If (($nOrphanedPosts > 0) || ($nOrphanedPostMeta > 0) || ($nOrphanedPictures > 0)) {
			$strTemp = '<p style="color:red;"><strong>Orphaned records were found in the database:<br/>';
			$strTemp .= ' - Orphaned Pictures: ' . number_format($nOrphanedPictures) . '<br/>';
			$strTemp .= ' - Orphaned Posts: ' . number_format($nOrphanedPosts) . '<br/>';
			$strTemp .= ' - Orphaned PostMeta: ' . number_format($nOrphanedPostMeta) . '</strong><br/>';
			$strTemp .= 'You should make a backup and then click "clean".</p>';
			$nOrphans = 1;
		}
		else {
			$strTemp = '<p>No Problems Found!</p>';
		}
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;		
		//
		if ($bRepair) {
			$strTemp = '</p><p><strong>One or more tables appear to need repair before cleaning (they did not pass a table check).  Make a backup before cleaning. </strong></p>';
			pqNGDBCLean_logoutput($strTemp);
			$strOutput .= $strTemp;
			$response->add( array(
				'data' => 'repair',
				'supplemental' => array(
					'message' => $strOutput,
					'error' => '',
					'orphans' => $nOrphans,
					),
				) );			
			
		} 
		else {
			$strOutput .= '</p><p></p>';
			$response->add( array(
				'data' => 'success',
				'supplemental' => array(
					'message' => $strOutput,
					'error' => '',
					'orphans' => $nOrphans,
					),
				) );			
		}
		//		
	}
	else
	{
		$strTemp = '</p>Security Error.<p></p>';
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;		
		$response->add( array(
			'data' => 'error',
			'supplemental' => array(
				'message' => $strOutput ,
				'error' => 'Security Error:',
				'orphans' => $nOrphans,
				),
			) );		
	}
	$time = date( "F jS Y, H:i:s", time() );
	pqNGDBCLean_logoutput('<p><strong>Finished pqNGDBClean_ajax_analyze_db:</strong>: ' . $time . '</p><hr/>');
	$response->send();
	exit();
}

function pqOptimizeTable(&$wpdb, $strTable, $strTableLabel, &$strOutput) {
	//returns true if repairs are needed
	$bSuccess = false;
	$strTemp = '<strong>Optimize Table: ' . $strTableLabel . '</strong><br/>';
	pqNGDBCLean_logoutput($strTemp);
	$strOutput .= $strTemp;
	$strQuery = 'OPTIMIZE TABLE ' . $strTable;
	pqNGDBCLean_logoutput('<em> - ' . $strQuery . '</em><br/>');
	$results = $wpdb->get_results($strQuery);
	foreach($results as $row) {
		$strTemp = ' - Optimize Table Result: ' . $strTableLabel . " table: " . $row->Msg_type . ": " . $row->Msg_text . '<br/>';
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;
		if (($row->Msg_text !=  "OK") || ($row->Msg_text != "Table is already up to date")) {
			$bSuccess = true;
		}
	}	
	return $bSuccess;
}

function pqCheckTable(&$wpdb, $strTable, $strTableLabel, &$strOutput) {
	//returns true if repairs are needed
	$bNeedsFix = true;
	$strTemp = '<strong>Check Table: ' . $strTable . '</strong><br/>';
	pqNGDBCLean_logoutput($strTemp);
	$strOutput .= $strTemp;
	$strQuery = 'CHECK TABLE ' . $strTable;
	pqNGDBCLean_logoutput('<em> - ' . $strQuery . '</em><br/>');
	$results = $wpdb->get_results($strQuery);
	foreach($results as $row) {
		$strTemp = ' - Check Table Result: ' . $strTableLabel . " table: " . $row->Msg_type . ": " . $row->Msg_text . '<br/>';
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;
		if (($row->Msg_text !=  "OK") || ($row->Msg_text != "Table is already up to date")) {
			$bNeedsFix = false;
		}	
	}	
	return $bNeedsFix;
}

function pqRepairTable(&$wpdb, $strTable, $strTableLabel, &$strOutput) {
	//determined that tables NEED TO BE REPAIRED BEFORE DELETING ORPHANED RECORDS
	//REGUARDLESS IF CHECK TABLE RETURNS OK STATUS.
	$bSuccess = false;
	$strTemp = '<strong>Run Table Repair on: ' . $strTableLabel . '</strong><br/>';
	pqNGDBCLean_logoutput($strTemp);
	$strOutput .= $strTemp;
	$strQuery = 'REPAIR TABLE ' . $strTable;
	pqNGDBCLean_logoutput('<em> - ' . $strQuery . '</em><br/>');
	$repairResults = $wpdb->get_results($strQuery);
	foreach($repairResults as $row) {
		$strTemp = ' - Repair Table Result: ' . $strTableLabel . " table: " . $row->Msg_type . ": " . $row->Msg_text . '<br/>';
		pqNGDBCLean_logoutput($strTemp);
		$strOutput .= $strTemp;
		if (($row->Msg_text == "OK") || ($row->Msg_text == "The storage engine for the table doesn't support repair")) {
			$bSuccess = true;
		}				
	}	
	return $bSuccess;
}

function pqNGDBCLean_customError($errno, $errstr, $errfile, $errline) {
		$time = date( "F jS Y, H:i:s", time() );
		pqNGDBClean_logoutput('<hr/><p style="color:red;"><b>Error:</b> [' . $errno . '] ' . $errstr . ' File: ' . $errfile . ' Line: ' . $errline . ' time: ' . $time . '</p><hr/>', true);
}

function pqNGDBCLean_logoutput($strOutput, $bHTML = true) {
	$fileLog = plugin_dir_path( __FILE__ ) . '/log.html';
	$open = fopen($fileLog, "a");
	if (!$bHTML) {
		$strOutput = '<p>' . $strOutput . '</p>';
	}
	$write = fputs($open, $strOutput);
	fclose($open);	
}

function pqNGDBCLean_options_page() {
	$path = plugin_dir_url( __FILE__ ); 
	if ( !current_user_can( 'manage_options' ) )  {
		wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
	}
	echo '<div class="wrap">';
	echo "<h1>pqInternet's NextGen Gallery Database Analysis and Clean-up Tool</h1>";
	echo '<p>NextGen Gallery can leave orphaned records/rows in the NextGen database tables, and also in the WordPress posts and postmeta database tables.  Depending on how you use NextGen Gallery, i.e. adding and deleting galleries often, this can really bloat your database.  This plugin removes these orphaned records.</p>';
	echo '<h2 style="color:red;">WARNING - READ THIS!</h2><p style="color:red;"><strong>I assume NO responsibility for any loss of data from using this plugin.</strong><br/><STRONG>YOU SHOULD MAKE A FULL BACKUP BEFORE USING THE CLEAN FUNCTION, TO ALLOW RECOVERY IF THERE IS A PROBLEM.</STRONG></p>';
	echo '<p style="color:red;">It is beyond the scope of this plugin to cover all the information you may need to backup and successfully restore database tables.  However I recommend reading my blog post <a href="https://www.pqinternet.com/technology/27-essential-wordpress-plugins/" target="_blank">27 Essential WordPress Plugins</a>, and get whatever backup plugin I currently recommend.  At the time of writing this sentence, I recommend BackWPup (link in the blog post along with some notes).  You can select to backup just the tables this plugin cleans: wp_ngg_album, wp_ngg_gallery, wp_ngg_pictures, wp_posts, wp_postmeta (note that the <em>wp_</em> prefix may be different on your system).  There is also a link in my post to a utility to help import large sql dumps (backups) if they fail.</p>';
	echo '<p><strong>NOTE: The "Analyze" and "Clean" functions can take A LONG TIME to execute, be patient, do not reload the page! The "Clean" function can take a VERY LONG TIME depending on how large and bloated the tables are!</p>';
	echo '<div id="pq_t1" name="pq_t1"></div>';
	echo '<div id="pq_t2" style="color:red;" name="pq_t2"></div>';
	echo '<input type="checkbox" name="pqNGDBCheck1" id="pqNGDBCheck1" value=""><label for="pqNGDBCheck1">I have read everything in red above and have a current backup</label><br/>You may "Analyze" without agreeing, but all other functionality require you check the checkbox.<br/>';
	$nonce = wp_create_nonce('pqNGDBClean-Analyze');
	echo '<input type="button" data-nonce="' . $nonce . '" name="pqNGDBClean_Analyze_Button" id="pqNGDBClean_Analyze_Button" value="Analyze">';
	$nonce = wp_create_nonce('pqNGDBClean-Clean');
	echo '<input type="button" data-nonce="' . $nonce . '" name="pqNGDBClean_Clean_Button" id="pqNGDBClean_Clean_Button" value="Clean" disabled>';
	echo '<p><strong>If you find this plugin useful, please consider placing a link to <a href="https://www.pqInternet.com" target="_blank">https://www.pqInternet.com</a> on your site, and making a donation via the form on my website.<br/>~Thank you! Fred Black.</strong></p>';
	echo '<a href="https://www.pqInternet.com" target="_blank"><img src="' . $path . 'images/Internet-Business-and-More-400x150.png"/></a>';
	echo '</div>';	
	pqNGDBClean_setup_jq();
}