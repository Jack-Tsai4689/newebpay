<?php 
namespace Jack\Newebpay;
use Exception;

class Newebpay
{
	private $set;
	private $RespondType = 'JSON';

	public function set($set)
	{
		$this->set = $set;
		if (!isset($this->set['ReturnURL'])) $this->set['ReturnURL'] = '';
		if (!isset($this->set['NotifyURL'])) $this->set['NotifyURL'] = '';
		// ReturnURL 導回商店網址 無填寫:停留在智付通頁面
		// NotifyURL 支付通知網址 以幕後方式回傳給商店相關支付結果資料
	}
	/**
	 * [respond 回傳格式]
	 * @param  [type] $type [JSON, String]
	 * @return [type]       [description]
	 */
	public function respond($type)
	{
		$this->RespondType = $type;
	}
	public function setting_check()
	{
		$error = false;
		if (!isset($this->set['MerchantID']) || empty($this->set['MerchantID'])) $error = true;
		if (!isset($this->set['ItemDesc'])) $error = true;
		if (!isset($this->set['HashKey']) || empty($this->set['HashKey'])) $error = true;
		if (!isset($this->set['HashIV']) || empty($this->set['HashIV'])) $error = true;
		if (!isset($this->set['url']) || empty($this->set['url'])) $error = true;
		if ($error) return false;
		return true;
	}
	private function data_format($order)
	{
		$data = array(
			'ItemDesc' => $this->set['ItemDesc'],
			'url'      => $this->set['url'],
			'CREDIT' => '0',
			'WEBATM' => '0',
			'BARCODE' => '0',
			'VACC' => '0',
			'CVS' => '0'
		);
		$mer_array = array(
			'MerchantID'      => $this->set['MerchantID'],
			'TimeStamp'       => time(),
			'MerchantOrderNo' => $order['order_id'],
			'Version'         => '1.2',
			'Amt'             => $order['all_total'],
		);
		ksort($mer_array);
		$check_merstr = http_build_query($mer_array);
		$CheckValue_str = "HashKey=".$this->set['HashKey']."&".$check_merstr."&HashIV=".$this->set['HashIV'];
		$data = array_merge($data, $mer_array);
		$data['CheckValue'] = strtoupper(hash("sha256", $CheckValue_str));
		// 分期付款
		$instflag = 0;
		$data['flag_str'] = '';
		if (isset($order['instflag'])) $instflag = intval($order['instflag']);
		if ($instflag > 0) $data['flag_str'] = '<input type="hidden" id="InstFlag" name="InstFlag" value="'.$instflag.'">';
		return $data;
	}
	private function create_html($data)
	{
		$cvscom_str = '';
		if (isset($data['CVSCOM'])) {
			$cvscom_str = '<input type="hidden" id="CVSCOM" name="CVSCOM" value="'.$data['CVSCOM'].'">
			<!-- 超商取貨付款
			1 = 啟用超商取貨不付款
			2 = 啟用超商取貨付款
			3 = 啟用超商取貨不付款及超商取貨付款
			0 或者未有此參數，即代表不開啟。
			-->';
		}
		echo '
			<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /> 
			<form name="mybank" id="mybank" method="post" action="'.$data['url'].'">
				<input type="hidden" name="MerchantID" value="'.$data['MerchantID'].'">
				<input type="hidden" name="RespondType" value="'.$this->RespondType.'">
				<input type="hidden" name="CheckValue" value="'.$data['CheckValue'].'">
				<input type="hidden" name="TimeStamp" value="'.$data['TimeStamp'].'">
				<input type="hidden" name="Version" value="'.$data['Version'].'">
				<input type="hidden" name="LangType" value="zh-tw">
				<input type="hidden" name="MerchantOrderNo" value="'.$data['MerchantOrderNo'].'">
				<input type="hidden" name="Amt" value="'.$data['Amt'].'">
				<input type="hidden" name="ItemDesc" value="'.$data['ItemDesc'].'">
				<input type="hidden" name="TradeLimit" value="300">
				<input type="hidden" name="LoginType" value="0">
				<input type="hidden" name="BARCODE" value="'.$data['BARCODE'].'">
				<input type="hidden" name="CVS" value="'.$data['CVS'].'">
				<input type="hidden" name="CREDIT" value="'.$data['CREDIT'].'">
				<input type="hidden" name="WEBATM" value="'.$data['WEBATM'].'">
				<input type="hidden" name="VACC" value="'.$data['VACC'].'">
				'.$cvscom_str.$data['flag_str'].'
				<input type="hidden" name="ReturnURL" value="'.$this->set['ReturnURL'].'">
				<input type="hidden" name="NotifyURL" value="'.$this->set['NotifyURL'].'">
			</form>
			<script language=javascript>
				document.forms.mybank.submit();
			</script>';
	}
	/**
	 * [all 同時開多個方式]
	 * @param  [type] $order [description]
	 * @param  [type] $pay   [description]
	 * @return [type]        [description]
	 */
	public function all($order, $pay)
	{
		if (!$this->setting_check()) {
			throw new Exception("支付系統異常，請聯繫系統管理員");
			exit;
		}
		$data = $this->data_format($order);
		foreach ($pay as $method) {
			switch ($method) {
				case 'bank':
					$data['CREDIT'] = '1';
					break;
				case 'webatm':
					$data['WEBATM'] = '1';
					break;
				case 'barcode':
					$data['BARCODE'] = '1';
					break;
				case 'atm':
					$data['VACC'] = '1';
					break;
				case 'store':
					$data['CVS'] = '1';
					break;
			}
		}
		$this->create_html($data);
	}
	public function atm($order)
	{
		if (!$this->setting_check()) {
			throw new Exception("支付系統異常，請聯繫系統管理員");
			exit;
		}
		$data = $this->data_format($order);
		$data['VACC'] = '1';
		$this->create_html($data);
	}
	/**
	 * [webatm WEBATM]
	 * @param  [type] $order [description]
	 * @return [type]        [description]
	 */
	public function webatm($order)
	{
		if (!$this->setting_check()) {
			throw new Exception("支付系統異常，請聯繫系統管理員");
			exit;
		}
		$data = $this->data_format($order);
		$data['WEBATM'] = '1';
		$this->create_html($data);
	}
	/**
	 * [barcode 條碼繳費]
	 * @param  [type] $order [description]
	 * @return [type]        [description]
	 */
	public function barcode($order)
	{
		if (!$this->setting_check()) {
			throw new Exception("支付系統異常，請聯繫系統管理員");
			exit;
		}
		$data = $this->data_format($order);
		$data['BARCODE'] = '1';
		$this->create_html($data);
	}
	/**
	 * [bank 信用卡]
	 * @param  [type] $order [description]
	 * @return [type]        [description]
	 */
	public function bank($order)
	{
		if (!$this->setting_check()) {
			throw new Exception("支付系統異常，請聯繫系統管理員");
			exit;
		}
		$data = $this->data_format($order);
		$data['CREDIT'] = '1';
		$this->create_html($data);
	}
	/**
	 * [store 超商繳費]
	 * @param  [type] $order [description]
	 * @return [type]        [description]
	 */
	public function store($order)
	{
		if (!$this->setting_check()) {
			throw new Exception("支付系統異常，請聯繫系統管理員");
			exit;
		}
		$data = $this->data_format($order);
		$data['CVS'] = '1';
		$this->create_html($data);
	}
	// 超商取貨
	public function market($order)
	{
		if (!$this->setting_check()) {
			throw new Exception("支付系統異常，請聯繫系統管理員");
			exit;
		}
		$data = $this->data_format($order);
		$data['CVSCOM'] = '2';
		$this->create_html($data);
	}
	public function notify($post)
	{
		$Status = $post["Status"];
		$Message = $post["Message"];
		// status 自己調整
		if($Status == "SUCCESS") {
			return array(
				'status' => true,
				'msg' => $Message
			);
		} else {
			return array(
				'status' => false,
				'msg' => $Message
			);
		}
	}
}