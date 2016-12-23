<?
use CashFlowManager\Groups;
use CashFlowManager\CashHolders;

class home extends Controller  {
		private $user = false;
		function __construct(){
			parent::__construct();

			$this->out('homepage', true);

			$this->user = $this->getVar('user');

			$groups = new Groups(array(
				'db' => $this->db,
				'smarty' => $this->smarty
			));
			$cash_holders = new CashHolders(array(
				'db' => $this->db,
				'smarty' => $this->smarty
			));

			$this->out( 'income_groups', $groups->Income($this->user[data][ID]) );
			$this->out( 'outgo_groups', $groups->Outgo($this->user[data][ID]) );
			$this->out( 'cash_holders', $cash_holders->get($this->user[data][ID]) );


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
