<?php
/**
 * Absorb API Client Class
 * 
 * 
 * 	Requirements
 *
 *	"Requests" library for API HTTP requests (Included in WP)
 *
 * 	Light SAML library for SSO 
 *	https://www.lightsaml.com
 *
	 * Example: 
	 * $api_client = new AbsorbAPIClient();
	 * $api_client -> register();
	 *
 */

if( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly

if( ! class_exists('AbsorbAPIClient') ) :



class AbsorbAPIClient {
	
	
		private $absorb_private_key;
		private $absorb_admin_username;
		private $absorb_admin_password;
		private $BASE_URL;
	
		private $Auth_Token = null;
		public $lms_user = null;

		function __construct($options) 
		{
			$this->absorb_private_key = $options['absorb_private_key'];
			$this->absorb_admin_username = $options['absorb_admin_username'];
			$this->absorb_admin_password = $options['absorb_admin_password'];
			$this->BASE_URL = $options['base_url'];
		}

		public function register(){


			// Return Auth token if it exists. 
			if( !empty($this->Auth_Token) ) return $this->Auth_Token;



			$headers = array(
					'Content-Type' 	=> 'application/json;  version=1',
					'Accept' 		=> 'application/json;  version=1',
				);

			
			$data = array(
      			"Username"	=> $this->absorb_admin_username, 
			  	"Password"	=> $this->absorb_admin_password, 
			  	"PrivateKey"=> $this->absorb_private_key
      		);

			$base_url 		= $this->BASE_URL ;
			$path 			= $base_url . '/Authenticate' ;

			error_log($path);

			$response 		= Requests::post( $path, $headers, json_encode($data));
			
			$response 		=  $this->handle_compression($response);

			error_log('Authenticate with Absorb');
			
			$response_data 	= $this->Auth_Token = json_decode($response->body);

			
			error_log($this->Auth_Token);


			return !empty( $this->Auth_Token ) ? $this->Auth_Token : false ;


		}

		

		public function enroll($params){
		

			error_log('Auth from enroll: ' . $this->Auth_Token );

			$headers = array(
				'Authorization' 	=> $this->Auth_Token,
				'Accept' 		=> 'application/json;  version=1',
			);

			$data = '';


			$user_id 		= $this -> lms_user->Id;
			$course_id 		= $params['course_id'];

			$base_url 		= $this->BASE_URL ;
			$path 			= $base_url . '/users/' . $user_id . '/enrollments/' . $course_id ;


			$response 	= Requests::post( $path, $headers);

			error_log('Enroll in course for user with Absorb');
			error_log($path);

			$response_data 	= json_decode($response->body);

			
			return empty($response_data) ? false : $response_data;

		}






		public function user_exists($params){
			
			$email_address = $params['email'];


			$headers = array(
				'Authorization' 	=> $this->Auth_Token,
				'Accept' 		=> 'application/json;  version=1',
			);


			$base_url 		= $this->BASE_URL ;
			$path 			= $base_url . '/users?email=' . $email_address;


			$response 	= Requests::get($path, $headers);


			error_log('Check user in Absorb');
			error_log($path);
			error_log($response->body);

			$response_data 	= json_decode($response->body);

			return empty($response_data) ? false : $response_data;


		}




		public function get_course($params){
			
			$external_id = $params['external_id'];


			error_log('Auth from get_course: ' . $this->Auth_Token );

			$headers = array(
				'Authorization' 	=> $this->Auth_Token,
				'Accept' 		=> 'application/json;  version=1',
			);


			$base_url 		= $this->BASE_URL ;
			$path 			= $base_url . '/Courses?externalId=' . $external_id;


			$response 	= Requests::get($path, $headers);


			error_log($path);

			$response_data 	= json_decode($response->body);

			return empty($response_data) ? false : $response_data;



		}



		public function create_user($params){
		

			error_log('Auth from create_user: ' . $this->Auth_Token );

			$headers = array(
				'Authorization' 	=> $this->Auth_Token,
				'Accept' 			=> 'application/json;  version=1'
			);

			$data 			= $params;

			$base_url 		= $this->BASE_URL ;
			$path 			= $base_url . '/users/';


			$response 	= Requests::post( $path, $headers, ($data) );

			// expected: {"Id":"5118fd06-14f1-45fb-ad13-d770c3bb282f","Username":"gregpymm"}

			error_log('Create User in Absorb');
			error_log($path);
			error_log($response->body);

			$response_data 	= json_decode($response->body);

			return empty($response_data) ? false : $response_data;

		}
		



		public function update_user($params){
		

			error_log('Auth from update_user: ' . $this->Auth_Token );

			$headers = array(
				'Authorization' => $this->Auth_Token,
				'Accept' 		=> 'application/json;  version=1',
			);

			$data 					= array(
					"DepartmentId" => $params['dept_id'],
					"FirstName"		=> $this -> lms_user -> FirstName,
				  	"LastName"		=> $this -> lms_user -> LastName,
				  	"Username"		=> $this -> lms_user -> Username,
				 	"Password"		=> $this -> lms_user -> Password,
				  	"EmailAddress"	=> $this -> lms_user -> EmailAddress

			); 
			

			$base_url 		= $this->BASE_URL ;
			$path 			= $base_url . '/users/' . $this -> lms_user -> Id;


			error_log('Update User in Absorb');
			error_log($path);

			$response 	= Requests::put( $path, $headers, $data);


			error_log($response->body);

			$response_data 	= json_decode($response->body);

			return empty($response_data) ? false : $response_data;

		}


		

		public function CreateSamlResponse($params){
		
			// Requires Light SAML 
			// https://www.lightsaml.com

			// Load LightSAML library from plugin directory. 
			require_once 'vendor/autoload.php'; 

			$datetime 		= new \DateTime(); 

			$issuer 		= 'http://ifm.dev:8888/saml.xml'; 
			$destination 	= 'https://ifm.myabsorb.com/account/saml';
			
			$email_address 	= $params['email'];

			
			// LIGHTSAML
			$response 		= new \LightSaml\Model\Protocol\Response();

			$response
			    ->addAssertion($assertion = new \LightSaml\Model\Assertion\Assertion())
			    ->setStatus(new \LightSaml\Model\Protocol\Status(
			        new \LightSaml\Model\Protocol\StatusCode(
			            \LightSaml\SamlConstants::STATUS_SUCCESS)
			        )
			    )
			    ->setID(\LightSaml\Helper::generateID())
			    ->setIssueInstant(new \DateTime())
			    ->setDestination($destination)
			    ->setIssuer(new \LightSaml\Model\Assertion\Issuer($issuer))
			;


			$assertion
			    ->setId(\LightSaml\Helper::generateID())
			    ->setIssueInstant(new \DateTime())
			    ->setIssuer(new \LightSaml\Model\Assertion\Issuer($issuer))
			    ->setSubject(
			        (new \LightSaml\Model\Assertion\Subject())
			            ->setNameID(new \LightSaml\Model\Assertion\NameID(
			                $email_address,
			                \LightSaml\SamlConstants::NAME_ID_FORMAT_EMAIL
			            ))
			            ->addSubjectConfirmation(
			                (new \LightSaml\Model\Assertion\SubjectConfirmation())
			                    ->setMethod(\LightSaml\SamlConstants::CONFIRMATION_METHOD_BEARER)
			                    ->setSubjectConfirmationData(
			                        (new \LightSaml\Model\Assertion\SubjectConfirmationData())
			                            ->setRecipient($destination)
			                    )
			            )
			    )
			    ->setConditions(
			        (new \LightSaml\Model\Assertion\Conditions())
			            ->setNotBefore(new \DateTime())
			            ->setNotOnOrAfter(new \DateTime('+1 MINUTE'))
			            ->addItem(
			                new \LightSaml\Model\Assertion\AudienceRestriction(['EnhanceU:_cid=122050'])
			            )
			    )
			  
			    ->addItem(
			        (new \LightSaml\Model\Assertion\AuthnStatement())
			            ->setAuthnInstant(new \DateTime('-10 MINUTE'))
			            ->setAuthnContext(
			                (new \LightSaml\Model\Assertion\AuthnContext())
			                    ->setAuthnContextClassRef(\LightSaml\SamlConstants::AUTHN_CONTEXT_PASSWORD_PROTECTED_TRANSPORT)
			            )
			    )
			;

			$certificate 	= \LightSaml\Credential\X509Certificate::fromFile( plugin_dir_path( __FILE__ ) . '/stage.crt');
			$privateKey 	= \LightSaml\Credential\KeyHelper::createPrivateKey( plugin_dir_path( __FILE__ ) . '/stageprivate.pem', '', true);


			$assertion->setSignature(new \LightSaml\Model\XmlDSig\SignatureWriter($certificate, $privateKey));

			$serializationContext = new \LightSaml\Model\Context\SerializationContext();
			$response->serialize($serializationContext->getDocument(), $serializationContext);

			$xml = $serializationContext->getDocument()->saveXML(); 

			
			error_log('SAML SSO in Absorb');

			return $xml;

		}



		


		function handle_compression($response){
			
			/*
				Note:
				Locally compressed JSON with "deflate" content-encoding would automatically inflate 
				but compressed JSON in the staging envirnment would not. 
				So checking for "decode-able" JSON first to handle local "wrongful" decpmpression.
			*/
			if( empty( json_decode($response->body) ) ){
			
				// Decompress JSON if compressed. 
				if ( isset($response->headers['content-encoding']) ) {
				  if ($response->headers['content-encoding'] == 'gzip') {
				    $response->body = gzinflate(substr($response->body, 10));
				  }
				  elseif ($response->headers['content-encoding'] == 'deflate') {
				    $response->body = gzinflate($response->body);
				  }
				}

			}

			return $response;
		}





}




endif; // Does class exists



?>
