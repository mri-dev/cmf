<?
namespace PortalManager;

use ExceptionManager\RedirectException;
use PortalManager\Category;
use PortalManager\Users;
use PortalManager\User;
use PortalManager\Applicants;
use PortalManager\Services;
use MailManager\Mails;

class Ad
{
	const TABLE 					= 'hirdetmenyek';
	const TABLE_AD_DETAILS 			= 'hirdetmeny_adat';
	const TABLE_PACKAGES_BUYED 		= 'hirdetmeny_csomag_vasarlasok';
	const TABLE_PACKAGES_USED_LOG 	= 'hirdetmeny_csomag_felhasznalasok';
	const TABLE_PREMIUM				= 'premium_hirdetmeny';

	public $loaded 					= false;
	public $lang 					= array();
	public $smarty 					= null;
	public $settings 				= null;

	private $ad_id 					= false;
	private $ad_details 			= null;
	private $db 					= null;	
	private $dateformat 			= 'Y. F d.';
	private $arg 					= null;
	private $load_applicants 		= false;

	function __construct( $ad_id = false, $arg = array() )
	{
		$this->ad_id 		= $ad_id;
		$this->db 			= $arg[db];
		$this->settings 	= $arg[settings];
		$this->lang 		= $arg[lang];
		$this->smarty 		= $arg[smarty];
		$this->arg 			= $arg;

		if( isset($arg['load_applicants']) ) {
			$this->load_applicants = $arg['load_applicants'];
		}

		$this->ad_details 	= $this->get($ad_id);

		return $this;
	}

	/**
	 * HIRDETÉS FELTÖLTÉSE
	 * 
	 * @param $employer_id int Munkaadó azonosító
	 * @param $data array A hirdetés nyers adatai
	 * 
	 * @uses PortalManager\Category
	 * 
	 * @return void
	 */
	public function create( $employer_id, $data )
	{
		$used_package_hash 	= $data['package'];
		$list_type 			= $data['list_type']; // job | trainee
		$is_free_slot 		= true;

		// Check validate
		/* */
		if( !isset($data['cim']) || empty($data['cim']) ) {	$this->error( $this->lang['lng_form_missed_cim'] ); }
		if( !isset($data['kulcsszavak']) || empty($data['kulcsszavak']) ) { $this->error( $this->lang['lng_form_missed_keywords'] ); }
		if( !isset($data['megye']) || empty($data['megye']) ) { $this->error( $this->lang['lng_form_missed_megye'] ); }
		if( !isset($data['city']) || empty($data['city']) ) { $this->error( $this->lang['lng_form_missed_city'] ); }
		if( !isset($data['valid_from_date']) || empty($data['valid_from_date']) ) { $this->error( $this->lang['lng_form_missed_valid_from_date'] ); }
		if( !isset($data['valid_days']) || empty($data['valid_days']) ) { $this->error( $this->lang['lng_form_missed_valid_days'] ); }
		if( !isset($data['job_mode']) || empty($data['job_mode']) ) { $this->error( $this->lang['lng_form_missed_job_mode'] ); }
		

		if( $list_type == 'job') {
			if( !isset($data['job_type']) || empty($data['job_type']) ) { $this->error( $this->lang['lng_form_missed_job_type'] ); }			
		} else {
			$data['job_type'] = 0;
		}

		if( !isset($data['description']) || empty($data['description']) ) { $this->error( $this->lang['lng_form_missed_description'] ); }
		if( !isset($data['short_description']) || empty($data['short_description']) ) { $this->error( $this->lang['lng_form_missed_shortdescription'] ); }
		
		if( $data['megye'] == \PortalManager\Categories::TYPE_TERULETEK_BUDAPEST_ID ) {
			if( !isset($data['kerulet']) || empty($data['kerulet']) ) { $this->error( $this->lang['lng_form_missed_kerulet'] ); }
		}
		/* */

		// Csomag szolgáltatás adatok
		if( md5('free') != $used_package_hash ) {

			$is_free_slot = false;

			$package_details = $this->db->query("SELECT id, hirdetes_maradt, fiok_id, csomag_azonosito FROM ".self::TABLE_PACKAGES_BUYED." WHERE fiok_id = $employer_id and elhasznalva = 0 and md5(id) = '$used_package_hash';")->fetch(\PDO::FETCH_ASSOC);


			if( !$package_details ) { $this->error( $this->lang['lng_form_package_notfound'] ); }
			if( $package_details['fiok_id'] != $employer_id ) { $this->error( $this->lang['lng_form_package_notvaliduser'] ); }
			if( $package_details['hirdetes_maradt'] <= 0 ) { $this->error( $this->lang['lng_form_package_notenoughtslot'] ); }
		}
	
		// Oktatói hirdetés
		if( $list_type != 'job' ) {

			// Ha új kategóriáról van szó
			if( empty($data['job_mode_id']) ) 
			{
				/**
				 * Oktatói kategória mentése KÖZÉ
				 * deep = 0
				 * */
				$cat = new Category( \PortalManager\Categories::TYPE_STUDIES, false, array('db' => $this->db) );

				$cat_neve 	= ucfirst( strtolower( trim( $data['job_mode'] ) ) );
				$cat_szulo 	= null;

				// Város terület beszúrása, ha nem található az adatbázisban
				if( !$cat->checkExists( array( 'neve' => $cat_neve, 'szulo_id' => $cat_szulo ) ) ) {
					$data['job_mode'] = $cat->add( array(
						'neve' 		=> $cat_neve,
						'szulo_id' 	=> $cat_szulo
					) );
				}
				
			} else {

				$data['job_mode'] = $data['job_mode_id'];

			}
		}

			
		/**
		 * VÁROS MENTÉSE TERÜLETEK KÖZÉ
		 * deep = 2
		 * kivéve, ha Budapest
		 * */
		if( $data['megye'] != \PortalManager\Categories::TYPE_TERULETEK_BUDAPEST_ID ) {

			$cat = new Category( \PortalManager\Categories::TYPE_TERULETEK, false, array('db' => $this->db) );

			$cat_neve 	= trim($data['city']);
			$cat_szulo 	= $data['megye']."_1";

			// Város terület beszúrása, ha nem található az adatbázisban
			if( !$cat->checkExists( array( 'neve' => $cat_neve, 'szulo_id' => $cat_szulo ) ) ) {
				$cat->add( array(
					'neve' => $cat_neve,
					'szulo_id' => $cat_szulo
				) );
			}

		}


		// Országkód ellenőrzés, hogy megvan-e
		if( empty($this->settings['country_id']) ) {
			$this->error( $this->lang['lng_form_missed_countrycode'] );
		}

		// Munkaadó ID ellenőrzés, hogy megvan-e
		if( empty($employer_id) ) {
			$this->error( $this->lang['lng_form_missed_employerid'] );
		}

		/**
		 * Terület ID megszerzése
		 * Ha nem Budapest
		 * */
		if( $data['megye'] != \PortalManager\Categories::TYPE_TERULETEK_BUDAPEST_ID ) {

			$terulet = $cat->checkData( array(
				'szulo_id' 	=> $data['megye'],
				'deep' 		=> 2,		
				'neve' 		=> $cat_neve
			) );

			$terulet_id = $terulet['id'];

		} else {
			// Budapest -> Kerület ID, mint terület ID
			$terulet_id = $data['kerulet'];
		}

		/**
		 * Adatok feltöltése az adatbázisba
		*/
		$ins = array(
			'orszag_id' 	=> $this->settings['country_id'],
			'fiok_id' 		=> $employer_id,
			'cim' 			=> addslashes($data['cim']),
			'feladas_ido' 	=> $data['valid_from_date'],
			'terulet_id' 	=> $terulet_id,
			'lejarat_ido' 	=> date( 'Y-m-d', strtotime($data['valid_from_date']. ' + '.$data['valid_days'].' days') ),
			'jobmode_id' 	=> $data['job_mode'],
			'jobtype_id' 	=> $data['job_type'],
			'tipus' 		=> $list_type,
			'kulcsszavak' 	=> addslashes($data['kulcsszavak'])
		);

		$this->db->insert(
			self::TABLE,
			$ins
		);

		$adid = $this->db->lastInsertId();

		/**
		 * Extra adatok feltöltése az adatbázisba
		*/
		$this->addAdDetail( $adid, 'description', addslashes($data['description']) );
		$this->addAdDetail( $adid, 'short_description', addslashes($data['short_description']) );

		if ( !$is_free_slot ) {
			$this->addAdDetail( $adid, 'used_package_key', $package_details['csomag_azonosito'] );
			$this->addAdDetail( $adid, 'used_package_id', $package_details['id'] );

			// Csomag adatok
			$maradt 		= ((int)$package_details['hirdetes_maradt']) - 1;
			$elhasznalva 	= ($maradt == 0) ? 1 : 0 ;
			$elhasznalva_ido= ($elhasznalva == 1) ? NOW : NULL;

			// Csomag felhasználtság kezelése
			$this->db->update(
				self::TABLE_PACKAGES_BUYED,
				array(
					'hirdetes_maradt' 	=> $maradt,
					'elhasznalva' 		=> $elhasznalva,
					'elhasznalva_ido' 	=> $elhasznalva_ido
				),
				"fiok_id = $employer_id and id = ".$package_details['id']
			);

			// Csomag felhasználásának logolása
			$this->db->insert(
				self::TABLE_PACKAGES_USED_LOG,
				array(
					'fiok_id' 		=> $employer_id,
					'csomag_id' 	=> $package_details['csomag_azonosito'],
					'package_id' 	=> $package_details['id'],
					'hird_id' 		=> $adid
				)
			);
		}

		// Szolgáltatások megrendelése
		$services_id = array();

		if( count($_POST['services']) != 0 ) {			
			$user = new User( $employer_id, $this->arg );

			$egyenleg =  $user->getEgyenleg();

			foreach ( $_POST['services'] as $service_id => $value ) {
				$services_id[] = $service_id;
			}

			// Services
			$services = new Services( array(
				'db' 		=> $this->db,
				'settings' 	=> $this->settings,
				'lang' 		=> $this->lang,
				'filters' 	=>  array(
					'in_ids' => $services_id
				)
			) );

			$s 				= $services->getList();

			while( $s->walk() ){
				// Szolgáltatás ára
				$price = $s->getPrice( true );
				// Egyenleg info leszámolás az árral
				$egyenleg -= $price;
				// Ha elfogyott az egyenleg, akkor leállítás
				if( $egyenleg < 0 ) break;

				// Tranzakció 
				$arg = array();
				$arg['price'] 				= $price;
				$arg['service_title'] 		= $s->getTitle();
				$arg['service_desc'] 		= $s->getDescription();				
				$arg['balance_after_buy'] 	= $egyenleg;

				switch( $s->getID() ) {
					case 'JOBADTOPO7':
						$this->db->insert(
							self::TABLE_PREMIUM,
							array(
								'hird_id' 	=> $adid,
								'szolg_id' 	=> $s->getID(),
								'mikortol' 	=> NOW,
								'meddig' 	=> date('Y-m-d H:i:s', strtotime(NOW.' +'.$s->getAllowedDay().' day')),
								'terulet' 	=> 'orszagos'
							)
						);
					break;
					case 'JOBADTOPM7':
						$this->db->insert(
							self::TABLE_PREMIUM,
							array(
								'hird_id' 	=> $adid,
								'szolg_id' 	=> $s->getID(),
								'mikortol' 	=> NOW,
								'meddig' 	=> date('Y-m-d H:i:s', strtotime(NOW.' +'.$s->getAllowedDay().' day')),
								'terulet' 	=> 'megyei',
								'terulet_id'=> $data['megye']
							)
						);
					break;
				}

				$arg['elem'] 				= $this->db->lastInsertId();

				$user->balance( ($price * -1), $s->getID(), true, $arg );			
			}
		}
		

		return true;
	}

	/**
	 * HIRDETÉS FELTÖLTÉSE
	 * 
	 * @param $employer_id int Munkaadó azonosító
	 * @param $data array A hirdetés nyers adatai
	 * 
	 * @uses PortalManager\Category
	 * 
	 * @return void
	 */
	public function change( $data )
	{
		// Check validate
		//if( !isset($data['cim']) || empty($data['cim']) ) {	$this->error( $this->lang['lng_form_missed_cim'] ); }
		if( !isset($data['megye']) || empty($data['megye']) ) { $this->error( $this->lang['lng_form_missed_megye'] ); }
		if( !isset($data['city']) || empty($data['city']) ) { $this->error( $this->lang['lng_form_missed_city'] ); }
		//if( !isset($data['valid_from_date']) || empty($data['valid_from_date']) ) { $this->error( $this->lang['lng_form_missed_valid_from_date'] ); }
		//if( !isset($data['valid_days']) || empty($data['valid_days']) ) { $this->error( $this->lang['lng_form_missed_valid_days'] ); }
		if( !isset($data['job_mode']) || empty($data['job_mode']) ) { $this->error( $this->lang['lng_form_missed_job_mode'] ); }
		if( !isset($data['job_type']) || empty($data['job_type']) ) { $this->error( $this->lang['lng_form_missed_job_type'] ); }
		if( !isset($data['kulcsszavak']) || empty($data['kulcsszavak']) ) { $this->error( $this->lang['lng_form_missed_keywords'] ); }
		if( !isset($data['description']) || empty($data['description']) ) { $this->error( $this->lang['lng_form_missed_description'] ); }
		if( !isset($data['short_description']) || empty($data['short_description']) ) { $this->error( $this->lang['lng_form_missed_shortdescription'] ); }

		if( $data['megye'] == \PortalManager\Categories::TYPE_TERULETEK_BUDAPEST_ID ) {
			if( !isset($data['kerulet']) || empty($data['kerulet']) ) { $this->error( $this->lang['lng_form_missed_kerulet'] ); }
		}

		/**
		 * VÁROS MENTÉSE TERÜLETEK KÖZÉ
		 * deep = 2
		 * */
		if( $data['megye'] != \PortalManager\Categories::TYPE_TERULETEK_BUDAPEST_ID ) {
			$cat = new Category( \PortalManager\Categories::TYPE_TERULETEK, false, array('db' => $this->db) );

			$cat_neve 	= trim($data['city']);
			$cat_szulo 	= $data['megye']."_1";

			// Város terület beszúrása, ha nem található az adatbázisban
			if( !$cat->checkExists( array( 'neve' => $cat_neve, 'szulo_id' => $cat_szulo ) ) ) {
				$cat->add( array(
					'neve' => $cat_neve,
					'szulo_id' => $cat_szulo
				) );
			}
		}


		// Országkód ellenőrzés, hogy megvan-e
		if( empty($this->settings['country_id']) ) {
			$this->error( $this->lang['lng_form_missed_countrycode'] );
		}

		// Hirdetés ID ellenőrzés, hogy megvan-e
		if( empty($this->ad_id) ) {
			$this->error( $this->lang['lng_form_missed_advid'] );
		}


		if( $data['megye'] != \PortalManager\Categories::TYPE_TERULETEK_BUDAPEST_ID ) {

			$terulet = $cat->checkData( array(
				'szulo_id' 	=> $data['megye'],
				'deep' 		=> 2,		
				'neve' 		=> $cat_neve
			) );

			$terulet_id = $terulet['id'];

		} else {
			// Budapest -> Kerület ID, mint terület ID
			$terulet_id = $data['kerulet'];
		}

		$services_id = array();		
		if( count($_POST['services']) != 0 ) {			
			$user = new User( $this->getEmployerID(), $this->arg );

			$egyenleg =  $user->getEgyenleg();

			foreach ( $_POST['services'] as $service_id => $value ) {
				$services_id[] = $service_id;
			}

			// Services
			$services = new Services( array(
				'db' 		=> $this->db,
				'settings' 	=> $this->settings,
				'lang' 		=> $this->lang,
				'filters' 	=>  array(
					'in_ids' => $services_id
				)
			) );

			$s = $services->getList();
			$used_services= $this->getActiveServices();

			while( $s->walk() ){
				// Ha aktív már a szolgáltatás akkor hagyjuk
				if( in_array( $s->getID(), $used_services ) ) { continue; }
				// Szolgáltatás ára
				$price = $s->getPrice( true );
				// Egyenleg info leszámolás az árral
				$egyenleg -= $price;
				// Ha elfogyott az egyenleg, akkor leállítás
				if( $egyenleg < 0 ) break;

				// Tranzakció 
				$arg = array();
				$arg['price'] 				= $price;
				$arg['service_title'] 		= $s->getTitle();
				$arg['service_desc'] 		= $s->getDescription();				
				$arg['balance_after_buy'] 	= $egyenleg;

				switch( $s->getID() ) {
					case 'JOBADTOPO7':
						$this->db->insert(
							self::TABLE_PREMIUM,
							array(
								'hird_id' 	=> $this->getID(),
								'szolg_id' 	=> $s->getID(),
								'mikortol' 	=> NOW,
								'meddig' 	=> date('Y-m-d H:i:s', strtotime(NOW.' +'.$s->getAllowedDay().' day')),
								'terulet' 	=> 'orszagos'
							)
						);
					break;
					// Megyei szintű kiemelés
					case 'JOBADTOPM7':
						$this->db->insert(
							self::TABLE_PREMIUM,
							array(
								'hird_id' 	=> $this->getID(),
								'szolg_id' 	=> $s->getID(),
								'mikortol' 	=> NOW,
								'meddig' 	=> date('Y-m-d H:i:s', strtotime(NOW.' +'.$s->getAllowedDay().' day')),
								'terulet' 	=> 'megyei',
								'terulet_id'=> $data['megye']
							)
						);
					break;
				}

				$arg['elem'] 				= $this->db->lastInsertId();

				$user->balance( ($price * -1), $s->getID(), true, $arg );			
			}
		}

		/**
		 * Adatok feltöltése az adatbázisba
		*/
		$updt = array(
			'orszag_id' 	=> $this->settings['country_id'],
			'cim' 			=> addslashes($data['cim']),
			'feladas_ido' 	=> $data['valid_from_date'],
			'terulet_id' 	=> $terulet_id,
			'jobmode_id' 	=> $data['job_mode'],
			'jobtype_id' 	=> $data['job_type'],
			'kulcsszavak' 	=> addslashes($data['kulcsszavak'])
		);

		unset($updt['cim']);

		$this->db->update(
			self::TABLE,
			$updt,
			sprintf( "id = %d", $this->ad_id )
		);

		$adid = $ad_id;

		/**
		 * Extra adatok módosítása az adatbázisba
		*/
		$this->editAdDetail( 'description', addslashes($data['description']) );
		$this->editAdDetail( 'short_description', strip_tags(addslashes($data['short_description'])) );

	}

	public function edit( $basic_fields = array(), $details_field = array() )
	{
		if( !$this->ad_id ) return false;

		// Basic
		if( count($basic_fields) > 0 ) {
			$this->db->update(
				self::TABLE,
				$basic_fields,
				sprintf( "id = %d", $this->ad_id )
			);
		}

		// Details
		if( count($details_field) > 0 ) {
			foreach ($details_field as $key => $value) {
				$this->db->update( sprintf("UPDATE ".self::TABLE_AD_DETAILS." SET $key = '$value' WHERE hirdetmeny_id = %d;", $this->ad_id) );
			}
		}
	}

	public function renew( $from_time, $days_to = false, $ad_package = false )
	{
		if( !$days_to ) $this->error( $this->lang['lng_form_job_renew_miss_validtoday'] );
		if( !$this->ad_details ) return false;

		$newdate 		= date( 'Y-m-d', strtotime( $from_time. ' + '.$days_to .' days') );
		$renewed 		= $this->getRenewedNum();
		$renew_price 	= 0;
		$package_allowed= false;
		$elem_id 		= 'free';

		$user = new User( $this->getEmployerID(), $this->arg );

		/* 
		* Hirdetői csomag ellenőrzése
		*/
		// Ha ingyenes csomagként regisztrálta
		if ( empty($ad_package) ) {
			$renew_price 		= $this->settings['ads_free_extension_price'];
			$package_allowed 	= true;
		} else 
		// Ha fizetős csomagként regisztrálta
		{
			$lq = "
				SELECT 					sl.id, sl.csomag_id, s.hossz_netto_ar as extension_price 
				FROM 					".self::TABLE_PACKAGES_USED_LOG." as sl 
				LEFT OUTER JOIN 		".\PortalManager\AdServices::DB_TABLE." as s ON s.csomag_id = sl.csomag_id 
				WHERE 					sl.fiok_id = ".$this->getEmployerID()." and 
										sl.hird_id = ".$this->getID();

			$logged_csomag 	= $this->db->query($lq)->fetch(\PDO::FETCH_ASSOC);
			$elem_id 		= $logged_csomag['id'];

			if( $logged_csomag['csomag_id'] === $ad_package ) {
				$package_allowed = true;

				// Hosszabbítás ára	
				$renew_price = $logged_csomag['extension_price'];
			}
		}

		if( !$package_allowed ) {
			$this->error( $this->lang['lng_form_job_renew_invalid_package_used'] );
		}

		if( isset($this->settings['AFA']) && $this->settings['AFA'] > 0 && is_numeric($this->settings['AFA']) ) {
			$renew_price = $renew_price * ( (100 + $this->settings['AFA']) / 100 );
		}
		// Választott nappal való felszorzás
		$renew_price = $renew_price * $days_to;

		$renew_price = round( $renew_price );

		// Hirdetői csomag hosszabbítás tranzakció
		// Logolás
		$arg = array();
		$arg['ad'] 					= $this;
		$arg['renew_price'] 		= $renew_price;
		$arg['balance_after_buy'] 	= $user->getEgyenleg() - $renew_price;
		$arg['elem'] 				= $elem_id;

		$user->balance( ($renew_price * -1), \PortalManager\User::BALANCE_AD_RENEW, true, $arg );

		// Időpont módosítás
		$this->db->update(
			self::TABLE,
			array(
				'lejarat_ido' => $newdate,
				'ujrainditva' => ($renewed+1)
			),
			"id = ".$this->getID()
		);

		// Értesítő email reset
		$this->db->query("DELETE FROM ".\AlertsManager\Alerts::DB_TABLE_AD." WHERE hird_id = ".$this->getID());
		$this->db->query("DELETE FROM ".\AlertsManager\Alerts::DB_TABLE_AD_RENEW." WHERE hird_id = ".$this->getID());
	}

	public function get( $ad_id )
	{
		if( empty($ad_id) ) return null;
		$megye_id 	= 0;
		$megye 		= $this->arg['results_in_terulet_parents'][0];

		if( !$this->arg['admin'] && empty($this->arg['employer']) ) return null;

		$qry_params 			= array();
		$qry_params['ad_id'] 	= $ad_id;
		$qry_params['megye_id'] = (int)$megye;	

		$qq = "
		SELECT 					adv.*,
								u.nev as employer,
								u.logo as employer_logo,
								(SELECT 1 FROM ".\PortalManager\Users::TABLE_PREMIUM." WHERE fiok_id = u.ID and NOW() > mikortol and NOW() < meddig ) as employer_premium, ";

		$qq .= "(SELECT SUM(1) FROM ".self::TABLE_PREMIUM." WHERE hird_id = adv.id and ( NOW() > mikortol and NOW() < meddig) and (terulet = 'orszagos' "; 
		
		if( $megye ) 
		{
			$qry_params['megye'] 	= $megye;
 			$qq .= " or ( terulet = 'megyei' and terulet_id IN (:megye) ) ";
		}

		$qq .= ") ) as ad_premium, ";		
		$qq .= "adsPriority(adv.id, adv.fiok_id, :megye_id) as ad_priority "; 
		$qq .= "
		FROM 					".self::TABLE." as adv 
		LEFT OUTER JOIN 		".\PortalManager\Users::TABLE_NAME." as u ON u.id = adv.fiok_id 
		WHERE 					1 = 1 and 
								adv.id = :ad_id";

		if( !$this->arg['admin'] && !empty($this->arg['employer']) ) 
		{
			$qq .= " and adv.fiok_id = :fiok_id";
			$qry_params['fiok_id'] = (int)$this->arg['employer'];
		}

		//echo $qq . '<br>';

		$qry = $this->db->squery( $qq, $qry_params );

		if( $qry->rowCount() == 0 ) return null;

		$data = $qry->fetch(\PDO::FETCH_ASSOC);

		// Details 
		$details = $this->getAdDetail( $data['id'] );

		foreach ($details as $key => $value) {
			$data[$key] = $value;
		}

		// Jelentkezők
		$applicants = false;
		if( $this->load_applicants ) {
			$applicants = (new Applicants( $data['id'], $this->arg ))->getList();
		}
		$data['applicants'] = $applicants;

		$this->loaded = true;

		return $data;
	}

	public function applicantForJob( $acc_id, $message = false )
	{
		if( empty($acc_id) ) return false;

		if( empty($this->lang) ) die(__CLASS__.' @ '.__FUNCTION__.': Hiányzó nyelvi fájlok. ');


		// Ellenőrzés, hogy nem jelentkezett már a munkára az adott felhasználó
		$q = "SELECT 1 FROM ".\PortalManager\Users::TABLE_APPLICANT." WHERE felh_id = $acc_id and hird_id = ".$this->ad_id;

		$check = $this->db->query($q);
		$apped = $check->rowCount();

		// Ha nem jelentkezett, akkor mentés az adatbázisba
		if( $apped != 0 ) { $this->error($this->lang['lng_applicant_form_already_apped']); }

		$fields = array(
			'felh_id' => $acc_id,
			'hird_id' => $this->ad_id,
			'jelentkezes' => NOW
		);

		// Üzenet hozzácsatolása
		if( isset($message) && !empty($message) ) {
			$fields['uzenet'] = addslashes(trim($message));
		}
		
		$this->db->insert(
			\PortalManager\Users::TABLE_APPLICANT,
			$fields
		);
		

		$marg = array();

		// Terület adatok betöltése
		$this->loadTerulet();

		$marg['ad'] = $this;
		$marg['message'] = $message;

		// Felhasználó értesítése e-mailben, hogy jelentkezett				
		$user = $this->db->query("
			SELECT 			a.ID, a.email, a.nev 
			FROM 			".\PortalManager\Users::TABLE_NAME." as a
			WHERE 			a.ID = ".$acc_id)->fetch(\PDO::FETCH_ASSOC
		);

		// Felhasználó további adatai
		$userdetails = array();
		$userdetails_qry = $this->db->query("
			SELECT 			nev, ertek
			FROM 			".\PortalManager\Users::TABLE_DETAILS_NAME."
			WHERE 			fiok_id = ".$acc_id)
		->fetchAll(\PDO::FETCH_ASSOC);
		foreach( $userdetails_qry as $detail ){
			$userdetails[$detail['nev']] = $detail['ertek'];
		}

		// Felhasználó Europass
		$ep = $this->db->query("
			SELECT 			1
			FROM 			".\PortalManager\Users::TABLE_EUROPASS_XML."
			WHERE 			felh_id = ".$acc_id)->rowCount();

		if( $ep != 0 ) {
			$marg['europass'] = true;
		}


		$user = array_merge( $user, $userdetails );
		
		$marg['userdata'] = $user;
		$marg['message'] = $message;
		
		(new Mails( $this, 'jobapplicant_to_user', $user['email'], $marg ))->send();


		// Munkáltató értesítése e-mailben a jelentkezésről
		$employer = $this->db->query("SELECT email, nev FROM ".\PortalManager\Users::TABLE_NAME." WHERE ID = ".$this->getEmployerID())->fetch(\PDO::FETCH_ASSOC);

		$marg['employer'] = $employer;

		(new Mails( $this, 'jobapplicant_to_employer', $employer['email'], $marg ))->send();

	}

	private function getAdDetail( $ad_id )
	{
		$list = array();

		$qs = "SELECT nev,ertek FROM ".self::TABLE_AD_DETAILS." WHERE hirdetmeny_id = $ad_id;" ;

		$q = $this->db->query($qs)->fetchAll(\PDO::FETCH_ASSOC);

		foreach ($q as $d) {
			$list[$d['nev']] = $d['ertek'];
		}

		return $list;
	}

	private function addAdDetail( $ad_id, $key, $value )
	{
		$this->db->insert(
			self::TABLE_AD_DETAILS,
			array(
				'hirdetmeny_id' 	=> $ad_id,
				'nev' 				=> $key,
				'ertek' 			=> $value
			)
		);
	}

	private function editAdDetail( $key, $value )
	{
		if( !$this->ad_id ) return false;

		$check = $this->db->query("SELECT 1 FROM ".self::TABLE_AD_DETAILS." WHERE hirdetmeny_id = ".$this->ad_id." and nev = '".$key."';");

		if( $check->rowCount() > 0 ) {
			$this->db->update(
				self::TABLE_AD_DETAILS,
				array(
					'ertek' 			=> $value
				),
				sprintf( "hirdetmeny_id = %d and nev = '%s'", $this->ad_id, $key)
			);
		} else {
			$this->db->insert(
				self::TABLE_AD_DETAILS,
				array(
					'hirdetmeny_id' 	=> $this->ad_id,
					'nev' 				=> $key,
					'ertek' 			=> $value
				)
			);
		}		
	}

	private function error( $msg )
	{
		throw new RedirectException( $msg, $_POST['form'], $_POST['return'], $_POST['session_path'] );
	}

	/*===============================
	=            GETTERS            =
	===============================*/
	public function getAdv( $field = false )
	{
		if( $field )
			return $this->ad_details[$field];
		else
			return $this->ad_details;
	}

	public function getID()
	{
		return $this->ad_details['id'];
	}

	public function getName()
	{
		return $this->ad_details['cim'];
	}

	public function loadTerulet()
	{
		$city_id = $this->ad_details['terulet_id'];

		if( !$city_id ) return false;

		$q = "SELECT neve, szulo_id FROM ".\PortalManager\Categories::TYPE_TERULETEK." WHERE ID = $city_id;";
		$qry = $this->db->query( $q )->fetch(\PDO::FETCH_ASSOC);

		if( is_null( $qry['szulo_id']) ) return false;

		// set city_name
		$this->ad_details['city_name'] = $qry['neve'];
		// set city_slug
		$this->ad_details['city_slug'] = \Helper::makeSafeUrl($qry['neve'],'',false);
		// set megye_id
		$this->ad_details['megye_id'] = $qry['szulo_id'];

		$q = "SELECT neve FROM ".\PortalManager\Categories::TYPE_TERULETEK." WHERE ID = ".$qry['szulo_id'].";";

		$qry = $this->db->query( $q )->fetch(\PDO::FETCH_ASSOC);
		// set megye_slug
		$this->ad_details['megye_slug'] = \Helper::makeSafeUrl($qry['neve'], '', false);
		// set megye_slug
		$this->ad_details['megye_name'] = $qry['neve'];
	}

	public function getMegyeID()
	{
		if( !isset($this->ad_details['megye_id']) ) return null;

		return $this->ad_details['megye_id'];
	}

	public function isBudapest()
	{
		if( !isset($this->ad_details['megye_id']) ) return null;

		if( $this->ad_details['megye_id'] == \PortalManager\Categories::TYPE_TERULETEK_BUDAPEST_ID ) {
			return true;
		}

		return false;
	}

	public function getMegyeName()
	{
		if( !isset($this->ad_details['megye_name']) ) return null;

		return $this->ad_details['megye_name'];
	}

	public function getMegyeSlug()
	{
		if( !isset($this->ad_details['megye_slug']) ) return null;

		return $this->ad_details['megye_slug'];
	}

	public function getCityName()
	{
		if( !isset($this->ad_details['city_name']) ) return null;

		if( $this->isBudapest() ) {
			$this->ad_details['city_name'] = 'Budapest, ' . $this->ad_details['city_name'];
		}

		return $this->ad_details['city_name'];
	}

	public function getMegyeSearchURL( $type = 'jobs' )
	{
		if( !isset($this->ad_details['megye_slug']) ) return null;

		return '/search/'.$type.'/'.$this->ad_details['megye_slug'];
	}

	public function getCitySearchURL( $type = 'jobs' )
	{
		if( !isset($this->ad_details['city_slug']) ) return null;
		if( !isset($this->ad_details['megye_slug']) ) return null;

		return '/search/'.$type.'/'.$this->ad_details['megye_slug'].'-'.$this->ad_details['city_slug'];
	}

	public function getCitySlug()
	{
		if( !isset($this->ad_details['city_slug']) ) return null;

		return $this->ad_details['city_slug'];
	}

	public function getCityID()
	{
		return $this->ad_details['terulet_id'];
	}

	public function hasShortDescription()
	{
		$text = $this->ad_details['short_description'];

		$stext = trim( strip_tags( $text ) );

		if( empty($stext) ) return false;

		return true;
	}

	public function hasDescription()
	{
		$text = $this->ad_details['description'];

		$stext = trim( strip_tags( $text ) );

		if( empty($stext) ) return false;

		return true;
	}

	public function isExpired()
	{
		$date 	= strtotime( $this->ad_details['lejarat_ido'] );
		$now 	= time();

		if( $now > $date ) return true;

		return false;
	}

	public function inWaiting()
	{
		$date 	= strtotime( $this->ad_details['feladas_ido'] );
		$now 	= time();

		if( $date > $now ) return true;

		return false;
	}

	public function isRunning()
	{
		$start 	= strtotime( $this->ad_details['feladas_ido'] );
		$to 	= strtotime( $this->ad_details['lejarat_ido'] );
		$now 	= time();

		if( $now >= $start && $now < $to ) return true;

		return false;
	}

	public function isActive()
	{
		if( $this->ad_details['active'] == '0' ) return false;

		return true;
	}

	public function isTrainee()
	{
		if( $this->ad_details['tipus'] == 'job' ) return false;

		return true;
	}

	public function validTo()
	{
		$date = $this->ad_details['lejarat_ido'];

		return \Helper::replaceMonths( date( $this->dateformat, strtotime($date)), $this->settings['language'] );
	}

	public function validToDays()
	{
		$end = $this->ad_details['lejarat_ido'];
		$start = NOW;

		if( $this->inWaiting() ) {
			$start = $this->ad_details['feladas_ido'];
		}

		if( $this->isOver() ) return 0; 

		$cd = \Helper::distanceDate( $end, $start );

		return $cd['num'] . ' ' . $cd['type'];
	}

	public function getStartDate()
	{
		$date = $this->ad_details['feladas_ido'];

		return \Helper::replaceMonths(date( $this->dateformat, strtotime($date)));
	}

	public function getURL()
	{
		return '/job/'.$this->getID();
	}

	public function getMunkakor()
	{
		$munkakor = '-';

		if( $this->isTrainee() ) 
		// Oktatói hirdetés
		{
			$cat = new Category( \PortalManager\Categories::TYPE_STUDIES, $this->ad_details['jobmode_id'], $this->arg );
			$munkakor = $cat->getName();

		} else 
		// Állásajánlat
		{
			$cat = new Category( \PortalManager\Categories::TYPE_MUNKAKOROK, $this->ad_details['jobmode_id'], $this->arg );
			$munkakor = $cat->getName();
		}

		return $munkakor;
	}

	public function getMunkatipus()
	{
		$munkatipus = '-';

		// Ha gyakornoki munkatípus
		if( $this->isTrainee() ) {
			return $this->lang['lng_trainee_job'];
		}

		$cat = new Category( \PortalManager\Categories::TYPE_MUNKATIPUS, $this->ad_details['jobtype_id'], $this->arg );

		$munkatipus = $cat->getName();

		return $munkatipus;
	}

	public function getActiveServices()
	{
		$s = array(
			'packages' => array(),
			'list' => array()
		);

		$q = $this->db->query("SELECT szolg_id, mikortol, meddig, terulet FROM ".self::TABLE_PREMIUM." WHERE hird_id = ".$this->getID()." and NOW() >= mikortol and NOW() <= meddig;");

		if( $q->rowCount() != 0 ){
			$qd = $q->fetch(\PDO::FETCH_ASSOC);
			$s['packages'][] = $qd['szolg_id'];
			$s['list'][$qd['szolg_id']] = array(
				'valid' => array(
					'from' 	=> $qd['mikortol'],
					'to' 	=> $qd['meddig']
				)
			);
		}
		
		return $s;
	}

	public function getShortDescription()
	{
		return $this->ad_details['short_description'];
	}

	public function getEmployerID()
	{
		return $this->ad_details['fiok_id'];
	}

	public function getEmployerName()
	{
		return $this->ad_details['employer'];
	}

	public function getUsedPackageID()
	{
		return $this->ad_details['used_package_id'];
	}

	public function employerHasLogo()
	{
		$has = false;

		if( isset($this->ad_details['employer_logo']) && !empty($this->ad_details['employer_logo']) ) {
			if( file_exists(ltrim($this->ad_details['employer_logo'], "/")) ){
				$has = true;
			}
		}

		return $has;
	}

	public function getEmployerLogo()
	{
		$logo = '';

		if ( $this->employerHasLogo() ) {
			$logo = $this->ad_details['employer_logo'];
		}

		return $logo;
	}

	public function isPremiumEmployer()
	{
		$premium = false;

		if( $this->ad_details['employer_premium'] == '1' ) {
			$premium = true;
		}

		return $premium;
	}
	
	public function isPremium()
	{
		$premium = false;

		if( !is_null($this->ad_details['ad_premium']) ) {
			$premium = true;
		}

		return $premium;
	}

	public function getEmployerURL()
	{
		return '/accounts/employer/'.\Helper::makeSafeUrl($this->ad_details['employer'],'_-'.$this->getEmployerID());
	}

	public function getDescription()
	{
		return $this->ad_details['description'];
	}

	public function getRenewedNum()
	{
		return $this->ad_details['ujrainditva'];
	}

	public function getKeywords( $array_format = false )
	{
		return $this->ad_details['kulcsszavak'];
	}

	public function getPriorityIndex()
	{
		return $this->ad_details['ad_priority'];
	}
	
	public function getApplicants()
	{
		return $this->ad_details['applicants'];
	}

	public function isOver()
	{
		$start 	= strtotime( $this->ad_details['feladas_ido'] );
		$to 	= strtotime( $this->ad_details['lejarat_ido'] );
		$now 	= time();

		if( $now > $to ) {
			return true;
		}

		return false;
	}

	public function getActionkey( $action )
	{
		switch( $action ) {
			case 'turnoff': case 'turnon':
				return base64_encode( json_encode( array( 
					'id' => $this->ad_details['id'], 
					'employer' => $this->ad_details['fiok_id'] 
				), JSON_UNESCAPED_UNICODE));
			break;
			default:
				return null;
			break;
		}
		
	}
	
	/*=====  End of GETTERS  ======*/
	

	public function __destruct()
	{
		$this->db = null;
		$this->smarty = null;
		$this->arg = null;
	}
}
?>