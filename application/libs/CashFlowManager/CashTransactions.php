<?
namespace CashFlowManager;

use PortalManager\User;

class CashTransactions
{
  private $db = null;
	public $smarty = null;

	function __construct( $arg = array() )
	{
    $this->db 			= $arg['db'];
		$this->smarty 		= $arg[smarty];

		return $this;
	}

  public function addIncome($uid, $post)
  {
    if (!$uid) {
      return false;
    }

    extract($post);

    $this->db->insert(
      "cash_flow",
      array(
        'acc_id' => $uid,
        'group_id' => $group,
        'trans_type_id' => 1,
        'holder_id' => $holder,
        'trans_date' => $date,
        'comment' => $title,
        'amount' => $cash
      )
    );

    return true;
  }

  public function addOutgo($uid, $post)
  {
    if (!$uid) {
      return false;
    }

    extract($post);

    return true;
  }

  public function getTransactions( $arg = array() )
  {
    $ret = array();
    $q = "SELECT
      t.*,
      tt.name as forgalom
    FROM cash_flow as t
    LEFT OUTER JOIN trans_types as tt ON tt.id = t.trans_type_id
    WHERE 1=1
    ";


    if (!empty($arg['trans_type_id']) && !empty($arg['trans_type_id'])) {
      $q .= " and t.trans_type_id = '".$arg['trans_type_id']."' ";
    }

    if (!empty($arg['date_from'])) {
      $q .= " and t.trans_date >= '".$arg['date_from']."' ";
    }
    if (!empty($arg['date_to'])) {
      $q .= " and t.trans_date <= '".$arg['date_to']."' ";
    }

    $q .= " ORDER BY t.register_date DESC ";

    if (isset($arg['limit']) && $arg['limit'] > 0) {
      $q .= " LIMIT 0,". ((int)$arg['limit']);

    }

    $data = $this->db->query($q)->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($data as $d) {
      $ret[] = $d;
    }

    return $ret;
  }

  public function __destruct()
  {
    $this->db = null;
    $this->smarty = null;
  }
}
?>
