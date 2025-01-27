<?php

namespace Increment\Hotel\Reservation\Http;

use Illuminate\Http\Request;
use App\Http\Controllers\APIController;
use App\TopChoice;
use Increment\Hotel\Reservation\Models\Reservation;
use Increment\Hotel\Reservation\Models\Booking;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class ReservationController extends APIController
{

	public $synqtClass = 'App\Http\Controllers\SynqtController';
	public $merchantClass = 'Increment\Account\Merchant\Http\MerchantController';
	public $messengerGroupClass = 'Increment\Messenger\Http\MessengerGroupController';
	public $ratingClass = 'Increment\Common\Rating\Http\RatingController';
	public $topChoiceClass = 'App\Http\Controllers\TopChoiceController';
	public $roomController = 'App\Http\Controllers\RoomController';
	public $locationClass = 'Increment\Imarket\Location\Http\LocationController';
	public $emailClass = 'App\Http\Controllers\EmailController';
	public $temp = array();

	function __construct()
	{
		$this->model = new Reservation();
		$this->notRequired = array(
			'code', 'coupon_id', 'payload', 'payload_value', 'total'
		);
	}

	public function retrieveAllDetails(Request $request){
		$data = $request->all();
		$reserve = Reservation::where('code', '=', $data['id'])->first();
		$cart = app('Increment\Hotel\Room\Http\CartController')->retrieveCartWithRooms($reserve['id']);
		$reserve['details'] = json_decode($reserve['details'], true);
		$reserve['check_in'] = Carbon::createFromFormat('Y-m-d H:i:s', $reserve['check_in'])->copy()->tz($this->response['timezone'])->format('F j, Y');
		$reserve['check_out'] = Carbon::createFromFormat('Y-m-d H:i:s', $reserve['check_out'])->copy()->tz($this->response['timezone'])->format('F j, Y');
		$reserve['coupon'] = $reserve['coupon_id'] !== null ? app('App\Http\Controllers\CouponController')->retrieveById($reserve['coupon_id']) : null;
		$array = array(
			'reservation' => $reserve,
			'cart' => $cart,
			'customer' => $this->retrieveAccountDetails($reserve['account_id']),
		);
		$this->response['data'] = $array;
		return $this->response();
	}

	public function create(Request $request)
	{
		$data = $request->all();
		$this->model = new Reservation();
		$temp = Reservation::where('account_id', '=', $data['account_id'])->count();
		$data['code'] = $this->generateCode($temp);
		$this->insertDB($data);
		if($this->response['data']){
			$condition = array(
				array('account_id', '=', $data['account_id']),
				array('reservation_id', '=', null),
				array('deleted_at', '=', null),
				array('status', '=', 'pending')
			);
			$updates = array(
				'status' => 'in_progress',
				'reservation_id' => $this->response['data'],
				'updated_at' => Carbon::now()
			);
			app('Increment\Hotel\Room\Http\CartController')->updateByParams($condition, $updates);
		}
		return $this->response();
	}

	public function update(Request $request){
		$data = $request->all();
		$this->model = new Reservation();
		// $this->insertDB($data);
		$cart = json_decode($data['carts']);
		for ($i=0; $i <= sizeof($cart)-1 ; $i++) { 
			$item = $cart[$i];
			$condition = array(
				array('account_id', '=', $data['account_id']),
				array('category_id', '=', $item->category),
				array('reservation_id', '=', $item->reservation_id),
				array('deleted_at', '=', null),
				array('status', '=', 'in_progress')
			);
			$updates = array(
				'status' => 'in_progress',
				'qty' => $item->checkoutQty,
				'updated_at' => Carbon::now()
			);
			app('Increment\Hotel\Room\Http\CartController')->updateByParams($condition, $updates);
		}
		$update = Reservation::where('id', '=', $data['id'])->update(array(
			'details' => $data['details'],
			'check_in' => $data['check_in'],
			'check_out' => $data['check_out'],
		));
		$this->response['data'] = $update;
		return $this->response();
	}

	public function updateCoupon(Request $request){
		$data = $request->all();
		$reserve = Reservation::where('account_id', '=', $data['account_id'])->where('id', '=', $data['id'])->first();
		if($reserve !== null){
			$details = json_decode($reserve['details']);
			$details->payment_method = $data['payment_method'];
			$res = Reservation::where('account_id', '=', $data['account_id'])->where('id', '=', $data['id'])->update(array(
				'details' => 	json_encode($details),	
				'status' => $data['status'],
				'total' => $data['amount']
			));
			$condition = array(
				array('reservation_id', '=', $data['id']),
				array('account_id', '=', $data['account_id'])
			);
			$updates = array(
				'status' => $data['status'],
				'updated_at' => Carbon::now()
			);
			app('Increment\Hotel\Room\Http\CartController')->updateByParams($condition, $updates);
			if($res !== null){
				$this->response['data'] = $reserve;
			}
		}
		return $this->response();
	}

	public function updateByParams($condition, $updates){
		return Reservation::where($condition)->update($updates);
	}

	public function updateReservationCart($data){
		$reserve = Reservation::where('id', '=', $data['id'])->first();
		if($reserve !== null){
			$details = json_decode($reserve['details']);
			$details->payment_method = $data['payment_method'];
			$res = Reservation::where('id', '=', $data['id'])->update(array(
				'details' => 	json_encode($details),	
				'status' => $data['status']
			));
			$condition = array(
				array('reservation_id', '=', $data['id'])
			);
			$updates = array(
				'status' => $data['status'],
				'updated_at' => Carbon::now()
			);
			app('Increment\Hotel\Room\Http\CartController')->updateByParams($condition, $updates);
			if($res !== null){
				return $reserve;
			}
		}
	}

	public function retrieveByParams($whereArray, $returns)
	{
		$result = Reservation::where($whereArray)->get($returns);
		return sizeof($result) > 0 ? $result[0] : null;
	}

	public function generateCode($counter)
	{
		$length = strlen((string)$counter);
    $code = '00000000';
    return 'MEZZO_'.substr_replace($code, $counter, intval(7 - $length));
	}

	public function retrieveBookings(Request $request)
	{
		$data = $request->all();
		$con = $data['condition'];
		$sortBy = 'reservations.'.array_keys($data['sort'])[0];
		$condition = array(
			array('reservations.' . $con[0]['column'], $con[0]['clause'], $con[0]['value'])
		);
		if ($con[0]['column'] == 'email') {
			$sortBy = 'T2.'.array_keys($data['sort'])[0];
			$condition = array(
				array('T2.' . $con[0]['column'], $con[0]['clause'], $con[0]['value'])
			);
		} else if ($con[0]['column'] == 'payload_value') {
			$sortBy = 'T3.'.array_keys($data['sort'])[0];
			$condition = array(
				array('T3.title', $con[0]['clause'], $con[0]['value'])
			);
		}else if ($con[0]['column'] == 'price') {
			$sortBy = 'T5.'.array_keys($data['sort'])[0];
			$condition = array(
				array('T5.regular', $con[0]['clause'], $con[0]['value'])
			);
		}
		$res = Reservation::leftJoin('accounts as T2', 'T2.id', '=', 'reservations.account_id')
			->leftJoin('rooms as T3', 'T3.id', 'reservations.payload_value')
			->leftJoin('account_informations as T4', 'T4.account_id', '=', 'T2.id')
			->leftJoin('pricings as T5', 'T5.room_id', '=', 'T3.id')
			->where($condition)
			->orderBy($sortBy, array_values($data['sort'])[0])
			->limit($data['limit'])
			->offset($data['offset'])
			->get(['reservations.*', 'T2.email', 'T3.title', 'T5.regular']);

		$size = Reservation::leftJoin('accounts as T2', 'T2.id', '=', 'reservations.account_id')
			->leftJoin('rooms as T3', 'T3.id', 'reservations.payload_value')
			->leftJoin('account_informations as T4', 'T4.account_id', '=', 'T2.id')
			->leftJoin('pricings as T5', 'T5.room_id', '=', 'T3.id')
			->where($condition)
			->orderBy($sortBy, array_values($data['sort'])[0])
			->get();
		
		for ($i=0; $i <= sizeof($res)-1; $i++) { 
			$item = $res[$i];
			$res[$i]['details'] = json_decode($item['details']);
			$res[$i]['check_in'] = Carbon::createFromFormat('Y-m-d H:i:s', $item['check_in'])->copy()->tz($this->response['timezone'])->format('F j, Y');
			$res[$i]['check_out'] = Carbon::createFromFormat('Y-m-d H:i:s', $item['check_out'])->copy()->tz($this->response['timezone'])->format('F j, Y');
			$res[$i]['name'] = $this->retrieveNameOnly($item['account']);
		}

		$this->response['size'] = sizeOf($size);
		$this->response['data'] = $res;
		return $this->response();
	}

	public function retrieveTotalPreviousBookings(){
		$currDate = Carbon::now()->toDateTimeString();
		$res = Reservation::where('status', '=', 'verified')->where('created_at', '<', $currDate)->count();

		return $res;
	}

	public function retrieveTotalUpcomingBookings(){
		$currDate = Carbon::now()->toDateTimeString();
		$res = Reservation::where('status', '=', 'verified')->where('check_in', '>=', $currDate)->count();
		return $res;
	}

	public function retrieveTotalReservationsByAccount($accountId){
		$res = Reservation::where('account_id', '=', $accountId)->count();
		return $res;
	}

	public function retrieveTotalSpentByAcccount($accountId){
		$res = Reservation::where('account_id', '=', $accountId)->sum('total');
		return $res;
	}

	public function getTotalBookings($date){
		$bookings = Reservation::where('check_in', '<=', $date)->where('deleted_at', '=', null)->count();
		$reservations = Reservation::where('check_in', '>', $date)->where('deleted_at', '=', null)->count();
		return array(
			'previous' => $bookings,
			'upcommings' => $reservations
		);
	}

	public function retrieveDetails(Request $request){
		$data = $request->all();
		$con = $data['condition'];
		$result = Reservation::where($con[0]['column'], $con[0]['clause'], $con[0]['value'])
			->where(function($query){
				$query->where('status', '=', 'in_progress')
					->orWhere('status', '=', 'failed')
					->orWhere('status', '=', 'pending');
			})
			->get();
		// $rooms = [];
		if(sizeof($result) > 0){
			for ($i=0; $i <= sizeof($result) -1; $i++) { 
				$item = $result[$i];
				$result[$i]['details'] = json_decode($item['details']);
				$result[$i]['payload_value'] =  json_decode($item['payload_value']);;
			}
		}
		$this->response['data'] = $result;
		return $this->response();
	}

	public function countByIds($accountId, $couponId){
		if($accountId === null && $couponId !== null){
			return Reservation::where('coupon_id', '=', $couponId)->count();
		}else if($accountId !== null && $couponId === null){
			return Reservation::where('account_id', '=', $accountId)->count();
		}else if($accountId !== null && $couponId !== null){
			return Reservation::where('account_id', '=', $accountId)->where('coupon_id', '=', $couponId)->count();
		}
	}

	public function updateByCouponCode($couponId, $id){
		return Reservation::where('id', '=', $id)->update(array(
			'coupon_id' => $id
		));
	}

	public function updatedCoupon(Request $request){
		$data = $request->all();
		$res = Reservation::where('id', '=', $data['id'])->update(array(
			'coupon_id' => null,
			'updated_at' => Carbon::now()
		));
		$this->response['data'] = $res;
		return $this->response();
	}

	public function getByIds($accountId, $status){
		return Reservation::where('account_id', '=', $accountId)->where('status', '=', $status)->first();
	}

	public function updateReservations(Request $request){
		$data = $request->all();
		$res = Reservation::where('code', '=', $data['roomCode'])->update(array(
			'status' => $data['status']
		));
		for ($i=0; $i <= sizeof($data['booking'])-1; $i++) { 
			$item = $data['booking'][$i];
			$params = array(
				'reservation_id' =>  $data['reservation_id'],
				'room_id' => $item['room_id'], 
				'room_type_id' => $item['category']
			);
			Booking::create($params);
		}
		$this->response['data'] = $res;
		return $this->response();
	}

	public function retrieveBookingsByParams($column, $value){
		return Booking::where($column, '=', $value)->get();
	}

	public function retrieveReservationByParams($column, $value, $return){
		return Reservation::where($column, '=', $value)->get($return);
	}

	public function retrieveSaleByCoupon($column, $value){
		if($column !== null && $value !== null){
			$result = Reservation::where($column, '=', $value)->select(DB::raw('COUNT(coupon_id) as total_booking'), DB::raw('SUM(total) as total'))->get();
		}else if($column === null && $value === null){
			$result = Reservation::select(DB::raw('COUNT(coupon_id) as total_booking'), DB::raw('SUM(total) as total'))->get();
		}
		return $result;
	}

	public function retrieveMyBookings(Request $request){
		$data = $request->all();
		$con = $data['condition'];
		$whereArray = array(
			array('reservations.'.$con[0]['column'], $con[0]['clause'], $con[0]['value']),
			array('reservations.'.$con[1]['column'], $con[1]['clause'], $con[1]['value']),
			array('reservations.'.$con[2]['column'], $con[2]['clause'], $con[2]['value']),
			array('reservations.'.$con[3]['column'], $con[3]['clause'], $con[3]['value'])
		);
		$result = Reservation::leftJoin('carts as T1', 'T1.reservation_id', '=', 'reservations.id')
			->leftJoin('pricings as T2', 'T2.id', '=', 'T1.price_id')
			->where($whereArray)
			->where('reservations.account_id', '=', $data['account_id'])
			->groupBy('T1.reservation_id')
			->limit($data['limit'])
			->offset($data['offset'])->get(['reservations.*', 'T2.regular', 'T2.refundable', 'T2.currency', 'T2.label']);
		if(sizeof($result) > 0){
			for ($i=0; $i <= sizeof($result)-1 ; $i++) { 
				$item = $result[$i];
				$result[$i]['details'] = json_decode($item['details']);
				$result[$i]['check_in'] = Carbon::createFromFormat('Y-m-d H:i:s', $item['check_in'])->copy()->tz($this->response['timezone'])->format('F d, Y');
        $result[$i]['check_out'] = Carbon::createFromFormat('Y-m-d H:i:s', $item['check_out'])->copy()->tz($this->response['timezone'])->format('F d, Y');
				$result[$i]['rooms'] = app('Increment\Hotel\Room\Http\CartController')->retrieveCartWithRoomDetails($item['id']);
			}
		}
		$this->response['data'] = $result;
		return $this->response();
	}

	public function checkout(Request $request){
		$data = $request->all();
		$reservation = Reservation::where('account_id', '=', $data['account_id'])->where('code', '=', $data['reservation_code'])->first();
		if($reservation !== null){
			Reservation::where('code', '=', $data['reservation_code'])->update(array(
				'total' => $data['amount']
			));
			$details = json_decode($reservation['details']);
			$params = array(
				"account_id" => $data['account_id'],
				"amount" => $data['amount'],
				"name" => $details->name,
				"email" => $details->email,
				"referenceNumber" => $reservation['code'],
				"contact_number" => $details->contactNumber,
				"payload" => "reservation",
				"payload_value" => $reservation['id'],
				"successUrl" => $data['success_url'],
				"failUrl" => $data['failure_url'],
				"cancelUrl" => $data['cancel_url']
			);
			$res = app('Increment\Hotel\Payment\Http\PaymentController')->checkout($params);
			if($res['data'] !== null){
				$this->response['data'] = $res['data'];
			}else{
				$this->response['data'] = $res['error'];
			}
		}
		return $this->response();
	}

}
