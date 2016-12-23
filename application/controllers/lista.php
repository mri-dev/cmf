<?
use CashFlowManager\CashTransactions;

class lista extends Controller  {
		private $user = false;
		function __construct(){
			parent::__construct();

			$this->user = $this->getVar('user');
			$this->out( 'page_lista', true);

			$trans = new CashTransactions(array(
				'db' => $this->db,
				'smarty' => $this->smarty
			));

			$date_from = date('Y').'-01-01';
			$date_to = date('Y-m-d');

			$arg = array();
			$arg['date_from'] = $date_from;
			$arg['date_to'] = $date_to;
			$this->out( 'list', $trans->getTransactions($arg) );

			// SEO Információk
			$SEO = null;
			// Site info
			$SEO .= $this->addMeta('description','');
			$SEO .= $this->addMeta('keywords','');
			$SEO .= $this->addMeta('revisit-after','3 days');

			// FB info
			$SEO .= $this->addOG('type','website');
			$SEO .= $this->addOG('url','');
			$SEO .= $this->addOG('image','');
			$SEO .= $this->addOG('site_name',parent::$pageTitle);

			$this->out( 'SEOSERVICE', $SEO );
		}

		function __destruct(){
			// RENDER OUTPUT
			parent::bodyHead();					# HEADER
			$this->displayView( __CLASS__.'/index', true );		# CONTENT
			parent::__destruct();				# FOOTER
		}
	}

?>
