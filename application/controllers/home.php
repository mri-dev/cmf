<?
use CashFlowManager\Groups;
use CashFlowManager\CashHolders;
use CashFlowManager\Statistics;
use CashFlowManager\CashTransactions;

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
			$stats = new Statistics(array(
				'db' => $this->db,
				'smarty' => $this->smarty
			));
			$trans = new CashTransactions(array(
				'db' => $this->db,
				'smarty' => $this->smarty
			));


			if (isset($_POST['income'])) {
				$trans->addIncome($this->user[data][ID], $_POST);
				//print_r($_POST);
				Helper::reload();
			}

			if (isset($_POST['outgo'])) {
				$trans->addOutgo($this->user[data][ID], $_POST);
				Helper::reload();
			}


			$this->out( 'income_groups', $groups->Income($this->user[data][ID]) );
			$this->out( 'outgo_groups', $groups->Outgo($this->user[data][ID]) );
			$this->out( 'cash_holders', $cash_holders->get($this->user[data][ID]) );
			$this->out( 'cash_info', array(
				'income' => $stats->totalIncome($this->user[data][ID], date('Y')),
				'all_income' => $stats->totalIncome($this->user[data][ID]),
				'outgo' => $stats->totalOutgo($this->user[data][ID], date('Y')),
				'all_outgo' => $stats->totalOutgo($this->user[data][ID]),
				'avaiable' => $stats->avaiableCash($this->user[data][ID])
			) );
			$this->out( 'trans_last_income', $trans->getTransactions(array(
				'limit' => 10,
				'trans_type_id' => 1
			)) );
			$this->out( 'trans_last_outgo', $trans->getTransactions(array(
				'limit' => 10,
				'trans_type_id' => 2
			)) );
			$this->out('year_inout', $stats->inout($this->user[data][ID], date('Y-m-d', strtotime('-365 days'))) );


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
