<? 
namespace PortalManager;

use TransactionManager\Transaction;
use MailManager\Mails;
use ExceptionManager\RedirectException;
/**
 * class Users
 * 
 */
class User
{
	private $db = null;
	public $smarty = null;
	public $lang = array();

	public $id 	= false;
	public $user = false;

	const BALANCE_TRANSACTION_TRANSFER 	= 'transfer_topup';
	const BALANCE_TRANSACTION_ADDITION 	= 'transfer_addition';
	const BALANCE_TRANSACTION_BARION 	= 'barion_topup';
	const BALANCE_SERVICE_ORDER_AD 		= 'services_order_ad';
	const BALANCE_AD_RENEW				= 'ad_renew';

	function __construct( $user_id, $arg = array() ){
		$this->id 			= $user_id;
		$this->db 			= $arg['db'];
		$this->settings 	= $arg[settings];
		$this->smarty 		= $arg[smarty];
		$this->lang 		= $arg[lang];

		$this->user = $this->get();
	}	
		
	private function get( $arg = array() )
	{
		$ret 			= array();
		
		if(!$this->id) return false;
		
		$ret[data] 			= $this->getData( $this->id, 'ID' );
		$ret[email] 		= $ret[data][email];
		$ret[europass] 		= $this->loadEuropass( $this->id, true );
		
		return $ret;
	}

	private function getData( $account_id, $db_by = 'email' ){
		if($account_id == '') return false;

		$q = "
		SELECT 			u.*, 
						(SELECT 1 FROM ".\PortalManager\Users::TABLE_PREMIUM." WHERE fiok_id = u.ID and NOW() > mikortol and NOW() < meddig ) as employer_premium
		FROM 			".\PortalManager\Users::TABLE_NAME." as u 
		WHERE 			1 = 1 ";
		
		$q .= " and u.".$db_by." = '$account_id';";
	
		extract($this->db->q($q));

		// Details
		$det = $this->db->query("SELECT nev, ertek FROM ".\PortalManager\Users::TABLE_DETAILS_NAME." WHERE fiok_id = $account_id;")->fetchAll(\PDO::FETCH_ASSOC);

		foreach ($det as $d ) {
			$data[$d['nev']] = $d['ertek'];
		}
				
		return $data;
	}

	/**
	 * Egyenleg jóváírása
	 * @param int $amount összeg mozgás jóváírás
	 * @param enum $transaction_type tranzakció típusa
	 * @param boolean $alert_out felhasználó kiértesítése a tranzakcióról
	 * @param array $arguments külső paraméterek 
	 * 
	 * @return string hashkey Tranzakció ID
	 * */ 
	public function balance ( $amount = 0, $transaction_type, $alert_out = false, $arguments = array() )
	{
		$elemid = false;

		// Egyenleg mentése
		if ( $amount == 0 || empty( $amount) ) { return false; }
		
		$transaction = new Transaction( null, array(
			'db' => $this->db,
			'settings' => $this->settings
		) );

		$hashkey 	= md5(microtime());
		$paymentid 	= null;
		$comment 	= null;
		$valuta 	= 'HUF'; 

		switch ( $transaction_type ) {
			case self::BALANCE_TRANSACTION_TRANSFER:
				$trans_type = 'balance_topup';
				$comment 	= 'lng_balance_topup_by_transfer';				
			break;
			case self::BALANCE_TRANSACTION_ADDITION:
				$trans_type = 'balance_topup';
				$comment 	= 'lng_balance_topup_by_addition';				
			break;
			case self::BALANCE_SERVICE_ORDER_AD:
				$trans_type = 'service_order';
				$comment 	= 'lng_balance_service_order_ad';				
			break;	
			case self::BALANCE_AD_RENEW:
				$trans_type = 'ad_renew';
				$comment 	= 'lng_balance_ad_renew';				
			break;
			default: 
				$trans_type = $transaction_type;
				$comment 	= 'lng_balance_'.$transaction_type;		
			break;
		}

		$message = $this->lang[$comment];

		if( isset($arguments['elem']) ) {
			$elemid = $arguments['elem'];
		}

		// Kiértesítés
		if ( $alert_out ) {
			$mailarg = array();			
			$mailarg['user'] 		= $this->user;
			$mailarg['amount'] 		= $amount;
			$mailarg['hashkey']		= $hashkey;
			$mailarg['trans_time']	= NOW;

			if( count( $arguments ) > 0 ) {
				$mailarg = array_merge( $mailarg, $arguments );
			}

			$this->sendEmail( $message, 'balance_'.$transaction_type, $mailarg );
		}


		$transaction->create( $this->id, $hashkey, $trans_type, $amount, $valuta, $paymentid, $comment, $elemid );
		$transaction->activate( $hashkey );

		return $hashkey;
	}

	private function loadEuropass( $account_id, $get_xml = false )
	{
		$ret = array(
			'xml_source' => null,
			'has_europass' => false,
			'last_refresh' => false
		);

		if( empty($account_id) ) return $ret;

		$get_ep = $this->db->query("SELECT europass_xml, idopont FROM ".\PortalManager\Users::TABLE_EUROPASS_XML." WHERE felh_id = $account_id;");

		if( $get_ep->rowCount() == 0 ) return $ret;

		$ep_data = $get_ep->fetch(\PDO::FETCH_ASSOC);

		if( $get_xml ) {
			$ret['xml_source']	= htmlentities( $ep_data['europass_xml'] );
		}
		
		$ret['has_europass'] 	= true;
		$ret['last_refresh'] 	= $ep_data['idopont'];

		return $ret;
	}

	public function getEmployerServices()
	{
		$account = $this->id;

		if( !$account ) return false;

		// Defaults
		$ret = array(
			'ads' => array(
				'slot_left' => 0,
				'free' => array(
					// Elérhető létrehozható hirdetések száma - ingyenesen
					'avaiable' 		=> 1, 
					// Engedélyezett futamidők - ingyenesen		
					'allowed_days' 	=> array( 5 ) 	
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
			$ret['ads']['paid']['avaiable'] 		= (int)$ret['ads']['paid']['avaiable'] + $service['hirdetes_maradt'];
			
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
		$honap_hirdetesek = $this->db->query("SELECT count(id) FROM ".\PortalManager\Ad::TABLE." WHERE fiok_id = $account and feladas_ido > '$date_edge';")->fetchColumn();
		$ret['ads']['free']['avaiable'] = ( (int) $ret['ads']['free']['avaiable'] ) - $honap_hirdetesek; 
		$ret['ads']['free']['avaiable'] = ( $ret['ads']['free']['avaiable'] < 0 ) ? 0 : $ret['ads']['free']['avaiable'];
		$ret['ads']['slot_left'] 		= (int)$ret['ads']['slot_left']  +  (int) $ret['ads']['free']['avaiable'];

		// Kapcsolat felvétel felvétel
		$check = $this->db->query($q = "SELECT mikortol, meddig FROM ".\PortalManager\Users::TABLE_SERVICES_ORDED." WHERE fiok_id = ".$account." and tipus = 'contact_watcher' and now() >= mikortol and now() <= meddig;");
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

	public function loadTerulet()
	{
		$city_id = $this->user['data']['city'];

		if( empty($city_id) ) return false;
		
		$q 		= "SELECT neve FROM ".\PortalManager\Categories::TYPE_TERULETEK." WHERE ID = $city_id;";
		$qry 	= $this->db->query( $q )->fetch(\PDO::FETCH_ASSOC);

		// set city_id
		$this->user['data']['city_id'] = $city_id;
		// set city_name
		$this->user['data']['city_name'] = $qry['neve'];
		// set city_slug
		$this->user['data']['city_slug'] = \Helper::makeSafeUrl($qry['neve'],'',false);
		// set megye_id
		$this->user['data']['megye_id'] = $this->user['data']['megye'];

		$q = "SELECT neve FROM ".\PortalManager\Categories::TYPE_TERULETEK." WHERE ID = ".$this->user['data']['megye'].";";
		$qry = $this->db->query( $q )->fetch(\PDO::FETCH_ASSOC);
		// set megye_slug
		$this->user['data']['megye_slug'] = \Helper::makeSafeUrl($qry['neve'], '', false);
		// set megye_slug
		$this->user['data']['megye_name'] = $qry['neve'];
	}

	public function getUserGroup()
	{
		return $this->user['data']['user_group'];
	}

	public function getUserImage()
	{
		$logo = $this->user['data']['logo'];

		if( empty($logo) ) return false;

		return $logo;
	}

	public function isPremiumEmployer()
	{
		if( $this->getUserGroup() != $this->settings['USERS_GROUP_EMPLOYER'] ) return false;

		$premium = false;

		if( $this->user['data']['employer_premium'] == '1' ) {
			$premium = true;
		}

		return $premium;
	}

	public function allowViewUserContact()
	{
		$allow = false;

		$check = $this->db->query($q = "SELECT 1 FROM ".\PortalManager\Users::TABLE_SERVICES_ORDED." WHERE fiok_id = ".$this->getID()." and tipus = 'contact_watcher' and now() >= mikortol and now() <= meddig;");

		if( $check->rowCount() != 0 ) $allow = true;

		return $allow;
	}

	public function getValue( $key )
	{
		$v = $this->user['data'][$key];

		if( empty($v) && !$v ) return false;

		return $v;
	}

	public function getMegyeID()
	{
		if( !isset($this->user['data']['megye_id']) ) return null;

		return $this->user['data']['megye_id'];
	}

	public function getMegyeName()
	{
		if( !isset($this->user['data']['megye_name']) ) return null;

		return $this->user['data']['megye_name'];
	}

	public function getMegyeSlug()
	{
		if( !isset($this->user['data']['megye_slug']) ) return null;

		return $this->user['data']['megye_slug'];
	}

	public function isBudapest()
	{
		if( !isset($this->user['data']['megye_id']) ) return false;

		if( $this->user['data']['megye_id'] == \PortalManager\Categories::TYPE_TERULETEK_BUDAPEST_ID ) {
			return true;
		}

		return false;
	}

	public function getCityName()
	{
		if( !isset($this->user['data']['city_name']) ) return null;

		if( $this->isBudapest() ) {
			$this->user['data']['city_name'] = 'Budapest, ' . $this->user['data']['city_name'];
		}

		return $this->user['data']['city_name'];
	}

	public function getMegyeSearchURL( $type = 'jobs' )
	{
		if( !isset($this->user['data']['megye_slug']) ) return null;

		return '/search/'.$type.'/'.$this->user['data']['megye_slug'];
	}

	public function getCitySearchURL( $type = 'jobs' )
	{
		if( !isset($this->user['data']['city_slug']) ) return null;
		if( !isset($this->user['data']['megye_slug']) ) return null;

		return '/search/'.$type.'/'.$this->user['data']['megye_slug'].'-'.$this->user['data']['city_slug'];
	}

	public function getCitySlug()
	{
		if( !isset($this->user['data']['city_slug']) ) return null;

		return $this->user['data']['city_slug'];
	}

	public function getCityID()
	{
		return $this->user['data']['terulet_id'];
	}

	public function getURL()
	{
		switch ( $this->getUserGroup() ) {
			case $this->settings['USERS_GROUP_USER']:
				$group = 'users';
			break;
			case $this->settings['USERS_GROUP_EMPLOYER']:
				$group = 'employer';
			break;
		}

		return '/accounts/'.$group.'/'.\Helper::makeSafeUrl( $this->getName(), "_-".$this->getID());
	}

	public function getWebsite( $hide_http = false )
	{
		if( $this->getUserGroup() != $this->settings['USERS_GROUP_EMPLOYER'] ) return false;

		$web = $this->user['data']['web'];

		if( empty($web) ) return false;

		if( !$hide_http && strpos( 'http://', $web ) === false ) {
			$web = 'http://'.$web;
		} 

		return $web;
	}

	public function getActiveAds()
	{
		if( $this->getUserGroup() != $this->settings['USERS_GROUP_EMPLOYER'] ) return false;

		$num = 0;

		$num = $this->db->query("SELECT count(id) FROM ".\PortalManager\Ad::TABLE." WHERE fiok_id = '".$this->getID()."' and active = '1' and now() > feladas_ido and now() < lejarat_ido;")->fetchColumn();

		return $num;
	}

	public function getEgyenleg()
	{
		return $this->user['data']['egyenleg'];
	}

	public function getName()
	{
		return $this->user['data']['nev'];
	}

	public function getID()
	{
		return $this->user['data']['ID'];
	}

	public function getZipCode()
	{
		return $this->user['data']['zip_code'];
	}

	public function getEuropass()
	{
		return $this->user['europass'];
	}

	public function getEmail()
	{
		return $this->user['email'];
	}

	public function getGender()
	{
		return ( isset( $this->user['data']['gender']) && !empty($this->user['data']['gender']) ) ? $this->user['data']['gender'] : false;
	}

	public function getBithDate()
	{
		return ( isset( $this->user['data']['born']) && !empty($this->user['data']['born']) ) ? $this->user['data']['born'] : false;
	}

	public function howOld()
	{
		$birthday = $this->getBithDate();

		if( !$birthday ) return false;

		$age = strtotime( $birthday );

		if( $age === false ){ 
	        return false; 
	    } 
    
	    list($y1,$m1,$d1) = explode("-",date("Y-m-d",$age)); 
	    
	    $now = strtotime("now"); 
	    
	    list($y2,$m2,$d2) = explode("-",date("Y-m-d",$now)); 
	    
	    $age = $y2 - $y1; 
	    
	    if((int)($m2.$d2) < (int)($m1.$d1)) {
        	$age -= 1;
        }

		return $age;
	}

	public function getMaritanStatus()
	{
		return ( isset( $this->user['data']['csaladi_allapot']) && !empty($this->user['data']['csaladi_allapot'])) ? $this->user['data']['csaladi_allapot'] : false;
	}


	public function isAllowed()
	{
		return ($this->user['data']['engedelyezve'] == '1') ? true : false;
	}

	public function getPhone()
	{
		return $this->user['data']['phone'];
	}

	public function getLastloginTime( $formated = false )
	{
		if( $formated ) {
			return \PortalManager\Formater::distanceDate($this->user['data']['utoljara_belepett']);	
		} else {
			return $this->user['data']['utoljara_belepett'];	
		}
		
	}

	public function getRegisterTime( $formated = false )
	{
		if( $formated ) {
			return \PortalManager\Formater::distanceDate($this->user['data']['regisztralt']);	
		} else {
			return $this->user['data']['regisztralt'];	
		}
	}

	public function getKeywords( $arrayed = false )
	{
		$keys = trim( $this->user['data']['kulcsszavak'] );

		if( empty($keys) ) return false;

		if( $arrayed ) {
			return explode(" ", $keys);	
		} else {
			return $keys;
		}
	}

	public function getKompetenciak( $arrayed = false )
	{
		$set = array();

		$qry = $this->db->query("SELECT komp_id FROM ".\PortalManager\Users::TABLE_COMPETENCE_XREF." WHERE fiok_id = ".$this->getID().";");

		if( $qry->rowCount() == 0 ) return false; 

		$dat = $qry->fetchAll(\PDO::FETCH_ASSOC);

		foreach ( $dat as $k ) {
			$set[] = $k['komp_id'];
		}

		if( $arrayed ) {
			return $set;
		} else {
			return implode( ",", $set );	
		}		
	}

	public function getKompetenciaEgyeb()
	{
		return $this->user['data']['kompetencia_kiegeszites'];
	}	

	public function sendEmail( $message, $email_template, $arg = array(), $from = false )
	{
		$this->checkLanguageFiles();
		$this->checkSmarty();

		if( empty($message) ) {
			$this->error( $this->lang['lng_users_form_sendmessage_miss_message'] );
		}

		$arg['message'] 	= $message;		
		$arg['from_name'] 	= $from['name'];
		$arg['from_email']	= $from['email'];
		$arg['infoMsg'] 	= $this->lang['lng_mail_sendth_jobabc'];

		$mail = new Mails( $this, $email_template, $this->getEmail(), $arg );

		$mail->send();
	}

	private function error( $msg )
	{
		throw new RedirectException( $msg, $_POST['form'], $_POST['return'], $_POST['session_path'] );
	}

	public function checkLanguageFiles()
	{
		if( empty($this->lang) ) die(__CLASS__.': '.'Hiányoznak a nyelvi fájlok.');
	}
	public function checkSmarty()
	{
		if( empty($this->smarty) ) die(__CLASS__.': '.'Hiányzik a Smarty controller');
	}

	public function __destruct()
	{
		$this->db = null;
		$this->smarty = null;
		$this->user = false;
	}
}

?>