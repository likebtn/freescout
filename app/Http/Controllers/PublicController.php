<?php

namespace App\Http\Controllers;

use App\Option;
use App\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Validator;

class PublicController extends Controller
{
    /**
     * Create a new controller instance.
     *
     * @return void
     */
    public function __construct()
    {
        
    }

    /**
     * Setup user from invitation.
     *
     * @return \Illuminate\Http\Response
     */
    public function userSetup($hash)
    {
        if (auth()->user()) {
            return redirect()->route('dashboard');
        }
        $user = User::where('invite_hash', $hash)->first();

        return view('public/user_setup', ['user' => $user]);
    }

    /**
     * Save user from invitation.
     *
     */
    public function userSetupSave($hash, Request $request)
    {
        if (auth()->user()) {
            return redirect()->route('dashboard');
        }
        $user = User::where('invite_hash', $hash)->first();

        if (!$user) {
            abort(404);
        }

        $validator = Validator::make($request->all(), [
            'email'       => 'required|string|email|max:100|unique:users,email,'.$user->id,
            'password'    => 'required|string|min:8|confirmed',
            'job_title'   => 'max:100',
            'phone'       => 'max:60',
            'timezone'    => 'required|string|max:255',
            'time_format' => 'required',
            'photo_url'   => 'nullable|image|mimes:jpeg,png,jpg,gif',
        ]);
        $validator->setAttributeNames([
            'photo_url'   => __('Photo'),
        ]);

        // Photo
        $validator->after(function ($validator) use ($user, $request) {
            if ($request->hasFile('photo_url')) {
                $path_url = $user->savePhoto($request->file('photo_url'));

                if ($path_url) {
                    $user->photo_url = $path_url;
                } else {
                    $validator->errors()->add('photo_url', __('Error occured processing the image. Make sure that PHP GD extension is enabled.'));
                }
            }
        });

        if ($validator->fails()) {
            return redirect()->route('user_setup', ['hash' => $hash])
                        ->withErrors($validator)
                        ->withInput();
        }

        $request_data = $request->all();
        if (isset($request_data['photo_url'])) {
            unset($request_data['photo_url']);
        }
        $user->fill($request_data);

        $user->password = bcrypt($request->password);

        $user->invite_state = User::INVITE_STATE_ACTIVATED;
        $user->invite_hash = '';

        $user->save();

        // Login user
        Auth::guard()->login($user);

        \Session::flash('flash_success_floating', __('Welcome to :company_name!', ['company_name' => Option::getCompanyName()]));

        return redirect()->route('dashboard');
    }
}
