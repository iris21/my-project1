<?php
use ninja\mailers\Mailer;

/**
* EtcController.php 클래스
* 최초작성일 : 2016-02-18
* 최종수정일 : 2016-02-18
*
* @author : back hee yeon(libe90@commercelab.co.kr)
*/
class EtcController extends BaseController {

	protected $layout = 'master';
	protected $mailer;

	public function __construct(Mailer $mailer = null)
	{
		parent::__construct();

		$this->mailer = $mailer;
	}

	/**
	* 환경설정 > 권한관리 > 화면설정 view 함수
	*
	* @author libe90
	* @return  view
	* @since : 2016-02-24 libe90
	*/
	public function menu_set()
	{
		$user_id = Auth::user()->id;
		$disp_yn = ($this->shop_id == 1 ? '%' : 'Y');

		$permissionMenus = DB::select("
							select pm.*,
								   pu.chk_yn,
								   pu.sort
							from permission_menu as pm
								 left outer join
								 (
								   select menu_id, chk_yn, sort
								   from permission_user
								   where user_id ={$user_id} and
										 shop_id ={$this->shop_id}
								 ) as pu on (pm.id = pu.menu_id)
							where pm.branch_disp_yn like '{$disp_yn}'
							order by pm.pos asc,
									 pu.sort asc,
									 pm.menu_name asc"
						);


		$menuList = array();
		$userMCount = 0;
		$adminMCount = 0;

		$menuLiDropBlank = "<li><div class='js-screen-drop menu'></div></li>";

        foreach ( $permissionMenus as $key => $menu ) {

   			$closeBtn = "<button class='js-screen-custom-remove remove pre-set' type='button' ids='{$menu->id}'><img src = '/img/content/setup_screen_custom_btn_remove.png' alt = '삭제' ></button >";
   			$chk_info = ($menu->chk_yn == 'Y' ? " ui-draggable-disabled" : "");
			$menuLiDrag = "<li><div class='js-screen-drag menu ui-draggable ui-draggable-handle{$chk_info}' ids='{$menu->id}'><img src='/{$menu->menu_set_img}'><span >{$menu->menu_name}</span></div></li>";
			$menuLiDrop = "<li><div class='js-screen-drop menu ui-droppable' ids='{$menu->id}'><img src='/{$menu->menu_set_img}'><span>{$menu->menu_name}</span>{$closeBtn}</div></li>";

   			if(isset($menu->chk_yn)) {
				if ( $menu->pos == '1' ) { //사용자 메뉴
					if($userMCount % 8 == 0 && $userMCount != 0) {
						$menuList[0] .= "</ul>\n<ul class='js-carousel-list-content'".($userMCount > 8 ? " style='display:none;'" : "").">\n";
						$menuList[1] .= "</ul>\n<ul class='js-carousel-list-content'".($userMCount > 8 ? " style='display:none;'" : "").">\n";
					}

					$menuList[0] .= $menuLiDrag;
					$menuList[1] .= ($menu->chk_yn == 'Y' ? $menuLiDrop : $menuLiDropBlank); //선택메뉴
					$userMCount++;
				} else if ( $menu->pos == '2' ) { //관리자 메뉴
					if($adminMCount % 8 == 0 && $adminMCount != 0) {
						$menuList[2] .= "</ul>\n<ul class='js-carousel-list-content'".($adminMCount > 8 ? " style='display:none;'" : "").">\n";
						$menuList[3] .= "</ul>\n<ul class='js-carousel-list-content'".($adminMCount > 8 ? " style='display:none;'" : "").">\n";
					}

					$menuList[2] .= $menuLiDrag;
					$menuList[3] .= ($menu->chk_yn == 'Y' ? $menuLiDrop : $menuLiDropBlank); //선택메뉴
					$adminMCount++;
				}
			}
		}

		return View::make('menu_set', array('permission_menu' => $permissionMenus, 'menu_list' => $menuList));
	}

	/**
	* 환경설정 > 권한관리 > 화면설정 proc 함수
	*
	* @author libe90
	* @return  script
	* @since : 2016-02-24 libe90
	*/
	public function menu_set_indb() {
		$menuIds = $_POST['menu_ids'];
		$menuUseYNs = $_POST['menu_use_yns'];
		$menuSorts = $_POST['menu_sorts'];

        DB::beginTransaction();

        try {
            //해당사용자의 권한 모두 삭제
            PermissionUser::where('user_id', Auth::user()->id)->where('shop_id', Auth::user()->shop_id)->delete();

			foreach($menuIds as $key => $id) {
				$perm_user = new PermissionUser;
				$perm_user->menu_id = $id;
				$perm_user->user_id = Auth::user()->id;
				$perm_user->shop_id = Auth::user()->shop_id;
				$perm_user->chk_yn = $menuUseYNs[$key];
				$perm_user->sort = $menuSorts[$key];
				$perm_user->save();
            }

			//fix_yn = 'Y'인 것은 무조건 보이게 처리
			PermissionUser::where('user_id', Auth::user()->id)->where('shop_id', Auth::user()->shop_id)
				->whereRaw("menu_id in (select id from permission_menu where fix_yn = 'Y')")->update(array('chk_yn' => 'Y'));


        }catch(ValidationException $e){
            Log::error($e);
            DB::rollback();
            return "<html><script>alert('저장에 실패했습니다.');location.replace('/permission/menu_set');</script></html>";
        } catch(\Exception $e){
            Log::error($e);
            DB::rollback();
            return "<html><script>alert('저장에 실패했습니다.');location.replace('/permission/menu_set');</script></html>";
        }

        DB::commit();

        return "<html><script>alert('저장되었습니다.');location.replace('/permission/menu_set');</script></html>";
    }

	/**
	* 환경설정 > 권한관리 > 권한설정 view 함수
	*
	* @author libe90
	* @return  
	* @since : 2016-02-24 libe90
	*/
	public function permission_set() {

		$user_id = Auth::user()->id;

		$disp_yn = ($this->shop_id == 1 ? '%' : 'Y');
		$permissionMenus = DB::select("
							select pm.*,
								   pu.chk_yn,
								   pu.sort
							from permission_menu as pm
								 left outer join
								 (
								   select menu_id, chk_yn, sort
								   from permission_user
								   where user_id ={$user_id} and
										 shop_id ={$this->shop_id}
								 ) as pu on (pm.id = pu.menu_id)
							where pm.branch_disp_yn like '{$disp_yn}'
							order by pm.pos asc,
									 pu.sort asc,
									 pm.menu_name asc"
		);

		$users = User::where('shop_id', $this->shop_id)->where('status','<>', 'I')->orderBy('id','asc')->get();

		return View::make('permission_set', 
            array(
                'permission_menu' => $permissionMenus,
                'users' => $users
            )
        );
    }

    /**
    * 환경설정 > 권한관리 > 권한설정 proc 함수
    *
    * @author libe90
    * @return  script
    * @since : 2016-02-24 libe90
    */
    public function permission_set_indb() {

        DB::beginTransaction();

        try {
            foreach($_POST['user_list'] as $user_id) {

                if ( empty($user_id) ) { continue; }  

                // 설정 모두 지우기
                $affectedRows = PermissionUser::where('user_id', $user_id)->where('shop_id', Auth::user()->shop_id);
                $affectedRows->delete();

                // 서비스 메뉴와 환경설정 메뉴를 merge해서 한번에 처리

                if ( $_POST['menu1_chk'] && $_POST['menu2_chk'] ) {
                    $arrMerged = array_merge($_POST['menu1_chk'], $_POST['menu2_chk']);
                } elseif ( $_POST['menu1_chk'] ) {
                    $arrMerged = $_POST['menu1_chk'];
                } elseif ( $_POST['menu2_chk'] ) {
                    $arrMerged = $_POST['menu2_chk'];
                }


                foreach($arrMerged as $key => $menu_id) {
                    $perm_user = PermissionUser::where('user_id', $user_id)
                        ->where('shop_id', Auth::user()->shop_id)
                        ->where('menu_id', $menu_id)->first();

                    if ( !$perm_user ) {
                        $perm_user = new PermissionUser;
                        $perm_user->menu_id = $menu_id;
                        $perm_user->user_id = $user_id;
                        $perm_user->shop_id = Auth::user()->shop_id;
                    }

                    $perm_user->chk_yn = 'Y';
                    $perm_user->sort = $key;
                    $perm_user->save();
                }

            }

        }catch(ValidationException $e){
            Log::error($e);
            DB::rollback();
            return "<html><script>alert('저장에 실패했습니다.');location.replace('/permission/permission_set');</script></html>";
        } catch(\Exception $e){
            Log::error($e);
            DB::rollback();
            return "<html><script>alert('저장에 실패했습니다.');location.replace('/permission/permission_set');</script></html>";
        }

        DB::commit();
        return "<html><script>alert('저장되었습니다.');location.replace('/permission/permission_set');</script></html>";
    }

    /**
     * 환경설정 > 공통환경설정> 기타환경설정view 함수
     *
     * @author daeyeob
     * @return view
     * @since : 2016-03-08 daeyeob
     */
    public function edit_etc() {

    	$stamp = Stamp::find('1');

    	$product = Product::where('shop_id', 1)->get();

    	$info = InfoSetting::find($this->shop_id);  // info_setting내용 조회

    	return View::make('etc_edit',array('stamp'=>$stamp, 'product'=>$product, 'info'=>$info));
    }
}
