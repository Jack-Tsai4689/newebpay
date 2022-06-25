<?php 
namespace Jack;
use Exception;

class Newebpay
{
	private $set;
	private $test_url = 'https://ccore.newebpay.com/MPG/mpg_gateway';
	private $stand_url = 'https://core.newebpay.com/MPG/mpg_gateway';

	function __construct()
	{
		$this->set['url'] = $this->test_url;
	}

	public function envSet($set)
	{
		$this->set;
	}
	public function modeChange($mode = 'test')
	{
		if ($mode == 'test') {
			$this->set['url'] = $this->test_url;
		} else {
			$this->set['url'] = $this->stand_url;
		}
	}
	private function envCheck()
	{
		$error = false;
		if (!isset($this->set['MerchantID']) || empty($this->set['MerchantID'])) $error = true;
		if (!isset($this->set['ItemDesc'])) $error = true;
		if (!isset($this->set['HashKey']) || empty($this->set['HashKey'])) $error = true;
		if (!isset($this->set['HashIV']) || empty($this->set['HashIV'])) $error = true;
		if ($error) return false;
		return true;
	}
	private function create_mpg_aes_encrypt($parameter = '', $key = '', $iv = '')
	{
		$return_str = '';
		if (!empty($parameter)) {
			$return_str = http_build_query($parameter);
		}
		return trim(bin2hex(openssl_encrypt($this->addpadding($return_str), 'AES-256-CBC', $this->set['HashIV'], OPENSSL_RAW_DATA|OPENSSL_ZERO_PADDING, $iv)));
	}
	private function addpadding($string, $blocksize = 32)
	{
		$len = strlen($string);
		$pad = $blocksize - ($len % $blocksize);
		$string.= str_repeat(chr($pad), $pad);
		return $string;
	}
	private function data_format($order)
	{
		$Version = '2.0';
		$data = array(
			'MerchantID' => $this->set['MerchantID'],
			// 'ItemDesc' => $this->set['ItemDesc'],
			// 'url'      => $this->set['url'],
			'Version' => $Version,
		);
		$TradeInfo_ar = [
			'MerchantID' => $this->set['MerchantID'],
			'TimeStamp' => time(),
			'Version' => $Version,
			'RespondType' => 'String',
			// 'LangType' => 'zh-tw',
			'MerchantOrderNo' => $order['order_id'],
			'Amt' => $order['all_total'],
			'ItemDesc' => $this->set['ItemDesc'],
			'ReturnURL' => $this->set['ReturnURL'],
			'NotifyURL' => $this->set['NotifyURL'],
		];
		if (isset($order['CREDIT'])) $TradeInfo_ar['CREDIT'];
		if (isset($order['CVSCOM'])) $TradeInfo_ar['CVSCOM'];
		$data['TradeInfo'] = $this->create_mpg_aes_encrypt($TradeInfo_ar, $this->set['HashKey'], $this->set['HashIV']);

		$TradeSha = "HashKey=".$this->set['HashKey']."&".$data['TradeInfo']."&HashIV=".$this->set['HashIV'];
		$data['TradeSha'] = strtoupper(hash("sha256", $TradeSha));
		return $data;
	}
	private function create_html($data)
	{
		$cols = ['MerchantID', 'TradeInfo', 'TradeSha'];
		$html = '';
		foreach ($cols as $key => $value) {
			$html.= '<input type="hidden" name="'.$value.'" value="'.$data[$value].'">';
		}
		return '
			<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /> 
			<form name="mybank" id="mybank" method="post" action="'.$data['url'].'">'.$html.'</form>
			<script language=javascript>
				document.forms.mybank.submit();
			</script>';
	}
	public function bank($order)
	{
		if (!$this->envCheck()) {
			throw new Exception("支付系統異常，請聯繫系統管理員");
			exit;
		}
		$order['CREDIT'] = '1';
		$data = $this->data_format($order);
		return $this->create_html($data);
	}
	// 超商取貨
	public function market($order)
	{
		if (!$this->envCheck()) {
			throw new Exception("支付系統異常，請聯繫系統管理員");
			exit;
		}
		$order['CVSCOM'] = '2';
		$data = $this->data_format($order);
		return $this->create_html($data);
	}
	// 定期定額
	public function period($order)
	{
		if (!$this->envCheck()) {
			throw new Exception("支付系統異常，請聯繫系統管理員");
			exit;
		}

		$data = $this->period_data_format($order);

		$this->create_period_html($data);
	}
	private function period_data_format($order)
	{
		$data = array(
			'MerchantID_'      => $this->set['MerchantID'],
			// 'Period_url'      => $this->set['Period_url'],
		);

		$PostData_array = array(
			'RespondType'	  => 'JSON',				//回傳格式
			'TimeStamp'       => time(),				//時間戳記
			'Version'         => '1.3',					//串接程式版本
			'LangType'		  => 'zh-Tw',				//語系 
			'MerOrderNo'	  => $order['order_id'],	//商店訂單編號 
			'ProdDesc'		  => $order['ProdDesc'],	//產品名稱
			'PeriodAmt'       => $order['all_total'],	//委託金額 Int(6)
			'PeriodType'	  => 'D',					//週期類別 D W M Y
			'PeriodPoint'	  => 30,					//交易週期授權時間 對應上面的週期類別 規則去串接手冊看
			'PeriodStartType' => 1,						//檢查卡號模式  1=用10元測試信用卡是否存在 並自動退款
			'PeriodTimes'	  => 99,					//授權期數
			'ReturnURL'		  => $this->set['ReturnURL'],	//返回商店網址
			'PayerEmail'	  => $order['pay_email'],	//付款人電子信箱
			'EmailModify'	  => 'N',					//付款人電子信箱是否開放修改
			'PaymentInfo'	  => 'N',					//是否開啟付款人資訊
			'OrderInfo'		  => 'N',					//是否開啟收件人資訊
			'NotifyURL'		  => $this->set['NotifyURL'],	//每期授權結果通知
			'UNIONPAY'		  => 0,						//信用卡 銀聯卡啟用
		);

		$data['PostData_'] = $this->create_mpg_aes_encrypt($PostData_array,$this->set['HashKey'],$this->set['HashIV']);

		return $data;
	}
	// 定期定額 form
	private function create_period_html($data)
	{
		echo '
			<meta http-equiv="Content-Type" content="text/html; charset=UTF-8" /> 
			<form name="mybank" id="mybank" method="post" action="'.$data['Period_url'].'">
				<input type="hidden" name="MerchantID_" value="'.$data['MerchantID'].'">
				<input type="hidden" name="PostData_" value="'.$data['PostData'].'">
			</form>
			<script language=javascript>
				document.mybank.submit();
			</script>';
	}
	/**
	 * [cancel_period 取消定期定額]
	 * @return [type] [description]
	 */
	public function cancel_period()
	{
		$data = array(
			'Period_url'      => $this->set['Period_url'],
			'MerchantID'      => $this->set['MerchantID'],
		);

		$PostData_array = array(
			'RespondType'	  => 'JSON',				//回傳格式
			'TimeStamp'       => time(),				//時間戳記
			'Version'         => '1.0',					//串接程式版本
			'LangType'		  => 'zh-Tw',				//語系 
			'MerOrderNo'	  => $order['order_id'],	//商店訂單編號 
			'ProdDesc'		  => $order['ProdDesc'],	//產品名稱
			'PeriodAmt'       => $order['all_total'],	//委託金額 Int(6)
			'PeriodType'	  => 'D',					//週期類別 D W M Y
			'PeriodPoint'	  => 30,					//交易週期授權時間 對應上面的週期類別 規則去串接手冊看
			'PeriodStartType' => 1,						//檢查卡號模式  1=用10元測試信用卡是否存在 並自動退款
			'PeriodTimes'	  => 99,					//授權期數
			'ReturnURL'		  => $this->set['ReturnURL'],	//返回商店網址
			'PayerEmail'	  => $order['pay_email'],	//付款人電子信箱
			'EmailModify'	  => 'N',					//付款人電子信箱是否開放修改
			'PaymentInfo'	  => 'N',					//是否開啟付款人資訊
			'OrderInfo'		  => 'N',					//是否開啟收件人資訊
			'NotifyURL'		  => $this->set['NotifyURL'],	//每期授權結果通知
			'UNIONPAY'		  => 0,						//信用卡 銀聯卡啟用
		);

		$data['PostData'] = $this->create_mpg_aes_encrypt($PostData_array,$this->set['HashKey'],$this->set['HashIV']);

		return $data;
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