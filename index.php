<?php
/**
 * Plugin Name: Absorb API Client
 * Description: Absorb API Client Plugin. This plugin allows WP to interface with the Absorb LMS to get course  information in order to create links to courses within WordPress/WooCommerce. Then allowing a logged in user, after purchasing courses, to processe the Single Sign On to Absorb from these links using lightSAML. 
 * Version: 0.1
 * Author: Greg Pymm
 * Author URI: http://pymm.com
 * License: No license available at this time. 
 * 
 * 
 */


if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists('AbsorbAPIClientPlugin') ) :

class AbsorbAPIClientPlugin {
	

		private $message 	= null; 
		private $debug		= false; 

		private $api_client;

		private $products_array;
		
		private $absorb_private_key;
		private $absorb_admin_username;
		private $absorb_admin_password;
		private $BASE_URL;
		
		function __construct($options) 
		{
			$this->absorb_private_key = $options['absorb_private_key'];
			$this->absorb_admin_username = $options['absorb_admin_username'];
			$this->absorb_admin_password = $options['absorb_admin_password'];
			$this->BASE_URL = $options['base_url'];
		}


		function initialize (){

			// Require
			require_once 'src/AbsorbAPIClient.php'; 
			// Any WOOCommerce action can be used here
			add_action( 'woocommerce_new_order', array($this, 'process') );
		

		}

		function message($message){

				$this->message = $message;
				error_log( $this->message );
				if( $this->debug) echo '<br>' . $this->message . '<br>';

		}




		function process()
		{

			$this->api_client = new AbsorbAPIClient(array(
				'absorb_private_key' => $this->absorb_private_key,
				'absorb_admin_username' => $this->absorb_admin_username,
				'absorb_admin_password' => $this->absorb_admin_password,
				'base_url' => $this->BASE_URL
			));

			$this->api_client -> register();

			// After Checkout
			
			// Register API client. 

			// User Setup
			// -- Does a user with this email address already exists? 
			// -- Yes 
			//		-- Retrieve User ID from LMS by email address.
			//		-- save user ID
			// -- NO
			// -- 	-- Create new user with full info from WP and DEPT ID if exists. 
			//		-- Retrieve User ID from LMS response
			//		-- Save user ID
			$this->setup_user();
			// Continue
			//
			// Collect all WP Product IDs from Cart
			//
			$this->get_products();
			// For each product
			// -- Retrieve the External ID and LMS Dept ID. 
			// -- Retrieve course ID from LMS by External ID
			// -- Do we have a Dept ID?
			// -- YES
			// -- 	-- Udpate User with Dept ID and User ID. 
			// -- NO
			// -- 	-- Continue
			$this->process_enrollments();
			// -- Enroll User in this course with Course ID and User ID. 
			//
			// Repeat


			// Notes:
			// Code is cleaned up. 
			//The Plugin now handles multiple Products/Courses 
			// Everything has been made more modular.  
			// It is possible for there to be multiple users with the same email address in the LMS. I added a rough validation for this. It will fail if there is, and report and error. 
			// It is possible for there to be multiple Courses with the same External ID in the LMS. I added a rough validation for this. It will fail if there is, and report and error. 
			// When created a new user a Dept ID is required, so I am using the Non-Member ID. Currently hard-coded in the plugin. 
			// Added a the ability to turn debugging on and off. set debug to "true" or false is vars. 
			// LMS Username will be the email address. 
			// I have set the WP User ID to the User External ID in the LMS.
			// In Wordpress the last name field can be blank, but is required for LMS. 

			// Added a validation for non-logged in user. 
			// Changed the ExternalID for users in the LMS to "{WP User ID}::{WP User email}
			//
			// TODO
			// Externalize credentials and certificate and privatekeys

		}



		function setup_user(){

			$api_client = $this -> api_client;


			// Get current logged-in WP user
			$current_user 	= wp_get_current_user();

			if($this->debug) echo "<b>Current logged in user email: </b><br>" . $current_user->user_email;
			if($this->debug) echo "<br><b>Current logged in user ID: </b><br>" . $current_user->ID;
			if($this->debug) echo "<br>---------<br>";
			
			// var_dump($current_user);

			if(!empty($current_user->user_email)){
			
				$user 			= $api_client ->user_exists(array(
																	'email' => $current_user->user_email)
																	);
			}else{
			
				$this->message ('User not logged in.');
				die();
			
			}

			// var_dump($user);
			/* 	Validation
			* 	Check for Mulitple users with this email address
			*/
			if ( count($user) === 1 ) {
			
				$user = $user[0];
			
			}else if ( count($user) > 1 ){
				
				$this->message ('Mutliple Users exist with that email address');
			
				die();
			}



			if($this->debug) echo "<b>Does user exist in LMS? </b><br>";
			if($this->debug) echo "<b>LMS User Data:</b><br>";
			if($this->debug) var_dump($user);
			
			if( !empty($user->Id ) ){
				
				if($this->debug) echo "<br><b>Yes. Absorb User ID returned from LMS: </b><br>";
				if($this->debug) echo ( $user -> Id);

				// Save User ID 
				$api_client -> lms_user 	= $user;

			}else{
				
				if($this->debug) echo "<br><b>No. User does not exist, create user in LMS: </b><br><br>";

				$non_member_dept_id = 'XXXXXX-786d-4adf-83b5-70179b27a437'; // Non Member Department

				$last_name 		= $current_user->user_lastname; 
				$first_name 	= $current_user->user_firstname; 

				$user_info  	= (object) array(

											  "DepartmentId" 	=> $non_member_dept_id, 
											  "FirstName"		=> empty($first_name) 	? 'FIRSTNAME' : $first_name,
											  "LastName"		=> empty($last_name) 	? 'LASTNAME' : $last_name,
											  "Username"		=> $current_user->user_email,
											  "Password"		=> $current_user->user_pass,
											  "EmailAddress"	=> $current_user->user_email,
											  "ExternalId"		=> $current_user->ID . '::' . $current_user->user_email

												);

				$new_user 		= $api_client -> create_user(	$user_info );

				if( !$new_user->Id ){
		
					$this->message ('Problem Creating a new user in the LMS');
					
					if($this->debug) var_dump($new_user);
				
				}else{
					
					// Save New User ID 
					$user_info -> Id 			= $new_user -> Id;
					// $api_client -> lms_user_id = $new_user->Id;
					$api_client -> lms_user 	= $user_info;
					
					if($this->debug) var_dump( $api_client -> lms_user );
				}

				
			}

			if($this->debug) echo("<br>---------<br><br>");





		}

		function get_products(){


			$products_array = array();

			$cart 			= new WC_Cart();
			$cart -> get_cart_from_session();
			
			$cart_items 	= $cart->get_cart();



			if($this->debug) echo "<b>Our Product Data from Cart: </b><br><br>";
			


			foreach ($cart_items as $key => $item) {

				$product 		= $item['data'];
				$product_id 	= $item['product_id'];
				$external_id  	= get_post_meta($product_id, '_external_id', true); 
				$lms_dept_id 	= get_post_meta($product_id, '_lms_department_id', true);
				$course_name 	= $product -> post -> post_title; 

				array_push($products_array, array(
						'product_id' 	=> $product_id,
						'external_id' 	=> $external_id,
						'dept_id' 		=> $lms_dept_id,
						'course_name' 	=> $course_name,
					));
			}

			if($this->debug) var_dump($products_array);


			$this->products_array 	= $products_array;
			

		}


		function process_enrollments(){

			

			$api_client 	= $this -> api_client;
			$products_array	= $this->products_array;



			foreach ($products_array as $key => $course) {
			
				$ext_id 	= $course['external_id']; 
				$dept_id 	= $course['dept_id']; 
				$course_name 		= $course['course_name']; 

				if($this->debug){
					echo("<br>---------<br>");
					echo "<b>Enroll User in: </b><br>" . $course_name. "<br>";
					echo "<b>Absorb Course External ID from Purchased course in WP: </b><br>" . $ext_id;
					echo "<br><b>Absorb Department ID from Purchased course in WP: </b><br>" . $dept_id;

					echo("<br>---------<br>");
					echo "<b>Absorb Course by External ID from LMS: </b><br>" ;
				} 

				if(!empty( $ext_id )){
					
					// * API Call - GET COURSE
					$course_by_ext_id 	= $api_client ->get_course(	array( 'external_id' => $ext_id	));
				
				}else{

					$this->message( "There is no External ID for this product in WP. User will no be enrolled in this course." );
					$course_by_ext_id = null;
					if($this->debug) echo("<br>---------<br>");
					
					continue;
				
				}
			

				if($this->debug) var_dump( $course_by_ext_id );
				if($this->debug) echo("<br>---------<br>");
			
				/* 	Validation
				* 	Check for Mulitple courses with this External ID
				*/
				if ( count($course_by_ext_id) === 1 ) {

					$course_by_ext_id = $course_by_ext_id[0];
				
				}else if ( count($course_by_ext_id) > 1 ){
					
					$this->message ('Mutliple Courses exist with that External ID');
					die();
				
				}

				$lms_id_from_ext = $course_by_ext_id->Id; 
			
				if($this->debug)  echo "<b>Absorb Course ID by External ID from LMS: </b><br>" . $lms_id_from_ext ;
			

				



				// * Update the User if this course has a Department ID (Member)
				if($this->debug)  echo "<br><b>Update Dept ID of User in LMS: </b><br>" . $dept_id ;
				
				if(!empty( $dept_id )){

					// * API Call - UPDATE USER
					$updated_user 	= $api_client -> update_user(array(

											  "dept_id" 	=> $dept_id, 
										
												));

					if($this->debug)  echo "<br><b>Updated User Data: </b><br>" ;
					if($this->debug) var_dump( $updated_user );

				}else{

					$this->message( "There is no Department ID for this product in WP. User will not be updated" );

				}




				// * Enroll User in Course
				if($this->debug) echo("<br>---------<br><br>");
				


				// * API Call - ENROLL
				$enrollment = $api_client -> enroll(array(
											'course_id' => $lms_id_from_ext,
												));
			
				if($this->debug) echo "<b>Enroll User In Course in LMS: </b><br>";
				if($this->debug) var_dump( $enrollment );
				if($this->debug) echo "<br><b>WP Purchased Product Name: </b>" . $course_by_ext_id -> Name . "<br>";
				if($this->debug) echo "<b>LMS Course Name: </b> " . $course_name . "<br>";
				if($this->debug) echo("<br>---------<br><br>");
				
				
				$this -> create_SSO_course_link( $lms_id_from_ext, $course_by_ext_id -> Name );

			} // end foreach

		
			
		}


		function create_SSO_course_link( $lms_id, $product_name ){	


			$api_client 	= empty( $this -> api_client ) ? new AbsorbAPIClient() : $this->api_client;
			
			$current_user 	= wp_get_current_user();
			
			
			if($this->debug) echo "<b>Absorb Single Sign On to LMS from WP: </b><br>";
			
			// Format and Sign the SAML 
			$saml_xml = $api_client -> CreateSamlResponse(	array('email' => $current_user->user_email )	);


			// Create link
			echo '<form action="https://ifm.myabsorb.com/account/saml" method="POST" id="samlform">
			  Signed SAML <br><textarea name="samlresponse" id="samlresponse" rows="2" cols="80">' . base64_encode($saml_xml) . '</textarea><br>
			  <input type="hidden" name="relaystate" id="relaystate" value="https://ifm.myabsorb.com/account/saml?CourseId=' . $lms_id . '" size="100">
			  <!-- input type="submit" value="Submit" -->
			  <b>This link submits the above SSO Form and logs in the user: </b><br>
			  <a href="javascript:{}" onclick="document.getElementById(\'samlform\').submit(); return false;">' . $product_name . '</a>
			</form>';

			echo "<b>Alternatively Build course link from Course ID if user is already logged in: </b><br>";
			
			$course_url = 'https://ifm.myabsorb.com/#/courses/course/' . $lms_id;

			echo("<a href='".$course_url."' target='_blank'>" . $product_name . "</a><br><br>");
			// var_dump($auth_token);

			if($this->debug) echo("<br><br>---------<br>");
			



			// error_reporting(E_ERROR | E_PARSE);



		}





}

/*
*  $absorb_api_client_plugin
*
* 
*/

function _absorb_api_client_plugin() {

	global $absorb_api_client_plugin;
	
	if( !isset($absorb_api_client_plugin) ) {
		
		error_log('new absorb_api_client_plugin');

		$absorb_api_client_plugin = new AbsorbAPIClientPlugin(array(
			'absorb_private_key' => (defined('ABSORB_PRIV_KEY')) ? constant('ABSORB_PRIV_KEY') : null, 
			'absorb_admin_username' => defined('ABSORB_USER') ? constant('ABSORB_USER') : null,
			'absorb_admin_password' => defined('ABSORB_PASS') ? constant('ABSORB_PASS') : null,
			'base_url' => defined('ABSORB_URL') ? constant('ABSORB_URL') : null
		));
		$absorb_api_client_plugin->initialize();
	}
	
	return $absorb_api_client_plugin;
}


// initialize
_absorb_api_client_plugin();


endif; // Does class exists



?>