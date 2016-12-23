<?
namespace CashFlowManager;

class Groups
{
  private $db = null;
	public $smarty = null;

	function __construct( $arg = array() )
	{
    $this->db 			= $arg['db'];
		$this->smarty 		= $arg[smarty];

		return $this;
	}

  public function Income( $userid = false )
  {
    $ret = array();

    $data = $this->db->query("SELECT * FROM cash_groupes WHERE acc_id = $userid and trans_type_id = 1;")->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($data as $d)
    {
      $ret[$d['id']] = $d['name'];
    }

    return $ret;
  }

  public function Outgo( $userid = false )
  {
    $ret = array();

    $data = $this->db->query("SELECT * FROM cash_groupes WHERE acc_id = $userid and trans_type_id = 2;")->fetchAll(\PDO::FETCH_ASSOC);

    foreach ($data as $d)
    {
      $ret[$d['id']] = $d['name'];
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
