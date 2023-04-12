<?php

namespace App\Http\Controllers\V1\User;

use App\Http\Controllers\Controller;
use App\Models\Otp;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class UserController extends Controller
{
    
    public static function create(Request $request)
    {
        try {
            DB::beginTransaction();
            $validate = Validator::make($request->all(), [
                'id_card'       => 'nullable|string|unique:users,id_card',
                'name'          => 'required|string',
                'email'         => 'nullable|string|unique:users,email',
                'password'      => 'required|string',
                'phone'         => 'nullable|string|unique:users,phone',
                'address'       => 'nullable|string',
            ])->validate();

            $validate['password'] = md5($validate['password']);
            $insert = User::create($validate);
            User::where('id', $insert['id'])
                ->update([
                    'created_by'    => $insert['id'],
                    'updated_by'    => $insert['id']
                ]);
            //bisa di kirim via sms atau email
            $generateOtp = generateOtp($insert['id']);

            DB::commit();
            return responses(200, true, 'Success Create User', $insert, null, null, null);
        } catch (QueryException $e) {
            DB::rollBack();
            return errorQuery($e);
        }
    }

    public static function verification(Request $request)
    {
        try {
            DB::beginTransaction();
            $validate = Validator::make($request->all(), [
                'email' => 'required|string|exists:users,email',
                'otp'   => 'required|integer|exists:otps,otp' 
            ])->validate();

            $data = User::where('email', $validate['email'])->first();
            if ($data) {
                $otp = Otp::where('user_id', $data['id'])->first();
                if ($otp) {
                    $dateTimeNow = Carbon::now()->format('Y-m-d H:i:s');
                    $expiredDateTime = Carbon::parse($otp['created_at'])->addMinutes()->format('Y-m-d H:i:s');
                    if ($dateTimeNow > $expiredDateTime) {
                        $otp->deleted_at = $dateTimeNow;
                        $otp->deleted_by = 'System';
                        $otp->save();
                        abort(400, 'OTP Expired, Please Request OTP Again!');
                    } else {
                        if ($validate['otp'] != $otp['otp']) abort(400, 'OTP Invalid!');
                        $data->status = 1;
                        $data->updated_at = $dateTimeNow;
                        $data->updated_by = 'System';
                        $data->save();
                    }
                }
            }

            DB::commit();
            return responses(200, true, 'Account Activated!', $data, null, null, null);
        } catch (QueryException $e) {
            DB::rollBack();
            return errorQuery($e);
        }
    }

    public static function requestOtp(Request $request)
    {
        try {
            DB::beginTransaction();
            $validate = Validator::make($request->all(), [
                'email' => 'required|string|exists:users,email'
            ])->validate();
            
            $data = User::where('email', $validate['email'])->first();
            if ($data) {
                if ($data['status'] != 1) {
                    $otp = Otp::where('user_id', $data['id'])->first();
                    $dateTimeNow = Carbon::now()->format('Y-m-d H:i:s');
                    $expiredDateTime = Carbon::parse($otp['created_at'])->addMinutes()->format('Y-m-d H:i:s');
                    if ($dateTimeNow < $expiredDateTime) {
                        $generateOtp = generateOtp($data['id']);
                    } else {
                        abort(400, 'OTP Still Available!');
                    }
                } else {
                    abort(400, 'Your Account Was Activated!');
                }
            }

            DB::commit();
            return responses(200, true, 'OTP', $generateOtp, null, null, null);
        } catch (QueryException $e) {
            DB::rollBack();
            return errorQuery($e);
        }
    }

    public static function list(Request $request)
    {
        try {
            DB::beginTransaction();
            $validate = Validator::make($request->all(), [
                'page'              => 'nullable|integer',
                'per_page'          => 'nullable|integer',
                'keyword'           => 'nullable|string',
                'filter'            => 'nullable',
            ])->validate();

            $page = isset($validate['page']) ? $validate['page'] : null;
            $per_page = isset($validate['per_page']) ? $validate['per_page'] : null;
            $keyword = isset($validate['keyword']) ? $validate['keyword'] : null;
            $filter = isset($validate['filter']) ? json_decode($validate['filter'], true) : null;

            $data = User::select('*')
                ->orderBy('name');

            if ($keyword != null) {
                $data->where(function ($sql) use ($keyword) {
                    $sql->whereRaw('LOWER(name) LIKE \'%' . strtolower($keyword) . '%\'');
                    $sql->orWhereRaw('LOWER(email) LIKE \'%' . strtolower($keyword) . '%\'');
                });
            }

            if ($filter != null) {
                foreach ($filter as $key => $val) {
                    if (is_array($val)) {
                        $data->whereIn($key, $val);
                    } else {
                        $data->where($key, $val);
                    }
                }
            }

            $totalData = $data->get()->toArray();

            if ($per_page != null) {
                $datas = $data->paginate($validate['per_page'])->toArray()['data'];
            } else {
                $datas = $data->get()->toArray();
            }

            $result = [];
            if (count($datas)) {
                foreach($datas as $key => $val) {
                    $result[$key] = $val;
                }
            }

            DB::commit();
            return responses(200, true, 'List User', $result, $page, $per_page, count($totalData));
        } catch (QueryException $e) {
            DB::rollBack();
            return errorQuery($e);
        }
    }

    public static function detail(Request $request)
    {
        try {
            DB::beginTransaction();
            $validate = Validator::make($request->all(), [
                'id' => 'required|integer|exists:users,id'
            ])->validate();

            $data = User::where('id', $validate['id'])->first();

            DB::commit();
            return responses(200, true, 'Detail User', $data, null, null, null);
        } catch (QueryException $e) {
            DB::rollBack();
            return errorQuery($e);
        }
    }

}