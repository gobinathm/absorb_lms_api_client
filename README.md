# Absorb LMS API Client Plugin for Wordpress
Absorb API Client Plugin. This plugin allows WP to interface with the Absorb LMS to get course  information in order to create links to courses within WordPress/WooCommerce. Then allowing a logged in user, after purchasing courses, to processe the Single Sign On to Absorb from these links using lightSAML. 


Set the following constants in wp-config.php:

// ** Absorb API ** //

define('ABSORB_PRIV_KEY', 'XXXXXXXXX');

define('ABSORB_USER', 'XXXXXXXXX');

define('ABSORB_PASS', 'XXXXXXXXX');

define('ABSORB_DEPT_ID', 'XXXXXXXXX'); 

define('ABSORB_URL', 'XXXXXXXXX');



This is a fairly bare-bones plugin that enables Asbosrb integration funcitonality, but requires some customization to get up and running with your specific implementation. Feel free to reach out with any questions. 

