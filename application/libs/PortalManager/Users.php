<? 
namespace PortalManager;

use MailManager\Mailer;
use MailManager\Mails;
use PortalManager\Template;
use PortalManager\Category;
use PortalManager\Portal;
use PortalManager\User;
use PortalManager\Ad;
use PortalManager\AdServices;
use PortalManager\Services;
use ExceptionManager\RedirectException;

/**
 * class Users
 * 
 */
class Users
{
	const TABLE_NAME 			= 'accounts';
	const TABLE_DETAILS_NAME 	= 'accounts_details';
	const TABLE_EUROPASS_XML 	= 'europass_xml';
	const TABLE_COMPETENCE_XREF	= 'felhasznalo_kompetencia_xref';
	const TABLE_APPLICANT 		= 'jelentkezesek';
	const TABLE_PREMIUM 		= 'premium_fiok';
	const TABLE_SERVICES_ORDED	= 'szolgaltatasok';

	private $db = null;
	private $is_cp = false;
	public $lang = array();
	public $smarty = null;
	public $arg = null;
	public $dateformat = 'Y. F d.';
	public $europass_dateformat = 'Y. F d. H:i';

	public $user 		= false;
	public $user_data 	= false;
	private $alerts 	= array(
		'has_alert' 	=> false,
		'alerts' 		=> array()
	);

	public $csaladi_allapotok = array(
		'single' 		=> 'lng_marital_status_single',
		'relationship' 	=> 'lng_marital_status_relationship',
		'married' 		=> 'lng_marital_status_married',	
		'divorced' 		=> 'lng_marital_status_divorced',
		'widow'			=> 'lng_marital_status_widow'
	);

	function __construct( $arg = array() ){
		$this->db 			= $arg['db'];
		$this->is_cp 		= $arg['admin'];
		$this->settings 	= $arg[view][settings];
		$this->lang 		= $arg[lang];
		$this->smarty 		= $arg[smarty];
		$this->arg 			= $arg;

		if( empty($this->lang) ) die(__CLASS__.' @ '.__FUNCTION__.': Hiányzó nyelvi fájlok. ');

		$this->Portal 		= new Portal( $arg );
		$this->getUser();
	}	
		
	function get( $arg = array() )
	{
		$ret 			= array();
		$ret[options] 	= $arg;

		$user_id = ( !$arg['user'] ) ? $this->user : $arg['user'];
		
		if(!$user_id) return false;
		
		$ret[data] 				= ($user_id) ? $this->getData( $user_id, 'ID' ) : false; 
		$ret[email] 			= $ret[data][email];
		$ret[user_group] 		= $ret[data][user_group];			

		switch ( $ret[user_group] ) {
			case $this->settings['USERS_GROUP_EMPLOYER']:
			break;
			case $this->settings['USERS_GROUP_USER']:
			break;
		}

		$this->user_data 	= $ret;
		$ret[alerts]		= $this->getAlerts( false, $ret['data']['user_group'] );		

		if( !$ret[data] ) {
			unset($_SESSION['user_id']);
			return false;
		}
		
		return $ret;
	}

	public function getActiveAds( $account_id )
	{
		if( !$account_id ) return false;

		$num = 0;

		$num = $this->db->query("SELECT count(id) FROM ".\PortalManager\Ad::TABLE." WHERE fiok_id = '".$account_id ."' and active = '1' and now() > feladas_ido and now() < lejarat_ido;")->fetchColumn();

		return $num;
	}

	public function getEuropass( $account_id, $get_xml = false )
	{
		$ret = array(
			'xml_source' => null,
			'has_europass' => false,
			'last_refresh' => false
		);

		if( empty($account_id) ) return $ret;

		$get_ep = $this->db->query("SELECT europass_xml, idopont FROM ".self::TABLE_EUROPASS_XML." WHERE felh_id = $account_id;");

		if( $get_ep->rowCount() == 0 ) return $ret;

		$ep_data = $get_ep->fetch(\PDO::FETCH_ASSOC);

		if( $get_xml ) {
			$ret['xml_source'] 		= htmlentities( $ep_data['europass_xml'] );
		}
		
		$ret['has_europass'] 	= true;
		$ret['last_refresh'] 	= \Helper::replaceMonths(date($this->europass_dateformat, strtotime($ep_data['idopont'])));

		return $ret;
	}

	public function getAlerts( $acc_id = false, $user_group = false )
	{
		$has_alerts 	= 0;
		$alerts 		= array();
		$is_admin 		= ( $user_group == $this->settings['USERS_GROUP_ADMIN'] ) ? true : false;
	
		/* 
			$alerts[] = array(
				'priority' 	=> 10,
				'type' 		=> 'info',
				'mode' 		=> 'static',
				'text' 		=> 'miss_user_kompetenciak'
			);
		*/

		$this->alerts['alerts'] 	= $alerts;
		$this->alerts['has_alert'] 	= ( $has_alerts === 0 ) ? false : $has_alerts;		

		return $this->alerts;
	}

	public function changeEuropass( $account_id, $xml_file )
	{
		if( empty($account_id) ) $this->error( $this->lang['lng_users_form_europass_miss_user'] );

		// Felhasználó adatok
		$user = $this->db->query("SELECT email FROM ".self::TABLE_NAME." WHERE ID = ".$account_id)->fetch(\PDO::FETCH_ASSOC);

		// Ha hiányzik az xml
		if( $xml_file['size'] == 0 ) {
			$this->error($this->lng['lng_users_europass_xml_emptyfile']);
		} 

		// Ha nem XML formátumú a feltöltés
		if( $xml_file['type'] != 'text/xml' ) {
			$this->error($this->lng['lng_users_europass_xml_wrongfiletype']);
		} 

		// Europass XML forrás
		$xml = file_get_contents( $xml_file['tmp_name'] );
		// Xml header eltávolítás
		$xml = str_replace("<?xml version='1.0' encoding='UTF-8'?>", "", $xml);

		// Ellenőrzés, hogy a felhasználónak van-e már mentett Europass XML forrása
		$check = $this->getEuropass( $account_id );

		if( !$check['has_europass'] ) {
			// Insert
			$this->db->insert(
				self::TABLE_EUROPASS_XML,
				array(
					'felh_id' => $account_id,
					'europass_xml' => $xml,
					'idopont' => NOW
				)
			);

			(new Mails( $this, 'europass_insert', $user['email'] ))->send();
		} else {
			// Update
			$this->db->update(
				self::TABLE_EUROPASS_XML,
				array(
					'europass_xml' => $xml,
					'idopont' => NOW
				),
				"felh_id = ".$account_id
			);
			(new Mails( $this, 'europass_update', $user['email'] ))->send();
		}

		return true;
	}

	public function getApplicants( $acc_id )
	{
		$ret = array(
			'active' 	=> 0,
			'expired' 	=> 0,
			'list' 		=> array(
				'active' => array(),
				'expired' => array()
			)
		);
		$active 	= 0;
		$expired 	= 0;

		if( empty($acc_id) ) return $ret;

		$data = $this->db->query("SELECT hird_id, megtekintve FROM ".self::TABLE_APPLICANT." WHERE felh_id = $acc_id;");

		if( $data->rowCount() == 0 ) return $ret;

		$data = $data->fetchAll(\PDO::FETCH_ASSOC);

		foreach (  $data as $ads ) {
			$ad = new Ad( $ads['hird_id'], array( 'db' => $this->db, 'settings' => $this->settings, 'admin' => true ) );

			if( $ad->isOver() || !$ad->isActive() ) {
				$ret['list']['expired'][] = array_merge( $ads, array( 'data' => $ad ) ); 
				$expired++;
			} else {
				$ret['list']['active'][] = array_merge( $ads, array( 'data' => $ad ) );		
				$active++;
			}

			
		}

		$ret['active'] = $active;
		$ret['expired'] = $expired;

		return $ret;
	}

	public function resetPassword( $data, $group = false ){
		$password =  rand(1111111,9999999);

		if( !$group ){
			$this->error( $this->lang['lng_users_form_password_wronggroupid'] );		
		}
				
		if(!$this->userExists( 'email', $data['email'], $group )){
			$this->error( $this->lang['lng_users_form_password_wrongemail'] );		
		}

		$user = $this->getData( $data['email'], 'email', $group);
			
		$this->db->update(self::TABLE_NAME,
			array(
				'jelszo' => \Hash::jelszo($jelszo)
			),
			"email = '".$data['email']."' and user_group = ".$group
		);

		$arg = array();
		$arg['password'] 	= $jelszo;
		$arg['user'] 		= $user;

		(new Mails( $this, 'password_reset', $data['email'], $arg ))->send();
	}

	private function getUser(){
		if( $_SESSION['loginsession_id'] ){
			$this->user = $_SESSION['loginsession_id']	;
		}
	}

	public function change( $userID, $user_group, $post, $details = array() )
	{
		try {
			return $this->changeUserAdat( $userID, $user_group, $post, $details );
		} catch (\Exception $e) {
			$this->error( $e->getMessage() );
		}
	}

	private function changeUserAdat( $userID, $user_group, $post, $details = array() ){
		extract($post);
		extract($details);


		// Felhasználó csoport prefix
		switch( $user_group ) {
			case $this->settings['USERS_GROUP_EMPLOYER']:
				$gp = 'employer';
			break;
			case $this->settings['USERS_GROUP_USER']:
				$gp = 'user';
			break;
		}
		/**
		 * VALIDATE
		 */

		if($nev == '') throw new \Exception( $this->lang['lng_users_form_'.$gp.'_miss_name'] );
		$post['nev'] = strip_tags($post['nev']);
		
		// Munkáltató specifikus | employer
		if( $gp == 'employer' ) {
			if($city 	== '') 			throw new \Exception( $this->lang['lng_users_form_'.$gp.'_miss_city'] );
			if($megye 	== '') 			throw new \Exception( $this->lang['lng_users_form_'.$gp.'_miss_megye'] );
			if($contact_name == '') 	throw new \Exception( $this->lang['lng_users_form_'.$gp.'_miss_contact_name'] );
			if($contact_phone == '') 	throw new \Exception( $this->lang['lng_users_form_'.$gp.'_miss_contact_phone'] );
			if($company_hq == '') 		throw new \Exception( $this->lang['lng_users_form_'.$gp.'_miss_company_hq'] );
			if($company_adoszam == '') 	throw new \Exception( $this->lang['lng_users_form_'.$gp.'_miss_company_adoszam'] );			

			// Logó feltöltése
			if( $_FILES['logo']['tmp_name'][0] ) {

				$file_format = '.jpg';
				if( $_FILES['logo']['type'][0] == 'image/png' ) {
					$file_format = '.png';
				}
				
				$logo = \PortalManager\Image::upload(array(
					'src' 			=> 'logo',
					'upDir' 		=> 'source/images/profil/employer/',
					'noRoot' 		=> true,
					'maxFileSize' 	=> 256,
					'fileName' 		=> md5($userID).$file_format,
					'lang' 			=> $this->lang
				));

				$post['logo'] = '/'.$logo['file'];			
			}

		}

		// Felhasználó specifikus | user
		if( $gp == 'user' ) {
			if($city 	== '') 			throw new \Exception( $this->lang['lng_users_form_'.$gp.'_miss_city'] );
			if($megye 	== '') 			throw new \Exception( $this->lang['lng_users_form_'.$gp.'_miss_megye'] );
			if($gender 	== '') 			throw new \Exception( $this->lang['lng_users_form_'.$gp.'_miss_gender'] );
			if($born 	== '') 			throw new \Exception( $this->lang['lng_users_form_'.$gp.'_miss_born'] );
			if($csaladi_allapot == '') 	throw new \Exception( $this->lang['lng_users_form_'.$gp.'_miss_maritan_status'] );

			// Logó feltöltése
			if( $_FILES['logo']['tmp_name'][0] ) {

				$file_format = '.jpg';
				if( $_FILES['logo']['type'][0] == 'image/png' ) {
					$file_format = '.png';
				}
				
				$logo = \PortalManager\Image::upload(array(
					'src' 			=> 'logo',
					'upDir' 		=> 'source/images/profil/users/',
					'noRoot' 		=> true,
					'maxFileSize' 	=> 1024,
					'fileName' 		=> md5($userID).$file_format,
					'lang' 			=> $this->lang
				));

				$post['logo'] = '/'.$logo['file'];			
			}
		}

		unset($post['kerulet']);

		$this->db->update(
			self::TABLE_NAME,
			$post,
			"ID = $userID"
		);

		// Extra adatok módosítása
		if( $gp == 'employer' ) {

			$city_id = \PortalManager\Categories::TYPE_TERULETEK_BUDAPEST_ID;

			/**
			 * VÁROS MENTÉSE TERÜLETEK KÖZÉ
			 * deep = 2
			 * kivéve, ha Budapest
			 * */
			if( $megye != \PortalManager\Categories::TYPE_TERULETEK_BUDAPEST_ID ) {

				$cat = new Category( \PortalManager\Categories::TYPE_TERULETEK, false, array('db' => $this->db) );

				$cat_neve 	= trim($city);
				$cat_szulo 	= $megye."_1";

				// Város terület beszúrása, ha nem található az adatbázisban
				$check_city = $cat->checkExists( array( 'neve' => $cat_neve, 'szulo_id' => $cat_szulo ) );
				$city_id 	= $check_city;

				if( !$check_city ) {
					$city_id = $cat->add( array(
						'neve' 		=> $cat_neve,
						'szulo_id' 	=> $cat_szulo
					) );
				}
			} else {
				$megye 		= \PortalManager\Categories::TYPE_TERULETEK_BUDAPEST_ID;
				$city_id 	= $kerulet;
			}

			$this->editAccountDetail( $userID, 'web', 				addslashes(strip_tags($web)) ); 
			$this->editAccountDetail( $userID, 'description', 		addslashes($description) ); 
			$this->editAccountDetail( $userID, 'contact_name',		addslashes($contact_name) ); 
			$this->editAccountDetail( $userID, 'contact_phone', 	addslashes($contact_phone) ); 
			$this->editAccountDetail( $userID, 'company_hq', 		addslashes($company_hq) ); 
			$this->editAccountDetail( $userID, 'company_adoszam', 	addslashes($company_adoszam) ); 
			$this->editAccountDetail( $userID, 'city', 				$city_id ); 
			$this->editAccountDetail( $userID, 'megye', 			$megye ); 
		}

		if( $gp == 'user' ) {						
			$this->editAccountDetail( $userID, 'kompetencia_kiegeszites', 	addslashes(strip_tags($kompetencia_kiegeszites)) ); 
			$this->editAccountDetail( $userID, 'phone', 					addslashes($phone) ); 			
			$this->editAccountDetail( $userID, 'zip_code', 					$zip_code ); 
			$this->editAccountDetail( $userID, 'gender', 					$gender ); 
			$this->editAccountDetail( $userID, 'born', 						$born ); 
			$this->editAccountDetail( $userID, 'csaladi_allapot', 			$csaladi_allapot ); 

			// Kompetenciák mentése
			if( is_array($kompetenciak) && !empty($kompetenciak) ) {
				$komp_data = array();

				foreach ( $kompetenciak as $k ) {
					$komp_data[] = array( $userID, $k ); 
				}

				// Kiürítés
				$this->db->query("DELETE FROM ".self::TABLE_COMPETENCE_XREF." WHERE fiok_id = $userID;");
				
				$this->db->multi_insert(
					self::TABLE_COMPETENCE_XREF,
					array( 'fiok_id', 'komp_id' ),
					$komp_data
				);

				unset( $komp_data );
			}


			$city_id = \PortalManager\Categories::TYPE_TERULETEK_BUDAPEST_ID;

			/**
			 * VÁROS MENTÉSE TERÜLETEK KÖZÉ
			 * deep = 2
			 * kivéve, ha Budapest
			 * */
			if( $megye != \PortalManager\Categories::TYPE_TERULETEK_BUDAPEST_ID ) {

				$cat = new Category( \PortalManager\Categories::TYPE_TERULETEK, false, array('db' => $this->db) );

				$cat_neve 	= trim($city);
				$cat_szulo 	= $megye."_1";

				// Város terület beszúrása, ha nem található az adatbázisban
				$check_city = $cat->checkExists( array( 'neve' => $cat_neve, 'szulo_id' => $cat_szulo ) );
				$city_id 	= $check_city;

				if( !$check_city ) {
					$city_id = $cat->add( array(
						'neve' => $cat_neve,
						'szulo_id' => $cat_szulo
					) );
				}

			} else {
				$megye 		= \PortalManager\Categories::TYPE_TERULETEK_BUDAPEST_ID;
				$city_id 	= $kerulet;
			}

			$this->editAccountDetail( $userID, 'city', $city_id ); 
			$this->editAccountDetail( $userID, 'megye',	$megye ); 			
		}


		return $this->lang['lng_users_form_success_saving'];
	}
	
	function changePassword($userID, $post){
		extract($post);

		if($userID == '') $this->error($this->lang['lng_users_form_password_miss_user']); 
		if($old == '')  $this->error($this->lang['lng_users_form_password_miss_old']);
		if($new == '' || $new2 == '')  $this->error($this->lang['lng_users_form_password_miss_new']);
		if($new !== $new2)  $this->error($this->lang['lng_users_form_password_miss_different']);
		
		$password = \Hash::jelszo($old);
		
		$checkOld = $this->db->query("SELECT id, email FROM ".self::TABLE_NAME." WHERE id = $userID and password = '$jelszo'");
		if($checkOld->rowCount() == 0){
			$this->error($this->lang['lng_users_form_password_miss_oldnotgood']);	
		}

		$checkdata = $checkOld->fetch(\PDO::FETCH_ASSOC);
		
		$this->db->update(self::TABLE_NAME,
			array(
				'jelszo' => \Hash::jelszo($new2)
			),
			"ID = $userID"
		);

		$marg = array();

		$marg[password] = $new2;

		(new Mails( $this, 'password_change', $checkdata['email'], $marg ))->send();
	}

	function getData( $account_id, $db_by = 'email', $user_group = false ){
		if($account_id == '') return false;

		$q = "SELECT u.* FROM ".self::TABLE_NAME." as u WHERE 1 = 1 ";

		if( $user_group ) {
			$q .= " and u.user_group = $user_group "; 	
		}
		
		$q .= " and u.".$db_by." = '$account_id';";
	
		extract($this->db->q($q));

		$account_id = $data['ID'];

		// Details
		$det = $this->db->query("SELECT nev, ertek FROM ".self::TABLE_DETAILS_NAME." WHERE fiok_id = $account_id;")->fetchAll(\PDO::FETCH_ASSOC);

		foreach ($det as $d ) {
			$data[$d['nev']] = $d['ertek'];
		}

		
		return $data;
	}
	
	function login($data){
		$re 	= array();

		$user_group = $data['user_group'];


		if( empty($data['email']) || empty($data['pw'])) {
			$this->error( $this->lang['lng_form_missing_details'] );	
		}
				
		if(!$this->userExists('email',$data['email'], $user_group)){		
			$this->error( $this->lang['lng_form_login_notregisteredemail'] );	
		}
		
		if(!$this->validUser($data['email'],$data[pw], $user_group)){
			$this->error( $this->lang['lng_form_login_invaliddetails'] );	
		}
		
		if(!$this->isActivated($data[email], $user_group)){
			$resendemailtext = '<form method="post" action=""><div class="text-form">'. sprintf( $this->lang['lng_form_login_resendactivatoremail'], $data['email'] ) .'</div></form>';
			
			$this->error( $this->lang['lng_form_login_notactivatedaccount'].'<br>'.$resendemailtext  );		
		}
		
		if(!$this->isEnabled($data[email], $user_group)){
			$this->error( $this->lang['lng_form_login_accountblocked'] );			
		}

		// Refresh
		$this->db->update(
			self::TABLE_NAME,
			array(
				'last_login_date' => NOW
			),
			"user_group = ".$user_group." and email = '".$data[email]."'"
		);

		$data 	= $this->getData( $data[email], 'email', $user_group  );
		
		\Session::set( 'loginsession_id', $data[ID] );
		\Session::set( 'loginsession_ug', $user_group );
	}

	public function logout()
	{
		\Session::kill( 'loginsession_id' );
		\Session::kill( 'loginsession_ug' );
	}

	public function getUserGroup( $account_id )
	{
		return $this->db->query("SELECT user_group FROM ".self::TABLE_NAME." WHERE ID = $account_id;")->fetchColumn();
	}

	function activate( $activate_arr ){
		$email 	= $activate_arr[0];
		$userID = $activate_arr[1];
		$pwHash = $activate_arr[2];
		
		if($email == '' || $userID == '' || $pwHash == '') throw new \Exception('Hibás azonosító');
		
		$q = $this->db->query("SELECT * FROM ".self::TABLE_NAME." WHERE ID = $userID and email = '$email' and password = '$pwHash'");
		
		if($q->rowCount() == 0) throw new \Exception('Hibás azonosító');
		
		$d = $q->fetch(\PDO::FETCH_ASSOC);
		
		if(!is_null($d[aktivalva]))  throw new \Exception('A fiók már aktiválva van!');
		
		$this->db->update(self::TABLE_NAME,
			array(
				'aktivalva' => NOW
			),
			"ID = $userID"
		);
	}

	private function register_user( $data, $user_group  )
	{
		
		if( empty($data['nev']) ){		
			$this->error( $this->lang['lng_form_reg_missingname']);
		}
		if( empty($data['email']) ){		
			$this->error( $this->lang['lng_form_login_missingemail']);
		}
		if( empty($data['country']) ){		
			$this->error( $this->lang['lng_form_reg_missingcountry']);
		}
		if( empty($data['zip_code']) ){		
			$this->error( $this->lang['lng_form_reg_missingzipcode']);
		}
		if( empty($data['city']) ){		
			$this->error( $this->lang['lng_form_reg_missingcity']);
		}
		if( empty($data['megye']) ){		
			$this->error( $this->lang['lng_form_reg_missingmegye']);
		}

		if( empty($data['born']) ){		
			$this->error( $this->lang['lng_form_reg_missingborn']);
		}

		if( empty($data['gender']) ){		
			$this->error( $this->lang['lng_form_reg_missinggender']);
		}

		/*if( empty($data['csaladi_allapot']) ){		
			$this->error( $this->lang['lng_form_reg_missingmaitalstatus']);
		}*/

		if( empty($data['born']) ){		
			$this->error( $this->lang['lng_form_reg_missingborn']);
		}

		if( empty($data['pw']) || empty($data['pw2'])   ){		
			$this->error( $this->lang['lng_form_reg_missingpasswords']);
		}

		if( $data['pw'] !== $data['pw2'] ){		
			$this->error( $this->lang['lng_form_login_alredyusedemail']);
		}

		if( empty($data['aszf']) ){		
			$this->error( $this->lang['lng_form_reg_wantacceptaszf']);
		}

		if( $data['megye'] == \PortalManager\Categories::TYPE_TERULETEK_BUDAPEST_ID ) {
			if( !isset($data['kerulet']) || empty($data['kerulet']) ) { $this->error( $this->lang['lng_form_missed_kerulet'] ); }
		}
		
		// Felhasználó használtság ellenőrzése
		if($this->userExists('email',$data['email'], $user_group )){		
			$this->error( $this->lang['lng_form_login_alredyusedemail']);
		}

		$data['nev'] 	= strip_tags($data['nev']);
		$data['email'] 	= strip_tags($data['email']);

		// Felhasználó regisztrálása
		$this->db->insert(
			self::TABLE_NAME,
			array(
				'email' 		=> trim($data[email]),
				'nev' 			=> trim($data[nev]),
				'jelszo' 		=> \Hash::jelszo($data[pw2]),
				'user_group' 	=> $user_group
			)
		);

		// Új regisztrált felhasználó ID-ka
		$uid = $this->db->lastInsertId();
		$this->addAccountDetail( $uid, 'country', 	strip_tags($data['country']) );		
		$this->addAccountDetail( $uid, 'zip_code', 	strip_tags($data['zip_code']) );
		$this->addAccountDetail( $uid, 'gender',	$data['gender'] );
		//$this->addAccountDetail( $uid, 'csaladi_allapot',	$data['csaladi_allapot'] );
		$this->addAccountDetail( $uid, 'born',		$data['born'] );

		$city_id = \PortalManager\Categories::TYPE_TERULETEK_BUDAPEST_ID;

		/**
		 * VÁROS MENTÉSE TERÜLETEK KÖZÉ
		 * deep = 2
		 * kivéve, ha Budapest
		 * */
		if( $data['megye'] != \PortalManager\Categories::TYPE_TERULETEK_BUDAPEST_ID ) {

			$cat = new Category( \PortalManager\Categories::TYPE_TERULETEK, false, array('db' => $this->db) );

			$cat_neve 	= strip_tags(trim($data['city']));
			$cat_szulo 	= $data['megye']."_1";

			$check_city = $cat->checkExists( array( 'neve' => $cat_neve, 'szulo_id' => $cat_szulo ) );
			$city_id 	= $check_city;

			// Város terület beszúrása, ha nem található az adatbázisban
			if( !$check_city ) {
				$city_id = $cat->add( array(
					'neve' => $cat_neve,
					'szulo_id' => $cat_szulo
				) );
			}
		} else {
			$data['megye'] 	= $city_id;
			$city_id 		= $data['kerulet'];
		}
		
		$this->addAccountDetail( $uid, 'city', 	$city_id );
		$this->addAccountDetail( $uid, 'megye', $data['megye'] );	
				
		// Aktiváló e-mail kiküldése
		$this->sendActivationEmail( $data['email'], $user_group );		

	}

	private function register_employer( $data, $user_group )
	{

		if( empty($data['nev']) ){		
			$this->error( $this->lang['lng_form_reg_missingcompany']);
		}
		if( empty($data['email']) ){		
			$this->error( $this->lang['lng_form_login_missingemail']);
		}
		if( empty($data['contact_name']) ){		
			$this->error( $this->lang['lng_form_reg_missingcontactname']);
		}
		if( empty($data['contact_phone']) ){		
			$this->error( $this->lang['lng_form_reg_missingcontactphone']);
		}
		if( empty($data['company_adoszam']) ){		
			$this->error( $this->lang['lng_form_reg_company_adoszam']);
		}
		if( empty($data['megye']) ){		
			$this->error( $this->lang['lng_form_reg_megye']);
		}
		if( empty($data['city']) ){		
			$this->error( $this->lang['lng_form_reg_city']);
		}
		if( empty($data['company_hq']) ){		
			$this->error( $this->lang['lng_form_reg_company_hq']);
		}
		if( empty($data['employer_aszf']) ){		
			$this->error( $this->lang['lng_form_reg_wantacceptaszf']);
		}
		if( empty($data['pw']) || empty($data['pw2'])   ){		
			$this->error( $this->lang['lng_form_reg_missingpasswords']);
		}

		if( $data['pw'] !== $data['pw2'] ){		
			$this->error( $this->lang['lng_form_login_alredyusedemail']);
		}

		if( $data['megye'] == \PortalManager\Categories::TYPE_TERULETEK_BUDAPEST_ID ) {
			if( !isset($data['kerulet']) || empty($data['kerulet']) ) { $this->error( $this->lang['lng_form_missed_kerulet'] ); }
		}
		
		// Felhasználó használtság ellenőrzése
		if($this->userExists('email',$data['email'], $user_group )){		
			$this->error( $this->lang['lng_form_login_alredyusedemail']);
		}

		$data['nev'] 	= strip_tags($data['nev']);
		$data['email'] 	= strip_tags($data['email']);

		// Felhasználó regisztrálása
		$this->db->insert(
			self::TABLE_NAME,
			array(
				'email' 		=> trim($data[email]),
				'nev' 			=> trim($data[nev]),
				'jelszo' 		=> \Hash::jelszo($data[pw2]),
				'user_group' 	=> $user_group
			)
		);

		// Új regisztrált felhasználó ID-ka
		$uid = $this->db->lastInsertId();

		$this->addAccountDetail( $uid, 'contact_name', 		strip_tags($data['contact_name']) );
		$this->addAccountDetail( $uid, 'contact_phone', 	strip_tags($data['contact_phone']) );
		$this->addAccountDetail( $uid, 'company_adoszam', 	strip_tags($data['company_adoszam']) );
		$this->addAccountDetail( $uid, 'company_hq', 		strip_tags($data['company_hq']) );
		

		$city_id = \PortalManager\Categories::TYPE_TERULETEK_BUDAPEST_ID;

		/**
		 * VÁROS MENTÉSE TERÜLETEK KÖZÉ
		 * deep = 2
		 * kivéve, ha Budapest
		 * */
		if( $data['megye'] != \PortalManager\Categories::TYPE_TERULETEK_BUDAPEST_ID ) {

			$cat = new Category( \PortalManager\Categories::TYPE_TERULETEK, false, array('db' => $this->db) );

			$cat_neve 	= strip_tags(trim($data['city']));
			$cat_szulo 	= $data['megye']."_1";

			$check_city = $cat->checkExists( array( 'neve' => $cat_neve, 'szulo_id' => $cat_szulo ) );
			$city_id 	= $check_city;

			// Város terület beszúrása, ha nem található az adatbázisban
			if( !$check_city ) {
				$city_id = $cat->add( array(
					'neve' 		=> $cat_neve,
					'szulo_id' 	=> $cat_szulo
				) );
			}

		} else {
			$data['megye'] 	= $city_id;
			$city_id 		= $data['kerulet'];
		}
		
		$this->addAccountDetail( $uid, 'city', 	$city_id );
		$this->addAccountDetail( $uid, 'megye', $data['megye'] );
		
		
		// Aktiváló e-mail kiküldése
		$this->sendActivationEmail( $data['email'], $user_group );	

		// Ingyenesen STARTER PACK
		if( isset($this->settings['ALLOW_STARTER_ADS']) && $this->settings['ALLOW_STARTER_ADS'] == '1' ) {
			$this->addStarterPackToEmployer( $uid );
		}		
	}

	private function addAccountDetail( $accountID, $key, $value )
	{
		$this->db->insert(
			self::TABLE_DETAILS_NAME,
			array(
				'fiok_id' 	=> $accountID,
				'nev' 		=> $key,
				'ertek' 	=> $value
			)
		);
	}

	private function addStarterPackToEmployer( $user_id )
	{		
		if( !$user_id ) return false; 

		$check = $this->db->query("SELECT 1 FROM ".\PortalManager\Ad::TABLE_PACKAGES_BUYED." WHERE fiok_id = $user_id and csomag_azonosito = 'JOBADSTARTER';");

		if( $check->rowCount() != 0 ) {
			return false;
		}

		// Szolgáltatás adatok
		$service = new AdServices( $this->arg );
		$service->getAd( 'JOBADSTARTER' );

		$this->db->insert(
			\PortalManager\Ad::TABLE_PACKAGES_BUYED,
			array(
				'fiok_id' 			=> $user_id,
				'csomag_azonosito' 	=> 'JOBADSTARTER',
				'elerheto_napok' 	=> (int)$service->getAllowedAdDays(),
				'kiadott_hirdetes' 	=> (int)$service->getHirdetes(),
				'hirdetes_maradt' 	=> (int)$service->getHirdetes(),
				'vasarolva' 		=> NOW
			)
		);
	}

	public function getAccountDetail( $id, $key )
	{
		$details = $this->getAccountDetails( $id );

		return $details[ $key ];
	}

	public function editAccountDetail( $account_id, $key, $value )
	{
		if( !$account_id ) return false;

		$check = $this->db->query("SELECT id FROM ".self::TABLE_DETAILS_NAME." WHERE fiok_id = ".$account_id." and nev = '".$key."';");

		if( $check->rowCount() !== 0 ) {
			$this->db->update(
				self::TABLE_DETAILS_NAME,
				array(
					'ertek' 			=> $value
				),
				sprintf( "fiok_id = %d and nev = '%s'", $account_id, $key)
			);
		} else {
			
			$this->db->insert(
				self::TABLE_DETAILS_NAME,
				array(
					'fiok_id' 	=> $account_id,
					'nev' 				=> $key,
					'ertek' 			=> $value
				)
			);
		}		
	}

	/**
	 * Munkáltató állásajánlatára jelentkezőinek száma, amelyiket még nem nyitotta meg
	 * értesítés buborékhoz kell
	 **/
	public function getUnwatchedApplicants( $acc_id )
	{
		if( !$acc_id ) return 0;

		$ad_set = $this->db->query("SELECT GROUP_CONCAT(ID) FROM ".\PortalManager\Ad::TABLE." WHERE fiok_id = ".$acc_id)->fetchColumn();

		if( empty( $ad_set ) ) {
			return 0;
		}
		
		$q = "SELECT count(id) FROM ".self::TABLE_APPLICANT." WHERE hird_id IN (".$ad_set.") and megtekintve = 0;";
		
		return $this->db->query( $q )->fetchColumn();
	}

	

	public function orderService( $acc_id, $package_id )
	{
		if( !$acc_id ) return false;

		$this->arg['settings'] = $this->arg['view']['settings'];

		// Szolgáltatás adatok
		$arg = $this->arg;
		$arg['filters'] = array(
			'in_ids' => array( $package_id )
		);

		$service = new Services( $arg );
		$service->getList();

		$service_price 	= 0;
		$service_title 	= false;
		$service_desc 	= false;
		$add_day 		= 0;

		while( $service->walk() ) {
			$service_price 		= $service->getPrice( true );
			$service_id 		= $service->getID();
			$service_title 		= $service->getTitle();
			$service_desc 		= $service->getDescription();
			$add_day 			= $service->getAllowedDay();
		}

		// Felhasználó adatok
		$user = new User( $acc_id, $this->arg );

		// Egyenleg ellenőrzés
		$egyenleg = (float) $user->getEgyenleg();
		$price_diff = $egyenleg - $service_price;

		// Nincs elegendő egyenleg
		if( $price_diff < 0 ) {
			$this->error( $this->lang['lng_services_ad_form_not_enought_balance'] );

		}

		// Szolgáltatás árának levonása
		// Kiértesítés
		// Logolás
		$arg = array();
		$arg['service_title'] 		= $service_title;
		$arg['service_desc'] 		= $service_desc;
		$arg['elem'] 				= $package_id;
		$arg['price'] 				= $service_price;
		$arg['balance_after_buy'] 	= $price_diff;

		$trans_hash = $user->balance( ($service_price * -1), $package_id, true, $arg );

		// Szolgáltatás regisztrálása
		$this->db->insert(
			self::TABLE_SERVICES_ORDED,
			array(
				'fiok_id' 			=> $acc_id,
				'tipus' 			=> 'contact_watcher',
				'mikortol' 			=> NOW,
				'meddig' 			=> date( 'Y-m-d H:i:s', strtotime( NOW . ' +'.$add_day.' day') ),
				'tranzakcio_hash'	=> $trans_hash
			)
		);
		
	}


	public function orderAdService( $acc_id, $package_id, $selected_day )
	{
		if( !$acc_id ) return false;

		$this->arg['settings'] = $this->arg['view']['settings'];

		// Szolgáltatás adatok
		$service = new AdServices( $this->arg );
		$service->getAd( $package_id );
		$service_price = $service->getPrice( $selected_day, true );

		// Felhasználó adatok
		$user = new User( $acc_id, $this->arg );

		// Egyenleg ellenőrzés
		$egyenleg = (float) $user->getEgyenleg();
		$price_diff = $egyenleg - $service_price;

			// Nincs elegendő egyenleg
			if( $price_diff < 0 ) {
				$this->error( $this->lang['lng_services_ad_form_not_enought_balance'] );
			}


		// Szolgáltatás árának levonása
		// Kiértesítés
		// Logolás
		$arg = array();
		$arg['service'] = $service;
		$arg['elem'] 	= $service->getID();
		$arg['price'] 	= $service_price;
		$arg['balance_after_buy'] = $price_diff;

		$user->balance( ($service_price * -1), \PortalManager\User::BALANCE_SERVICE_ORDER_AD, true, $arg );

		// Szolgáltatás regisztrálása
		$this->db->insert(
			\PortalManager\Ad::TABLE_PACKAGES_BUYED,
			array(
				'fiok_id' 			=> $user->getID(),
				'csomag_azonosito' 	=> $service->getID(),
				'elerheto_napok' 	=> $selected_day,
				'kiadott_hirdetes' 	=> $service->getHirdetes(),
				'hirdetes_maradt' 	=> $service->getHirdetes(),
				'vasarolva' 		=> NOW
			)
		);
	}

	public function getAccountDetails( $id )
	{
		$list = array();

		$q = $this->db->query( "SELECT nev,ertek FROM ".self::TABLE_DETAILS_NAME." WHERE fiok_id = $id;" )->fetchAll(\PDO::FETCH_ASSOC);

		foreach ($q as $d) {
			$list[$d['nev']] = $d['ertek'];
		}

		return $list;
	}

	function add( $data ){

		$user_group = $data['user_group'];

		switch( $user_group ) {
			// User
			default: case 20:
				$this->register_user( $data, $user_group );
			break;
			// Employer
			case 10:
				$this->register_employer( $data, $user_group );
			break;
		}

	}

	public function getUserNum($user_group = false, $arg = array() )
	{
		$num = 0;
		$emp_ids = array();

		// Terület ID-k, szülőkkel együtt
		$terulets 	= $arg['ad_in_terulet'];

		if( !$terulets ) {
			$terulets = array();
		}

		$q = "
		SELECT 			u.ID, GROUP_CONCAT(h.terulet_id) as terulet_id_set
		FROM 			".self::TABLE_NAME." as u 
		LEFT OUTER JOIN ".\PortalManager\Ad::TABLE." as h ON h.fiok_id = u.ID 
		WHERE 1=1 ";

		if( $user_group ) {
			$q .= " and u.user_group = " . $user_group;
		}

		$q .= " and h.active = 1 and now() > h.feladas_ido and now() < h.lejarat_ido ";

		$q .= " GROUP BY u.ID ";

		$qry = $this->db->query( $q )->fetchAll(\PDO::FETCH_ASSOC);

		foreach ( $qry as $d ) {
			$uset = explode(",",$d['terulet_id_set']);

			if( count($terulets) > 0) {
				foreach ( $terulets as $t ) {
					if( in_array( $t, $uset ) ) {
						if( !in_array( $d['ID'], $emp_ids ) ) {
							$emp_ids[] = $d['ID'];
							continue;
						}						
					}
				}
			} else {
				if( !in_array( $d['ID'], $emp_ids ) ) {
					$emp_ids[] = $d['ID'];
				}
			}
		}

		$num = count($emp_ids);

		return $num;
	}

	public function sendActivationEmail( $email, $user_group )
	{
		$data = $this->db->query( sprintf(" SELECT * FROM ".self::TABLE_NAME." WHERE user_group = $user_group and email = '%s';", $email) )->fetch(\PDO::FETCH_ASSOC);

		if( !$data ) return false; 

		$account_details = $this->getAccountDetails( $data['ID'] );

		$activateKey = base64_encode(trim($email).'='.$data['ID'].'='.$data['jelszo']);

		// Aktiváló e-mail kiküldése
		$mail = new Mailer( $this->settings['page_title'], $this->settings['email_noreply_address'], $this->settings['mail_sender_mode'] );				
		$mail->add( $email );	

		$this->smarty->assign( 'company', trim($data['nev']) );
		$this->smarty->assign( 'nev', trim($account_details['contact_name']) );
		$this->smarty->assign( 'activateKey', $activateKey );
		$this->smarty->assign( 'infoMsg', $this->lang['lng_mail_donotreply'] );

		switch( $user_group ) {
			// Felh.
			case $this->settings['USERS_GROUP_USER']:
				$mail->setSubject( $this->lang['lng_mail_user_reg_activation_title'] );
			break;
			// Munkáltató
			case $this->settings['USERS_GROUP_EMPLOYER']:
				$mail->setSubject( $this->lang['lng_mail_employer_reg_activation_title'] );
			break;
		}
		
		$mail->setMsg( $this->smarty->fetch( 'mails/register_activation.tpl' ) );			
		$re = $mail->sendMail();
	}

	public function testMail( $email )
	{
		
		// Aktiváló e-mail kiküldése
		$mail = new Mailer( $this->settings['page_title'], $this->settings['email_noreply_address'], $this->settings['mail_sender_mode'] );				
		$mail->add( $email );	
		
		$mail->setMsg( $this->smarty->fetch( 'mails/teszt.tpl' ) );			
		$re = $mail->sendMail();
	}
	
	function userExists( $by = 'email', $val, $group = 0 ){
		$q = "SELECT ID FROM ".self::TABLE_NAME." WHERE ".$by." = '".$val."' ";

		if( $group !== 0 ){
			$q .= " and user_group = $group ";
		}
		$c = $this->db->query($q);
		
		if($c->rowCount() == 0){
			return false;	
		}else{
			return true;	
		}
	}

	function oldUser($email)
	{
		$q = "SELECT ID FROM ".self::TABLE_NAME." WHERE email = '".$email."' and old_user = 1 and password = 'xxxx';";
		
		$c = $this->db->query($q);
		
		if($c->rowCount() == 0){
			return false;	
		}else{
			return true;	
		}
	}
	
	function isActivated($email, $group = 0 ){
		$q = "SELECT ID FROM ".self::TABLE_NAME." WHERE user_group = $group and email = '".$email."' and aktivalva IS NOT NULL";
		
		$c = $this->db->query($q);
		
		if($c->rowCount() == 0){
			return false;	
		}else{
			return true;	
		}
	}
	
	function isEnabled($email, $group = 0 ){
		$q = "SELECT ID FROM ".self::TABLE_NAME." WHERE user_group = $group and email = '".$email."' and engedelyezve = 1";
		
		$c = $this->db->query($q);
		
		if($c->rowCount() == 0){
			return false;	
		}else{
			return true;	
		}
	}
	
	function validUser($email, $password, $group = 0 ){
		if($email == '' || $password == '') throw new \Exception('Hiányzó adatok. Nem lehet azonosítani a felhasználót!');
		
		$c = $this->db->query("SELECT ID FROM ".self::TABLE_NAME." WHERE user_group = $group and  email = '$email' and password = '".\Hash::jelszo($password)."'");
		
		if($c->rowCount() == 0){
			return false;	
		}else{
			return true;	
		}
	}

	public function getJobApplicantNum($acc_id, $user_group = false)
	{
		$num = 0;

		switch ( $user_group ) {
			default: case $this->settings['USERS_GROUP_USER']:
				$q = "SELECT count(id) FROM ".self::TABLE_APPLICANT." WHERE felh_id = ".$acc_id.";";
			break;
			case $this->settings['USERS_GROUP_EMPLOYER']:
				$q = "SELECT count(a.id) FROM ".self::TABLE_APPLICANT." as a WHERE (SELECT fiok_id FROM ".\PortalManager\Ad::TABLE." WHERE id = a.hird_id) = ".$acc_id.";";
			break;
		}

		$num = $this->db->query($q)->fetchColumn();

		return $num;
	}

	public function getSubscribeNum($acc_id)
	{
		$num = 0;

		return $num;
	}

	public function getUserList( $arg = array(), $user_group = -1 )
	{
		$q = "
		SELECT 			f.*						
		FROM 			".self::TABLE_NAME." as f";
		// WHERE
		$q .= " WHERE 1=1 ";

		if( $user_group != -1 ) {
			$q .= " and f.user_group = ".$user_group;
		}

		// Csak bizonyos ID-jú elemek listázása
		if( isset($arg['in_ids']) && !empty($arg['in_ids']) ) {
			if( is_array($arg['in_ids']) && count($arg['in_ids']) > 0 ) {
				$q .= " and f.ID IN (".implode(",",$arg['in_ids']).") ";
			}			
		}


		if(count($arg[filters]) > 0){
			foreach($arg[filters] as $key => $v){
				switch($key)
				{
					case 'ID':
						$q .= " and t.".$key." LIKE '".$v."%' ";
					break;
					case 'nev':
						$q .= " and ".$key." LIKE '".$v."%' ";
					break;
					default: 
						$q .= " and ".$key." = '".$v."' ";
					break;	
				}
				
			}	
		}
		$q .= "
		ORDER BY f.regisztralt DESC
		";
		$arg[multi] = "1";
		extract($this->db->q($q, $arg));	
		
		$B = array();
		foreach($data as $d){
			switch($d['user_group']) {
				case $this->settings['USERS_GROUP_EMPLOYER']:
					$group = 'employer';
				break;
				case $this->settings['USERS_GROUP_EMPLOYER']:
					$group = 'user';
				break;
			}
			$d['url'] 			= '/accounts/'.$group.'/'.\Helper::makeSafeUrl($d['nev'],'_-'.$d['ID']);
			$d['active_ads'] 	= $this->activeAds($d['ID'] );
			$d[details] 		= $this->getAccountDetails( $d['ID'] );

			// Logo
			if( !file_exists(ltrim($d['logo'],"/")) ) {
				$d['logo'] = false;
			}

			$B[] = $d; 	
		}
		
		$ret[data] = $B;
		
		return $ret;
	}

	public function getEmployerServices( $account )
	{
		// Defaults
		$ret = array(
			'ads' => array(
				'slot_left' => 0,
				'free' => array(
					// Elérhető létrehozható hirdetések száma - ingyenesen
					'avaiable' 		=> $this->settings['ads_free_creation'], 
					// Engedélyezett futamidők - ingyenesen		
					'allowed_days' 	=> array( 21 ) 	
				),
				'paid' => array(
					// Elérhető létrehozható hirdetések száma - fizetett
					'avaiable' 		=> 0, 
					// Engedélyezett futamidők - fizetett		
					'allowed_days' 	=> array( ),
					'package' 		=> false 	
				)
			),
			'contact_watcher' 	=> array(
				'avaiable' 		=> 0,
				'acces_time' 	=> array( false, false )
			)
		);

		// Fizetett slotok
		$services = $this->db->query("SELECT id, csomag_azonosito, elerheto_napok, hirdetes_maradt, kiadott_hirdetes FROM ".\PortalManager\Ad::TABLE_PACKAGES_BUYED." WHERE fiok_id = $account and elhasznalva = 0;")->fetchAll(\PDO::FETCH_ASSOC);

		foreach( $services as $service ) {
			$ret['ads']['paid']['avaiable'] = (int)$ret['ads']['paid']['avaiable'] + $service['hirdetes_maradt'];
			
			if( !in_array( $service['elerheto_napok'], $ret['ads']['paid']['allowed_days'] )) {
				$ret['ads']['paid']['allowed_days'][] 	= $service['elerheto_napok'];	
			}
			 
			$ret['ads']['paid']['package'][] 		= array(
				'id' 			=> $service['csomag_azonosito'],
				'eid' 			=> $service['id'],
				'total_slot' 	=> $service['kiadott_hirdetes'],
				'left_slot' 	=> $service['hirdetes_maradt'],
				'used_slot' 	=> $service['kiadott_hirdetes'] - $service['hirdetes_maradt'],
				'usage_percent'	=> 100 - \Helper::getPercent( $service['kiadott_hirdetes'], $service['hirdetes_maradt'] ),
				'day'			=> $service['elerheto_napok']
			);
			$ret['ads']['slot_left'] 				= (int)$ret['ads']['slot_left'] + (int)$service['hirdetes_maradt'];
		}

		// Ingyenes hirdetés számolás
		$date_edge = date( 'Y-m-d H:i:s', strtotime( NOW . ' -30 day' ) );		
		$honap_hirdetesek = $this->db->query( $cq = "SELECT count(c.id) FROM ".\PortalManager\Ad::TABLE." as c WHERE c.fiok_id = $account and c.feladas_ido > '$date_edge' and (SELECT 1 FROM ".\PortalManager\Ad::TABLE_PACKAGES_USED_LOG." WHERE hird_id = c.id) IS NULL;")->fetchColumn();
		$ret['ads']['free']['avaiable'] = ( (int) $ret['ads']['free']['avaiable'] ) - $honap_hirdetesek; 
		$ret['ads']['free']['avaiable'] = ( $ret['ads']['free']['avaiable'] < 0 ) ? 0 : $ret['ads']['free']['avaiable'];
		$ret['ads']['slot_left'] 		= (int)$ret['ads']['slot_left']  +  (int) $ret['ads']['free']['avaiable'];

		// Kapcsolat felvétel felvétel
		$check = $this->db->query($q = "SELECT mikortol, meddig FROM ".self::TABLE_SERVICES_ORDED." WHERE fiok_id = ".$account." and tipus = 'contact_watcher' and now() >= mikortol and now() <= meddig;");
		if( $check->rowCount() != 0 ) {
			$cd = $check->fetch(\PDO::FETCH_ASSOC);

			$ret['contact_watcher']['avaiable'] 	= 1;
			$ret['contact_watcher']['acces_time'] 	= array(
				$cd['mikortol'],
				$cd['meddig']
			);
		}

		return $ret;
	}

	private function activeAds( $account = false )
	{
		$num = 0;

		if( !$account ) return $num;

		$num = $this->db->query("SELECT count(id) FROM ".\PortalManager\Ad::TABLE." WHERE fiok_id = '$account' and active = '1' and now() > feladas_ido and now() < lejarat_ido;")->fetchColumn();

		return $num;
	}

	private function error( $msg )
	{
		throw new RedirectException( $msg, $_POST['form'], $_POST['return'], $_POST['session_path'] );
	}

	public function __destruct()
	{
		$this->db = null;
		$this->arg = null;
		$this->user = false;
		$this->smarty = null;
		$this->user_data = false;
	}
}

?>