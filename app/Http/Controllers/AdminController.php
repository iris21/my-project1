<?php

use ninja\mailers\Mailer;

/**
* AdminController.php 클래스
* 최초작성일 : 2016-02-25
* 최종수정일 : 2016-02-25
*
* @author : back hee yeon(libe90@commercelab.co.kr)
*/
class AdminController extends BaseController {

	protected $layout = 'master';
	protected $mailer;

	public function __construct(Mailer $mailer = null)
	{
		parent::__construct();

		$this->mailer = $mailer;
	}

	/**
	* 가맹점 생성용 view 함수
	*
	* @author libe90
	* @return  view
	* @since : 2016-02-25 libe90
	*/
	public function chain_reg()
	{
		$site_name = Config::get('site.site_name');
		$server_name = $_SERVER['SERVER_NAME'];
		$remote_addr = $_SERVER['REMOTE_ADDR'];

		return View::make('admin.chain_reg',array('site_name'=>$site_name, 'server_name'=>$server_name, 'remote_addr'=>$remote_addr));
	}

	/**
	* 가맹점 생성 proc 함수
	*
	* @author libe90
	* @return  script
	* @since : 2016-02-25 libe90
	*/
	public function chain_indb()
	{
		if(Request::ajax()){
			$returnData = Utils::getCurlData('', 'smspduometissms', '_empolyIdCheck', Input::get('id'));
			return $returnData->result;
		}else{
			$employ_id = Input::get('id');
			$employ_name = Input::get('site_key');
			$employ_orgnzt_domain = Input::get('server_name');
			$employ_ip = Input::get('remote_addr');
			$employ_pw = Input::get('password');
			$employ_email = Input::get('email');
			$employ_licensee = trim(Input::get('licensee'));
			$employ_addr1 = Input::get('addr1');
			$employ_addr2 = Input::get('addr2');

			$employ_tel = Input::get('phone2_1').Input::get('phone2_2').Input::get('phone2_3');
			$employ_hp = Input::get('phone_1').Input::get('phone_2').Input::get('phone_3');
			$employ_mem_name = Input::get('name');
			$owner = Input::get('owner');

			$checkId = Utils::getCurlData('', 'smspduometissms', '_empolyIdCheck', $employ_id);

			# 아이디 중복 체크 한번 더 실행
			if($checkId->result == 'true'){
				DB::beginTransaction();
				try {
					#1. shops 테이블작업
					# 가맹점 생성
					$newShop = new Shop;
					$newShop->name = $employ_mem_name;
					$newShop->biz_no = $employ_licensee;
					$newShop->mobile = $employ_hp;
					$newShop->phone = $employ_tel;
					$newShop->owner = $owner;
					$newShop->addr = $employ_addr1;
					$newShop->addr_etc = $employ_addr2;
					$newShop->save();

					$arrData = array(
						'key' => 'smspduometissms',
						'cmd_mode' => '_empolyAdd',
						'employ_id' => $employ_id,
						'employ_name' => $employ_name,
						'employ_orgnzt_domain' => $employ_orgnzt_domain,
						'employ_ip' => $employ_ip,
						'employ_pw' => $employ_pw,
						'employ_email' => $employ_email,
						'employ_hp' => $employ_hp,
						'employ_tel' => $employ_tel,
						'employ_mem_name' => $employ_mem_name,
						'employ_licensee' => $employ_licensee,
						'employ_addr1' => $employ_addr1,
						'employ_addr2' => $employ_addr2,
						'solution' => 'H',
						'hashdata' => md5('_empolyAddsmspduometissms')
					);
					$returnData = Utils::sendCurlData($arrData);

					if($returnData->resultList->esntl_key){
						# DB 입력 실패시 삭제에 대비해 데이터 셋팅
						$arrDataDel = array(
							'key' => 'smspduometissms',
							'cmd_mode' => '_empolyDelete',
							'esntl_key' => $returnData->resultList->esntl_key,
							'hashdata' => md5('_empolyDeletesmspduometissms')
						);
						$updShop = Shop::find($newShop->id);
						$updShop->site_key = $returnData->resultList->esntl_key;
						$updShop->save();
					}

					# 2. info_setting
					$info = new InfoSetting;
					$info->shop_id = $newShop->id;
					$info->card_fee = '2.5';
					$info->reservation_yn = 'Y';
					$info->save();

					#4. ranks
					$rank = new Rank;
					$rank->name = '점주';
					$rank->shop_id = $newShop->id;
					$rank->save();

					#5. users
					$user = new User;
					$user->username = $owner;
					$user->rank_id = $rank->id;
					$user->status = 'A';
					$user->is_admin = true;
					$user->shop_id = $newShop->id;
					$user->base_salary = '1000000';

					$user->email = $employ_hp.'@test.com';
					$user->phone = $employ_hp;
					$user->phone2 = $employ_hp;

					$user->password = $employ_pw;
					$user->password_confirmation = $employ_pw;

					$user->save();

					#8. user_salarys
					$findSalaryUser =  new UserSalary;
					$findSalaryUser->user_id = $user->id;
					$findSalaryUser->base_salary = '1000000';
					$findSalaryUser->shop_id = $newShop->id;
					$findSalaryUser->save();

					#4. permission_user
					$PermissionMenu = PermissionMenu::orderBy('id','asc')->get();
					foreach($PermissionMenu as $ck => $cv){
							$PermissionUser = new PermissionUser;
							$PermissionUser->menu_id = $cv->id;
							$PermissionUser->user_id = $user->id;
							$PermissionUser->chk_yn = 'Y';
							$PermissionUser->shop_id = $newShop->id;
							$PermissionUser->sort = $cv->id;
							$PermissionUser->save();
					}

				}catch(ValidationException $e){
					# SHOP의 DB입력 실패시 롤백전에 통신 대상의 DB를 삭제
					if($returnData->result == 'true') $returnDataDel = Utils::sendCurlData($arrDataDel);
					DB::rollback();
				} catch(\Exception $e){
					# SHOP의 DB입력 실패시 롤백전에 통신 대상의 DB를 삭제
					if($returnData->result == 'true') $returnDataDel = Utils::sendCurlData($arrDataDel);
					DB::rollback();
				}

				# 가맹점 등록 통신 실패시 해당 SHOP의 DB 데이터를 롤백시킴
				if($returnData->result == 'true'){
					DB::commit();
				}else{
					DB::rollback();
				}
			}

			if($returnData->result == 'true'){
				return "<html><script>alert('가맹점이 등록되었습니다.');location.replace('/admin/chain_reg');</script></html>";
			}else{
				return "<html><script>alert('가맹점 등록이 실패했습니다.');location.replace('/admin/chain_reg');</script></html>";
			}
			return Redirect::action('/admin/chain_reg', array('result' => $returnData->result));
		}
	}

	/**
	* 등급 배치 적용 proc 함수
	*
	* @author dwar0825
	* @return  string
	* @since : 2016-03-18 dwar0825
	*/
	public function autoGrade()
	{
		$infoSetting = InfoSetting::where("shop_id", 1)->first();
		if($infoSetting->batch_amount){
			$result = Utils::setGradeBatch(1, $infoSetting->batch_amount);
		}else{
			$result = "등급배치 설정 정보가 없습니다.";
		}

		return $result;
	}
}
