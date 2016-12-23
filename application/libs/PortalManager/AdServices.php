<?
namespace PortalManager;

use ExceptionManager\RedirectException;

class AdServices
{
	public $filters 		= array();
	public $total_items 	= 0;
	private $row_items 		= 0;

	private $db = null;
	private $lang = array();
	private $smarty = null;
	private $settings = null;
	private $arg = null;
	private $items = false;
	private $current_item = false;
	private $item_steped_item = false;	
	private $walk_step = 0;

	const DB_TABLE = 'szolgaltatas_hirdetes';

	function __construct( $arg = array() )
	{
		$this->db 			= $arg[db];
		$this->arg 			= $arg;
		$this->settings 	= $arg[settings];
		$this->lang 		= $arg[lang];
		$this->smarty 		= $arg[smarty];
		$this->filters 		= $arg[filters];

		if( !isset( $this->lang['lng_services_php'] ) ) {
			die(__CLASS__.': nyelvi fájl hiányzik a szolgáltatásokhoz ({template}/languages/{language}/services.php). Az osztályhoz paraméterezze a "services" nyelvi fájlt.');
			return false;
		}

		return $this;
	}

	public function getList( $arg = array() )
	{
		$this->items = false;
		$items = array();

		$q = "
		SELECT 
		SQL_CALC_FOUND_ROWS 				
							s.*
		FROM 				".self::DB_TABLE." as s
		WHERE 				1 = 1  ";

		// WHERE's
		if( !isset($this->filters['admin']) ){

			$q .=  " and s.aktiv = 1 and csomag_id != 'JOBADSTARTER'  ";
		} else {
			if( isset($this->filters['hide_offline']) ) {
				$q .= " and s.aktiv = 1 ";
			}
		}

		if( isset($this->filters['group']) ){

			$q .=  " and s.ad_group = '".$this->filters['group']."' ";
		}
	
		// ORDER
		$order = ' s.sorrend ASC ';

		if( isset($arg['orderby']) ){
			$order = $arg['orderby'];
		}

		$q .= " ORDER BY ".$order;

		$qry 				= $this->db->query($q);
		$this->total_items 	= $this->db->query("SELECT FOUND_ROWS();")->fetchColumn();
		$qry_data 			= $qry->fetchAll(\PDO::FETCH_ASSOC); 

		if( $qry->rowCount() == 0 ) return $this; 
		
		foreach ( $qry_data as $service ) {
			$this->row_items++;

			$item = $service;

			$items[] = $item;
		}

		$this->items = $items;

		return $this;
	}

	public function getAd( $id )
	{
		$data = $this->db->query("SELECT * FROM ".self::DB_TABLE." WHERE csomag_id = '$id';")->fetch(\PDO::FETCH_ASSOC);

		$this->current_item = $data;

		return $this;
	}

	public function walk()
	{			
		$this->current_item = $this->items[$this->walk_step];		

		$this->walk_step++;

		if ( $this->walk_step > $this->row_items ) {
			// Reset Walk
			$this->walk_step = 0;
			$this->current_item = false;

			return false;
		}

		return true;	
	}


	public function get()
	{
		return $this->current_item;
	}

	/**
	 * Csomag egyedi azonosítója
	 * */
	public function getID()
	{

		return  $this->current_item['csomag_id'];
	}

	public function getGroup()
	{
		return  $this->current_item['ad_group'];
	}

	public function getTitle()
	{
		$nev = $this->lang['lng_'.$this->current_item['nev_lng']];

		if( empty($nev) ) {
			$nev = $this->current_item['nev_lng'];
		}

		return $nev;
	}

	public function getDescription()
	{
		$leiras = $this->lang['lng_'.$this->current_item['leiras_lng']];

		if( empty($leiras) ) {
			$leiras = $this->current_item['leiras_lng'];
		}

		return $leiras;
	}

	/**
	 * A csomagban engedélyezett futamidőtartamak
	 * */
	public function getAllowedAdDays( $array_format = false )
	{
		$days = $this->current_item['elerheto_idotartamok'];

		if( $array_format ) {
			$days = explode(",",$this->current_item['elerheto_idotartamok']);
		}

		return $days;
	}

	/**
	 * A csomag ára futamidőktől függően
	 * */
	public function getPrice( $day = false, $with_vat = false )
	{
		if( !$day ) return 0;

		$price = $this->current_item['netto_ar_'.$day.'nap'];

		// Kedvezmény leszámolás
		$kedvezmeny = $this->isDiscounted();
		if( $kedvezmeny ) {
			$price = $price - ($price / 100 * $kedvezmeny);
		}

		// Áfával való felszorzás
		if( $with_vat ) {
			if( isset($this->settings['AFA']) && $this->settings['AFA'] > 0 && is_numeric($this->settings['AFA']) ) {
				$price = $price * ( (100 + $this->settings['AFA']) / 100 );
			}
		}

		// Kerekítés 0 - 5 pontossábra, vagy sima kerekítés
		if( isset($this->settings['price_round_510']) && $this->settings['price_round_510'] == '1' ) {
			$price = round( $price / 5 ) * 5;
		} else {
			$price = round( $price );
		}

		return $price;
	}

	public function getExtensionPrice( $day = false, $with_vat = false )
	{
		if( !$day ) return 0;

		$price = $this->current_item['hossz_netto_ar_'.$day.'nap'];

		// Kedvezmény leszámolás
		$kedvezmeny = $this->isDiscounted();
		if( $kedvezmeny ) {
			$price = $price - ($price / 100 * $kedvezmeny);
		}

		// Áfával való felszorzás
		if( $with_vat ) {
			if( isset($this->settings['AFA']) && $this->settings['AFA'] > 0 && is_numeric($this->settings['AFA']) ) {
				$price = $price * ( (100 + $this->settings['AFA']) / 100 );
			}
		}

		// Kerekítés 0 - 5 pontossábra, vagy sima kerekítés
		if( isset($this->settings['price_round_510']) && $this->settings['price_round_510'] == '1' ) {
			$price = round( $price / 5 ) * 5;
		} else {
			$price = round( $price );
		}

		return $price;
	}

	/**
	 * Létrehozható hirdetések száma
	 * */
	public function getHirdetes()
	{
		return $this->current_item['hirdetes'];
	}

	public function isDiscounted()
	{
		return ($this->current_item['kedvezmeny_szazalek'] !== 0) ? $this->current_item['kedvezmeny_szazalek'] : false;
	}

	public function getStandardPrice( $day = false, $with_vat = false )
	{
		if( !$day ) return 0;

		$price = $this->current_item['netto_ar_'.$day.'nap'];

		// Áfával való felszorzás
		if( $with_vat ) {
			if( isset($this->settings['AFA']) && $this->settings['AFA'] > 0 && is_numeric($this->settings['AFA']) ) {
				$price = $price * ( (100 + $this->settings['AFA']) / 100 );
			}
		}

		// Kerekítés 0 - 5 pontossábra, vagy sima kerekítés
		if( isset($this->settings['price_round_510']) && $this->settings['price_round_510'] == '1' ) {
			$price = round( $price / 5 ) * 5;
		} else {
			$price = round( $price );
		}

		return $price;
	}

	public function getStandardExtensionPrice( $day = false, $with_vat = false )
	{
		//if( !$day ) return 0;

		$price = $this->current_item['hossz_netto_ar'];

		// Áfával való felszorzás
		if( $with_vat ) {
			if( isset($this->settings['AFA']) && $this->settings['AFA'] > 0 && is_numeric($this->settings['AFA']) ) {
				$price = $price * ( (100 + $this->settings['AFA']) / 100 );
			}
		}

		// Kerekítés 0 - 5 pontossábra, vagy sima kerekítés
		if( isset($this->settings['price_round_510']) && $this->settings['price_round_510'] == '1' ) {
			$price = round( $price / 5 ) * 5;
		} else {
			$price = round( $price );
		}

		return $price;
	}

	public function isActive( )
	{
		return ($this->current_item['aktiv'] == '1') ? true : false;
	}

	public function getVersionNums( )
	{
		return count($this->getAllowedAdDays( true ));
	}

	private function error( $msg )
	{
		throw new RedirectException( $msg, $_POST['form'], $_POST['return'], $_POST['session_path'] );
	}

	public function __destruct()
	{
		$this->db = null;
		$this->arg = null;
		$this->smarty = null;
		$this->items = false;
		$this->current_item = false;
		$this->item_steped_item = false;
		$this->total_items = 0;
		$this->walk_step = 0;
	}
}
?>