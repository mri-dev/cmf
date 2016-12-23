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

  public function inout( $uid, $start_point = false)
  {
    $ret = array();


    if (!$uid) {
      return $ret;
    }

    $q = "SELECT
      SUM(cf.amount) as total,
      substr(trans_date, 1, 7) as date_on
    FROM cash_flow as cf
    WHERE
    trans_type_id = 1 and
    acc_id = $uid ";

    if ($start_point) {
      $q .= " and trans_date >= '{$start_point}' ";
    }

    $q .= "
    GROUP BY substr(trans_date, 1, 7)
    ORDER BY trans_date ASC;";

    $in = $this->db->query($q)->fetchAll(\PDO::FETCH_ASSOC);

    $q = "SELECT
      SUM(cf.amount) as total,
      substr(trans_date, 1, 7) as date_on
    FROM cash_flow as cf
    WHERE
    trans_type_id = 2 and
    acc_id = $uid ";

    if ($start_point) {
      $q .= " and trans_date >= '{$start_point}' ";
    }

    $q .= "
    GROUP BY substr(trans_date, 1, 7)
    ORDER BY trans_date ASC;";

    $out = $this->db->query($q)->fetchAll(\PDO::FETCH_ASSOC);

    $ret['in'] = $in;
    $ret['out'] = $out;

    return $ret;
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
