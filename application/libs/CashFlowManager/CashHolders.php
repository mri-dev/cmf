<?
namespace CashFlowManager;

class CashHolders
{
  private $db = null;
	public $smarty = null;

	function __construct( $arg = array() )
	{
    $this->db 			= $arg['db'];
		$this->smarty 		= $arg[smarty];

		return $this;
	}

  public function get( $userid = false )
  {
    $ret = array();

    $data = $this->db->query("SELECT * FROM cash_holder WHERE acc_id = $userid;")->fetchAll(\PDO::FETCH_ASSOC);

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
