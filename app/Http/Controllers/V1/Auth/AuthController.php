<?php

namespace App\Http\Controllers\V1\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\QueryException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;

class AuthController extends Controller
{

    public function login(Request $request)
    {
        try {
            DB::beginTransaction();

            $validate = Validator::make($request->all(), [
                'email'     => 'required|string|exists:users,email',
                'password'  => 'required|string',
                'fcm_token' => 'nullable|string'
            ])->validate();

            $data = User::where('email', $validate['email'])->first();
            if ($data) {
                if ($data['password'] == md5($validate['password'])) {
                    if ($data['status'] == 1) {
                        $data->last_login = date('Y-m-d H:i:s');
                        $data->updated_at = date('Y-m-d H:i:s');
                        $data->updated_by = $data['id'];
                        $data->save();

                        $token = createToken($data['email'], $validate['password']);
                        $data['oauth_data'] = $token ? $token : null;
                    } else {
                        abort(400, 'Please Verify your Account First!');
                    }
                } else {
                    abort(400, 'Wrong Password!');
                }
            } else {
                abort(400, 'Email Not Registered!');
            }

            DB::commit();
            return responses(200, true, 'Login Success!', $data, null, null, null);
        } catch (QueryException $e) {
            DB::rollBack();
            return errorQuery($e);
        }
    }

    public function loginToken()
    {
        try {
            DB::beginTransaction();
            $data = User::where('id', auth()->user()->id)->first();
            $data->last_login = date('Y-m-d H:i:s');
            $data->updated_at = date('Y-m-d H:i:s');
            $data->updated_by = $data['id'];
            $data->save();
            
            $token = createToken($data['email'], $data['password']);
            $data['oauth_data'] = $token ? $token : null;

            DB::commit();
            return responses(200, true, 'Login Token Success!', $data, null, null, null);
        } catch (QueryException $e) {
            DB::rollBack();
            return errorQuery($e);
        }
    }

    public function refreshToken(Request $request)
    {
        try {
            $validate = Validator::make($request->all(), [
                'refresh_token'  => 'required|string'
            ])->validate();
    
            $refreshToken = refreshToken($validate['refresh_token']);
            if (!$refreshToken) abort(400, 'Token Not Valid!');
    
            return responses(200, true, 'Refresh Token Success!', $refreshToken, null, null, null);
        } catch (QueryException $e) {
            return errorQuery($e);
        }
    }

    public function logout()
    {
        try {
            $tokens = auth()->user()->tokens;

            foreach ($tokens as $token) {
                $token->revoke();
            }

            return responses(200, true, 'Logout Success!', null, null, null, null);
        } catch (QueryException $e) {
            return errorQuery($e);
        }
    }
}