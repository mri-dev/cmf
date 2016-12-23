<?
namespace CashFlowManager;

class Statistics
{
  private $db = null;
	public $smarty = null;

	function __construct( $arg = array() )
	{
    $this->db 			= $arg['db'];
		$this->smarty 		= $arg[smarty];

		return $this;
	}

  public function totalIncome( $uid, $year = false )
  {
    $n = 0;

    if (!$uid) {
      return $n;
    }

    $q = "SELECT
      SUM(cf.amount)
    FROM cash_flow as cf
    WHERE
    trans_type_id = 1 and
    acc_id = $uid ";
    if($year) {
      $q .= " and trans_date LIKE '$year%' ";
    }

    $n = $this->db->query($q)->fetchColumn();

    return $n;
  }

  public function totalOutgo(  $uid, $year = false )
  {
    $n = 0;

    if (!$uid) {
      return $n;
    }

    $q = "SELECT
      SUM(cf.amount)
    FROM cash_flow as cf
    WHERE
    trans_type_id = 2 and
    acc_id = $uid ";

    if($year) {
      $q .= " and trans_date LIKE '$year%' ";
    }

    $n = $this->db->query($q)->fetchColumn();

    return $n;
  }

  public function avaiableCash( $uid )
  {
    $n = 0;

    if (!$uid) {
      return $n;
    }

    $in = $this->totalIncome($uid);
    $out =  $this->totalOutgo($uid);

    $n = $in - $out;

    return $n;
  }


  public function __destruct()
  {
    $this->db = null;
    $this->smarty = null;
  }
}
?>
